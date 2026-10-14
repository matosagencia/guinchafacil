<?php
/**
 * fix-B-guincho-pagamento.php
 *
 * Objetivo:
 *   Enriquecer o payload da oferta do guincho com forma_pagamento_escolhida
 *   e provedor_pagamento_escolhido.
 *   Adicionar helper JS gfMensagemPagamento(pedido) no dashboard.
 *
 * Regra de negocio (dono, 2026-10-08):
 *   - pago_na_chegada -> "Receba R$ X do cliente no local"
 *   - sem_cobranca    -> "Isento - nao cobre nada do cliente"
 *   - pago_agora      -> "Voce recebera R$ X (ja pago pelo cliente)"
 *   - online          -> "Pagamento online confirmado. Sua parte: R$ X"
 */

$root = 'C:/xampp/htdocs/guinchafacil';

$controllerFile  = $root . '/src/Controllers/GuinchoController.php';
$dashboardFile   = $root . '/src/Views/guincho/dashboard.php';

foreach ([$controllerFile, $dashboardFile] as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "[ERRO] Arquivo nao encontrado: $f\n");
        exit(1);
    }
}

$stamp = date('Ymd-His');

// =====================================================================
// 1) Controller: enriquecer payload
// =====================================================================
$cBackup = $controllerFile . '.bak-pagamento-' . $stamp;
copy($controllerFile, $cBackup);
echo "[ok] Backup controller: $cBackup\n";

$c = file_get_contents($controllerFile);

// Ancora 1: dentro de $ofertaItem (montarOfertasDisponiveis)
$a1 = "                'custo_estimado'     => (float)(\$pedido['custo_estimado'] ?? 0),";
$r1 = "                'custo_estimado'     => (float)(\$pedido['custo_estimado'] ?? 0),\n"
    . "                'forma_pagamento_escolhida' => (string)(\$pedido['forma_pagamento_escolhida'] ?? ''),\n"
    . "                'provedor_pagamento_escolhido' => (string)(\$pedido['provedor_pagamento_escolhido'] ?? ''),";

if (strpos($c, $a1) === false) {
    fwrite(STDERR, "[ERRO] Ancora 1 (custo_estimado) nao encontrada no controller.\n");
    copy($cBackup, $controllerFile);
    exit(2);
}
$c = str_replace($a1, $r1, $c);
echo "[ok] Ancora 1 (montarOfertasDisponiveis) aplicada\n";

// Ancora 2: dentro do $resultado em pedidosDisponiveis
$a2 = "                'custo_estimado'         => (float)(\$o['custo_estimado'] ?? 0),";
$r2 = "                'custo_estimado'         => (float)(\$o['custo_estimado'] ?? 0),\n"
    . "                'forma_pagamento_escolhida' => (string)(\$o['forma_pagamento_escolhida'] ?? ''),\n"
    . "                'provedor_pagamento_escolhido' => (string)(\$o['provedor_pagamento_escolhido'] ?? ''),";

if (strpos($c, $a2) !== false) {
    $c = str_replace($a2, $r2, $c);
    echo "[ok] Ancora 2 (pedidosDisponiveis) aplicada\n";
} else {
    echo "[aviso] Ancora 2 nao encontrada - pulando\n";
}

file_put_contents($controllerFile, $c);

$lint = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($controllerFile) . ' 2>&1');
echo $lint;
if (strpos($lint, 'No syntax errors') === false) {
    fwrite(STDERR, "[ERRO] php -l controller falhou. Restaurando backup...\n");
    copy($cBackup, $controllerFile);
    exit(3);
}

// =====================================================================
// 2) Dashboard: adicionar helper JS gfMensagemPagamento
// =====================================================================
$dBackup = $dashboardFile . '.bak-pagamento-' . $stamp;
copy($dashboardFile, $dBackup);
echo "[ok] Backup dashboard: $dBackup\n";

$d = file_get_contents($dashboardFile);

if (strpos($d, 'gfMensagemPagamento') !== false) {
    echo "[info] Helper JS ja existe no dashboard - pulando\n";
} else {
    $helperJs = <<<'JS'

    // [B-GUINCHO-PAGAMENTO-01] Mensagem de forma de pagamento (2026-10-08)
    // REGRA: parceiro precisa saber como recebe ANTES de aceitar o pedido.
    function gfMensagemPagamento(pedido) {
        var forma = String(pedido.forma_pagamento_escolhida || '');
        var valor = Number(pedido.valor_receber_guincho || pedido.custo_estimado || 0);
        var valorFmt = 'R$ ' + valor.toFixed(2).replace('.', ',');
        if (forma === 'pago_na_chegada') {
            return { icone: '💰', texto: 'Receba ' + valorFmt + ' do cliente no local', cor: '#2fb34a' };
        }
        if (forma === 'sem_cobranca') {
            return { icone: '✓', texto: 'Isento - nao cobre nada do cliente', cor: '#6c757d' };
        }
        if (forma === 'pago_agora') {
            return { icone: '💰', texto: 'Voce recebera ' + valorFmt + ' (ja pago pelo cliente)', cor: '#2fb34a' };
        }
        if (forma === 'online') {
            return { icone: '🌐', texto: 'Pagamento online confirmado. Sua parte: ' + valorFmt, cor: '#0d6efd' };
        }
        return { icone: '💰', texto: 'Valor: ' + valorFmt, cor: '#6c757d' };
    }
JS;

    $posUltimoScript = strrpos($d, '</script>');
    if ($posUltimoScript !== false) {
        $d = substr($d, 0, $posUltimoScript) . $helperJs . "\n" . substr($d, $posUltimoScript);
        file_put_contents($dashboardFile, $d);
        echo "[ok] Helper JS gfMensagemPagamento adicionado\n";

        $lint2 = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($dashboardFile) . ' 2>&1');
        echo $lint2;
        if (strpos($lint2, 'No syntax errors') === false) {
            fwrite(STDERR, "[ERRO] php -l dashboard falhou. Restaurando backup...\n");
            copy($dBackup, $dashboardFile);
            exit(4);
        }
    } else {
        echo "[aviso] Nao achei </script> no dashboard\n";
    }
}

echo "\n=== PATCH B-GUINCHO-PAGAMENTO APLICADO ===\n";
echo "\nProximo passo: usar gfMensagemPagamento(pedido) no card de oferta.\n";