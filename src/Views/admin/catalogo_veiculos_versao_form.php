<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$versao = $versao ?? null;
$modelo = $modelo ?? [];
$marca = $marca ?? [];
$categorias = $categorias ?? [];

$v = static function (string $campo, $default = '') use ($versao) {
    return $versao[$campo] ?? $default;
};
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow"><?php echo htmlspecialchars($marca['name'] ?? ''); ?> — <?php echo htmlspecialchars($modelo['name'] ?? ''); ?></span>
            <h1><?php echo $versao ? 'Editar versão' : 'Nova versão'; ?></h1>
            <p><a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/<?php echo (int)$modelo['id']; ?>"><i class="fas fa-arrow-left me-1"></i>Voltar pras versões</a></p>
        </div>
    </header>

    <div class="card" style="max-width:760px">
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/catalogo-veiculos/versao/salvar">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo $versao ? (int)$versao['id'] : 0; ?>">
                <input type="hidden" name="modelo_id" value="<?php echo (int)$modelo['id']; ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome da versão</label>
                        <input type="text" class="form-control" name="name" maxlength="120" required
                               value="<?php echo htmlspecialchars((string)$v('name')); ?>" placeholder='Ex.: "1.0 Flex Manual"'>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ano inicial</label>
                        <input type="number" class="form-control" name="start_year" value="<?php echo htmlspecialchars((string)$v('start_year')); ?>" placeholder="2018">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ano final</label>
                        <input type="number" class="form-control" name="end_year" value="<?php echo htmlspecialchars((string)$v('end_year')); ?>" placeholder="(vazio = em produção)">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Motorização</label>
                        <input type="text" class="form-control" name="engine" value="<?php echo htmlspecialchars((string)$v('engine')); ?>" placeholder="Ex.: 1.0 12V">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Combustível</label>
                        <select class="form-select" name="fuel_type">
                            <?php foreach (['' => '—', 'flex' => 'Flex', 'gasolina' => 'Gasolina', 'diesel' => 'Diesel', 'eletrico' => 'Elétrico', 'hibrido' => 'Híbrido'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo (string)$v('fuel_type') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Câmbio</label>
                        <select class="form-select" name="transmission_type">
                            <?php foreach (['' => '—', 'manual' => 'Manual', 'automatico' => 'Automático', 'cvt' => 'CVT'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo (string)$v('transmission_type') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tração</label>
                        <select class="form-select" name="traction_type">
                            <?php foreach (['' => '—', '4x2' => '4x2', '4x4' => '4x4'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo (string)$v('traction_type') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Carroceria</label>
                        <select class="form-select" name="body_type">
                            <?php foreach (['' => '—', 'hatch' => 'Hatch', 'sedan' => 'Sedã', 'suv' => 'SUV', 'pickup' => 'Picape', 'van' => 'Van', 'moto' => 'Moto'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo (string)$v('body_type') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Elétrico/híbrido</label>
                        <select class="form-select" name="electric_type">
                            <?php foreach (['' => 'Combustão', 'hibrido' => 'Híbrido', 'eletrico' => 'Elétrico'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo (string)$v('electric_type') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Categoria operacional</label>
                        <select class="form-select" name="operational_category_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo (int)$v('operational_category_id') === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-muted small mt-1 mb-0">Usada pra decidir compatibilidade prestador × veículo (Etapa 15).</p>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="startStop" name="start_stop" value="1" <?php echo !empty($v('start_stop')) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="startStop">Possui sistema Start-Stop</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Peso em ordem de marcha (kg)</label>
                        <input type="number" class="form-control" name="curb_weight_kg" value="<?php echo htmlspecialchars((string)$v('curb_weight_kg')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Peso bruto total (kg)</label>
                        <input type="number" class="form-control" name="gross_weight_kg" value="<?php echo htmlspecialchars((string)$v('gross_weight_kg')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Comprimento (mm)</label>
                        <input type="number" class="form-control" name="length_mm" value="<?php echo htmlspecialchars((string)$v('length_mm')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Altura (mm)</label>
                        <input type="number" class="form-control" name="height_mm" value="<?php echo htmlspecialchars((string)$v('height_mm')); ?>">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="versaoAtiva" name="active" value="1" <?php echo (!$versao || !empty($versao['active'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="versaoAtiva">Ativa (selecionável pelo cliente)</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i>Salvar</button>
            </form>
        </div>
    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
