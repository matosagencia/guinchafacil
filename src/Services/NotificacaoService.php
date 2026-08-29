<?php
// File: guinchafacil/src/Services/NotificacaoService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/../Models/Pagamento.php';

/**
 * Serviço de notificações por email (§9: PHPMailer + SMTP).
 * Todos os emails usam o template HTML com identidade visual GuinchaFácil.
 */
class NotificacaoService
{
    // ── Helpers internos ───────────────────────────────────────────────────

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private static function dinheiro(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private static function appUrl(): string
    {
        return rtrim(defined('APP_URL') ? APP_URL : 'https://guinchafacil.com.br', '/');
    }

    // ── Template base de email ─────────────────────────────────────────────

    /**
     * Envolve o conteúdo específico no layout HTML completo da GuinchaFácil.
     * @param string $preheader  Texto curto visível na prévia dos apps de email
     * @param string $titulo     Título do email (exibido no header colorido)
     * @param string $corpo      HTML do corpo principal
     * @param string $rodape     Linha extra opcional abaixo do corpo
     */
    private static function template(
        string $preheader,
        string $titulo,
        string $corpo,
        string $rodape = ''
    ): string {
        $logoUrl  = self::appUrl() . '/public/assets/img/logo-wordmark.png';
        $siteUrl  = self::appUrl();
        $anoAtual = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$titulo}</title>
<!--[if mso]><style>td,th,div,p,a,h1,h2,h3,h4,h5,h6{font-family:Arial,sans-serif!important;}</style><![endif]-->
<style>
  body,html{margin:0;padding:0;background:#f4f6f4;font-family:Arial,Helvetica,sans-serif;color:#152218;}
  .wrapper{width:100%;background:#f4f6f4;padding:24px 0;}
  .container{max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.10);}
  .header{background:linear-gradient(135deg,#1f8a36 0%,#2fb34a 60%,#38d458 100%);padding:32px 40px;text-align:center;}
  .header img{max-width:180px;height:auto;}
  .header-badge{display:inline-block;margin-top:14px;background:rgba(255,255,255,.18);color:#fff;border-radius:20px;padding:4px 16px;font-size:13px;letter-spacing:.5px;}
  .body{padding:36px 40px;}
  .title{font-size:22px;font-weight:700;color:#152218;margin:0 0 8px;}
  .subtitle{font-size:15px;color:#4e7257;margin:0 0 24px;}
  .info-box{background:#f0faf2;border-left:4px solid #2fb34a;border-radius:8px;padding:16px 20px;margin:20px 0;}
  .info-box table{width:100%;border-collapse:collapse;}
  .info-box td{padding:6px 4px;font-size:14px;vertical-align:top;}
  .info-box td:first-child{color:#4e7257;font-weight:600;width:38%;white-space:nowrap;}
  .info-box td:last-child{color:#152218;}
  .alert-box{background:#fff8e1;border-left:4px solid #f59e0b;border-radius:8px;padding:14px 18px;margin:16px 0;font-size:14px;color:#78350f;}
  .danger-box{background:#fff1f1;border-left:4px solid #dc2626;border-radius:8px;padding:14px 18px;margin:16px 0;font-size:14px;color:#991b1b;}
  .cta{text-align:center;margin:28px 0 16px;}
  .btn{display:inline-block;background:#2fb34a;color:#ffffff!important;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:16px;font-weight:700;letter-spacing:.3px;}
  .btn-danger{background:#dc2626;}
  .btn-blue{background:#2563eb;}
  .divider{height:1px;background:#e2f0e5;margin:24px 0;}
  .footer{background:#f0faf2;border-top:1px solid #cde5d1;padding:22px 40px;text-align:center;font-size:12px;color:#5e8a68;}
  .footer a{color:#2fb34a;text-decoration:none;}
  p{font-size:15px;line-height:1.65;color:#152218;margin:0 0 14px;}
  h3{font-size:17px;color:#1f8a36;margin:20px 0 8px;}
</style>
</head>
<body>
<!-- Preheader invisível -->
<div style="display:none;font-size:1px;color:#f4f6f4;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
  {$preheader}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
</div>

<div class="wrapper">
  <div class="container">

    <!-- CABEÇALHO -->
    <div class="header">
      <img src="{$logoUrl}" alt="GuinchaFácil" onerror="this.style.display='none'">
      <div class="header-badge">{$titulo}</div>
    </div>

    <!-- CORPO -->
    <div class="body">
      {$corpo}
      {$rodape}
    </div>

    <!-- RODAPÉ -->
    <div class="footer">
      <p style="margin:0 0 6px;">
        &copy; {$anoAtual} <a href="{$siteUrl}">GuinchaFácil</a> &mdash; Assistência 24h para o seu veículo
      </p>
      <p style="margin:0;font-size:11px;color:#7da882;">
        Este email foi enviado automaticamente. Não responda a este endereço.<br>
        <a href="{$siteUrl}">Acessar plataforma</a> &bull;
        <a href="{$siteUrl}/politica-privacidade">Política de Privacidade</a>
      </p>
    </div>

  </div>
</div>
</body>
</html>
HTML;
    }

    // ── Envio via PHPMailer ────────────────────────────────────────────────

    public static function enviarEmail(string $para, string $assunto, string $corpoHtml): bool
    {
        $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!file_exists($vendorAutoload)) {
            error_log('[NotificacaoService] vendor/autoload.php não encontrado. Execute: composer install');
            return false;
        }
        require_once $vendorAutoload;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : '';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
            $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
            $mail->SMTPSecure = ((int)(defined('SMTP_PORT') ? SMTP_PORT : 465) === 465)
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SMTP_USER') ? SMTP_USER : '');
            $fromName  = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : 'GuinchaFácil';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($para);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $corpoHtml;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h2>', '</h3>'], "\n", $corpoHtml));

            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log('[NotificacaoService] Falha ao enviar email para ' . $para . ': ' . $mail->ErrorInfo);
            return false;
        }
    }

    // ── Emails de negócio ──────────────────────────────────────────────────

    public static function pedidoConfirmado(array $pedido, array $cliente): void
    {
        $pedidoId   = (int)($pedido['id'] ?? 0);
        $nome       = self::e($cliente['nome'] ?? 'Cliente');
        $tipo       = self::e($pedido['tipo_problema'] ?? '—');
        $origem     = self::e($pedido['endereco_origem']  ?? '—');
        $destino    = self::e($pedido['endereco_destino'] ?? '—');
        $custo      = self::dinheiro((float)($pedido['custo_estimado'] ?? 0));
        $distancia  = number_format((float)($pedido['distancia_km'] ?? 0), 1, ',', '.') . ' km';
        $statusUrl  = self::appUrl() . "/cliente/pedido/{$pedidoId}";

        $corpo = <<<HTML
<p>Olá, <strong>{$nome}</strong>!</p>
<p>Seu pedido de socorro foi <strong>registrado com sucesso</strong> e já estamos localizando o guincho mais próximo de você. 🚛</p>

<div class="info-box">
  <table>
    <tr><td>Nº do Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Tipo do problema</td><td>{$tipo}</td></tr>
    <tr><td>Localização atual</td><td>{$origem}</td></tr>
    <tr><td>Destino</td><td>{$destino}</td></tr>
    <tr><td>Distância estimada</td><td>{$distancia}</td></tr>
    <tr><td>Custo estimado</td><td><strong>{$custo}</strong></td></tr>
  </table>
</div>

<p>Você receberá um novo email assim que um guincheiro aceitar seu pedido.<br>
Enquanto isso, acompanhe a situação em tempo real pela plataforma:</p>

<div class="cta">
  <a href="{$statusUrl}" class="btn">Acompanhar meu pedido</a>
</div>

<div class="divider"></div>
<p style="font-size:13px;color:#5e8a68;">
  Precisa de ajuda? Entre em contato pelo app ou responda a este email.
</p>
HTML;

        $html = self::template(
            "Pedido #{$pedidoId} confirmado — aguardando guincho disponível.",
            'Pedido Confirmado ✓',
            $corpo
        );
        self::enviarEmail((string)($cliente['email'] ?? ''), "Pedido #{$pedidoId} confirmado — GuinchaFácil", $html);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function guinchoAceito(array $pedido, array $cliente, array $guincho): void
    {
        $pedidoId    = (int)($pedido['id'] ?? 0);
        $nomeCliente = self::e($cliente['nome'] ?? 'Cliente');
        $nomeGuincho = self::e($guincho['nome'] ?? 'Guincheiro');
        $telefone    = self::e($guincho['telefone'] ?? '—');
        $placa       = self::e($guincho['placa_guincho'] ?? '—');
        $veiculo     = self::e($guincho['modelo_guincho'] ?? '');
        $statusUrl   = self::appUrl() . "/cliente/pedido/{$pedidoId}";

        $veiculoLinha = $veiculo ? "<tr><td>Veículo</td><td>{$veiculo} &mdash; {$placa}</td></tr>"
                                 : "<tr><td>Placa</td><td>{$placa}</td></tr>";

        $corpo = <<<HTML
<p>Olá, <strong>{$nomeCliente}</strong>!</p>
<p>Ótima notícia! Um guincheiro aceitou seu pedido e está a caminho da sua localização. 📍</p>

<div class="info-box">
  <table>
    <tr><td>Nº do Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Guincheiro</td><td><strong>{$nomeGuincho}</strong></td></tr>
    <tr><td>Telefone</td><td><a href="tel:{$telefone}" style="color:#2fb34a;">{$telefone}</a></td></tr>
    {$veiculoLinha}
  </table>
</div>

<div class="alert-box">
  ⏱️ <strong>Atenção:</strong> Permaneça próximo ao seu veículo e mantenha o telefone acessível. O guincheiro pode entrar em contato para confirmar sua localização exata.
</div>

<p>Acompanhe a localização do guincheiro em tempo real:</p>

<div class="cta">
  <a href="{$statusUrl}" class="btn">Ver localização ao vivo</a>
</div>
HTML;

        $html = self::template(
            "{$nomeGuincho} está a caminho! Pedido #{$pedidoId}.",
            'Guincho a Caminho 🚛',
            $corpo
        );
        self::enviarEmail((string)($cliente['email'] ?? ''), "Guincho confirmado — Pedido #{$pedidoId}", $html);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function pedidoConcluido(array $pedido, array $cliente, array $guincho, float $valorGuincheiro): void
    {
        $pedidoId    = (int)($pedido['id'] ?? 0);
        $nomeCliente = self::e($cliente['nome'] ?? 'Cliente');
        $nomeGuincho = self::e($guincho['nome'] ?? 'Guincheiro');
        $avaliacaoUrl = self::appUrl() . "/cliente/avaliar/{$pedidoId}";

        $origem  = self::e($pedido['endereco_origem']  ?? '—');
        $destino = self::e($pedido['endereco_destino'] ?? '—');

        // ── Email para o CLIENTE ──
        $novoPedidoUrl = self::appUrl() . '/cliente/pedido/novo';

        $htmlCliente = self::template(
            "Serviço concluído. Avalie {$nomeGuincho} e ajude outros motoristas.",
            'Serviço Concluído ✓',
            <<<HTML
<p>Olá, <strong>{$nomeCliente}</strong>!</p>
<p>Seu veículo foi entregue com sucesso. Esperamos ter sido úteis! 🎉</p>

<div class="info-box">
  <table>
    <tr><td>Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Origem</td><td>{$origem}</td></tr>
    <tr><td>Destino</td><td>{$destino}</td></tr>
    <tr><td>Guincheiro</td><td>{$nomeGuincho}</td></tr>
  </table>
</div>

<h3>⭐ Avalie o atendimento</h3>
<p>Sua opinião ajuda outros motoristas a escolherem os melhores profissionais da plataforma. Leva menos de 1 minuto!</p>

<div class="cta">
  <a href="{$avaliacaoUrl}" class="btn">Deixar avaliação</a>
</div>

<div class="divider"></div>
<p style="font-size:13px;color:#5e8a68;">
  Precisou de outro socorro? <a href="{$novoPedidoUrl}" style="color:#2fb34a;">Solicitar novo atendimento</a>.
</p>
HTML
        );
        self::enviarEmail((string)($cliente['email'] ?? ''), "Serviço concluído — Avalie o atendimento! (Pedido #{$pedidoId})", $htmlCliente);

        // ── Email para o GUINCHEIRO ──
        $pagamento   = Pagamento::buscarPorPedido($pedidoId);
        $valorReal   = ($pagamento && (float)($pagamento['valor_guincho'] ?? 0) > 0)
                     ? (float)$pagamento['valor_guincho'] : $valorGuincheiro;
        $valorFmt    = self::dinheiro($valorReal);
        $painelUrl   = self::appUrl() . '/guincho/dashboard';

        $htmlGuincho = self::template(
            "Atendimento #{$pedidoId} finalizado. Pagamento via Pix em processamento.",
            'Atendimento Finalizado',
            <<<HTML
<p>Olá, <strong>{$nomeGuincho}</strong>!</p>
<p>O atendimento do pedido <strong>#{$pedidoId}</strong> foi concluído com sucesso. Obrigado pelo trabalho! 💪</p>

<div class="info-box">
  <table>
    <tr><td>Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Valor a receber</td><td><strong>{$valorFmt}</strong></td></tr>
    <tr><td>Forma de pagamento</td><td>Pix</td></tr>
    <tr><td>Status</td><td>Em processamento</td></tr>
  </table>
</div>

<div class="alert-box">
  💡 O repasse via Pix é processado automaticamente após a confirmação do pagamento do cliente. Em casos de demora, a equipe GuinchaFácil atuará manualmente.
</div>

<div class="cta">
  <a href="{$painelUrl}" class="btn">Acessar meu painel</a>
</div>
HTML
        );
        self::enviarEmail((string)($guincho['email'] ?? ''), "Atendimento #{$pedidoId} finalizado — GuinchaFácil", $htmlGuincho);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function pedidoCancelado(array $pedido, array $cliente, string $motivo, bool $comEstorno): bool
    {
        $pedidoId       = (int)($pedido['id'] ?? 0);
        $nome           = self::e($cliente['nome'] ?? 'Cliente');
        $motivoEsc      = self::e($motivo);
        $novoPedidoUrl  = self::appUrl() . '/cliente/pedido/novo';

        $blocoEstorno = $comEstorno
            ? '<div class="alert-box">💳 <strong>Reembolso solicitado:</strong> O valor pago foi enviado para estorno ao gateway de pagamento. O prazo varia conforme a operadora do cartão (geralmente 1–7 dias úteis).</div>'
            : '<div class="alert-box">ℹ️ Não havia pagamento processado para este pedido, portanto não há valor a estornar.</div>';

        $html = self::template(
            "Pedido #{$pedidoId} cancelado. {$motivoEsc}",
            'Pedido Cancelado',
            <<<HTML
<p>Olá, <strong>{$nome}</strong>,</p>
<p>Informamos que o seu pedido de socorro foi <strong>cancelado</strong>.</p>

<div class="info-box">
  <table>
    <tr><td>Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Motivo</td><td>{$motivoEsc}</td></tr>
  </table>
</div>

{$blocoEstorno}

<p>Se precisar de novo atendimento, basta solicitar novamente — estamos disponíveis 24 horas:</p>

<div class="cta">
  <a href="{$novoPedidoUrl}" class="btn btn-blue">Solicitar novo socorro</a>
</div>

<div class="divider"></div>
<p style="font-size:13px;color:#5e8a68;">
  Se você cancelou por engano ou tem dúvidas, entre em contato com nosso suporte.
</p>
HTML
        );

        return self::enviarEmail((string)($cliente['email'] ?? ''), "Pedido #{$pedidoId} cancelado — GuinchaFácil", $html);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function novoPedidoParaGuincho(array $pedido, array $guincho_usuario): void
    {
        $pedidoId   = (int)($pedido['id'] ?? 0);
        $nome       = self::e($guincho_usuario['nome'] ?? 'Guincheiro');
        $tipo       = self::e($pedido['tipo_problema'] ?? '—');
        $origem     = self::e($pedido['endereco_origem']  ?? '—');
        $destino    = self::e($pedido['endereco_destino'] ?? '—');
        $custo      = self::dinheiro((float)($pedido['custo_estimado'] ?? 0));
        $distancia  = number_format((float)($pedido['distancia_km'] ?? 0), 1, ',', '.') . ' km';
        $aceitarUrl = self::appUrl() . "/guincho/aceitar/{$pedidoId}";
        $painelUrlG = self::appUrl() . '/guincho/dashboard';

        $html = self::template(
            "Novo pedido #{$pedidoId} disponível perto de você — {$tipo}.",
            'Novo Pedido Disponível 🚛',
            <<<HTML
<p>Olá, <strong>{$nome}</strong>!</p>
<p>Há um pedido de socorro próximo à sua localização aguardando atendimento. Seja o primeiro a aceitar!</p>

<div class="info-box">
  <table>
    <tr><td>Nº do Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Tipo de problema</td><td><strong>{$tipo}</strong></td></tr>
    <tr><td>Local do cliente</td><td>{$origem}</td></tr>
    <tr><td>Destino</td><td>{$destino}</td></tr>
    <tr><td>Distância da rota</td><td>{$distancia}</td></tr>
    <tr><td>Valor do serviço</td><td><strong>{$custo}</strong></td></tr>
  </table>
</div>

<div class="alert-box">
  ⚡ <strong>Atenção:</strong> Pedidos expiram em 30 minutos ou quando aceitos por outro guincheiro. Aceite rápido!
</div>

<div class="cta">
  <a href="{$aceitarUrl}" class="btn">Aceitar este pedido</a>
</div>

<div class="divider"></div>
<p style="font-size:13px;color:#5e8a68;">
  Para gerenciar seus pedidos, <a href="{$painelUrlG}" style="color:#2fb34a;">acesse seu painel</a>.
</p>
HTML
        );

        self::enviarEmail((string)($guincho_usuario['email'] ?? ''), "Novo pedido #{$pedidoId} aguarda você — GuinchaFácil", $html);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function cadastroAprovado(array $usuario): void
    {
        $nome      = self::e($usuario['nome'] ?? 'Guincheiro');
        $email     = self::e($usuario['email'] ?? '');
        $painelUrl = self::appUrl() . '/guincho/dashboard';

        $html = self::template(
            "Parabéns, {$nome}! Seu cadastro como guincheiro foi aprovado.",
            'Cadastro Aprovado! 🎉',
            <<<HTML
<p>Olá, <strong>{$nome}</strong>!</p>
<p>Temos uma ótima notícia: seu cadastro como guincheiro na <strong>GuinchaFácil</strong> foi <strong>aprovado</strong>! Bem-vindo à plataforma! 🚛✅</p>

<h3>O que acontece agora?</h3>
<div class="info-box">
  <table>
    <tr><td>📍</td><td>Mantenha sua localização atualizada no app para receber pedidos próximos.</td></tr>
    <tr><td>🔔</td><td>Você será notificado por email e app quando um pedido for compatível com sua área.</td></tr>
    <tr><td>💳</td><td>Os pagamentos são repassados via Pix após cada atendimento concluído.</td></tr>
    <tr><td>⭐</td><td>Boas avaliações aumentam sua visibilidade e prioridade nos pedidos.</td></tr>
  </table>
</div>

<p>Acesse o seu painel para configurar seu perfil, cadastrar seu veículo e começar a receber pedidos:</p>

<div class="cta">
  <a href="{$painelUrl}" class="btn">Acessar meu painel agora</a>
</div>

<div class="divider"></div>
<p style="font-size:13px;color:#5e8a68;">
  Email de acesso: <strong>{$email}</strong><br>
  Dúvidas? Acesse a central de ajuda no painel.
</p>
HTML
        );
        self::enviarEmail((string)($usuario['email'] ?? ''), 'Cadastro aprovado — Bem-vindo à GuinchaFácil!', $html);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function pixEfetivadoAdmin(array $pedido, array $guincho, array $pagamento): bool
    {
        $adminEmail  = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '');
        $pedidoId    = (int)($pedido['id'] ?? 0);
        $nomeGuincho = self::e($guincho['nome'] ?? '—');
        $emailGuincho = self::e($guincho['email'] ?? '—');
        $valor       = self::dinheiro((float)($pagamento['valor_guincho'] ?? 0));
        $transacao   = self::e($pagamento['id_transacao_pix'] ?? '—');
        $adminUrl    = self::appUrl() . "/admin/pedido/{$pedidoId}";
        $dataHora    = date('d/m/Y H:i');

        $html = self::template(
            "Pix efetivado — Pedido #{$pedidoId} — {$valor}.",
            'Repasse Pix Efetivado ✓',
            <<<HTML
<p>Um repasse via Pix foi processado com sucesso.</p>

<div class="info-box">
  <table>
    <tr><td>Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Guincheiro</td><td>{$nomeGuincho}</td></tr>
    <tr><td>Email</td><td>{$emailGuincho}</td></tr>
    <tr><td>Valor repassado</td><td><strong>{$valor}</strong></td></tr>
    <tr><td>ID da transação</td><td><code>{$transacao}</code></td></tr>
    <tr><td>Data/Hora</td><td>{$dataHora}</td></tr>
  </table>
</div>

<div class="cta">
  <a href="{$adminUrl}" class="btn">Ver detalhes do pedido</a>
</div>
HTML
        );

        return self::enviarEmail($adminEmail, "[GuinchaFácil] Pix efetivado — Pedido #{$pedidoId}", $html);
    }

    // ─────────────────────────────────────────────────────────────────────

    public static function falhaPixAdmin(array $pedido, string $erro): void
    {
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '');
        $pedidoId   = (int)($pedido['id'] ?? 0);
        $erroEsc    = self::e($erro);
        $adminUrl   = self::appUrl() . "/admin/pedido/{$pedidoId}";
        $dataHora   = date('d/m/Y H:i');

        $html = self::template(
            "[ALERTA] Falha no repasse Pix — Pedido #{$pedidoId}. Ação manual necessária.",
            '⚠️ Falha no Repasse Pix',
            <<<HTML
<div class="danger-box">
  🚨 <strong>Ação necessária:</strong> O repasse Pix do pedido <strong>#{$pedidoId}</strong> falhou e entrou na fila de reprocessamento automático.
</div>

<div class="info-box">
  <table>
    <tr><td>Pedido</td><td><strong>#{$pedidoId}</strong></td></tr>
    <tr><td>Data/Hora</td><td>{$dataHora}</td></tr>
    <tr><td>Erro reportado</td><td><code>{$erroEsc}</code></td></tr>
    <tr><td>Ação automática</td><td>Fila de reprocessamento ativada</td></tr>
  </table>
</div>

<p>O sistema tentará reprocessar automaticamente. Caso persista, intervenção manual é necessária.</p>

<div class="cta">
  <a href="{$adminUrl}" class="btn btn-danger">Verificar pedido agora</a>
</div>
HTML
        );
        self::enviarEmail($adminEmail, "[ALERTA] Falha no Pix — Pedido #{$pedidoId} — GuinchaFácil", $html);
    }
}
