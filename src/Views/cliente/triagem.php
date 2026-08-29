<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Pedir socorro</span>
            <h1>O que aconteceu?</h1>
            <p>Responda algumas perguntas rápidas para a gente te enviar o profissional certo já na primeira tentativa.</p>
        </div>
    </header>

    <form method="POST" action="<?php echo $bp; ?>/cliente/triagem/responder" id="formTriagem" class="card" style="max-width:640px;">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
            <input type="hidden" name="session_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16))); ?>">

            <div class="mb-4">
                <label class="form-label fw-semibold">O que aconteceu?</label>
                <select name="symptom_code" id="symptomCode" class="form-select" required>
                    <option value="">Selecione...</option>
                    <option value="NAO_LIGA">O veículo não liga</option>
                    <option value="PNEU">Pneu furado ou danificado</option>
                    <option value="PAROU_TRAJETO">O veículo parou durante o trajeto</option>
                    <option value="CHAVE">Chave presa ou perdida</option>
                    <option value="SEM_COMBUSTIVEL">Combustível acabou</option>
                    <option value="COLISAO">O veículo sofreu colisão</option>
                    <option value="PRECISA_TRANSPORTAR">Preciso transportar o veículo</option>
                    <option value="NAO_SEI">Não sei identificar</option>
                </select>
            </div>

            <div class="triagem-q2" data-symptom="NAO_LIGA">
                <p class="text-muted small mb-2">Mais alguns detalhes:</p>
                <div class="form-check"><input class="form-check-input" type="radio" name="resposta[painel_acende]" value="sim" id="pa_sim"><label class="form-check-label" for="pa_sim">O painel acende</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="resposta[painel_acende]" value="nao" id="pa_nao"><label class="form-check-label" for="pa_nao">O painel não acende</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="resposta[motor_gira]" value="sim" id="mg_sim"><label class="form-check-label" for="mg_sim">O motor tenta girar</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="resposta[motor_gira]" value="nao" id="mg_nao"><label class="form-check-label" for="mg_nao">O motor não reage</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="resposta[luzes_fracas]" value="sim" id="lf"><label class="form-check-label" for="lf">As luzes estão fracas</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="resposta[apagou_rodando]" value="sim" id="ar"><label class="form-check-label" for="ar">O veículo apagou enquanto estava rodando</label></div>
            </div>

            <div class="triagem-q2" data-symptom="PNEU">
                <p class="text-muted small mb-2">Mais alguns detalhes:</p>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="resposta[estepe_existe]" value="sim" id="ee"><label class="form-check-label" for="ee">Existe estepe no veículo</label></div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Condição do estepe</label>
                    <select class="form-select form-select-sm" name="resposta[estepe_condicao]" style="max-width:200px;">
                        <option value="boa">Boa</option>
                        <option value="ruim">Ruim / murcho</option>
                    </select>
                </div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="resposta[parafuso_antifurto]" value="sim" id="pat"><label class="form-check-label" for="pat">Tem parafuso antifurto</label></div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Quantos pneus foram afetados?</label>
                    <input type="number" min="1" max="4" class="form-control form-control-sm" name="resposta[pneus_afetados]" value="1" style="max-width:100px;">
                </div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="resposta[roda_danificada]" value="sim" id="rd"><label class="form-check-label" for="rd">A roda parece danificada</label></div>
            </div>

            <div class="triagem-q2" data-symptom="PAROU_TRAJETO">
                <p class="text-muted small mb-2">Mais alguns detalhes:</p>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="resposta[cheiro_queimado]" value="sim" id="cq"><label class="form-check-label" for="cq">Existe cheiro de queimado ou fumaça</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="resposta[apagou_em_movimento]" value="sim" id="aem"><label class="form-check-label" for="aem">O veículo apagou em movimento</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="resposta[bateria_trocada_recente]" value="sim" id="btr"><label class="form-check-label" for="btr">A bateria foi trocada recentemente</label></div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right me-1"></i>Continuar</button>
            </div>
        </div>
    </form>

    <script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?>>
    (function () {
        var select = document.getElementById('symptomCode');
        var grupos = document.querySelectorAll('.triagem-q2');
        function atualizar() {
            var atual = select.value;
            grupos.forEach(function (g) {
                g.style.display = (g.getAttribute('data-symptom') === atual) ? '' : 'none';
            });
        }
        grupos.forEach(function (g) { g.style.display = 'none'; });
        select.addEventListener('change', atualizar);
        atualizar();
    })();
    </script>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
