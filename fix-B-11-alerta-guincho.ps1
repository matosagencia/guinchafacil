$ErrorActionPreference = 'Stop'
Set-Location 'C:\xampp\htdocs\guinchafacil'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$php = 'C:\xampp\php\php.exe'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

$viewPath = (Resolve-Path 'src\Views\guincho\dashboard.php').Path
$viewBak  = "$viewPath.bak-fix11-$stamp"
Copy-Item $viewPath $viewBak
Write-Host "[backup] $viewBak" -ForegroundColor Cyan

$v = [IO.File]::ReadAllText($viewPath)

if ($v.Contains('gf-guard-poller-v1')) {
    Write-Host "SKIP: fix-B-11 ja aplicado." -ForegroundColor Yellow
    exit 0
}

$bloco = @'

<!-- gf-guard-poller-v1 (fix-B-11) -->
<script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?>>
(function () {
    'use strict';
    if (window.__gfGuardPollerV1) return;
    window.__gfGuardPollerV1 = true;

    var BP   = <?php echo json_encode(defined('BASE_PATH') ? BASE_PATH : ''); ?>;
    var URL  = BP + '/guincho/pedidos-disponiveis?_t=';
    var TICK = 15000;
    var KEY  = 'gf_pedidos_vistos_v1';

    function getVistos() {
        try {
            var raw = sessionStorage.getItem(KEY);
            return raw ? new Set(JSON.parse(raw)) : new Set();
        } catch (e) { return new Set(); }
    }
    function salvarVistos(set) {
        try { sessionStorage.setItem(KEY, JSON.stringify(Array.from(set))); } catch (e) {}
    }

    function beep() {
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            var ctx = new AC();
            var osc = ctx.createOscillator();
            var g   = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            g.gain.value = 0.15;
            osc.connect(g).connect(ctx.destination);
            osc.start();
            setTimeout(function () { osc.stop(); ctx.close(); }, 250);
        } catch (e) {}
    }

    function toast(qtd, primeiro) {
        var id = 'gf-toast-novos';
        var old = document.getElementById(id);
        if (old) old.parentNode.removeChild(old);
        var box = document.createElement('div');
        box.id = id;
        box.style.cssText = [
            'position:fixed','top:16px','left:50%','transform:translateX(-50%)',
            'background:#2fb34a','color:#fff','padding:12px 20px','border-radius:10px',
            'box-shadow:0 8px 24px rgba(0,0,0,.35)','z-index:2147483647',
            'font:600 14px/1.3 system-ui,sans-serif','cursor:pointer','max-width:92vw'
        ].join(';');
        var titulo = qtd === 1
            ? 'Novo pedido disponivel!'
            : qtd + ' novos pedidos disponiveis!';
        var sub = primeiro
            ? (primeiro.marca || '') + ' ' + (primeiro.modelo || '') + ' - ' + (primeiro.endereco_origem || '')
            : '';
        box.innerHTML = '<div>' + titulo + '</div>' +
            (sub ? '<div style="font-weight:400;font-size:12px;margin-top:3px;opacity:.9">' + sub + '</div>' : '') +
            '<div style="font-weight:400;font-size:11px;margin-top:6px;opacity:.85">Toque para atualizar</div>';
        box.addEventListener('click', function () {
            var firstId = primeiro && primeiro.id ? primeiro.id : null;
            var target = firstId ? ('?pedido_id=' + firstId) : '';
            window.location.href = BP + '/guincho/dashboard' + target;
        });
        document.body.appendChild(box);
        setTimeout(function () { if (box.parentNode) box.parentNode.removeChild(box); }, 20000);
    }

    function piscarTitulo(n) {
        var original = document.title.replace(/^\(\d+\)\s*/, '');
        var state = false;
        var count = 0;
        var iv = setInterval(function () {
            state = !state;
            document.title = state ? '(' + n + ') ' + original : original;
            count++;
            if (count > 6 || !document.hidden === false && count > 2) {
                clearInterval(iv);
                document.title = original;
            }
        }, 700);
    }

    function aplicar(pedidos) {
        // Se houver um container conhecido, dispara evento para o painel existente
        try {
            document.dispatchEvent(new CustomEvent('gf:guincho-pedidos', { detail: { pedidos: pedidos } }));
        } catch (e) {}
    }

    var primeiraRodada = true;

    function poll() {
        fetch(URL + Date.now(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j || !j.ok || !Array.isArray(j.pedidos)) return;

                var vistos = getVistos();
                var novos = j.pedidos.filter(function (p) { return !vistos.has(p.id); });

                j.pedidos.forEach(function (p) { vistos.add(p.id); });
                salvarVistos(vistos);

                aplicar(j.pedidos);

                // Primeira rodada: nao alerta (so registra o estado inicial)
                if (primeiraRodada) {
                    primeiraRodada = false;
                    return;
                }

                if (novos.length > 0 && j.localizacao_disponivel !== false) {
                    beep();
                    toast(novos.length, novos[0]);
                    if (document.hidden) piscarTitulo(novos.length);
                }
            })
            .catch(function () { /* silencioso */ });
    }

    // Roda 1x imediato e agenda o intervalo
    poll();
    setInterval(poll, TICK);

    // Ao voltar pra aba, faz um poll imediato
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });
})();
</script>
'@ -replace "`r?`n", "`r`n"

$anchor = '</body>'
$idx = $v.LastIndexOf($anchor)
if ($idx -lt 0) {
    Write-Host "Anchor '</body>' nao encontrado. Abortando." -ForegroundColor Red
    Copy-Item $viewBak $viewPath -Force
    exit 1
}

$v2 = $v.Substring(0, $idx) + $bloco + $v.Substring($idx)
[IO.File]::WriteAllText($viewPath, $v2, $utf8NoBom)

$lint = (& $php -l $viewPath 2>&1 | Out-String)
if ($lint -match 'No syntax errors') {
    Write-Host ""
    Write-Host "OK - fix-B-11 aplicado." -ForegroundColor Green
    Write-Host "Backup: $viewBak" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "TESTAR:" -ForegroundColor Cyan
    Write-Host "  1. Login guincho -> /guincho/dashboard (deixa aberto)" -ForegroundColor Yellow
    Write-Host "  2. Em outra aba, login admin -> criar pedido com 'Pago na chegada'" -ForegroundColor Yellow
    Write-Host "  3. Volta na aba do guincho (nao precisa F5)" -ForegroundColor Yellow
    Write-Host "  4. Em ate 15s deve aparecer toast verde + beep" -ForegroundColor Yellow
} else {
    Copy-Item $viewBak $viewPath -Force
    Write-Host "php -l falhou - revertido." -ForegroundColor Red
    Write-Host $lint
    exit 1
}