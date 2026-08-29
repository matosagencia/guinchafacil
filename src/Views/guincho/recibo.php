<?php
declare(strict_types=1);

$bp = defined('BASE_PATH') ? BASE_PATH : '';
$guinchoCompleto = $guinchoCompleto ?? [];
$pedido = $pedido ?? [];
$pagamento = $pagamento ?? [];

$money = static fn($value): string => 'R$ ' . number_format((float)$value, 2, ',', '.');

$bruto = (float)($pagamento['valor_total'] ?? $pedido['custo_final'] ?? $pedido['custo_estimado'] ?? 0);
$liquido = (float)($pagamento['valor_guincho'] ?? 0);
$comissao = max(0, $bruto - $liquido);
$comissaoPercent = $bruto > 0 ? round(($comissao / $bruto) * 100, 2) : 0;

$dataRepasse = !empty($pagamento['data_pagamento_guincho'])
    ? date('d/m/Y \à\s H:i', strtotime((string)$pagamento['data_pagamento_guincho']))
    : '—';
$dataPedido = !empty($pedido['criado_em'])
    ? date('d/m/Y', strtotime((string)$pedido['criado_em']))
    : '—';

$numeroRecibo = 'PIX-' . str_pad((string)($pedido['id'] ?? 0), 6, '0', STR_PAD_LEFT) . '-' . date('Ymd', strtotime((string)($pagamento['data_pagamento_guincho'] ?? 'now')));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo #<?php echo htmlspecialchars($numeroRecibo); ?> — GuinchaFácil</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f2f4f7;
            margin: 0;
            padding: 24px;
            color: #1f2937;
        }
        .recibo {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
            padding: 40px;
        }
        .recibo-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .recibo-header h1 { margin: 0; font-size: 22px; color: #0d6efd; }
        .recibo-header .numero { text-align: right; font-size: 13px; color: #6b7280; }
        .recibo-header .numero strong { display: block; font-size: 15px; color: #1f2937; }
        .bloco { margin-bottom: 22px; }
        .bloco h2 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            margin: 0 0 8px;
        }
        .bloco p { margin: 2px 0; font-size: 14px; }
        table.valores { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.valores td { padding: 8px 0; border-bottom: 1px solid #eef0f3; }
        table.valores td:last-child { text-align: right; }
        table.valores tr.total td { font-weight: 700; font-size: 16px; border-top: 2px solid #1f2937; border-bottom: none; padding-top: 12px; }
        .badge-pago {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
        }
        .disclaimer {
            margin-top: 28px;
            padding: 14px 16px;
            background: #fff8e1;
            border: 1px solid #fde68a;
            border-radius: 6px;
            font-size: 12.5px;
            color: #7c5a00;
        }
        .rodape {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #eef0f3;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .acoes { max-width: 720px; margin: 16px auto 0; text-align: right; }
        .acoes button {
            background: #0d6efd; color: #fff; border: none; border-radius: 6px;
            padding: 8px 18px; font-size: 14px; cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .acoes { display: none; }
            .recibo { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="recibo">
        <div class="recibo-header">
            <div>
                <h1>GuinchaFácil</h1>
                <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                    <?php echo htmlspecialchars(defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : ''); ?><br>
                    WhatsApp: <?php echo htmlspecialchars(defined('COMPANY_WHATSAPP') ? COMPANY_WHATSAPP : ''); ?>
                </div>
            </div>
            <div class="numero">
                Recibo de repasse
                <strong>#<?php echo htmlspecialchars($numeroRecibo); ?></strong>
                <span class="badge-pago">PAGO VIA PIX</span>
            </div>
        </div>

        <div class="bloco">
            <h2>Prestador de serviço (recebedor)</h2>
            <p><strong><?php echo htmlspecialchars((string)($guinchoCompleto['nome_operador'] ?? '—')); ?></strong></p>
            <?php if (!empty($guinchoCompleto['cpf'])): ?>
            <p>CPF/CNPJ: <?php echo htmlspecialchars((string)$guinchoCompleto['cpf']); ?></p>
            <?php endif; ?>
            <p>Chave Pix: <?php echo htmlspecialchars((string)($guinchoCompleto['chave_pix'] ?? '—')); ?></p>
        </div>

        <div class="bloco">
            <h2>Atendimento</h2>
            <p>Pedido #<?php echo (int)($pedido['id'] ?? 0); ?> — <?php echo htmlspecialchars((string)($pedido['tipo_problema'] ?? '')); ?></p>
            <p>Data do atendimento: <?php echo htmlspecialchars($dataPedido); ?></p>
            <p>Origem: <?php echo htmlspecialchars((string)($pedido['endereco_origem'] ?? '—')); ?></p>
            <p>Destino: <?php echo htmlspecialchars((string)($pedido['endereco_destino'] ?? '—')); ?></p>
        </div>

        <div class="bloco">
            <h2>Valores</h2>
            <table class="valores">
                <tr><td>Valor bruto do serviço</td><td><?php echo $money($bruto); ?></td></tr>
                <tr><td>Comissão da plataforma (<?php echo number_format($comissaoPercent, 1, ',', '.'); ?>%)</td><td>&minus; <?php echo $money($comissao); ?></td></tr>
                <tr class="total"><td>Valor líquido recebido via Pix</td><td><?php echo $money($liquido); ?></td></tr>
            </table>
        </div>

        <div class="bloco">
            <h2>Dados do repasse</h2>
            <p>Data/hora do repasse: <?php echo htmlspecialchars($dataRepasse); ?></p>
            <p>ID da transação Pix: <?php echo htmlspecialchars((string)($pagamento['id_transacao_pix'] ?? '—')); ?></p>
            <p>Forma de pagamento do cliente: <?php echo htmlspecialchars((string)($pagamento['metodo'] ?? '—')); ?></p>
        </div>

        <div class="disclaimer">
            <strong>Aviso:</strong> este é um recibo interno gerado automaticamente pela plataforma GuinchaFácil para fins de controle e apoio contábil do prestador. Ele <strong>não substitui</strong> nota fiscal de serviço nem tem valor fiscal — a emissão de nota fiscal, quando exigida, é de responsabilidade do prestador junto ao seu regime tributário.
        </div>

        <div class="rodape">
            Gerado em <?php echo date('d/m/Y H:i'); ?> — GuinchaFácil
        </div>
    </div>

    <div class="acoes">
        <button onclick="window.print()"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>
    </div>
</body>
</html>
