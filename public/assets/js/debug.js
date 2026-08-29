/**
 * Modo de debug global (frontend) — GuinchaFácil.
 *
 * Espelho no JS do DebugMode.php: só produz saída quando window.APP_DEBUG
 * for true (setado inline no <head> a partir de DebugMode::jsFlag(), que lê
 * a config `debug_mode_ativo` do admin). Objetivo: dar a humanos e IA um
 * jeito único e padronizado de rastrear sistema/classe/função/localização em
 * qualquer tela, sem precisar abrir o DevTools manualmente em cada bug.
 *
 * Uso nas views:
 *   gfDebug('pedidostatus', 'atualizarBotaoCancelar', 'iniciando', { data });
 *
 * Também captura, quando o modo está ativo, erros JS não tratados e promises
 * rejeitadas sem catch — e os espelha para o backend (POST /debug/jslog),
 * pra aparecerem nos logs do servidor (app-YYYY-MM-DD.jsonl / app_logs) junto
 * com o resto do rastro da requisição — já que o console do navegador não é
 * visível pra quem (ou qual IA) está olhando os logs do servidor depois.
 */
(function () {
    var ATIVO = typeof window.APP_DEBUG !== 'undefined' && window.APP_DEBUG === true;

    function localizacaoDoChamador() {
        var linhas = (new Error()).stack ? (new Error()).stack.split('\n') : [];
        // [0] = "Error", [1] = esta função, [2] = gfDebug, [3] = quem chamou gfDebug
        return linhas[3] ? linhas[3].trim() : 'localização desconhecida';
    }

    function enviarParaServidor(nivel, sistema, mensagem, contexto, stack) {
        try {
            var bp = document.body ? (document.body.getAttribute('data-base-path') || '') : '';
            fetch(bp + '/debug/jslog', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nivel: nivel,
                    sistema: sistema,
                    mensagem: mensagem,
                    contexto: contexto || {},
                    stack: stack || '',
                    url: window.location.href,
                }),
                keepalive: true,
            }).catch(function () { /* silencioso: espelho de log, não pode quebrar a UI */ });
        } catch (e) { /* nunca deixa o espelho de debug derrubar a página */ }
    }

    window.gfDebugAtivo = function () {
        return ATIVO;
    };

    window.gfDebug = function (sistema, funcao, mensagem, contexto) {
        if (!ATIVO) return;
        var local = localizacaoDoChamador();
        // eslint-disable-next-line no-console
        console.debug(
            '%c[GF-DEBUG]%c ' + sistema + ' :: ' + funcao + '()',
            'color:#0d6efd;font-weight:bold', 'color:inherit',
            '\n  msg:', mensagem,
            '\n  ctx:', contexto || {},
            '\n  em:', local
        );
    };

    if (ATIVO) {
        window.addEventListener('error', function (evento) {
            var stack = evento.error && evento.error.stack ? evento.error.stack : '';
            console.error('[GF-DEBUG][JS-ERROR]', evento.message, 'em', evento.filename + ':' + evento.lineno + ':' + evento.colno, stack);
            enviarParaServidor('ERROR', 'js-runtime', evento.message, {
                arquivo: evento.filename,
                linha: evento.lineno,
                coluna: evento.colno,
            }, stack);
        });

        window.addEventListener('unhandledrejection', function (evento) {
            var razao = evento.reason;
            var msg = razao && razao.message ? razao.message : String(razao);
            var stack = razao && razao.stack ? razao.stack : '';
            console.error('[GF-DEBUG][PROMISE-REJECTION]', msg, stack);
            enviarParaServidor('ERROR', 'js-promise', msg, {}, stack);
        });

        console.info('%c[GF-DEBUG] Modo de debug global ATIVO — logs verbosos ligados nesta sessão.', 'color:#f59e0b;font-weight:bold');
    }
})();
