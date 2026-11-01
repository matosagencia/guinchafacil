<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $dados = $triagem['dados']; $triageId = (string)($triagem['id'] ?? ''); ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/pre-cotacao.css?v=20261101-3">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/gf-layout.css?v=20261101-1">
<style>
.gf-checkout{max-width:1180px;margin:0 auto;padding:36px 22px 72px}.gf-checkout__grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:30px;align-items:start}.gf-checkout__panel,.gf-checkout__side{background:#fff;border:1px solid #e5e9e6;border-radius:22px;box-shadow:0 12px 34px rgba(19,54,30,.08);padding:28px}.gf-checkout__side{position:sticky;top:22px}.gf-checkout__eyebrow{color:#16853a;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.gf-checkout h1{margin:8px 0;color:#12301d;font-size:clamp(26px,4vw,38px)}.gf-checkout p{color:#617066}.gf-checkout__price{color:#16853a;font-weight:800;font-size:25px;margin-top:8px}.gf-checkout__empty{padding:18px;background:#f4faf5;border-radius:14px}.gf-checkout__btn{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:48px;border:0;border-radius:12px;background:#16853a;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.gf-checkout__btn:hover{background:#10682d;color:#fff}@media(max-width:760px){.gf-checkout{padding:22px 14px 50px}.gf-checkout__grid{grid-template-columns:1fr}.gf-checkout__side{position:static;order:-1}}
.gf-checkout__select{display:block;width:100%;min-height:50px;margin:8px 0 22px;padding:0 44px 0 15px;border:1px solid #cfe0d3;border-radius:12px;background:#fff;color:#183522;font:inherit;font-weight:600;appearance:auto;box-shadow:0 2px 7px rgba(19,54,30,.05)}.gf-checkout__select:focus{outline:0;border-color:#16853a;box-shadow:0 0 0 4px rgba(22,133,58,.13)}
</style>
<div class="gf-utility-bar"><div class="gf-container"><span><span class="gf-status-dot"></span> Socorro 24h. Todos os dias. Em qualquer imprevisto.</span><a href="<?php echo htmlspecialchars($bp); ?>/guincho/niteroi-rj">Niterói, RJ</a></div></div>
<header class="gf-site-header"><div class="gf-container"><a class="gf-brand" href="<?php echo htmlspecialchars($bp); ?>/">Guincha<span>Fácil</span>.</a><nav class="gf-nav" aria-label="Navegação principal"><a href="<?php echo htmlspecialchars($bp); ?>/pre-cotacao">Como funciona</a><a href="<?php echo htmlspecialchars($bp); ?>/guincho/niteroi-rj">Onde atendemos</a><a href="<?php echo htmlspecialchars($bp); ?>/parceiros">Seja um parceiro</a></nav><a class="gf-btn gf-btn--primary gf-btn--sm" href="<?php echo htmlspecialchars($bp); ?>/pre-cotacao">Pedir guincho</a></div></header>
<main class="gf-checkout">
  <div class="gf-checkout__grid">
    <section class="gf-checkout__panel">
      <span class="gf-checkout__eyebrow">Próximo passo</span>
      <h1>Escolha o veículo para este atendimento</h1>
      <p>Cadastre fabricante, modelo e os dados básicos para encontrarmos o atendimento correto.</p>
      <?php if (empty($veiculos)): ?>
        <div class="gf-checkout__empty">
          <strong>Você ainda não tem um veículo cadastrado.</strong>
          <p>Na próxima tela, selecione o tipo e preencha fabricante, modelo, ano, placa, cidade e UF.</p>
          <a class="gf-checkout__btn" href="<?php echo htmlspecialchars($bp); ?>/cliente/veiculo/novo?triage_session_id=<?php echo rawurlencode($triageId); ?>">Cadastrar veículo</a>
        </div>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($bp); ?>/checkout/pagar">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="triage_session_id" value="<?php echo htmlspecialchars($triagem['id']); ?>">
          <label class="form-label" for="veiculo_id">Veículo</label>
          <select class="form-select mb-4" name="veiculo_id" id="veiculo_id" required>
            <option value="">Selecione</option>
            <?php foreach ($veiculos as $veiculo): ?>
              <option value="<?php echo (int)$veiculo['id']; ?>"><?php echo htmlspecialchars(trim(($veiculo['marca'] ?? '') . ' ' . ($veiculo['modelo'] ?? '') . ' · ' . ($veiculo['placa'] ?? ''))); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="gf-checkout__btn" type="submit">Continuar para pagamento</button>
        </form>
      <?php endif; ?>
    </section>
    <aside class="gf-checkout__side">
      <span class="gf-checkout__eyebrow">Cotação preservada</span>
      <h2><?php echo htmlspecialchars(ucfirst($dados['tipo_problema'] ?? 'Atendimento')); ?></h2>
      <p><?php echo htmlspecialchars($dados['endereco_origem'] ?? ''); ?> → <?php echo htmlspecialchars($dados['endereco_destino'] ?? ''); ?></p>
      <div class="gf-checkout__price">R$ <?php echo number_format((float)($dados['custo_estimado'] ?? 0), 2, ',', '.'); ?></div>
    </aside>
  </div>
</main>
<footer class="gf-site-footer"><div class="gf-container"><div class="gf-footer-bottom"><span>&copy; <?php echo date('Y'); ?> GuinchaFácil. Todos os direitos reservados.</span><span>Assistência 24 horas · Niterói, RJ</span></div></div></footer>
<?php if (false): ?>
  <section class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
      <p class="text-success fw-bold text-uppercase small mb-2">Cotação preservada</p>
      <h1 class="h3 mb-3">Escolha o veículo para este atendimento</h1>
      <div class="rounded p-3 mb-4" style="background:#f4f8f5">
        <strong><?php echo htmlspecialchars(ucfirst($dados['tipo_problema'])); ?></strong>
        <div class="small text-muted mt-1"><?php echo htmlspecialchars($dados['endereco_origem']); ?> → <?php echo htmlspecialchars($dados['endereco_destino']); ?></div>
        <div class="mt-2 fw-bold">Estimativa: R$ <?php echo number_format((float)$dados['custo_estimado'], 2, ',', '.'); ?></div>
      </div>
      <?php if (empty($veiculos)): ?>
        <p>Cadastre um veículo antes de continuar.</p>
        <a class="btn btn-success" href="<?php echo htmlspecialchars($bp); ?>/cliente/veiculo/novo">Cadastrar veículo</a>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($bp); ?>/checkout/pagar">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="triage_session_id" value="<?php echo htmlspecialchars($triagem['id']); ?>">
          <label class="form-label" for="veiculo_id">Veículo</label>
          <select class="form-select mb-4" name="veiculo_id" id="veiculo_id" required>
            <option value="">Selecione</option>
            <?php foreach ($veiculos as $veiculo): ?>
              <option value="<?php echo (int)$veiculo['id']; ?>"><?php echo htmlspecialchars(trim(($veiculo['marca'] ?? '') . ' ' . ($veiculo['modelo'] ?? '') . ' · ' . ($veiculo['placa'] ?? ''))); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-success w-100" type="submit">Continuar para pagamento</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>
