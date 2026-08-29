// File: guinchafacil/qa/fixtures/stress-scenarios.fixture.ts
//
// Catálogo de cenários de "stress funcional" — ocorrências reais localizadas
// na Gamboa (RJ), sede real da GuinchaFácil (Rua da Gamboa, 131 — ver
// COMPANY_ADDRESS no .env). Segue o mesmo princípio já usado pelas rotas RJ
// existentes em qa/helpers/atendimento.ts (rjEspecialistaApproachRoute etc.):
// coordenadas reais, amostradas de uma rota de carro de verdade via OSRM
// (router.project-osrm.org, api /route/v1/driving), NÃO uma linha reta —
// senão o antifraude de rastreamento (ProofOfRoadService/LocationValidationService)
// teria uma rota fisicamente implausível pra validar (ver discussão real de
// 29/07/2026 sobre a rota "em loop" no mapa do cliente, que era só o
// desenho *sugerido* do Leaflet Routing Machine, e não os pontos reais
// enviados pelo teste).
//
// IMPORTANTE: por padrão do próprio OSRM, o caminho dirigível entre Gamboa e
// Av. Venezuela passa pelo Túnel Prefeito Marcello Alencar (o trajeto direto
// mais curto tem ruas de mão única que o profile de carro do OSRM não usa) —
// 8,7km/9min de ida, 7,2km/7min de volta por um caminho parcialmente
// diferente. Isso é o roteamento real da região, não um artefato do teste.

export type QaPoint = {
  lat: number;
  lng: number;
  label: string;
  street?: string;
};

export type StressScenarioTipo = 'COLISAO' | 'PANE_ELETRICA';

export type StressScenario = {
  code: string;
  tipo: StressScenarioTipo;
  origem: QaPoint;
  prestadorBase: QaPoint;
  destino?: QaPoint;
  acceptDelayMinutes: number;
  arrivalDelayMinutes: number;
};

export const RJ_GAMBOA = {
  guinchoBase: {
    lat: -22.897419,
    lng: -43.199037,
    label: 'Rua da Gamboa, 131',
  } satisfies QaPoint,

  ocorrencia: {
    lat: -22.8958989,
    lng: -43.1857243,
    label: 'Av. Venezuela, 134',
  } satisfies QaPoint,

  oficina: {
    lat: -22.8969477,
    lng: -43.1994326,
    label: 'Rua da Gamboa, 275',
  } satisfies QaPoint,
};

export const stressScenarios: StressScenario[] = [
  {
    code: 'RJ-COLISAO-001',
    tipo: 'COLISAO',
    prestadorBase: RJ_GAMBOA.guinchoBase,
    origem: RJ_GAMBOA.ocorrencia,
    destino: RJ_GAMBOA.oficina,
    acceptDelayMinutes: 5,
    arrivalDelayMinutes: 10,
  },

  {
    code: 'RJ-ELETRICA-001',
    tipo: 'PANE_ELETRICA',
    prestadorBase: RJ_GAMBOA.guinchoBase,
    origem: RJ_GAMBOA.ocorrencia,
    acceptDelayMinutes: 3,
    arrivalDelayMinutes: 5,
  },
];

// ─────────────────────────────────────────────────────────────────────────
// Rotas reais Gamboa <-> Av. Venezuela, amostradas (16 e 14 pontos) da
// geometria devolvida pelo OSRM demo (router.project-osrm.org) em
// 29/07/2026 para os pares de coordenadas acima. Distância/duração real
// devolvida pelo próprio OSRM: ida (Gamboa 131 -> Venezuela 134) 8,73km /
// ~9min de carro; volta (Venezuela 134 -> Gamboa 275) 7,16km / ~7min —
// ambas passando pelo Túnel Prefeito Marcello Alencar, confirmado nos nomes
// de via devolvidos pela própria API ("hint"/"name" dos waypoints).
export const rjGamboaToVenezuelaRoute: QaPoint[] = [
  { lat: -22.897417, lng: -43.199058, label: 'p1', street: 'Rua União (saída Gamboa 131)' },
  { lat: -22.899293, lng: -43.201006, label: 'p2', street: 'Via real (OSRM)' },
  { lat: -22.898882, lng: -43.201645, label: 'p3', street: 'Via real (OSRM)' },
  { lat: -22.895867, lng: -43.201051, label: 'p4', street: 'Via real (OSRM)' },
  { lat: -22.894093, lng: -43.19652, label: 'p5', street: 'Via real (OSRM)' },
  { lat: -22.893724, lng: -43.191133, label: 'p6', street: 'Via real (OSRM)' },
  { lat: -22.897424, lng: -43.187347, label: 'p7', street: 'Via real (OSRM)' },
  { lat: -22.901958, lng: -43.184698, label: 'p8', street: 'Via real (OSRM)' },
  { lat: -22.905784, lng: -43.182651, label: 'p9', street: 'Via real (OSRM)' },
  { lat: -22.906871, lng: -43.18128, label: 'p10', street: 'Via real (OSRM)' },
  { lat: -22.907288, lng: -43.174975, label: 'p11', street: 'Via real (OSRM)' },
  { lat: -22.91176, lng: -43.1726, label: 'p12', street: 'Via real (OSRM)' },
  { lat: -22.912061, lng: -43.16918, label: 'p13', street: 'Via real (OSRM)' },
  { lat: -22.911765, lng: -43.169577, label: 'p14', street: 'Via real (OSRM)' },
  { lat: -22.905709, lng: -43.168587, label: 'p15', street: 'Via real (OSRM)' },
  { lat: -22.895955, lng: -43.185748, label: 'p16', street: 'Túnel Prefeito Marcello Alencar (chegada Av. Venezuela)' },
];

export const rjVenezuelaToOficinaRoute: QaPoint[] = [
  { lat: -22.895955, lng: -43.185748, label: 'p1', street: 'Túnel Prefeito Marcello Alencar (saída Av. Venezuela)' },
  { lat: -22.89268, lng: -43.195249, label: 'p2', street: 'Via real (OSRM)' },
  { lat: -22.898465, lng: -43.21002, label: 'p3', street: 'Via real (OSRM)' },
  { lat: -22.895113, lng: -43.213476, label: 'p4', street: 'Via real (OSRM)' },
  { lat: -22.89246, lng: -43.215976, label: 'p5', street: 'Via real (OSRM)' },
  { lat: -22.895721, lng: -43.214467, label: 'p6', street: 'Via real (OSRM)' },
  { lat: -22.898626, lng: -43.210833, label: 'p7', street: 'Via real (OSRM)' },
  { lat: -22.897737, lng: -43.207757, label: 'p8', street: 'Via real (OSRM)' },
  { lat: -22.894957, lng: -43.199335, label: 'p9', street: 'Via real (OSRM)' },
  { lat: -22.896383, lng: -43.202744, label: 'p10', street: 'Via real (OSRM)' },
  { lat: -22.896591, lng: -43.201416, label: 'p11', street: 'Via real (OSRM)' },
  { lat: -22.895209, lng: -43.199877, label: 'p12', street: 'Via real (OSRM)' },
  { lat: -22.896111, lng: -43.198774, label: 'p13', street: 'Via real (OSRM)' },
  { lat: -22.896937, lng: -43.199385, label: 'p14', street: 'Rua da Gamboa (chegada Gamboa 275)' },
];
