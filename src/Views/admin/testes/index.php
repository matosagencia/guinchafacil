<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Console de Testes - GuinchaFácil</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Console de Testes &amp; Diagrama Animado</h1>
        <p>Monitoramento em tempo real dos cenários E2E e de integração.</p>
        <svg width="800" height="200" style="background:#fafafa; border:1px solid #ddd; border-radius:4px;">
            <path class="edge" d="M 50 100 L 250 100 L 450 100 L 650 100" />
            <circle id="node-precotacao" class="node" cx="50" cy="100" r="20" />
            <circle id="node-decisao" class="node" cx="250" cy="100" r="20" />
            <circle id="node-pagamento" class="node" cx="450" cy="100" r="20" />
            <circle id="node-pedido" class="node" cx="650" cy="100" r="20" />
        </svg>
    </div>
</body>
</html>