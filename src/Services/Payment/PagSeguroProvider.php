<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/../EstornoService.php';

/**
 * Pacote L1.7 (pend�ncia #33). Delega para EstornoService::estornarPagSeguro()
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
     * Checkout transparente PagSeguro (API v1 "Checkout Transparente" -
     * developer.pagbank.com.br/v1/reference/checkout-transparente): fluxo
     * documentado e est�vel (sess�o -> PagSeguroDirectPayment.js no browser
     * -> senderHash/creditCardToken -> POST /v2/transactions aqui). N�O
     * cobre Pix (a API v1 n�o exp�e Pix; a "Orders API" mais nova cobre,
     * mas sem documenta��o de endpoint confirmada o suficiente pra
     * implementar sem testar ao vivo - ficou fora desta rodada, registrado
     * como pend�ncia). Cart�o e boleto ficam 100% transparentes (sem
     * redirect); s� o formul�rio de cart�o exige os campos de titular e
     * endere�o de cobran�a que a API do PagSeguro sempre exigiu - n�o �
     * escolha de UX nossa, � requisito deles pra aceitar a cobran�a.
     *
     * A confirma��o real de status ('aprovado') continua vindo do webhook
     * IPN existente (WebhookController::pagSeguro(), j� implementado e
     * testado) - a resposta s�ncrona daqui s� informa o status inicial da
     * transa��o (normalmente "1 - aguardando pagamento" ou "3 - paga" pra
     * alguns m�todos), sem alterar esse contrato.
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
            'itemDescription1' => mb_substr((string)($dados['descricao'] ?? 'Servi�o de Guincho - GuinchaF�cil'), 0, 100),
            'itemAmount1'      => number_format($valor, 2, '.', ''),
            'itemQuantity1'    => '1',
            'reference'        => (string)$pedidoId,
            'senderName'       => mb_substr((string)($dados['nome'] ?? ''), 0, 50),
            'senderEmail'      => (string)($dados['payerEmail'] ?? ''),
            'senderCPF'        => preg_replace('/\D+/', '', (string)($dados['docNumero'] ?? '')),
            'senderAreaCode'   => (string)($dados['telefoneDdd'] ?? ''),
            'senderPhone'      => (string)($dados['telefoneNumero'] ?? ''),
            'senderHash'       => (string)($dados['senderHash'] ?? ''),
            // Servi�o (reboque), n�o produto f�sico - sem frete a calcular.
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
            return ['sucesso' => false, 'status' => 'erro', 'idExterno' => null, 'detalhe' => [], 'erro' => 'Erro de comunica��o com o PagSeguro.'];
        }

        // �PS-XML-01 (mesmo padr�o do WebhookController): SimpleXML, nunca regex em XML.
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string)$resp);

        if ($xml === false) {
            error_log('[PagSeguroProvider][criarPagamento] Resposta n�o � XML v�lido (HTTP ' . $httpCode . '): ' . substr((string)$resp, 0, 500));
            return ['sucesso' => false, 'status' => 'erro', 'idExterno' => null, 'detalhe' => [], 'erro' => 'Resposta inv�lida do PagSeguro.'];
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
            '3', '4' => 'aprovado', // 3=paga, 4=dispon�vel
            '1', '2', '5', '6', '7' => 'pendente', // aguardando/em an�lise/em disputa/devolvida(*)/cancelada tratada abaixo
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
     * Passo 1 do Checkout Transparente PagSeguro: cria a sess�o que o
     * PagSeguroDirectPayment.js do browser precisa antes de gerar o
     * senderHash/tokenizar o cart�o. Sess�o � de uso �nico e expira r�pido
     * - deve ser pedida de novo a cada tentativa de pagamento, nunca
     * reaproveitada entre carregamentos de p�gina.
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
            return ['sucesso' => false, 'sessionId' => null, 'erro' => 'Erro de comunica��o com o PagSeguro.'];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string)$resp);
        $sessionId = $xml !== false ? (string)($xml->id ?? '') : '';

        if ($sessionId === '') {
            error_log('[PagSeguroProvider][criarSessao] Sem session id na resposta: ' . substr((string)$resp, 0, 300));
            return ['sucesso' => false, 'sessionId' => null, 'erro' => 'PagSeguro n�o retornou sess�o v�lida.'];
        }

        return ['sucesso' => true, 'sessionId' => $sessionId, 'erro' => null];
    }
}
