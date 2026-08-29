<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/OrderFlow/HybridFlowDefinition.php
// ROADMAP socorro automotivo — Fundamento 4, §5.4 ("Prestador híbrido").
//
// Estruturalmente, os estados possíveis são os mesmos do OnSiteFlowDefinition
// (herda o mapa por composição/extends) — o que muda no caso híbrido não é
// QUAIS estados existem, é COMO se chega em `preparacao_veiculo`: sem nova
// disputa de matching, porque o mesmo prestador já tem capacidade de reboque
// aprovada (Etapa 4/7 decidem isso na camada de serviço, não aqui).

require_once __DIR__ . '/OnSiteFlowDefinition.php';

final class HybridFlowDefinition extends OnSiteFlowDefinition
{
    // Sem overrides por enquanto — reservado para o dia em que o fluxo
    // híbrido precisar de uma transição que o socorro-local puro não tem
    // (ex.: pular diagnostico_iniciado quando o prestador já chega sabendo
    // que vai virar reboque). Ver Etapa 7.
}
