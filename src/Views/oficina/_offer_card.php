<?php
// src/Views/oficina/_offer_card.php
// Espera: $p (pedido), $bp, $csrfToken
?>
<div class="tow-offer" data-pedido-id="<?= (int)$p['id'] ?>">
  <div class="tow-offer-head d-flex justify-content-between align-items-start">
    <div>
      <span class="tow-offer-eyebrow"><i class="fas fa-bolt me-1"></i>Nova solicitação</span>
      <h4 class="tow-offer-title">Pedido #<?= (int)$p['id'] ?></h4>
      <p class="tow-offer-subtitle mb-0">
        <?= htmlspecialchars((string)($p['tipo_problema'] ?? 'Socorro')) ?>
      </p>
    </div>
    <?php if (!empty($p['expira_em'])): ?>
      <span class="tow-offer-timer" data-expira="<?= htmlspecialchars((string)$p['expira_em']) ?>">--:--</span>
    <?php endif; ?>
  </div>

  <div class="tow-offer-metrics">
    <div class="tow-offer-metric">
      <span>Distância</span>
      <strong><?= number_format((float)($p['distancia_km'] ?? 0), 1, ',', '.') ?> km</strong>
    </div>
    <div class="tow-offer-metric">
      <span>Valor estimado</span>
      <strong>R$ <?= number_format((float)($p['custo_estimado'] ?? 0), 2, ',', '.') ?></strong>
    </div>
    <div class="tow-offer-metric">
      <span>Categoria</span>
      <strong><?= htmlspecialchars((string)($p['categoria'] ?? '—')) ?></strong>
    </div>
  </div>

  <div class="tow-offer-actions">
    <form method="post" action="<?= $bp ?>/oficina/recusar/<?= (int)$p['id'] ?>" class="flex-grow-1 m-0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <button type="submit" class="btn btn-outline-secondary w-100">
        <i class="fas fa-xmark me-1"></i>Recusar
      </button>
    </form>
        <form method="post" action="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>/aceitar" class="flex-grow-1 m-0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <button type="submit" class="btn btn-success w-100">
        <i class="fas fa-check me-1"></i>Aceitar
      </button>
    </form>
  </div>
</div>