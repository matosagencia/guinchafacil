// File: guinchafacil/qa/helpers/gps-simulator.ts
//
// Camada fina de conveniência sobre a simulação de GPS que JÁ existe e já
// foi validada em campo (postTowLocation / moveTowAlongRouteRealTime, em
// qa/helpers/atendimento.ts). Não reimplementa o envio do ponto: o endpoint
// real é POST /guincho/localizacao (pedido_id vai no corpo, não na URL —
// confirmado em GuinchoController::atualizarLocalizacao), autenticado por
// sessão de cookie + csrf_token lido do DOM da própria página do guincheiro.
// Esse contrato só é resolvível de dentro do contexto do navegador
// (page.evaluate), não via page.request.post direto — foi por isso que
// atendimento.ts já implementa assim, e foi essa implementação que recebeu,
// em 29/07/2026, a correção real §POR-QA-NAV-01 (retry quando um reload
// disparado por SSE destrói o page.evaluate em voo). Duplicar essa lógica
// aqui seria recriar um bug já corrigido em outro lugar.
//
// O que este arquivo adiciona: um tipo de rota mais simples (QaPoint, de
// stress-scenarios.fixture.ts) e uma função `driveRoute` que já embute a
// escala de tempo do QaClock — pensada pros novos cenários de stress
// (RJ-COLISAO-001, RJ-ELETRICA-001), sem duplicar CSRF/retry/rejeição.

import type { Page } from '@playwright/test';
import type { QaPoint } from '../fixtures/stress-scenarios.fixture';
import { moveTowAlongRouteRealTime, type RoutePoint } from './atendimento';
import { qaMinutes } from './qa-clock';

function toRoutePoints(route: QaPoint[]): RoutePoint[] {
  return route.map((p) => ({ lat: p.lat, lng: p.lng, street: p.street ?? p.label }));
}

/**
 * Percorre `route` (pontos reais, ver stress-scenarios.fixture.ts) enviando
 * GPS pelo caminho já validado de atendimento.ts, distribuindo o tempo
 * lógico (`durationLogicalMinutes`) igualmente entre os pontos e aplicando
 * a escala de QA_TIME_MODE/QA_TIME_SCALE. Em modo acelerado, uma rota de
 * "10 minutos lógicos" com 16 pontos vira ~750ms de intervalo real entre
 * pontos (10min * 0.02 / 16) em vez de 37,5 segundos reais.
 */
export async function driveRoute(
  page: Page,
  pedidoId: string | number,
  route: QaPoint[],
  durationLogicalMinutes: number,
  startSequence?: number
): Promise<QaPoint[]> {
  const totalMs = qaMinutes(durationLogicalMinutes);
  const intervalMs = Math.max(200, Math.floor(totalMs / Math.max(1, route.length)));

  // logicalTimestampStepMs é o tempo LÓGICO (não acelerado) entre dois
  // pontos da rota — durationLogicalMinutes é sempre em minutos "de
  // negócio" reais, independente de QA_TIME_SCALE. Isso é o que faz o
  // antifraude (LocationValidationService/POR-VAL-006) enxergar uma
  // velocidade plausível mesmo com a rota inteira rodando em segundos no
  // relógio real do teste: distância real da rota / tempo LÓGICO decorrido
  // (não o tempo real, comprimido pelo QaClock) — ver moveTowAlongRouteRealTime.
  const logicalTimestampStepMs = (durationLogicalMinutes * 60_000) / Math.max(1, route.length);

  // eslint-disable-next-line no-console
  console.log(
    `[GpsSimulator][driveRoute] pedido=${pedidoId} pontos=${route.length} ` +
    `logical=${durationLogicalMinutes}min real_total=${totalMs}ms interval=${intervalMs}ms ` +
    `logical_timestamp_step=${logicalTimestampStepMs}ms`
  );

  const accepted = await moveTowAlongRouteRealTime(page, String(pedidoId), toRoutePoints(route), intervalMs, startSequence, logicalTimestampStepMs);

  return route.filter((_, i) => i < accepted.length);
}

export { toRoutePoints };
