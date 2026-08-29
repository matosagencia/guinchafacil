<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$registros = $historico ?? $pedidos ?? [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/tow-historico.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="tow-history">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Histórico</span>
                <h1><i class="fas fa-clock-rotate-left me-2 text-primary-custom"></i>Histórico de Atendimentos</h1>
                <p><?php echo count($registros); ?> registro(s) nesta visualização</p>
            </div>
        </header>

        <section class="tow-history-hero p-4 p-lg-5 mb-4">
            <h2 class="mb-2 tow-history-title">Resumo das corridas concluídas.</h2>
            <p class="mb-0 text-muted">O histórico do guincho agora segue a mesma linha visual dos painéis novos. A camada de dados foi preservada e ganhou compatibilidade com os registros reais do controller.</p>
        </section>

        <section class="tow-history-card p-4 p-lg-5">
            <div class="row g-3">
                <?php if (!empty($registros)): foreach ($registros as $h): ?>
                <?php
                $dataExibicao = $h['data'] ?? (!empty($h['criado_em']) ? date('d/m/Y', strtotime($h['criado_em'])) : '—');
                $cliente = $h['cliente'] ?? $h['cliente_nome'] ?? 'Cliente';
                $servico = $h['servico'] ?? ucfirst(str_replace('_', ' ', (string)($h['tipo_problema'] ?? 'serviço')));
                $valorBruto = (float)($h['valor_bruto'] ?? $h['custo_final'] ?? $h['custo_estimado'] ?? 0);
                $valorLiquido = (float)($h['valor_liquido'] ?? $h['valor_guincho'] ?? $valorBruto);
                $avaliacao = (int)($h['avaliacao'] ?? round((float)($h['nota_media'] ?? 5)));
                ?>
                <div class="col-xl-6">
                    <article class="tow-history-item h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="small text-muted mb-1"><?php echo htmlspecialchars($dataExibicao); ?></div>
                                <strong class="tow-history-cliente"><?php echo htmlspecialchars($cliente); ?></strong>
                            </div>
                            <span class="badge text-bg-success">Concluído</span>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted mb-1">Serviço</div>
                            <strong><?php echo htmlspecialchars($servico); ?></strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="small text-muted mb-1">Valor bruto</div>
                                <strong>R$ <?php echo number_format($valorBruto, 2, ',', '.'); ?></strong>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted mb-1">Valor líquido</div>
                                <strong class="text-success">R$ <?php echo number_format($valorLiquido, 2, ',', '.'); ?></strong>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="small text-muted mb-1">Avaliação</div>
                            <div class="tow-history-stars"><?php echo str_repeat('★', max(0, min(5, $avaliacao))); ?></div>
                        </div>
                    </article>
                </div>
                <?php endforeach; else: ?>
                <div class="col-12">
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-road fa-2x mb-2 d-block"></i>
                        Nenhum atendimento encontrado.
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
