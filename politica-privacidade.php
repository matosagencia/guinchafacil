<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Models/Configuracao.php';

$companyName = 'GuinchaFácil';
$whatsDigits = preg_replace('/\D+/', '', (string)COMPANY_WHATSAPP);
$privacyEmail = defined('ADMIN_EMAIL') && ADMIN_EMAIL !== '' ? (string)ADMIN_EMAIL : 'privacidade@guinchafacil.com.br';
$retention = Configuracao::getMultiplas([
    'retention_por_days',
    'retention_evidencias_days',
    'retention_chat_days',
    'retention_jsonl_logs_days',
    'retention_simulation_runs_days',
]);

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
    <title>Política de Privacidade - GuinchaFácil</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.65; margin: 0; padding: 24px; background: #f5f5f5; color: #222; }
        .container { max-width: 920px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 10px; box-shadow: 0 10px 24px rgba(0,0,0,.08); }
        h1, h2 { color: #1f2937; }
        p, li { color: #4b5563; }
        a { color: #0f766e; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .muted { color: #6b7280; }
        .box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; margin: 18px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; font-size: 0.95rem; }
        th { background: #f8fafc; color: #1f2937; }
    </style>
</head>
<body>
<div class="container">
    <h1>Política de Privacidade - <?php echo h($companyName); ?></h1>
    <p class="muted">Última atualização: <?php echo date('d/m/Y'); ?></p>

    <p>Esta Política de Privacidade descreve como a plataforma <?php echo h($companyName); ?> trata dados pessoais, dados operacionais e evidências associadas aos atendimentos. O documento foi estruturado para refletir a operação real da plataforma, inclusive geolocalização, prova operacional de deslocamento (POR), chat, evidências de coleta/entrega, faturamento, repasse e auditoria.</p>

    <h2>1. Controlador e contato</h2>
    <p>Para os fins da legislação aplicável de proteção de dados, o controlador responsável pelo tratamento é a operação identificada como <strong><?php echo h($companyName); ?></strong>.</p>
    <p>
        Endereço operacional informado: <?php echo h((string)COMPANY_ADDRESS); ?><br>
        WhatsApp: <a href="https://wa.me/55<?php echo h($whatsDigits); ?>"><?php echo h((string)COMPANY_WHATSAPP); ?></a><br>
        Canal de privacidade e direitos do titular: <a href="mailto:<?php echo h($privacyEmail); ?>"><?php echo h($privacyEmail); ?></a>
    </p>

    <h2>2. Dados que tratamos</h2>
    <ul>
        <li><strong>Cadastro:</strong> nome, e-mail, telefone, CPF, endereço, dados do veículo, dados operacionais e bancários do guincheiro.</li>
        <li><strong>Atendimento:</strong> pedido, origem, destino, tipo do problema, horários, status, valores, chat e avaliação.</li>
        <li><strong>Geolocalização operacional:</strong> coordenadas, precisão, sequência, velocidade estimada, horário do dispositivo e horário do servidor.</li>
        <li><strong>Evidências:</strong> imagens e metadados vinculados à coleta e à entrega, incluindo associação a ponto GPS e horário do evento.</li>
        <li><strong>Pagamentos e repasses:</strong> identificadores transacionais, status de cobrança, estorno, repasse e logs relacionados.</li>
        <li><strong>Segurança e auditoria:</strong> request ID, logs técnicos, trilhas de auditoria, eventos administrativos e métricas operacionais.</li>
    </ul>

    <h2>3. Finalidades do tratamento</h2>
    <ul>
        <li>cadastrar usuários e gerir permissões de acesso;</li>
        <li>operar o pedido e coordenar o atendimento;</li>
        <li>prevenir fraude, validar deslocamento e comprovar etapas do serviço;</li>
        <li>processar cobrança, cancelamento, estorno e repasse;</li>
        <li>atender exigências legais, regulatórias e de prevenção a abuso;</li>
        <li>melhorar disponibilidade, segurança, observabilidade e suporte da plataforma.</li>
    </ul>

    <h2>4. Base legal</h2>
    <p>O tratamento pode se fundamentar, conforme o caso, em execução de contrato, exercício regular de direitos, legítimo interesse, cumprimento de obrigação legal ou regulatória, proteção do crédito, prevenção a fraude e, quando aplicável, consentimento.</p>

    <h2>5. Localização, POR e evidências operacionais</h2>
    <p>Durante o atendimento, a plataforma pode tratar dados de localização do guincheiro para compor a prova operacional do percurso. Esse tratamento serve, entre outras finalidades, para:</p>
    <ul>
        <li>confirmar deslocamento real até a origem e o destino;</li>
        <li>bloquear mudança de status fora da geofence operacional;</li>
        <li>comprovar distância e tempo em casos de cancelamento ou contestação;</li>
        <li>vincular evidências de coleta e entrega ao evento efetivamente ocorrido.</li>
    </ul>
    <p>O sistema pode rejeitar pontos inconsistentes ou suspeitos e manter registros técnicos de rejeição para auditoria.</p>

    <h2>6. Compartilhamento de dados</h2>
    <p>Os dados podem ser compartilhados estritamente na medida necessária com:</p>
    <ul>
        <li>guincheiros e clientes envolvidos no mesmo atendimento;</li>
        <li>provedores de infraestrutura, e-mail, hospedagem, observabilidade e antifraude;</li>
        <li>gateways de pagamento e parceiros financeiros do fluxo transacional;</li>
        <li>autoridades públicas ou judiciais, quando houver obrigação legal ou requisição válida.</li>
    </ul>
    <p>Não há venda de dados pessoais como atividade principal da plataforma.</p>

    <h2>7. Retenção e descarte</h2>
    <p>Os prazos abaixo refletem a política operacional atualmente configurada no sistema, podendo ser ajustados por obrigação legal, disputa, auditoria, prevenção a fraude ou revisão administrativa pendente.</p>
    <table>
        <thead>
        <tr><th>Categoria</th><th>Prazo operacional atual</th></tr>
        </thead>
        <tbody>
        <tr><td>Pontos POR de pedidos encerrados</td><td><?php echo h((string)($retention['retention_por_days'] ?? '180')); ?> dias</td></tr>
        <tr><td>Evidências operacionais</td><td><?php echo h((string)($retention['retention_evidencias_days'] ?? '365')); ?> dias</td></tr>
        <tr><td>Chat de pedidos encerrados</td><td><?php echo h((string)($retention['retention_chat_days'] ?? '365')); ?> dias</td></tr>
        <tr><td>Logs JSONL em disco</td><td><?php echo h((string)($retention['retention_jsonl_logs_days'] ?? '30')); ?> dias</td></tr>
        <tr><td>Registros de execução QA</td><td><?php echo h((string)($retention['retention_simulation_runs_days'] ?? '30')); ?> dias</td></tr>
        </tbody>
    </table>
    <p>Ao final do prazo aplicável, os registros podem ser eliminados, agregados, anonimizados ou mantidos pelo período adicional necessário para defesa de direitos, cumprimento de obrigação legal ou tratamento de disputa em aberto.</p>

    <h2>8. Direitos do titular</h2>
    <p>Observadas as limitações legais e a necessidade de preservação mínima de registros operacionais e antifraude, o titular poderá solicitar:</p>
    <ul>
        <li>confirmação da existência de tratamento;</li>
        <li>acesso aos dados;</li>
        <li>correção de dados incompletos, inexatos ou desatualizados;</li>
        <li>anonimização, bloqueio ou eliminação de dados excessivos ou tratados em desconformidade;</li>
        <li>informação sobre compartilhamentos;</li>
        <li>revisão de contestação operacional ou financeira;</li>
        <li>informações sobre critérios de retenção aplicáveis ao seu caso.</li>
    </ul>

    <h2>9. Contestação de taxa, cancelamento e uso de trilha</h2>
    <p>Quando houver divergência sobre cancelamento, taxa ou prestação do serviço, a plataforma poderá utilizar trilha POR, eventos de geofence, chat, horários, logs e evidências associadas ao pedido para revisão interna. O titular pode solicitar análise administrativa pelos canais indicados nesta Política.</p>

    <h2>10. Segurança da informação</h2>
    <p>A plataforma adota medidas de segurança compatíveis com sua operação, incluindo controles de acesso, segregação de ambiente, auditoria, mascaramento de dados em logs, validação de arquivos, hardening de configuração, monitoramento de cron e retenção operacional. Ainda assim, nenhum ambiente é absolutamente imune a incidentes.</p>

    <h2>11. Cookies e registros técnicos</h2>
    <p>A plataforma pode utilizar cookies de sessão, identificadores técnicos e registros operacionais estritamente necessários ao funcionamento, autenticação, estabilidade, prevenção a abuso e investigação de incidentes.</p>

    <h2>12. Atualizações desta Política</h2>
    <p>Esta Política poderá ser atualizada sempre que houver alteração legal, técnica ou operacional relevante. A data de atualização será revisada nesta página.</p>

    <div class="box">
        <strong>Canal para LGPD, retenção e contestação:</strong><br>
        E-mail: <a href="mailto:<?php echo h($privacyEmail); ?>"><?php echo h($privacyEmail); ?></a><br>
        WhatsApp: <a href="https://wa.me/55<?php echo h($whatsDigits); ?>"><?php echo h((string)COMPANY_WHATSAPP); ?></a>
    </div>
</div>
</body>
</html>
