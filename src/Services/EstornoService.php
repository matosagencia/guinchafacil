<?php

// File: guinchafacil/src/Services/EstornoService.php

require_once __DIR__ . '/AuditTrailService.php';
require_once __DIR__ . '/Payment/PayoutLedgerService.php';
require_once __DIR__ . '/Payment/PaymentProviderFactory.php';
require_once __DIR__ . '/Logger.php';

/**

 * Serviço de estorno de pagamentos (§4.4)

 * Regras:

 * - Cancelamentos em aguardando_pagamento ou aguardando_guincho → estorno automático

 * - Cancelamentos após a_caminho → análise manual do admin (sem estorno automático)
 *
 * Pacote L1.7: o estorno agora faz um "claim" atômico do pagamento (lock de linha
 * + transição para o estado transitório 'estornando') ANTES de chamar a API do
 * gateway. Isso fecha a race condition em que duas chamadas concorrentes (ex.:
 * duplo clique do admin, ou cancelamento automático cruzando com ação manual)
 * ambas liam status='aprovado' e disparavam dois refunds reais no gateway.

 */

class EstornoService

{

    /**

     * Estorna pagamento aprovado de um pedido via API do gateway.

     *

     * @return array ['sucesso' => bool, 'erro' => string|null]

     */

    /**
     * §ESTORNO-ARQUIVADO-01 (27/07/2026, achado em revisão de código): depois
     * que ConversionService arquiva o pagamento aprovado original (socorro
     * no local) em `pagamentos_arquivados` — para liberar a linha viva pra
     * cobrança complementar do reboque —, esse pagamento original ficava
     * INALCANÇÁVEL por qualquer estorno: nem o automático (cancelamento de
     * cliente/cron de expiração) nem o manual (DemandaService 'reembolso')
     * conseguiam encontrá-lo, porque este método só consultava a tabela
     * viva. Silenciosamente devolvia sucesso="nada a estornar" mesmo
     * havendo, sim, um pagamento aprovado — só que arquivado.
     *
     * $incluirArquivado (default false, muda comportamento SÓ quando true)
     * é a válvula de escape: chamadores AUTOMÁTICOS (cancelamento de
     * cliente, cron de expiração) mantêm o comportamento de sempre — nunca
     * reabrem sozinhos um pagamento já arquivado, porque o serviço no local
     * já foi de fato prestado e estornar automaticamente isso seria uma
     * decisão de negócio que ninguém pediu. Só a ação MANUAL do admin
     * (DemandaService, caso 'reembolso' — já passa por aprovação e, acima
     * de um limiar configurável, por dupla aprovação) habilita essa busca,
     * porque ali existe uma decisão humana explícita de estornar aquele
     * pedido especificamente.
     *
     * @return array ['sucesso' => bool, 'erro' => string|null]
     */
    public static function estornar(int $pedidoId, ?float $valorParcial = null, bool $incluirArquivado = false): array

    {

        $pdo = getPDO();
        $pag = null;
        $origemArquivado = false;

        try {
            $pdo->beginTransaction();

            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $pagamento = $pdo->prepare(
                "SELECT id, pedido_id, metodo, id_externo, status, valor_guincho, valor_plataforma FROM pagamentos WHERE pedido_id = ? AND status = 'aprovado' LIMIT 1" . $lockClause
            );
            $pagamento->execute([$pedidoId]);
            $pag = $pagamento->fetch(PDO::FETCH_ASSOC);

            if (!$pag && $incluirArquivado) {
                $arquivado = $pdo->prepare(
                    "SELECT id, pedido_id, metodo, id_externo, status FROM pagamentos_arquivados
                      WHERE pedido_id = ? AND status = 'aprovado' ORDER BY id DESC LIMIT 1" . $lockClause
                );
                $arquivado->execute([$pedidoId]);
                $pagArquivado = $arquivado->fetch(PDO::FETCH_ASSOC);
                if ($pagArquivado) {
                    // pagamentos_arquivados não guarda valor_guincho/valor_plataforma
                    // separado do split original só para fins de reversão de
                    // ledger — o ledger daquele pagamento já foi lançado (e,
                    // se o guincho já sacou, não pode ser revertido às cegas
                    // aqui); então o estorno arquivado NUNCA mexe no ledger
                    // automaticamente (ver bloco de sucesso abaixo).
                    $pag = $pagArquivado + ['valor_guincho' => 0.0, 'valor_plataforma' => 0.0];
                    $origemArquivado = true;
                }
            }

            if (!$pag) {
                $pdo->rollBack();
                // Sem pagamento aprovado — nada a estornar (pedido pode ter sido cancelado antes do pagamento,
                // ou já estornado/estornando por uma chamada concorrente que ganhou o claim primeiro).
                return ['sucesso' => true, 'erro' => null];
            }

            // Claim: move para o estado transitório ANTES de sair para a API externa,
            // dentro da mesma transação que travou a linha — nenhuma segunda chamada
            // concorrente consegue ler status='aprovado' depois deste commit.
            $tabelaClaim = $origemArquivado ? 'pagamentos_arquivados' : 'pagamentos';
            $pdo->prepare("UPDATE {$tabelaClaim} SET status = 'estornando' WHERE id = ?")->execute([$pag['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::exception('EstornoService', 'estornar', 'pagamento', $e, ['pedido_id' => $pedidoId, 'fase' => 'claim']);
            return ['sucesso' => false, 'erro' => 'Erro interno ao iniciar estorno.'];
        }



        // Pacote L1.7 (pendência #33): PaymentProviderFactory (interface
        // PaymentProviderInterface + MercadoPagoProvider/PagSeguroProvider,
        // em src/Services/Payment/) agora é a forma canônica de resolver
        // "que gateway trata este pagamento" para consumidores novos.
        // Aqui dentro, porém, o dispatch continua via static::estornarX()
        // (late static binding) em vez de $provider->estornar() de
        // propósito: EstornoServiceTestable (usado pelos testes existentes
        // em tests/Unit/EstornoServiceTest.php) funciona sobrescrevendo
        // httpPostRaw() e depende desse binding tardio para interceptar a
        // chamada HTTP com um mock — se disparássemos via
        // MercadoPagoProvider/PagSeguroProvider (que chamam
        // EstornoService::... explicitamente, sem late static binding),
        // o mock deixaria de ser interceptado e os testes passariam a
        // fazer chamadas HTTP reais. A mensagem de erro para gateway
        // desconhecido usa a mesma factory só para decidir se o gateway é
        // suportado, preservando o texto exato já coberto por
        // EstornoServiceTest::testGatewayDesconhecidoMensagemContemNaoSuportado.
        $gatewaySuportado = PaymentProviderFactory::forGateway($pag['metodo']) !== null;
        $resultado = match (true) {
            !$gatewaySuportado => ['sucesso' => false, 'erro' => "Gateway '{$pag['metodo']}' não suportado para estorno automático."],
            $pag['metodo'] === 'mercadopago' => static::estornarMercadoPago($pag['id_externo'], $valorParcial),
            $pag['metodo'] === 'pagseguro' => static::estornarPagSeguro($pag['id_externo'], $valorParcial),
            default => ['sucesso' => false, 'erro' => "Gateway '{$pag['metodo']}' não suportado para estorno automático."],
        };



        if ($resultado['sucesso']) {

            // O status 'estornado' já venceu no gateway — isso é garantido, não pode
            // depender do ledger. Lançamento contábil é uma segunda etapa best-effort,
            // separada, para uma falha ali nunca reverter a confirmação do estorno.
            $pdo->prepare("UPDATE {$tabelaClaim} SET status = 'estornado' WHERE id = ?")->execute([$pag['id']]);

            try {
                // Estorno parcial não zera o split completo — apenas estornos integrais
                // (valorParcial === null) revertem 100% dos créditos no ledger.
                // §ESTORNO-ARQUIVADO-01: pagamento arquivado NUNCA lança no
                // ledger automaticamente — `$pag['id']` aqui é o id em
                // pagamentos_arquivados, um espaço de IDs DIFERENTE do de
                // `pagamentos` (payout_ledger_entries.pagamento_id tem FK
                // pra `pagamentos`, não pra `pagamentos_arquivados`); gravar
                // usando esse id quebraria a FK ou, pior, apontaria por
                // acidente pra uma linha viva não relacionada. O split
                // daquele pagamento já foi lançado quando ele foi aprovado
                // originalmente — reversão de ledger de um pagamento
                // arquivado, se necessária, é ação manual separada do admin.
                if ($valorParcial === null && !$origemArquivado) {
                    $pdo->beginTransaction();
                    PayoutLedgerService::registrarEstorno($pdo, (int)$pag['id'], (int)$pag['pedido_id'], (float)$pag['valor_guincho'], (float)$pag['valor_plataforma']);
                    $pdo->commit();
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                Logger::exception('EstornoService', 'estornar', 'pagamento', $e, ['pedido_id' => $pedidoId, 'pagamento_id' => $pag['id'], 'fase' => 'ledger']);
            }

            Logger::log(Logger::LEVEL_INFO, 'EstornoService', 'estornar', 'pagamento',
                "Estorno pedido {$pedidoId} via {$pag['metodo']} concluído.",
                ['pedido_id' => $pedidoId, 'pagamento_id' => $pag['id'], 'metodo' => $pag['metodo'], 'valor_parcial' => $valorParcial]);

            AuditTrailService::evento('pagamento_estornado', 'EstornoService', 'estornar', [
                'pedido_id' => $pedidoId, 'pagamento_id' => $pag['id'], 'metodo' => $pag['metodo'],
                'valor_parcial' => $valorParcial, 'event_code' => 'PAG-EST-001',
            ]);

        } else {

            // Falha na API externa: devolve o pagamento para 'aprovado' (não fica
            // preso em 'estornando' para sempre) para permitir nova tentativa.
            $pdo->prepare("UPDATE {$tabelaClaim} SET status = 'aprovado' WHERE id = ?")->execute([$pag['id']]);

            Logger::log(Logger::LEVEL_WARN, 'EstornoService', 'estornar', 'pagamento',
                "Falha estorno pedido {$pedidoId}: " . (string)$resultado['erro'],
                ['pedido_id' => $pedidoId, 'pagamento_id' => $pag['id'], 'metodo' => $pag['metodo'], 'erro' => $resultado['erro']]);

            AuditTrailService::evento('pagamento_estorno_falhou', 'EstornoService', 'estornar', [
                'pedido_id' => $pedidoId, 'pagamento_id' => $pag['id'], 'metodo' => $pag['metodo'],
                'erro' => $resultado['erro'], 'event_code' => 'PAG-EST-002',
            ]);

        }



        return $resultado;

    }



    // Pacote L1.7 (pendência #33): visibilidade ampliada de protected para
    // public para que MercadoPagoProvider/PagSeguroProvider (em
    // src/Services/Payment/) possam delegar para cá sem duplicar a lógica
    // de chamada HTTP. Mudança apenas de visibilidade — nenhum
    // comportamento muda, e os testes existentes via ReflectionMethod
    // continuam funcionando de qualquer forma (setAccessible ignora
    // visibilidade).
    public static function estornarMercadoPago(string $idExterno, ?float $valorParcial = null): array

    {

        // Remove prefixo 'mp_' se houver

        $mpId = str_starts_with($idExterno, 'mp_') ? substr($idExterno, 3) : $idExterno;



        $url = "https://api.mercadopago.com/v1/payments/{$mpId}/refunds";

        $result = static::httpPostRaw($url, [

            'Authorization: Bearer ' . MP_ACCESS_TOKEN,

            'Content-Type: application/json',

        ], $valorParcial !== null && $valorParcial > 0 ? json_encode(['amount' => round($valorParcial, 2)]) : '{}');

        $resp     = $result['body'] ?? '';

        $httpCode = (int)($result['code'] ?? 0);

        $curlErr  = (string)($result['error'] ?? '');



        if ($curlErr) return ['sucesso' => false, 'erro' => "cURL: {$curlErr}"];

        if ($httpCode === 201 || $httpCode === 200) return ['sucesso' => true, 'erro' => null];



        $data = json_decode($resp, true);

        $msg  = $data['message'] ?? $data['error'] ?? "HTTP {$httpCode}";

        return ['sucesso' => false, 'erro' => "MercadoPago: {$msg}"];

    }



    public static function estornarPagSeguro(string $idExterno, ?float $valorParcial = null): array

    {

        // Remove prefixo 'ps_' se houver

        $psCode = str_starts_with($idExterno, 'ps_') ? substr($idExterno, 3) : $idExterno;



        $url = PS_BASE_URL . '/v2/transactions/refunds'

             . '?email=' . urlencode(PS_EMAIL)

             . '&token=' . PS_TOKEN

             . '&transactionCode=' . urlencode($psCode);

        $refund = ['transactionCode' => $psCode];
        if ($valorParcial !== null && $valorParcial > 0) { $refund['refundValue'] = number_format($valorParcial, 2, '.', ''); }
        $result = static::httpPostRaw($url, [], http_build_query($refund));

        $resp     = $result['body'] ?? '';

        $httpCode = (int)($result['code'] ?? 0);

        $curlErr  = (string)($result['error'] ?? '');



        if ($curlErr) return ['sucesso' => false, 'erro' => "cURL: {$curlErr}"];

        if ($httpCode === 200) return ['sucesso' => true, 'erro' => null];

        return ['sucesso' => false, 'erro' => "PagSeguro HTTP {$httpCode}: {$resp}"];

    }



    protected static function httpPostRaw(string $url, array $headers, string $body): array

    {

        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST           => true,

            CURLOPT_POSTFIELDS     => $body,

            CURLOPT_HTTPHEADER     => $headers,

            CURLOPT_TIMEOUT        => 30,

        ]);

        if ($ca = ca_bundle_path()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }

        $resp     = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curlErr  = curl_error($ch);

        curl_close($ch);



        return ['body' => (string)$resp, 'code' => (int)$httpCode, 'error' => (string)$curlErr];

    }

}

