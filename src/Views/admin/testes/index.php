<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Console de Testes — GuinchaFácil</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .node { fill: #ccc; transition: fill 0.3s; }
        .node.running { fill: #3498db; animation: pulse 1s infinite; }
        .node.ok { fill: #2ecc71; }
        .node.fail { fill: #e74c3c; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .edge { stroke: #ccc; stroke-width: 3; fill: none; stroke-dasharray: 5; transition: stroke-dashoffset 0.5s; }
        button { padding: 10px 15px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #219653; }
        .log-box { background: #222; color: #0f0; padding: 10px; font-family: monospace; height: 150px; overflow-y: scroll; margin-top: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Console de Testes & Diagrama Animado</h1>
        <p>Monitoramento em tempo real dos cenários E2E e de integração com polling real.</p>
        
        <select id="scenario-select" style="padding: 8px; margin-right: 10px;">
            <option value="fluxo-1a">fluxo-1a</option>
            <option value="idempotencia-pagamento">idempotencia-pagamento</option>
            <option value="idempotencia-webhook">idempotencia-webhook</option>
            <option value="admin-enxerga-oficina">admin-enxerga-oficina</option>
            <option value="oficina-alertas">oficina-alertas</option>
            <option value="mp-config">mp-config</option>
        </select>
        <button onclick="runScenario()">Executar Cenário</button>

        <div style="margin-top: 20px;">
            <svg width="800" height="200" style="background:#fafafa; border:1px solid #ddd; border-radius:4px;">
                <path class="edge" d="M 100 100 L 300 100 L 500 100 L 700 100" />
                <circle id="node-precotacao" class="node" cx="100" cy="100" r="20" />
                <circle id="node-decisao" class="node" cx="300" cy="100" r="20" />
                <circle id="node-pagamento" class="node" cx="500" cy="100" r="20" />
                <circle id="node-pedido" class="node" cx="700" cy="100" r="20" />
            </svg>
        </div>

        <div class="log-box" id="log-output">Pronto para iniciar execução...</div>
    </div>

    <script>
        let pollInterval = null;

        function log(msg) {
            const box = document.getElementById('log-output');
            box.innerHTML += `<div>[${new Date().toLocaleTimeString()}] ${msg}</div>`;
            box.scrollTop = box.scrollHeight;
        }

        function resetNodes() {
            document.querySelectorAll('.node').forEach(n => n.className.baseVal = 'node');
        }

        async function runScenario() {
            resetNodes();
            const scenario = document.getElementById('scenario-select').value;
            log(`Iniciando execução do cenário: ${scenario}`);
            
            try {
                const res = await fetch(`/admin/testes/executar?scenario=${scenario}`);
                const data = await res.json();
                if (data.status === 'ok') {
                    log(`Run ID ${data.run_id} iniciado. Monitorando eventos...`);
                    pollEvents(data.run_id);
                } else {
                    log(`Erro ao iniciar: ${data.message}`);
                }
            } catch (e) {
                log(`Falha na requisição: ${e.message}`);
            }
        }

        function pollEvents(runId) {
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(async () => {
                try {
                    const res = await fetch(`/admin/testes/eventos?run_id=${runId}`);
                    const data = await res.json();
                    
                    if (data.steps) {
                        data.steps.forEach(step => {
                            const el = document.getElementById(step.node);
                            if (el) {
                                el.className.baseVal = `node ${step.status}`;
                            }
                        });
                    }

                    if (data.run_status === 'ok' || data.run_status === 'fail') {
                        log(`Cenário finalizado com status: ${data.run_status}`);
                        clearInterval(pollInterval);
                    }
                } catch (e) {
                    console.error(e);
                }
            }, 1000);
        }
    </script>
</body>
</html>