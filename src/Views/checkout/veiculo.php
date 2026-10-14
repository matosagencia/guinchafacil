<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $dados = $triagem['dados']; ?>
<main class="container py-5" style="max-width:760px">
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
</main>
