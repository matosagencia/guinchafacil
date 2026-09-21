<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$pending = ($perfil['perfil_status'] ?? '') === 'PENDENTE_APROVACAO';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete seu perfil | GuinchaFácil</title>
    <link href="<?= $esc($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $esc($bp) ?>/public/assets/css/style.css" rel="stylesheet">
    <style>
        :root{--gf-blue:#1769e0;--gf-ink:#10233f;--gf-soft:#f4f8ff}
        body{min-height:100vh;background:linear-gradient(135deg,#eef5ff 0%,#fff 58%,#eefbf8 100%);color:var(--gf-ink)}
        .profile-shell{max-width:1060px;margin:0 auto;padding:32px 18px 56px}
        .brand{display:flex;align-items:center;gap:12px;color:var(--gf-ink);text-decoration:none;font-weight:800;font-size:1.15rem}
        .brand img{width:42px;height:42px;border-radius:12px}
        .hero{padding:46px 0 28px;max-width:700px}.hero .eyebrow{color:var(--gf-blue);font-size:.78rem;letter-spacing:.12em;font-weight:800;text-transform:uppercase}.hero h1{font-size:clamp(2rem,5vw,3.6rem);font-weight:800;letter-spacing:-.05em;line-height:1.02}.hero p{color:#5d6b80;font-size:1.08rem}
        .card-panel{background:rgba(255,255,255,.93);border:1px solid #e3eaf4;border-radius:24px;box-shadow:0 20px 60px rgba(22,65,120,.12);padding:28px}
        .role-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.role input{position:absolute;opacity:0}.role label{height:100%;display:block;border:1px solid #dce4ef;border-radius:18px;padding:20px 15px;cursor:pointer;transition:.18s;background:#fff}.role label:hover{border-color:#8bb4f5;transform:translateY(-2px)}.role input:checked+label{border-color:var(--gf-blue);background:var(--gf-soft);box-shadow:0 0 0 3px rgba(23,105,224,.12)}.role-icon{font-size:1.8rem;display:block;margin-bottom:12px}.role strong{display:block}.role small{display:block;color:#718096;margin-top:6px;line-height:1.35}
        .form-control{border-radius:12px;padding:.78rem 1rem;border-color:#dce4ef}.btn-primary{border:0;border-radius:12px;background:var(--gf-blue);padding:.85rem 1.25rem;font-weight:700}.hint{color:#6c7a90;font-size:.9rem}.status{border-radius:14px;background:#eef8f2;border:1px solid #c9ead7;padding:15px;color:#17633b}
        @media(max-width:800px){.role-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:480px){.role-grid{grid-template-columns:1fr}.card-panel{padding:20px}.hero{padding-top:32px}}
    </style>
</head>
<body>
<main class="profile-shell">
    <a class="brand" href="<?= $esc($bp) ?>/"><img src="<?= $esc($bp) ?>/public/assets/img/logo-128.png" alt=""> GuinchaFácil</a>
    <section class="hero">
        <div class="eyebrow">Primeiro acesso com Google</div>
        <h1>Vamos deixar seu perfil do jeito certo.</h1>
        <p>Olá, <strong><?= $esc($perfil['nome'] ?? '') ?></strong>. Escolha como você quer usar o GuinchaFácil. Seu e-mail já foi confirmado pelo Google.</p>
    </section>
    <?php if (!empty($flash)): ?><div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
    <?php if ($pending): ?>
        <div class="card-panel status"><strong>Solicitação em análise</strong><br>Seu perfil profissional foi recebido. O acesso de guincho, oficina ou prestador móvel será liberado depois da validação administrativa.</div>
    <?php else: ?>
    <form class="card-panel" method="post" action="<?= $esc($bp) ?>/auth/google/profile">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
        <h2 class="h4 fw-bold mb-1">Qual é o seu perfil?</h2><p class="hint mb-4">Perfis administrativos são criados exclusivamente pelo administrador.</p>
        <div class="role-grid mb-4">
            <div class="role"><input required type="radio" id="cliente" name="perfil" value="CLIENTE"><label for="cliente"><span class="role-icon">🚗</span><strong>Cliente</strong><small>Solicitar socorro e acompanhar atendimentos.</small></label></div>
            <div class="role"><input type="radio" id="guincho" name="perfil" value="GUINCHO"><label for="guincho"><span class="role-icon">🚛</span><strong>Guincho</strong><small>Receber chamados e trabalhar na estrada.</small></label></div>
            <div class="role"><input type="radio" id="oficina" name="perfil" value="OFICINA"><label for="oficina"><span class="role-icon">🔧</span><strong>Oficina</strong><small>Atender clientes como oficina parceira.</small></label></div>
            <div class="role"><input type="radio" id="prestador-movel" name="perfil" value="PRESTADOR_MOVEL"><label for="prestador-movel"><span class="role-icon">⚡</span><strong>Prestador móvel</strong><small>Oferecer serviços técnicos diretamente no local.</small></label></div>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold" for="telefone">Celular</label><input class="form-control" id="telefone" name="telefone" placeholder="(21) 99999-9999" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold" for="cpf">CPF</label><input class="form-control" id="cpf" name="cpf" inputmode="numeric" placeholder="000.000.000-00" required></div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4"><span class="hint">Você poderá complementar os dados profissionais após a análise.</span><button class="btn btn-primary" type="submit">Continuar meu cadastro <span aria-hidden="true">→</span></button></div>
    </form>
    <?php endif; ?>
</main>
</body>
</html>
