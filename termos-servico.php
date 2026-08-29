<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$companyName = 'GuinchaFácil';
$whatsDigits = preg_replace('/\D+/', '', (string)COMPANY_WHATSAPP);
$adminEmail = defined('ADMIN_EMAIL') && ADMIN_EMAIL !== '' ? (string)ADMIN_EMAIL : 'privacidade@guinchafacil.com.br';
$paymentGateway = strtoupper((string)(defined('PAYMENT_GATEWAY_ACTIVE') ? PAYMENT_GATEWAY_ACTIVE : 'mercadopago'));

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Serviço - GuinchaFácil</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.65; margin: 0; padding: 24px; background: #f5f5f5; color: #222; }
        .container { max-width: 920px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 10px; box-shadow: 0 10px 24px rgba(0,0,0,.08); }
        h1, h2 { color: #1f2937; }
        p, li { color: #4b5563; }
        a { color: #0f766e; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .muted { color: #6b7280; }
        .box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; margin: 18px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>Termos de Serviço - <?php echo h($companyName); ?></h1>
    <p class="muted">Última atualização: <?php echo date('d/m/Y'); ?></p>

    <p>Estes Termos de Serviço regulam o acesso e o uso da plataforma <?php echo h($companyName); ?>, incluindo site, painel administrativo, área do cliente, área do guincheiro e ferramentas de suporte operacional. Ao utilizar a plataforma, o usuário declara que leu, compreendeu e concorda com estes Termos.</p>

    <h2>1. Objeto</h2>
    <p>A plataforma conecta clientes, guincheiros e administradores para viabilizar serviços de assistência e remoção veicular, incluindo abertura de pedido, aceitação, deslocamento, evidências operacionais, pagamento, cancelamento, atendimento e encerramento da ocorrência.</p>

    <h2>2. Elegibilidade e cadastro</h2>
    <ul>
        <li>O cliente deve ter capacidade civil para contratar.</li>
        <li>O guincheiro deve fornecer dados cadastrais e operacionais válidos, incluindo documentação, dados bancários e informações do veículo de atendimento.</li>
        <li>Todos os usuários devem manter dados corretos e atualizados.</li>
        <li>O uso de cadastro falso, incompleto, fraudulento ou de terceiro sem autorização pode gerar suspensão ou encerramento da conta.</li>
    </ul>

    <h2>3. Fluxo operacional do atendimento</h2>
    <ul>
        <li>O pedido pode passar por etapas como aguardando pagamento, aguardando guincho, deslocamento, chegada ao local, embarque/reboque, conclusão ou cancelamento.</li>
        <li>O atendimento pode depender de geolocalização, prova operacional de deslocamento, evidências fotográficas e validações antifraude.</li>
        <li>O sistema pode bloquear mudanças de status quando os critérios operacionais mínimos não forem atendidos.</li>
    </ul>

    <h2>4. Geolocalização, POR e evidências</h2>
    <p>Para segurança, auditoria operacional, prevenção a fraude, cálculo de cancelamento e comprovação da prestação do serviço, a plataforma pode registrar:</p>
    <ul>
        <li>localização do guincheiro durante o atendimento;</li>
        <li>horário, sequência, precisão e consistência dos pontos enviados;</li>
        <li>ruas, distância validada e trilha operacional do deslocamento;</li>
        <li>evidências de coleta e entrega associadas ao pedido, ao ponto GPS e ao horário do evento.</li>
    </ul>
    <p>O envio de localização e evidências fora do contexto do atendimento, por meios artificiais ou com inconsistência material, pode resultar em rejeição do evento, auditoria manual, bloqueio de repasse e medidas administrativas adicionais.</p>

    <h2>5. Preço, pagamento e repasse</h2>
    <ul>
        <li>O valor estimado ou final do serviço será informado conforme a lógica comercial e operacional vigente no momento do pedido.</li>
        <li>O pagamento poderá ser intermediado por gateway habilitado na plataforma, atualmente em modo operacional identificado como <strong><?php echo h($paymentGateway); ?></strong>.</li>
        <li>Taxas adicionais justificadas por distância, espera, reboque efetivo, deslocamento comprovado ou cancelamento podem ser aplicadas conforme as regras do sistema e da operação.</li>
        <li>O repasse ao guincheiro pode ocorrer de forma assíncrona após a conclusão operacional e as validações antifraude.</li>
    </ul>

    <h2>6. Cancelamento e contestação</h2>
    <ul>
        <li>O pedido pode ser cancelado por cliente, guincheiro, administrador ou por regra automática do sistema, conforme o estágio do atendimento.</li>
        <li>Após aceite e deslocamento efetivo, o cálculo de cancelamento pode considerar distância e tempo comprovados pelo percurso validado.</li>
        <li>Quando houver cobrança, taxa, retenção ou estorno parcial, o usuário poderá solicitar revisão administrativa.</li>
    </ul>
    <div class="box">
        <strong>Canal de contestação:</strong><br>
        WhatsApp: <a href="https://wa.me/55<?php echo h($whatsDigits); ?>"><?php echo h((string)COMPANY_WHATSAPP); ?></a><br>
        E-mail: <a href="mailto:<?php echo h($adminEmail); ?>"><?php echo h($adminEmail); ?></a><br>
        O pedido de revisão deve informar, sempre que possível, número do pedido, data, descrição da divergência e documentos de suporte.
    </div>

    <h2>7. Obrigações do cliente</h2>
    <ul>
        <li>Informar corretamente o local de origem, destino, tipo do problema e características relevantes do veículo.</li>
        <li>Não acionar a plataforma para fins ilícitos, perigosos ou incompatíveis com o serviço.</li>
        <li>Responder pelas informações prestadas e pelos itens deixados no veículo.</li>
    </ul>

    <h2>8. Obrigações do guincheiro</h2>
    <ul>
        <li>Executar o atendimento com diligência, dentro dos limites operacionais e legais aplicáveis.</li>
        <li>Manter disponibilidade, documentação e dados bancários atualizados.</li>
        <li>Registrar localização e evidências de forma fiel ao atendimento realizado.</li>
        <li>Não utilizar a plataforma para manipular distância, status, fotos, localização ou valores.</li>
    </ul>

    <h2>9. Suspensão, bloqueio e auditoria</h2>
    <p>A plataforma poderá suspender ou limitar contas, reter repasse, exigir revisão manual ou encerrar o acesso em casos de suspeita de fraude, descumprimento contratual, uso abusivo, inconsistência operacional grave, ordem legal ou risco à segurança dos envolvidos.</p>

    <h2>10. Limitação de responsabilidade</h2>
    <p>A plataforma atua como intermediadora e coordenadora tecnológica da operação. Sem prejuízo das obrigações legais aplicáveis, não garante disponibilidade ininterrupta, cobertura universal, resposta imediata em toda localidade ou ausência absoluta de falhas técnicas, operacionais ou de terceiros. Eventuais incidentes serão tratados conforme este Termo, a Política de Privacidade e a legislação aplicável.</p>

    <h2>11. Propriedade intelectual e uso da plataforma</h2>
    <p>O usuário não poderá copiar, explorar, desmontar, automatizar de forma abusiva, contornar restrições técnicas ou utilizar a plataforma para fins diversos daqueles previstos nestes Termos.</p>

    <h2>12. Privacidade e proteção de dados</h2>
    <p>O tratamento de dados pessoais, dados operacionais de localização, evidências e registros de atendimento segue a Política de Privacidade da plataforma, disponível em <a href="/politica-privacidade.php">Política de Privacidade</a>.</p>

    <h2>13. Alterações destes Termos</h2>
    <p>Os Termos poderão ser atualizados para refletir mudanças legais, operacionais, técnicas ou comerciais. A versão vigente será publicada nesta página com a respectiva data de atualização.</p>

    <h2>14. Contato institucional</h2>
    <p>
        <strong><?php echo h($companyName); ?></strong><br>
        Endereço informado pela operação: <?php echo h((string)COMPANY_ADDRESS); ?><br>
        WhatsApp: <a href="https://wa.me/55<?php echo h($whatsDigits); ?>"><?php echo h((string)COMPANY_WHATSAPP); ?></a><br>
        E-mail administrativo: <a href="mailto:<?php echo h($adminEmail); ?>"><?php echo h($adminEmail); ?></a>
    </p>
</div>
</body>
</html>
