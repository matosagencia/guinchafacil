<?php
// File: guinchafacil/src/Services/NotificacaoService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Serviço de notificações por email (§9: usa PHPMailer + SMTP, mail() proibido)
 */
class NotificacaoService
{
    /**
     * Envia email HTML via PHPMailer + SMTP
     */
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
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($para);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $corpoHtml;

            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log('[NotificacaoService] Falha ao enviar email para ' . $para . ': ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Notifica cliente que pedido foi confirmado e está aguardando guincho
     */
    public static function pedidoConfirmado(array $pedido, array $cliente): void
    {
        $assunto = "Pedido #{$pedido['id']} confirmado - GuinchaFácil";
        $corpo = "
        <h2>Pedido confirmado!</h2>
        <p>Olá, <b>{$cliente['nome']}</b>!</p>
        <p>Seu pedido de socorro foi confirmado e estamos procurando o guincho mais próximo.</p>
        <table border='0' cellpadding='8'>
            <tr><td><b>Tipo:</b></td><td>{$pedido['tipo_problema']}</td></tr>
            <tr><td><b>Origem:</b></td><td>{$pedido['endereco_origem']}</td></tr>
            <tr><td><b>Destino:</b></td><td>{$pedido['endereco_destino']}</td></tr>
            <tr><td><b>Custo:</b></td><td>R$ " . number_format($pedido['custo_estimado'], 2, ',', '.') . "</td></tr>
        </table>
        <p>Acompanhe o status em tempo real no app.</p>
        <p>Equipe GuinchaFácil</p>";
        self::enviarEmail($cliente['email'], $assunto, $corpo);
    }

    /**
     * Notifica cliente que guincho aceitou o pedido
     */
    public static function guinchoAceito(array $pedido, array $cliente, array $guincho): void
    {
        $assunto = "Guincho a caminho! Pedido #{$pedido['id']} - GuinchaFácil";
        $corpo = "
        <h2>Guincho confirmado!</h2>
        <p>Olá, <b>{$cliente['nome']}</b>!</p>
        <p>Um guincho aceitou seu pedido e está a caminho.</p>
        <table border='0' cellpadding='8'>
            <tr><td><b>Guincheiro:</b></td><td>{$guincho['nome']}</td></tr>
            <tr><td><b>Telefone:</b></td><td>{$guincho['telefone']}</td></tr>
            <tr><td><b>Veículo:</b></td><td>{$guincho['placa_guincho']}</td></tr>
        </table>
        <p>Acompanhe a localização em tempo real no app.</p>
        <p>Equipe GuinchaFácil</p>";
        self::enviarEmail($cliente['email'], $assunto, $corpo);
    }

    /**
     * Notifica cliente e guincho que pedido foi concluído (§9: link de avaliação obrigatório)
     */
    public static function pedidoConcluido(array $pedido, array $cliente, array $guincho, float $valorGuincheiro): void
    {
        // Email para o cliente
        $assunto = "Pedido #{$pedido['id']} concluído - Avalie o serviço!";
        $corpo = "
        <h2>Serviço concluído!</h2>
        <p>Olá, <b>{$cliente['nome']}</b>! Seu veículo foi entregue com sucesso.</p>
        <p>Por favor, avalie o serviço prestado pelo guincheiro <b>{$guincho['nome']}</b>.</p>
        <p><a href='" . APP_URL . "/cliente/avaliar/{$pedido['id']}'
           style='background:#ff7f00;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>
           Avaliar serviço
        </a></p>
        <p>Equipe GuinchaFácil</p>";
        self::enviarEmail($cliente['email'], $assunto, $corpo);

        // Email para o guincheiro com valor real do split (§4.2)
        $assunto2 = "Atendimento #{$pedido['id']} finalizado - GuinchaFácil";
        $corpo2 = "
        <h2>Atendimento finalizado!</h2>
        <p>Olá, <b>{$guincho['nome']}</b>!</p>
        <p>O atendimento #{$pedido['id']} foi concluído. O pagamento será processado em breve via PIX.</p>
        <p>Valor a receber: <b>R$ " . number_format($valorGuincheiro, 2, ',', '.') . "</b></p>
        <p>Equipe GuinchaFácil</p>";
        self::enviarEmail($guincho['email'], $assunto2, $corpo2);
    }

    /**
     * Notifica guincho de novo pedido disponível na área
     */
    public static function novoPedidoParaGuincho(array $pedido, array $guincho_usuario): void
    {
        $assunto = "Novo pedido disponível perto de você! - GuinchaFácil";
        $corpo = "
        <h2>Novo pedido de socorro!</h2>
        <p>Olá, <b>{$guincho_usuario['nome']}</b>!</p>
        <p>Há um pedido de socorro próximo à sua localização.</p>
        <table border='0' cellpadding='8'>
            <tr><td><b>Tipo:</b></td><td>{$pedido['tipo_problema']}</td></tr>
            <tr><td><b>Local:</b></td><td>{$pedido['endereco_origem']}</td></tr>
            <tr><td><b>Destino:</b></td><td>{$pedido['endereco_destino']}</td></tr>
            <tr><td><b>Valor:</b></td><td>R$ " . number_format($pedido['custo_estimado'], 2, ',', '.') . "</td></tr>
        </table>
        <p><a href='" . APP_URL . "/guincho/aceitar/{$pedido['id']}'
           style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>
           Aceitar pedido
        </a></p>
        <p>Equipe GuinchaFácil</p>";
        self::enviarEmail($guincho_usuario['email'], $assunto, $corpo);
    }

    /**
     * Notifica guincho que seu cadastro foi aprovado
     */
    public static function cadastroAprovado(array $usuario): void
    {
        $assunto = "Cadastro aprovado! Bem-vindo à GuinchaFácil";
        $corpo = "
        <h2>Cadastro aprovado!</h2>
        <p>Olá, <b>{$usuario['nome']}</b>!</p>
        <p>Seu cadastro como guincheiro foi aprovado. Você já pode começar a receber pedidos!</p>
        <p><a href='" . APP_URL . "/guincho/dashboard'
           style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>
           Acessar painel
        </a></p>
        <p>Equipe GuinchaFácil</p>";
        self::enviarEmail($usuario['email'], $assunto, $corpo);
    }

    /**
     * Notifica admin sobre falha no repasse Pix (§4.3 + §9)
     */
    public static function falhaPixAdmin(array $pedido, string $erro): void
    {
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : SMTP_FROM_EMAIL;
        $assunto = "[ALERTA] Falha no repasse Pix — Pedido #{$pedido['id']}";
        $corpo = "
        <h2>Falha no repasse Pix</h2>
        <p>O repasse para o guincheiro do pedido <b>#{$pedido['id']}</b> falhou.</p>
        <p><b>Erro:</b> {$erro}</p>
        <p>O pagamento entrou na fila de reprocessamento automático.</p>
        <p>Verifique o painel administrativo para mais detalhes.</p>";
        self::enviarEmail($adminEmail, $assunto, $corpo);
    }
}
