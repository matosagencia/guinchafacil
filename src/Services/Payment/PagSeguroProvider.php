<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/../EstornoService.php';

/**
 * Pacote L1.7 (pendência #33). Delega para EstornoService::estornarPagSeguro()
 * pelo mesmo motivo documentado em MercadoPagoProvider.
 */
final class PagSeguroProvider implements PaymentProviderInterface
{
    public function nome(): string
    {
        return 'pagseguro';
    }

    public function estornar(string $idExterno, ?float $valorParcial = null): array
    {
        return EstornoService::estornarPagSeguro($idExterno, $valorParcial);
    }

    /**
     * Checkout transparente PagSeguro (API v1 "Checkout Transparente" —
     * developer.pagbank.com.br/v1/reference/checkout-transparente): fluxo
     * documentado e estável (sessão -> PagSeguroDirectPayment.js no browser
     * -> senderHash/creditCardToken -> POST /v2/transactions aqui). NÃO
     * cobre Pix (a API v1 não expõe Pix; a "Orders API" mais nova cobre,
     * mas sem documentação de endpoint confirmada o suficiente pra
     * implementar sem testar ao vivo — ficou fora desta rodada, registrado
     * como pendência). Cartão e boleto ficam 100% transparentes (sem
     * redirect); só o formulário de cartão exige os campos de titular e
     * endereço de cobrança que a API do PagSeguro sempre exigiu — não é
     * escolha de UX nossa, é requisito deles pra aceitar a cobrança.
     *
     * A confirmação real de status ('aprovado') continua vindo do webhook
     * IPN existente (WebhookController::pagSeguro(), já implementado e
     * testado) — a resposta síncrona daqui só informa o status inicial da
     * transação (normalmente "1 - aguardando pagamento" ou "3 - paga" pra
     * alguns métodos), sem alterar esse contrato.
     */
    public function criarPagamento(array $dados): array
    {
        $metodo = (string)($dados['paymentMethodId'] ?? 'creditCard'); // 'creditCard' | 'boleto'
        $pedidoId = (int)($dados['pedidoId'] ?? 0);
        $valor = round((float)($dados['valor'] ?? 0), 2);

        $params = [
            'paymentMode'      => 'default',
            'paymentMethod'    => $metodo,
            'receiverEmail'    => PS_EMAIL,
            'currency'         => 'BRL',
            'itemId1'          => '1',
            'itemDescription1' => mb_substr((string)($dados['descricao'] ?? 'Serviço de Guincho - GuinchaFácil'), 0, 100),
            'itemAmount1'      => number_format($valor, 2, '.', ''),
            'itemQuantity1'    => '1',
            'reference'        => (string)$pedidoId,
            'senderName'       => mb_substr((string)($dados['nome'] ?? ''), 0, 50),
            'senderEmail'      => (string)($dados['payerEmail'] ?? ''),
            'senderCPF'        => preg_replace('/\D+/', '', (string)($dados['docNumero'] ?? '')),
            'senderAreaCode'   => (string)($dados['telefoneDdd'] ?? ''),
            'senderPhone'      => (string)($dados['telefoneNumero'] ?? ''),
            'senderHash'       => (string)($dados['senderHash'] ?? ''),
            // Serviço (reboque), não produto físico — sem frete a calcular.
            'shippingType'     => '3',
            'notificationURL'  => (string)($dados['notificationUrl'] ?? ''),
        ];

        if ($metodo === 'creditCard') {
            $params += [
                'creditCardToken'          => (string)($dados['token'] ?? ''),
                'installmentQuantity'      => (string)max(1, (int)($dados['parcelas'] ?? 1)),
                'installmentValue'         => number_format($valor / max(1, (int)($dados['parcelas'] ?? 1)), 2, '.', ''),
                'noInterestInstallmentQuantity' => '1',
                'creditCardHolderName'     => mb_substr((string)($dados['nome'] ?? ''), 0, 50),
                'creditCardHolderCPF'      => preg_replace('/\D+/', '', (string)($dados['docNumero'] ?? '')),
                'creditCardHolderBirthDate'=> (string)($dados['nascimento'] ?? ''), // dd/mm/aaaa
                'creditCardHolderAreaCode' => (string)($dados['telefoneDdd'] ?? ''),
                'creditCardHolderPhone'    => (string)($dados['telefoneNumero'] ?? ''),
                'billingAddressStreet'     => mb_substr((string)($dados['enderecoRua'] ?? ''), 0, 80),
                'billingAddressNumber'     => (string)($dados['enderecoNumero'] ?? ''),
                'billingAddressComplement' => (string)($dados['enderecoComplemento'] ?? ''),
                'billingAddressDistrict'   => (string)($dados['enderecoBairro'] ?? ''),
                'billingAddressPostalCode' => preg_replace('/\D+/', '', (string)($dados['enderecoCep'] ?? '')),
                'billingAddressCity'       => (string)($dados['enderecoCidade'] ?? ''),
                'billingAddressState'      => (string)($dados['enderecoUf'] ?? ''),
                'billingAddressCountry'    => 'BRA',
            ];
        }

        $url = PS_BASE_URL . '/v2/transactions?email=' . urlencode((string)PS_EMAIL) . '&token=' . (string)PS_TOKEN;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($ca = ca_bundle_path()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp     = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            error_log('[PagSeguroProvider][criarPagamento] cURL: ' . $curlErr);
            return ['sucesso' => false, 'status' => 'erro', 'idExterno' => null, 'detalhe' => [], 'erro' => 'Erro de comunicação com o PagSeguro.'];
        }

        // §PS-XML-01 (mesmo padrão do WebhookController): SimpleXML, nunca regex em XML.
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string)$resp);

        if ($xml === false) {
            error_log('[PagSeguroProvider][criarPagamento] Resposta não é XML válido (HTTP ' . $httpCode . '): ' . substr((string)$resp, 0, 500));
            return ['sucesso' => false, 'status' => 'erro', 'idExterno' => null, 'detalhe' => [], 'erro' => 'Resposta inválida do PagSeguro.'];
        }

        if ($xml->getName() === 'errors') {
            $mensagens = [];
            foreach ($xml->error as $erro) {
                $mensagens[] = (string)$erro->code . ': ' . (string)$erro->message;
            }
            $msg = implode(' | ', $mensagens) ?: "HTTP {$httpCode}";
            error_log('[PagSeguroProvider][criarPagamento] Recusado: ' . $msg);
            return ['sucesso' => false, 'status' => 'recusado', 'idExterno' => null, 'detalhe' => ['errors' => $mensagens], 'erro' => $msg];
        }

        $psStatus = (string)($xml->status ?? '');
        $status = match ($psStatus) {
            '3', '4' => 'aprovado', // 3=paga, 4=disponível
            '1', '2', '5', '6', '7' => 'pendente', // aguardando/em análise/em disputa/devolvida(*)/cancelada tratada abaixo
            default => 'pendente',
        };
        if ($psStatus === '7') { // cancelada
            $status = 'recusado';
        }

        $txCode = (string)($xml->code ?? '');
        $detalhe = ['status_pagseguro' => $psStatus];
        if ($metodo === 'boleto') {
            $detalhe['boleto_url'] = (string)($xml->paymentLink ?? '');
        }

        return [
            'sucesso'   => $status !== 'recusado' && $status !== 'erro',
            'status'    => $status,
            'idExterno' => $txCode !== '' ? 'ps_' . $txCode : null,
            'detalhe'   => $detalhe,
            'erro'      => $status === 'recusado' ? 'Pagamento recusado pelo PagSeguro (status ' . $psStatus . ').' : null,
        ];
    }

    /**
     * Passo 1 do Checkout Transparente PagSeguro: cria a sessão que o
     * PagSeguroDirectPayment.js do browser precisa antes de gerar o
     * senderHash/tokenizar o cartão. Sessão é de uso único e expira rápido
     * — deve ser pedida de novo a cada tentativa de pagamento, nunca
     * reaproveitada entre carregamentos de página.
     *
     * @return array{sucesso:bool, sessionId:?string, erro:?string}
     */
    public function criarSessao(): array
    {
        $url = PS_BASE_URL . '/v2/sessions?email=' . urlencode((string)PS_EMAIL) . '&token=' . (string)PS_TOKEN;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_TIMEOUT        => 15,
        ]);
        if ($ca = ca_bundle_path()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp     = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            error_log('[PagSeguroProvider][criarSessao] cURL: ' . $curlErr);
            return ['sucesso' => false, 'sessionId' => null, 'erro' => 'Erro de comunicação com o PagSeguro.'];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string)$resp);
        $sessionId = $xml !== false ? (string)($xml->id ?? '') : '';

        if ($sessionId === '') {
            error_log('[PagSeguroProvider][criarSessao] Sem session id na resposta: ' . substr((string)$resp, 0, 300));
            return ['sucesso' => false, 'sessionId' => null, 'erro' => 'PagSeguro não retornou sessão válida.'];
        }

        return ['sucesso' => true, 'sessionId' => $sessionId, 'erro' => null];
    }
}
