<?php
/**
 * fix-B-oficina-alerta-e-toast.php
 *
 * Corrige 3 bugs na oficina:
 *   1. Alerta sonoro nao toca (location.reload mata o audio)
 *   2. Sem toast clicavel (guincho tem, oficina nao)
 *   3. Card nao re-renderiza sem reload
 *
 * Estrategia:
 *   - Remove o location.reload() do checarPedidos()
 *   - Re-renderiza o card inline via funcao renderizarOferta()
 *   - Adiciona mostrarToastNovoPedido() (clone do gfToastNovos do guincho)
 *   - Adiciona [B-OFICINA-AUDIO-UNLOCK] pra desbloquear AudioContext no 1o clique
 */

$root = 'C:/xampp/htdocs/guinchafacil';
$stamp = date('Ymd-His');

$file = $root . '/src/Views/oficina/dashboard.php';

if (!file_exists($file)) {
    fwrite(STDERR, "[ERRO] Arquivo nao encontrado: $file\n");
    exit(1);
}

$backup = $file . '.bak-toast-' . $stamp;
copy($file, $backup);
echo "[ok] Backup: $backup\n";

$c = file_get_contents($file);

// ---------------------------------------------------------------------
// 1) Substitui o bloco do checarPedidos (remove location.reload)
// ---------------------------------------------------------------------
$antes1 = <<<'JS'
            var pedidos = (j.pedidos || []);
            if (pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(pedidos[0].id) !== String(atualId)) {
                    // [B-OFICINA-ALERTA-RELOAD] Dispara o alerta ANTES do reload
                    // para o beep/toast saírem. Sem isso, o location.reload() mata
                    // o JS antes do alerta rodar.
                    if (window.__gfOficinaChecarAlerta) {
                        try { window.__gfOficinaChecarAlerta(pedidos); } catch (e) {}
                    }
                    setTimeout(function() { location.reload(); }, 800);
                    return pedidos;
                }
            }
            return pedidos;
JS;
$antes1 = str_replace("\r\n", "\n", $antes1);

$depois1 = <<<'JS'
            var pedidos = (j.pedidos || []);
            if (pedidos.length) {
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(pedidos[0].id) !== String(atualId)) {
                    // [B-OFICINA-ALERTA-INLINE] Alerta + re-render SEM reload.
                    // O reload matava o AudioContext e o toast nunca saía.
                    if (window.__gfOficinaChecarAlerta) {
                        try { window.__gfOficinaChecarAlerta(pedidos); } catch (e) {}
                    }
                    renderizarOferta(pedidos[0]);
                }
            }
            return pedidos;
JS;
$depois1 = str_replace("\r\n", "\n", $depois1);

$cNorm = str_replace("\r\n", "\n", $c);

if (strpos($cNorm, $antes1) === false) {
    fwrite(STDERR, "[ERRO] Ancora 1 (checarPedidos) nao encontrada.\n");
    copy($backup, $file);
    exit(2);
}
$cNorm = str_replace($antes1, $depois1, $cNorm);
echo "[ok] checarPedidos corrigido (sem reload)\n";

// ---------------------------------------------------------------------
// 2) Adiciona renderizarOferta() antes do "iniciarTimer();"
// ---------------------------------------------------------------------
$anchor2 = "    iniciarTimer();\n";

if (strpos($cNorm, $anchor2) === false) {
    fwrite(STDERR, "[ERRO] Ancora 2 (iniciarTimer) nao encontrada.\n");
    copy($backup, $file);
    exit(3);
}

$novoBloco = <<<'JS'
    // [B-OFICINA-INLINE-RENDER] Re-renderiza o card sem reload,
    // preservando AudioContext e o toast em andamento.
    function renderizarOferta(p) {
        var container = document.getElementById('ofertaAtivaContainer');
        if (!container || !p) return;
        var pedidoId = p.id;
        var tipo = p.tipo_problema || 'Socorro';
        var dist = Number(p.distancia_km || 0).toFixed(1).replace('.', ',');
        var valor = Number(p.custo_estimado || 0).toFixed(2).replace('.', ',');
        var cat = p.categoria || '—';
        var expira = p.expira_em || '';
        container.innerHTML = ''
            + '<div class="tow-offer" data-pedido-id="' + pedidoId + '">'
            +   '<div class="tow-offer-head d-flex justify-content-between align-items-start">'
            +     '<div>'
            +       '<span class="tow-offer-eyebrow"><i class="fas fa-bolt me-1"></i>Nova solicita\u00e7\u00e3o</span>'
            +       '<h4 class="tow-offer-title">Pedido #' + pedidoId + '</h4>'
            +       '<p class="tow-offer-subtitle mb-0">' + tipo + '</p>'
            +     '</div>'
            +     (expira ? '<span class="tow-offer-timer" data-expira="' + expira + '">--:--</span>' : '')
            +   '</div>'
            +   '<div class="tow-offer-metrics">'
            +     '<div class="tow-offer-metric"><span>Dist\u00e2ncia</span><strong>' + dist + ' km</strong></div>'
            +     '<div class="tow-offer-metric"><span>Valor estimado</span><strong>R$ ' + valor + '</strong></div>'
            +     '<div class="tow-offer-metric"><span>Categoria</span><strong>' + cat + '</strong></div>'
            +   '</div>'
            +   '<div class="tow-offer-actions">'
            +     '<form method="post" action="' + BP + '/oficina/recusar/' + pedidoId + '" class="flex-grow-1 m-0">'
            +       '<input type="hidden" name="csrf_token" value="' + CSRF + '">'
            +       '<button type="submit" class="btn btn-outline-secondary w-100"><i class="fas fa-xmark me-1"></i>Recusar</button>'
            +     '</form>'
            +     '<form method="post" action="' + BP + '/oficina/pedido/' + pedidoId + '/aceitar" class="flex-grow-1 m-0">'
            +       '<input type="hidden" name="csrf_token" value="' + CSRF + '">'
            +       '<button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Aceitar</button>'
            +     '</form>'
            +   '</div>'
            + '</div>';
        iniciarTimer();
    }

    iniciarTimer();
JS;
$novoBloco = str_replace("\r\n", "\n", $novoBloco);

// Substitui apenas a primeira ocorrencia
$pos = strpos($cNorm, $anchor2);
$cNorm = substr($cNorm, 0, $pos) . $novoBloco . substr($cNorm, $pos + strlen($anchor2));
echo "[ok] renderizarOferta() adicionada\n";

// ---------------------------------------------------------------------
// 3) Substitui o bloco tocarAlerta + adiciona toast
// ---------------------------------------------------------------------
$antes3 = <<<'JS'
        window.__gfOficinaChecarAlerta = function (pedidos) {
            if (!Array.isArray(pedidos)) return;
            var totalNovos = 0;
            pedidos.forEach(function (p) {
                var id = String(p.id || p.pedido_id || '');
                if (id && !vistos[id]) { vistos[id] = true; totalNovos++; }
            });
            try { sessionStorage.setItem(SS_KEY, JSON.stringify(vistos)); } catch (e) {}
            if (totalNovos > 0) { tocarAlerta(); mostrarToast(totalNovos + ' novo(s) pedido(s) na sua fila'); }
        };
JS;
$antes3 = str_replace("\r\n", "\n", $antes3);

$depois3 = <<<'JS'
        // [B-OFICINA-TOAST-CLICK] Toast clicavel que rola ate o card
        // (clone do gfToastNovos do guincho, adaptado).
        function mostrarToastNovoPedido(qtd, pedido) {
            var id = 'oficina-toast-novo-pedido';
            var old = document.getElementById(id);
            if (old) old.remove();
            var box = document.createElement('div');
            box.id = id;
            box.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);'
                + 'background:#2fb34a;color:#fff;padding:12px 20px;border-radius:10px;'
                + 'box-shadow:0 8px 24px rgba(0,0,0,.35);z-index:2147483647;'
                + 'font:600 14px/1.3 system-ui,sans-serif;cursor:pointer;max-width:92vw';
            var titulo = qtd === 1 ? 'Novo pedido dispon\u00edvel!' : qtd + ' novos pedidos dispon\u00edveis!';
            var sub = pedido ? [pedido.marca, pedido.modelo].filter(Boolean).join(' ') : '';
            var rodape = 'Toque para ir ao pedido';
            box.innerHTML = '<div>' + titulo + '</div>'
                + (sub ? '<div style="font-weight:400;font-size:12px;margin-top:3px;opacity:.9">' + sub + '</div>' : '')
                + '<div style="font-weight:400;font-size:11px;margin-top:6px;opacity:.85">' + rodape + '</div>';
            box.addEventListener('click', function () {
                var el = document.querySelector('[data-pedido-id]');
                if (el && el.scrollIntoView) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                box.remove();
            });
            document.body.appendChild(box);
            setTimeout(function () { if (box.parentNode) box.parentNode.remove(); }, 20000);
        }

        window.__gfOficinaChecarAlerta = function (pedidos) {
            if (!Array.isArray(pedidos)) return;
            var totalNovos = 0;
            var primeiroNovo = null;
            pedidos.forEach(function (p) {
                var id = String(p.id || p.pedido_id || '');
                if (id && !vistos[id]) {
                    vistos[id] = true;
                    totalNovos++;
                    if (!primeiroNovo) primeiroNovo = p;
                }
            });
            try { sessionStorage.setItem(SS_KEY, JSON.stringify(vistos)); } catch (e) {}
            if (totalNovos > 0) {
                tocarAlerta();
                mostrarToastNovoPedido(totalNovos, primeiroNovo);
            }
        };
JS;
$depois3 = str_replace("\r\n", "\n", $depois3);

if (strpos($cNorm, $antes3) === false) {
    fwrite(STDERR, "[ERRO] Ancora 3 (__gfOficinaChecarAlerta) nao encontrada.\n");
    copy($backup, $file);
    exit(4);
}
$cNorm = str_replace($antes3, $depois3, $cNorm);
echo "[ok] toast clicavel adicionado\n";

// ---------------------------------------------------------------------
// 4) [B-OFICINA-AUDIO-UNLOCK] Desbloqueia AudioContext no 1o clique
// ---------------------------------------------------------------------
$anchor4 = "    var toggle = document.getElementById('toggleDisponivel');\n";
if (strpos($cNorm, $anchor4) === false) {
    fwrite(STDERR, "[ERRO] Ancora 4 (toggle) nao encontrada.\n");
    copy($backup, $file);
    exit(5);
}

$unlock = <<<'JS'
    // [B-OFICINA-AUDIO-UNLOCK] Desbloqueia AudioContext no 1o clique
    // (o Chrome bloqueia som ate o usuario interagir com a pagina).
    (function () {
        var unlocked = false;
        function unlock() {
            if (unlocked) return;
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                var ctx = new AC();
                if (ctx.state === 'suspended') { ctx.resume(); }
                var o = ctx.createOscillator();
                var g = ctx.createGain();
                g.gain.value = 0.0001;
                o.connect(g).connect(ctx.destination);
                o.start();
                o.stop(ctx.currentTime + 0.05);
                unlocked = true;
                document.removeEventListener('click', unlock);
                document.removeEventListener('keydown', unlock);
                document.removeEventListener('touchstart', unlock);
            } catch (e) {}
        }
        document.addEventListener('click', unlock);
        document.addEventListener('keydown', unlock);
        document.addEventListener('touchstart', unlock);
    })();

JS;
$unlock = str_replace("\r\n", "\n", $unlock);
$cNorm = str_replace($anchor4, $unlock . $anchor4, $cNorm);
echo "[ok] audio unlock adicionado\n";

// ---------------------------------------------------------------------
// 5) Devolve CRLF e grava
// ---------------------------------------------------------------------
$c = str_replace("\n", "\r\n", $cNorm);
file_put_contents($file, $c);

$lint = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($file) . ' 2>&1');
echo $lint;
if (strpos($lint, 'No syntax errors') === false) {
    copy($backup, $file);
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurado.\n");
    exit(6);
}

echo "\n=== PATCH B-OFICINA-ALERTA-E-TOAST APLICADO ===\n";
echo "Backup: $backup\n";
echo "\nTeste:\n";
echo "  1. Abre /oficina/dashboard\n";
echo "  2. Clica em qualquer lugar (audio unlock)\n";
echo "  3. Cria pedido ON_SITE pelo admin\n";
echo "  4. Espera ate 15s -> 2 bipes + toast verde\n";
echo "  5. Clica no toast -> rola ate o card do pedido\n";