<?php
$copy = require __DIR__ . '/_copy_precotacao.php';
?>
<div class="container py-4">
    <!-- Passo 1: Endereço -->
    <div id="step-endereco" class="card p-4 shadow-sm mb-3">
        <h4>Onde você está?</h4>
        <div class="mb-3">
            <input type="text" id="endereco" class="form-control" placeholder="Digite seu endereço ou CEP">
        </div>
        <button type="button" class="btn btn-primary" onclick="avancarParaProblema()">Continuar</button>
    </div>

    <!-- Passo 2: O que aconteceu? -->
    <div id="step-problema" class="card p-4 shadow-sm mb-3" style="display:none;">
        <h4>O que está acontecendo com o veículo?</h4>
        <div class="d-grid gap-2 mt-3">
            <button class="btn btn-outline-secondary" onclick="selecionarProblema('orientacao')">Me orientem</button>
            <button class="btn btn-outline-primary" onclick="selecionarProblema('resolver_local')">Resolver no local</button>
            <button class="btn btn-outline-primary" onclick="selecionarProblema('levar_carro')">Levar o carro para oficina</button>
        </div>
    </div>

    <!-- Passo 3: Resultado da Triagem -->
    <div id="step-resultado" style="display:none;"></div>

    <!-- Modal de Recusa de Orçamento -->
    <div id="modal-recusa" class="modal" tabindex="-1" style="display:none; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content p-4">
                <h5>Recusar Orçamento Local</h5>
                <p>Você recusou o orçamento local. Deseja chamar o guincho com desconto especial?</p>
                <p>Valor original: R$ <span id="valor-original"></span></p>
                <p><strong>Valor com desconto: R$ <span id="valor-desconto"></span></strong></p>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-secondary" onclick="fecharModal()">Voltar</button>
                    <button class="btn btn-success" onclick="confirmarRecusaGuincho()">Sim, chamar guincho</button>
                </div>
            </div>
        </div>
    </div>
</div>
