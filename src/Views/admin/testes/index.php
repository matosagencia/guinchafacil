<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Console de Testes — GuinchaFácil (Funil Admin)</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .node { fill: #ccc; transition: fill 0.3s; }
        .node.running { fill: #3498db; animation: pulse 1s infinite; }
        .node.ok { fill: #2ecc71; }
        .node.fail { fill: #e74c3c; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .edge { stroke: #ccc; stroke-width: 2; fill: none; }
        button { padding: 10px 15px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #219653; }
        .log-box { background: #222; color: #0f0; padding: 10px; font-family: monospace; height: 180px; overflow-y: scroll; margin-top: 15px; border-radius: 4px; }
        .node-label { font-size: 10px; fill: #333; text-anchor: middle; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Console de Testes & Diagrama do Funil Admin</h1>
        <p>Monitoramento E2E e validação de contratos (Faixa B & C).</p>
        
        <select id="scenario-select" style="padding: 8px; margin-right: 10px;">
            <option value="admin-pedido-funil-modal">admin-pedido-funil-modal</option>
            <option value="admin-pedido-sem-guincho">admin-pedido-sem-guincho</option>
            <option value="admin-pedido-pagamento-online">admin-pedido-pagamento-online</option>
            <option value="admin-pedido-pagamento-online-idem">admin-pedido-pagamento-online-idem</option>
            <option value="admin-pedido-pagamento-provedor-errado">admin-pedido-pagamento-provedor-errado</option>
            <option value="admin-modal-csrf">admin-modal-csrf</option>
            <option value="admin-modal-veiculo-cliente-invalido">admin-modal-veiculo-cliente-invalido</option>
        </select>
        <button onclick="runScenario()">Executar Cenário</button>

        <div style="margin-top: 20px; overflow-x: auto;">
            <svg width="1050" height="150" style="background:#fafafa; border:1px solid #ddd; border-radius:4px;">
                <path class="edge" d="M 80 75 L 200 75 L 320 75 L 440 75 L 560 75 L 680 75 L 800 75 L 920 75" />
                <g>
                    <circle id="auth" class="node" cx="80" cy="75" r="16" /><text x="80" y="110" class="node-label">auth</text>
                    <circle id="admin_ctx" class="node" cx="200" cy="75" r="16" /><text x="200" y="110" class="node-label">admin_ctx</text>
                    <circle id="modal_cliente" class="node" cx="320" cy="75" r="16" /><text x="320" y="110" class="node-label">modal_cli</text>
                    <circle id="modal_veiculo" class="node" cx="440" cy="75" r="16" /><text x="440" y="110" class="node-label">modal_vei</text>
                    <circle id="title_swap" class="node" cx="560" cy="75" r="16" /><text x="560" y="110" class="node-label">title_swap</text>
                    <circle id="admin_pedido_criar" class="node" cx="680" cy="75" r="16" /><text x="680" y="110" class="node-label">ped_criar</text>
                    <circle id="link_gerado" class="node" cx="800" cy="75" r="16" /><text x="800" y="110" class="node-label">link_gerado</text>
                    <circle id="api_pedido_criar_idem" class="node" cx="920" cy="75" r="16" /><text x="920" y="110" class="node-label">criar_idem</text>
                </g>
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
                if (data.status === 'ok' || data.status === 'fail') {
                    log(`Run ID ${data.run_id} criado. Monitorando eventos...`);
                    pollEvents(data.run_id);
                } else {
                    log(`Erro: ${data.message}`);
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
                            if (el) el.className.baseVal = `node ${step.status}`;
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