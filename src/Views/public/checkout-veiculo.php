<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$cotacao = $_SESSION['pre_cotacao'] ?? [];
$valorCotado = (float)($cotacao['valor'] ?? 0);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seu veiculo | GuinchaFacil</title>
    <link href="<?= $esc($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --gf-green:#2fb34a; --gf-green-dark:#1f8a36; --gf-bg:#0a1a0d; --gf-ink:#10233f; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; background:var(--gf-bg); color:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid rgba(47,179,74,.15); }
        .topbar .brand { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; font-weight:700; }
        .topbar .brand img { width:34px; height:34px; border-radius:10px; }
        .topbar .brand span { color:var(--gf-green); }
        .topbar .user { color:rgba(255,255,255,.7); font-size:.85rem; }
        .topbar .user strong { color:#fff; }
        .wrap { max-width:620px; margin:0 auto; padding:40px 20px 60px; }
        .hero { text-align:center; margin-bottom:28px; }
        .hero .eyebrow { color:var(--gf-green); font-size:.75rem; letter-spacing:.15em; font-weight:800; text-transform:uppercase; }
        .hero h1 { font-size:clamp(1.6rem,4vw,2rem); font-weight:800; letter-spacing:-.02em; margin:8px 0 6px; color:#fff; }
        .hero p { color:rgba(255,255,255,.55); font-size:.95rem; margin:0; }
        .card-panel { background:#fff; color:var(--gf-ink); border-radius:18px; padding:28px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
        .resumo { display:flex; justify-content:space-between; align-items:center; padding:12px 0 16px; border-bottom:1px dashed #e3eaf4; margin-bottom:22px; color:#334155; font-size:.9rem; }
        .resumo strong { color:var(--gf-green-dark); font-size:1.25rem; }
        label { font-weight:600; font-size:.88rem; color:#334155; margin-bottom:6px; display:block; }
        .form-control, .form-select { border-radius:10px; padding:.72rem .9rem; border:1.5px solid #dce4ef; font-size:.95rem; }
        .form-control:focus, .form-select:focus { border-color:var(--gf-green); box-shadow:0 0 0 3px rgba(47,179,74,.15); outline:none; }
        .tipo-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:20px; }
        .tipo-grid input { position:absolute; opacity:0; }
        .tipo-grid label { display:block; text-align:center; padding:12px 8px; border:1.5px solid #dce4ef; border-radius:10px; cursor:pointer; background:#fff; font-size:.85rem; transition:.15s; margin:0; color:#334155; }
        .tipo-grid input:checked + label { border-color:var(--gf-green); background:#f0fdf4; box-shadow:0 0 0 3px rgba(47,179,74,.15); color:var(--gf-green-dark); font-weight:700; }
        .btn-primary { background:linear-gradient(135deg,var(--gf-green),var(--gf-green-dark)); border:0; border-radius:10px; padding:.9rem 1.4rem; font-weight:700; width:100%; font-size:1rem; color:#fff; box-shadow:0 6px 20px rgba(47,179,74,.35); transition:.15s; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 26px rgba(47,179,74,.45); }
        .veiculo-existente { border:1.5px solid #dce4ef; border-radius:10px; padding:12px 14px; margin-bottom:10px; cursor:pointer; background:#fff; display:block; color:#334155; font-size:.9rem; transition:.15s; }
        .veiculo-existente:hover { border-color:var(--gf-green); }
        .divisor { text-align:center; color:#94a3b8; font-size:.82rem; margin:20px 0; }
        .alerta { background:#fff5f5; border:1px solid #fed7d7; color:#c53030; border-radius:10px; padding:12px 14px; font-size:.9rem; margin-bottom:16px; }
        @media(max-width:520px) { .tipo-grid { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>
<div class="topbar">
    <a class="brand" href="<?= $esc($bp) ?>/"><img src="<?= $esc($bp) ?>/public/assets/img/logo-48.png" alt=""> Guincha<span>Facil</span></a>
    <span class="user">Ola, <strong><?= $esc($_SESSION['user']['nome'] ?? '') ?></strong></span>
</div>

<div class="wrap">
    <section class="hero">
        <div class="eyebrow">Falta pouco</div>
        <h1>Qual e o seu veiculo?</h1>
        <p>Precisamos desses dados pra enviar o guincho certo.</p>
    </section>

    <?php if (!empty($flash)): ?>
        <div class="alerta"><?= $esc($flash['message']) ?></div>
    <?php endif; ?>

    <form class="card-panel" method="post" action="<?= $esc($bp) ?>/checkout/veiculo">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">

        <div class="resumo">
            <span>Total a pagar</span>
            <strong>R$ <?= number_format($valorCotado, 2, ',', '.') ?></strong>
        </div>

        <?php if (!empty($veiculos)): ?>
            <label>Veiculo cadastrado</label>
            <div id="veiculos-lista">
                <?php foreach ($veiculos as $v): ?>
                    <label class="veiculo-existente">
                        <input type="radio" name="veiculo_id" value="<?= (int)$v['id'] ?>" style="margin-right:8px">
                        <strong><?= $esc($v['marca'] . ' ' . $v['modelo']) ?></strong>
                        <span style="color:#64748b"> &middot; <?= $esc($v['ano']) ?> &middot; <?= $esc($v['placa']) ?></span>
                    </label>
                <?php endforeach; ?>
                <label class="veiculo-existente">
                    <input type="radio" name="veiculo_id" value="0" checked style="margin-right:8px">
                    Cadastrar outro veiculo
                </label>
            </div>
            <div class="divisor">- ou cadastre um novo -</div>
        <?php endif; ?>

        <?php
        // Pre-seleciona o tipo a partir da categoria coletada no funil
        // (moto/popular/suv/caminhonete/eletrico).
        $categoriaFunil = (string)($cotacao['categoria'] ?? 'popular');
        $tipoMap = [
            'moto'        => 'moto',
            'caminhonete' => 'van',
            'popular'     => 'carro',
            'suv'         => 'carro',
            'eletrico'    => 'carro',
        ];
        $tipoPreSelecionado = $tipoMap[$categoriaFunil] ?? 'carro';
        ?>
        <label>Tipo</label>
        <div class="tipo-grid">
            <input type="radio" id="tipo-carro" name="tipo" value="carro" <?= $tipoPreSelecionado === 'carro' ? 'checked' : '' ?>>
            <label for="tipo-carro">Carro</label>
            <input type="radio" id="tipo-moto" name="tipo" value="moto" <?= $tipoPreSelecionado === 'moto' ? 'checked' : '' ?>>
            <label for="tipo-moto">Moto</label>
            <input type="radio" id="tipo-caminhao" name="tipo" value="caminhao" <?= $tipoPreSelecionado === 'caminhao' ? 'checked' : '' ?>>
            <label for="tipo-caminhao">Caminhao</label>
            <input type="radio" id="tipo-van" name="tipo" value="van" <?= $tipoPreSelecionado === 'van' ? 'checked' : '' ?>>
            <label for="tipo-van">Van</label>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="marca">Marca</label>
                <input class="form-control" id="marca" name="marca" list="marcas-lista" autocomplete="off" required>
                <datalist id="marcas-lista"></datalist>
                <input type="hidden" id="vehicle_brand_id" name="vehicle_brand_id">
            </div>
            <div class="col-md-6">
                <label for="modelo">Modelo</label>
                <input class="form-control" id="modelo" name="modelo" list="modelos-lista" autocomplete="off" required disabled>
                <datalist id="modelos-lista"></datalist>
                <input type="hidden" id="vehicle_model_id" name="vehicle_model_id">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label for="ano">Ano</label>
                <select class="form-select" id="ano" name="ano" required>
                    <option value="">Ano</option>
                    <?php for ($a = (int)date('Y') + 1; $a >= 1990; $a--): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="cor">Cor</label>
                <input class="form-control" id="cor" name="cor" placeholder="Branco" required>
            </div>
            <div class="col-md-4">
                <label for="placa">Placa</label>
                <input class="form-control" id="placa" name="placa" maxlength="8" style="text-transform:uppercase" placeholder="ABC1D23" required>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label for="uf_placa">UF do emplacamento</label>
                <select class="form-select" id="uf_placa" name="uf_placa">
                    <option value="">UF</option>
                    <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                        <option value="<?= $uf ?>"><?= $uf ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label for="cidade_placa">Cidade do emplacamento</label>
                <input class="form-control" id="cidade_placa" name="cidade_placa" placeholder="Opcional">
            </div>
        </div>

        <button type="submit" class="btn-primary" id="btn-continuar">Continuar para o pagamento</button>
    </form>
</div>

<script src="<?= $esc($bp) ?>/public/assets/js/checkout-veiculo.js?v=3" defer<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?>></script>
<script>
document.querySelector('form').addEventListener('submit', function (e) {
    var btn = document.getElementById('btn-continuar');
    if (btn && btn.disabled) { e.preventDefault(); return; }
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
});
</script>
</body>
</html>