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
        :root { --gf-blue:#1769e0; --gf-ink:#10233f; --gf-soft:#f4f8ff; }
        body { min-height:100vh; background:linear-gradient(135deg,#eef5ff 0%,#fff 58%,#eefbf8 100%); color:var(--gf-ink); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .shell { max-width:760px; margin:0 auto; padding:32px 18px 56px; }
        .brand { display:flex; align-items:center; gap:12px; font-weight:800; font-size:1.15rem; text-decoration:none; color:var(--gf-ink); }
        .brand img { width:42px; height:42px; border-radius:12px; }
        .hero { padding:36px 0 20px; }
        .hero .eyebrow { color:var(--gf-blue); font-size:.78rem; letter-spacing:.12em; font-weight:800; text-transform:uppercase; }
        .hero h1 { font-size:clamp(1.8rem,4vw,2.6rem); font-weight:800; letter-spacing:-.03em; margin:6px 0 8px; }
        .hero p { color:#5d6b80; font-size:1.05rem; margin:0; }
        .card-panel { background:rgba(255,255,255,.95); border:1px solid #e3eaf4; border-radius:20px; box-shadow:0 16px 48px rgba(22,65,120,.1); padding:28px; }
        .resumo { display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px dashed #e3eaf4; margin-bottom:20px; }
        .resumo strong { color:var(--gf-blue); font-size:1.3rem; }
        label { font-weight:600; font-size:.9rem; color:#334155; margin-bottom:6px; display:block; }
        .form-control, .form-select { border-radius:10px; padding:.72rem .9rem; border-color:#dce4ef; font-size:.95rem; }
        .form-control:focus, .form-select:focus { border-color:var(--gf-blue); box-shadow:0 0 0 3px rgba(23,105,224,.15); }
        .tipo-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:20px; }
        .tipo-grid input { position:absolute; opacity:0; }
        .tipo-grid label { display:block; text-align:center; padding:14px 8px; border:1.5px solid #dce4ef; border-radius:12px; cursor:pointer; background:#fff; font-size:.85rem; transition:.15s; margin:0; }
        .tipo-grid input:checked + label { border-color:var(--gf-blue); background:var(--gf-soft); box-shadow:0 0 0 3px rgba(23,105,224,.12); }
        .btn-primary { background:var(--gf-blue); border:0; border-radius:12px; padding:.9rem 1.4rem; font-weight:700; width:100%; font-size:1rem; }
        .btn-primary:hover { background:#1558c0; }
        .veiculo-existente { border:1.5px solid #dce4ef; border-radius:12px; padding:14px; margin-bottom:12px; cursor:pointer; background:#fff; display:block; }
        .veiculo-existente:hover { border-color:var(--gf-blue); }
        .divisor { text-align:center; color:#94a3b8; font-size:.85rem; margin:22px 0; }
        @media(max-width:520px) { .tipo-grid { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>
<div class="shell">
    <a class="brand" href="<?= $esc($bp) ?>/"><img src="<?= $esc($bp) ?>/public/assets/img/logo-128.png" alt=""> GuinchaFacil</a>

    <section class="hero">
        <div class="eyebrow">Falta pouco</div>
        <h1>Qual e o seu veiculo?</h1>
        <p>Precisamos desses dados pra enviar o guincho certo. Voce so preenche isso uma vez.</p>
    </section>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= $esc($flash['message']) ?></div>
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

        <label>Tipo</label>
        <div class="tipo-grid">
            <input type="radio" id="tipo-carro" name="tipo" value="carro" checked>
            <label for="tipo-carro">Carro</label>
            <input type="radio" id="tipo-moto" name="tipo" value="moto">
            <label for="tipo-moto">Moto</label>
            <input type="radio" id="tipo-caminhao" name="tipo" value="caminhao">
            <label for="tipo-caminhao">Caminhao</label>
            <input type="radio" id="tipo-van" name="tipo" value="van">
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

        <button type="submit" class="btn-primary">Continuar para o pagamento</button>
    </form>
</div>

<script src="<?= $esc($bp) ?>/public/assets/js/checkout-veiculo.js?v=1" defer></script>
</body>
</html>