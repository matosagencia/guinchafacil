<?php
/** F18F1: navegação visual de Pessoas e Parceiros, sem alterar filtros, autenticação ou fontes de dados. */
$gfF18f1Tab = isset($gfF18f1Tab) ? (string)$gfF18f1Tab : 'clientes';
$gfF18f1Bp = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : '';
$gfF18f1Bp = htmlspecialchars($gfF18f1Bp, ENT_QUOTES, 'UTF-8');
?>
<style>
.gf18f1-nav{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-start;gap:12px;padding:10px 12px;background:transparent;border-bottom:1px solid #e1e6e3;color:#17221c}
.gf18f1-nav__intro{display:none}.gf18f1-nav__eyebrow{display:block;color:#627268;font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:700}
.gf18f1-nav__title{margin:2px 0 0;color:#16241c;font-size:20px;line-height:1.3;font-weight:750}
.gf18f1-nav__tabs{display:flex;flex-wrap:wrap;gap:6px;background:#eaf0ec;border-radius:12px;padding:5px}
.gf18f1-nav__tabs a,.gf18f1-nav__tabs span{display:inline-flex;align-items:center;gap:7px;border:1px solid transparent;text-decoration:none;color:#314538;border-radius:9px;padding:9px 13px;font-size:13px;font-weight:650;white-space:nowrap}
.gf18f1-nav__tabs a:hover{background:#fff;color:#176b36}
.gf18f1-nav__tabs .is-active{color:#165d2b;background:#fff;border-color:#cfdfd3;box-shadow:0 1px 4px #10291a12}
.gf18f1-nav__tabs .is-pending{color:#697971;cursor:not-allowed;opacity:.8}
.gf18f1-nav__hint{display:block;flex-basis:100%;font-size:12px;color:#67766d}
@media(max-width:720px){.gf18f1-nav{padding:12px}.gf18f1-nav__tabs{width:100%}.gf18f1-nav__tabs a,.gf18f1-nav__tabs span{flex:1;justify-content:center;padding:9px 7px}}
</style>
<nav class="gf18f1-nav" aria-label="Pessoas e Parceiros">
  <div class="gf18f1-nav__intro">
    <span class="gf18f1-nav__eyebrow">Administração</span>
    <h2 class="gf18f1-nav__title">Pessoas e Parceiros</h2>
  </div>
  <div class="gf18f1-nav__tabs" role="group" aria-label="Áreas de cadastro">
    <a href="<?= $gfF18f1Bp ?>/admin/usuarios?tipo=cliente" class="<?= $gfF18f1Tab === 'clientes' ? 'is-active' : '' ?>" <?= $gfF18f1Tab === 'clientes' ? 'aria-current="page"' : '' ?>>Clientes</a>
    <a href="<?= $gfF18f1Bp ?>/admin/guinchos" class="<?= $gfF18f1Tab === 'guinchos' ? 'is-active' : '' ?>" <?= $gfF18f1Tab === 'guinchos' ? 'aria-current="page"' : '' ?>>Guinchos</a>
    <a href="<?= $gfF18f1Bp ?>/admin/usuarios?tipo=oficina" class="<?= $gfF18f1Tab === 'oficinas' ? 'is-active' : '' ?>" <?= $gfF18f1Tab === 'oficinas' ? 'aria-current="page"' : '' ?>>Oficinas</a>
  </div>
  <div class="gf-f18f18-create-actions" aria-label="Criar conta na área selecionada">
    <?php
      $gfF18f18CreateType = $gfF18f1Tab === 'guinchos' ? 'guincho' : ($gfF18f1Tab === 'oficinas' ? 'oficina' : 'cliente');
      $gfF18f18CreateText = ['cliente' => 'Criar cliente', 'guincho' => 'Criar guincho', 'oficina' => 'Criar oficina'][$gfF18f18CreateType];
      $gfF18f18CreateIcon = ['cliente' => 'fa-user-plus', 'guincho' => 'fa-truck', 'oficina' => 'fa-tools'][$gfF18f18CreateType];
    ?>
    <a href="<?= $gfF18f1Bp ?>/admin/usuario/novo?tipo=<?= $gfF18f18CreateType ?>" class="gf-f18f18-create gf-f18f18-create--<?= $gfF18f18CreateType ?>"><i class="fas <?= $gfF18f18CreateIcon ?>" aria-hidden="true"></i> <?= $gfF18f18CreateText ?></a>
  </div>
</nav>
