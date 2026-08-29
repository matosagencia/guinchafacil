import { expect, type Page } from '@playwright/test';
import { appPath } from './paths';

export type RoutePoint = {
  lat: number;
  lng: number;
  street: string;
};

// Mesma fórmula de GeoService::haversine (backend, raio 6371km) — precisa
// bater com o que o antifraude calcula, senão o "tempo mínimo pro trecho"
// calculado aqui não protege de verdade contra POR-VAL-006.
function haversineMetros(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
  const toRad = (d: number) => (d * Math.PI) / 180;
  const raioTerraM = 6371000;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return raioTerraM * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

// LocationValidationService::validatePoint (POR-VAL-006) rejeita quando
// distance_m / elapsed_s * 3.6 > PorThresholds::maxSpeedKmh() (padrão real:
// 130 km/h). Usamos uma margem de segurança abaixo disso — não pra "quase
// estourar" o limite real, mas pra absorver arredondamento de ms/precisão
// de coordenada sem depender de bater o limite exato do backend.
const POR_SAFE_MAX_SPEED_KMH = 100;

export const atendimentoRoutePoints: RoutePoint[] = [
  { lat: -23.55052, lng: -46.63331, street: 'Praça da Sé' },
  { lat: -23.55184, lng: -46.63872, street: 'Rua Líbero Badaró' },
  { lat: -23.55643, lng: -46.64591, street: 'Viaduto Nove de Julho' },
  { lat: -23.56084, lng: -46.65274, street: 'Rua Augusta' },
  { lat: -23.56140, lng: -46.65650, street: 'Avenida Paulista' }
];

// "Avenida Paulista" por extenso é como o seed grava endereco_destino (ver
// tools/prepare_atendimento_completo_qa_seed.php); "Av. Paulista" nunca bateu
// de verdade — este teste só foi ativado agora (antes ficava sempre pulado
// por falta de env var), e essa era a primeira vez que o regex era exercitado.
const defaultExpectedStreetPattern = /Praça da Sé|Rua Líbero Badaró|Viaduto Nove de Julho|Rua Augusta|Av(?:enida|\.)? Paulista/i;

function parseRouteJson(rawValue?: string): RoutePoint[] | null {
  if (!rawValue || !rawValue.trim()) {
    return null;
  }

  try {
    const parsed = JSON.parse(rawValue);
    if (!Array.isArray(parsed) || parsed.length === 0) {
      return null;
    }

    const route = parsed
      .map((point) => ({
        lat: Number(point?.lat),
        lng: Number(point?.lng),
        street: String(point?.street || '')
      }))
      .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng) && point.street.trim() !== '');

    return route.length > 0 ? route : null;
  } catch {
    return null;
  }
}

function parseExpectedStreetPattern(rawValue?: string): RegExp {
  if (!rawValue || !rawValue.trim()) {
    return defaultExpectedStreetPattern;
  }

  try {
    return new RegExp(rawValue, 'i');
  } catch {
    return defaultExpectedStreetPattern;
  }
}

export function atendimentoConfig() {
  const route = parseRouteJson(process.env.TEST_ATENDIMENTO_ROUTE_JSON) || atendimentoRoutePoints;
  return {
    pedidoId: process.env.TEST_ATENDIMENTO_COMPLETO_PEDIDO_ID
      || process.env.TEST_ATENDIMENTO_PEDIDO_ID
      || process.env.TEST_PEDIDO_ATENDIMENTO_ID
      || '',
    freePayment: (process.env.FREE_PAYMENT || '1') === '1',
    arrivalImage: resolveEvidenceImage(
      process.env.TEST_EVIDENCE_ARRIVAL_IMAGE,
      process.env.TEST_EVIDENCE_IMAGE
    ),
    deliveryImage: resolveEvidenceImage(
      process.env.TEST_EVIDENCE_DELIVERY_IMAGE,
      process.env.TEST_EVIDENCE_IMAGE_2,
      process.env.TEST_EVIDENCE_IMAGE
    ),
    route,
    expectedStreetPattern: parseExpectedStreetPattern(process.env.TEST_ATENDIMENTO_EXPECTED_STREET_REGEX)
  };
}

export function resolveEvidenceImage(...preferred: Array<string | undefined>): string {
  const candidates = [
    ...preferred,
    'C:\\Windows\\Web\\Wallpaper\\Windows\\img0.jpg',
    'C:\\Windows\\Web\\Wallpaper\\Theme1\\img1.jpg',
    'C:\\Users\\Public\\Pictures\\NVIDIA Corporation\\3D Vision Experience\\3D Vision preview pack 1\\burnout paradise - 1 - esrb everyone 10+.jpg'
  ].filter((value): value is string => Boolean(value && value.trim()));

  return candidates[0];
}

export async function sendChatMessage(page: Page, selector: string, text: string): Promise<void> {
  await page.locator(selector).fill(text);
  await page.locator(selector).press('Enter');
}

export async function clickChatButton(page: Page, inputSelector: string, buttonSelector: string, text: string): Promise<void> {
  await page.locator(inputSelector).fill(text);
  await page.locator(buttonSelector).click();
}

export async function waitForChatMessage(page: Page, text: string): Promise<void> {
  await expect(page.locator('#chatBox')).toContainText(text, { timeout: 20000 });
}

export async function postTowLocation(
  page: Page,
  pedidoId: string,
  point: RoutePoint,
  sequence: number,
  endpointPath = '/guincho/localizacao',
  deviceTimestampMs?: number,
  speedMps = 6,
  evaluateTimeoutMs = 8000
): Promise<{ ok?: boolean; accepted?: boolean; reason?: string; raw_text?: string; status?: number }> {
  // §POR-QA-NAV-01 (29/07/2026, achado em Sandbox real): a própria página
  // guincho/atendimento.php dá window.location.reload() sempre que recebe,
  // via SSE, um status_update com status diferente do que carregou
  // (ver atendimento.php ~linha 834). Se esse reload acontecer bem no meio
  // de um fetch() disparado por page.evaluate() (ex.: um webhook/reavaliação
  // assíncrona tocando o pedido enquanto o teste está no meio do envio de um
  // ponto de rota), o contexto de execução do CDP é destruído e a promise do
  // Playwright fica PENDURADA PARA SEMPRE — sem erro, sem rejeição — travando
  // o teste inteiro até o timeout global (confirmado: log real mostrando
  // /guincho/localizacao parar de receber pontos, enquanto status-json da
  // mesma página continuava respondendo normalmente minutos depois, ou seja,
  // a página recarregou e seguiu viva; só o page.evaluate em voo é que nunca
  // voltou). Corrigido com uma corrida contra um timeout curto: se o
  // evaluate não responder a tempo, tratamos como "possível navegação" em
  // vez de travar o teste — quem chama decide se tenta de novo.
  const evaluatePromise = page.evaluate(async ({
    pedidoId: localPedidoId,
    point: localPoint,
    sequence: localSequence,
    endpointPath: localEndpointPath,
    deviceTimestampMs: localDeviceTimestampMs,
    speedMps: localSpeedMps
  }) => {
    const csrf = (document.querySelector('#btnEnviarMsg') as HTMLButtonElement | null)?.dataset?.csrf
      || (document.querySelector('input[name="csrf_token"]') as HTMLInputElement | null)?.value
      || '';

    const body = new URLSearchParams({
      csrf_token: csrf,
      pedido_id: String(localPedidoId),
      latitude: String(localPoint.lat),
      longitude: String(localPoint.lng),
      accuracy_m: '8',
      speed_mps: String(localSpeedMps),
      heading_deg: '90',
      device_timestamp: String(localDeviceTimestampMs || Date.now()),
      sequence: String(localSequence),
      // Bug real (achado em STRESS-POR-001): usava Date.now() do BROWSER aqui,
      // gerado de novo a cada chamada — mesmo passando o MESMO
      // deviceTimestampMs pro teste, 3 chamadas concorrentes geravam 3
      // client_point_id DIFERENTES. A "duplicata" real então colidia por
      // sequence_number (uk_pedido_sequence), não por client_point_id; o
      // catch de concorrência do backend (ProofOfRoadService::ingestPoint)
      // só sabe achar o vencedor por client_point_id, não achava, e caía no
      // erro genérico em vez da resposta idempotente. Usar o timestamp
      // recebido (determinístico) faz o client_point_id realmente repetir
      // quando o teste manda o mesmo ponto 2x de propósito.
      client_point_id: `pw-e2e-${localSequence}-${localDeviceTimestampMs || Date.now()}`
    });

    const response = await fetch(localEndpointPath, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body
    });

    const rawText = await response.text();
    try {
      const parsed = JSON.parse(rawText);
      return { ...parsed, status: response.status, raw_text: rawText };
    } catch {
      return {
        ok: false,
        accepted: false,
        reason: 'non_json_response',
        status: response.status,
        raw_text: rawText
      };
    }
  }, { pedidoId, point, sequence, endpointPath, deviceTimestampMs, speedMps })
    // Bug real (STRESS-CHAOS-001 sob stress agregado com 4 workers,
    // 31/07/2026): o mesmo reload que §POR-QA-NAV-01 já tratava (evaluate
    // PENDURADO pra sempre) também pode acontecer rápido o bastante pro
    // evaluate() REJEITAR na hora ("Execution context was destroyed, most
    // likely because of a navigation") em vez de travar — daí o timeout
    // nunca chega a vencer a corrida, e sem .catch() aqui essa rejeição
    // vazava pra fora do Promise.race sem passar pelo mesmo retry que já
    // existe pra esse cenário. Convertendo pro mesmo formato de "possível
    // navegação" o retry em moveTowAlongRouteRealTime trata os dois casos
    // (trava ou rejeita) de forma idêntica.
    .catch((err: unknown) => ({
      ok: false,
      accepted: false,
      reason: 'evaluate_timeout_possible_navigation',
      raw_text: err instanceof Error ? err.message : String(err),
    }));

  const timeoutPromise = new Promise<{ ok: boolean; accepted: boolean; reason: string }>((resolve) => {
    setTimeout(() => resolve({ ok: false, accepted: false, reason: 'evaluate_timeout_possible_navigation' }), evaluateTimeoutMs);
  });

  return Promise.race([evaluatePromise, timeoutPromise]);
}

export async function fetchCurrentPointMeta(page: Page, pedidoId: string): Promise<{ sequence: number; deviceTimestampMs: number }> {
  const statusPath = new URL(appPath(`/guincho/pedido/status-json/${pedidoId}`), 'http://localhost').pathname;
  return page.evaluate(async ({ localStatusPath }) => {
    const response = await fetch(localStatusPath, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    const data = await response.json();
    return {
      sequence: Number(data?.por_snapshot?.last_point?.sequence_number || 0),
      deviceTimestampMs: Number(data?.por_snapshot?.last_point?.device_timestamp || 0)
    };
  }, { localStatusPath: statusPath });
}

// Achado no gate completo (150 testes, firefox/webkit): POR-VAL-005 (device_timestamp
// longe demais do "agora" do servidor) e POR-VAL-009 (device_timestamp <= ponto
// anterior) apareciam esporadicamente SÓ em firefox/webkit, nunca em chromium —
// e sempre no mesmo ponto da rota (o 2º ponto enviado), o que aponta pra
// firefox/webkit serem mensuravelmente mais lentos pra resolver fetch() dentro de
// page.evaluate() neste ambiente Windows, alargando o intervalo real entre uma
// chamada e a próxima o suficiente pra o timestamp calculado ficar fora da janela
// aceita. Isso é um artefato do RELÓGIO QUE O PRÓPRIO TESTE GERA, não uma falha de
// antifraude de verdade (que seria detectada por POR-VAL-006/007/008 — velocidade,
// gap ou distância implausíveis — esses NUNCA são retentados aqui, de propósito).
// Um retry único, recalculando o timestamp com base no relógio real no momento do
// reenvio, absorve esse ruído sem mascarar uma regressão genuína.
const CLOCK_ARTIFACT_REJECTION_CODES = new Set(['POR-VAL-005', 'POR-VAL-009']);

/**
 * Achado real por trás do "Falha ao registrar ponto de rastreamento"
 * residual (mesmo depois de corrigir o retry que colidia sequence): a
 * própria página guincho/atendimento.php tem um enviador de GPS automático em
 * background (iniciarGps() -> navigator.geolocation.watchPosition, throttle
 * de 10s, ver montarPontoPor()/enviarPontoPor()). Como os testes rodam com
 * geolocation MOCKADA (context com `geolocation` fixo), esse watcher da
 * própria página dispara e envia pontos pro MESMO endpoint
 * /guincho/localizacao, com seu PRÓPRIO contador de sequência
 * (`gpsSequence`), inteiramente alheio ao contador que o teste mantém em
 * moveTowAlongRoute/moveTowAlongRouteRealTime — duas fontes de verdade
 * competindo pela mesma sequência do mesmo pedido. Isso é real disputa de
 * concorrência, não flakiness de ambiente. Como o teste quer controle total
 * sobre os pontos/timestamps enviados (esse é o objetivo dos dois helpers),
 * pausamos o enviador automático da página antes de dirigir a rota
 * manualmente — pararGps() já existe no próprio atendimento.php.
 */
async function pararGpsAutomaticoDaPagina(page: Page): Promise<void> {
  await page.evaluate(() => {
    // @ts-ignore - função global definida no <script> de guincho/atendimento.php
    if (typeof pararGps === 'function') {
      // @ts-ignore
      pararGps();
    }
  }).catch(() => {
    // Página pode não ter esse script carregado (ex: outro fluxo) — não é fatal.
  });
}

export async function moveTowAlongRoute(page: Page, pedidoId: string, route: RoutePoint[], startSequence?: number): Promise<RoutePoint[]> {
  const pointIntervalMs = 120_000;
  await pararGpsAutomaticoDaPagina(page);
  const meta = await fetchCurrentPointMeta(page, pedidoId);
  let sequence = startSequence ?? (meta.sequence + 1);
  const accepted: RoutePoint[] = [];
  const endpointPath = new URL(appPath('/guincho/localizacao'), 'http://localhost').pathname;
  // Ancorado no MAIOR entre o último device_timestamp já gravado (evita
  // POR-VAL-009: timestamp <= prevTs — regressão introduzida ao usar só
  // Date.now(), que pode "voltar no tempo" em relação ao último ponto da
  // chamada anterior de moveTowAlongRoute, já que cada ponto avança +120s no
  // relógio simulado e pode ficar no "futuro" em relação ao Date.now() real
  // quando a próxima chamada começa poucos segundos depois) e o relógio real
  // (evita POR-VAL-005: timestamp velho demais, quando os passos anteriores
  // do teste — login, chat, seed via CLI — demoram bastante, especialmente
  // no gate completo com 150 testes serializados em 1 worker, ~25-30min).
  let simulatedTimestamp = Math.max(meta.deviceTimestampMs || 0, Date.now());

  for (const point of route) {
    simulatedTimestamp = Math.max(simulatedTimestamp + pointIntervalMs, Date.now());
    const result = await postTowLocation(page, pedidoId, point, sequence, endpointPath, simulatedTimestamp, 3);

    // Segunda tentativa de lidar com POR-VAL-005/009 (removida): reenviar a
    // MESMA coordenada minutos depois criava, ela mesma, dois pontos quase
    // idênticos e muito próximos no tempo — LocationValidationService compara
    // sempre contra o último ponto GRAVADO (aceito OU rejeitado, ver
    // ProofOfRoadService::ingestPoint -> buscarUltimoPorPedido), então o
    // retry disparava POR-VAL-008 (distância≤3m e elapsed≤15s, "parado") de
    // verdade — meu próprio retry estava CRIANDO a rejeição que eu queria
    // evitar. Correto é não insistir na mesma coordenada: uma rejeição isolada
    // por artefato de relógio do teste não é um bug de produção (o pedido
    // segue progredindo com os demais pontos aceitos); o que precisa continuar
    // fatal são POR-VAL-006/007/008 quando NÃO nascem de um retry nosso.
    if (result.ok && result.accepted === false && CLOCK_ARTIFACT_REJECTION_CODES.has((result as any).rejection_code)) {
      console.warn(`[QA] ponto ${sequence} rejeitado por artefato de relógio (${(result as any).rejection_code}), seguindo para o próximo ponto da rota.`);
      sequence += 1;
      await page.waitForTimeout(250);
      continue;
    }

    expect(result.ok, result.raw_text || result.reason || 'location update failed').toBeTruthy();
    expect(result.accepted, result.raw_text || result.reason || 'location rejected').not.toBe(false);
    accepted.push(point);
    sequence += 1;
    await page.waitForTimeout(250);
  }

  return accepted;
}

// ─────────────────────────────────────────────────────────────────────────
// Rota real (via OSRM, router.project-osrm.org) para o cenário de tempo
// real (qa/suites/atendimento-tempo-real.spec.ts). Diferente de
// atendimentoRoutePoints (5 waypoints esparsos, "atalho" entre esquinas
// distantes — bom o bastante pra testar a máquina de estados, mas insuficiente
// pra parecer uma rota de verdade no mapa e não serve pra validar tempo real),
// estas são amostras a cada ~160m ao longo da geometria real devolvida pelo
// OSRM entre dois pontos de São Paulo, ~1.8km cada trecho — dá uma trilha que
// realmente acompanha as ruas (Praça da Sé → Rua Senador Feijó → Rua
// Cristóvão Colombo → Viaduto/Av. Brigadeiro Luís Antônio → Av. 23 de Maio →
// Rua Maestro Cardim → Rua Monsenhor Passaláqua, e depois → Av. Brigadeiro
// Luís Antônio → Rua Treze de Maio → Rua Cincinato Braga).
export const realtimeApproachRoute: RoutePoint[] = [
  { lat: -23.550782, lng: -46.63388, street: 'Praça da Sé' },
  { lat: -23.550632, lng: -46.635091, street: 'Rua Senador Feijó' },
  { lat: -23.550133, lng: -46.636536, street: 'Rua Cristóvão Colombo' },
  { lat: -23.551297, lng: -46.637097, street: 'Viaduto Brigadeiro Luís Antônio' },
  { lat: -23.552726, lng: -46.637867, street: 'Avenida Brigadeiro Luís Antônio' },
  { lat: -23.553807, lng: -46.637222, street: 'Rua Asdrubal do Nascimento' },
  { lat: -23.555191, lng: -46.637304, street: 'Avenida Vinte e Três de Maio' },
  { lat: -23.556415, lng: -46.637818, street: 'Avenida Vinte e Três de Maio' },
  { lat: -23.557162, lng: -46.63814, street: 'Avenida Vinte e Três de Maio' },
  { lat: -23.559175, lng: -46.639144, street: 'Rua Maestro Cardim' },
  { lat: -23.560324, lng: -46.639981, street: 'Rua Maestro Cardim' },
  { lat: -23.560882, lng: -46.640469, street: 'Rua Monsenhor Passaláqua' },
  { lat: -23.56064, lng: -46.641541, street: 'Rua Monsenhor Passaláqua' }
];

export const realtimeDeliveryRoute: RoutePoint[] = [
  { lat: -23.56064, lng: -46.641541, street: 'Rua Monsenhor Passaláqua' },
  { lat: -23.560036, lng: -46.642875, street: 'Rua Monsenhor Passaláqua' },
  { lat: -23.560966, lng: -46.643818, street: 'Avenida Brigadeiro Luís Antônio' },
  { lat: -23.562159, lng: -46.644831, street: 'Avenida Brigadeiro Luís Antônio' },
  { lat: -23.563183, lng: -46.645861, street: 'Rua Treze de Maio' },
  { lat: -23.564542, lng: -46.645385, street: 'Rua Treze de Maio' },
  { lat: -23.565742, lng: -46.644937, street: 'Rua 13 de Maio' },
  { lat: -23.566102, lng: -46.644821, street: 'Rua 13 de Maio' },
  { lat: -23.568662, lng: -46.644065, street: 'Rua 13 de Maio' },
  { lat: -23.570097, lng: -46.643942, street: 'Rua Cincinato Braga' },
  { lat: -23.569356, lng: -46.644835, street: 'Rua Cincinato Braga' },
  { lat: -23.568304, lng: -46.646114, street: 'Rua Cincinato Braga' }
];

export const realtimeExpectedStreetPattern = /Praça da Sé|Rua Senador Feijó|Rua Cristóvão Colombo|Brigadeiro Luís Antônio|Asdrubal do Nascimento|Vinte e Três de Maio|Rua Maestro Cardim|Monsenhor Passaláqua|Treze de Maio|13 de Maio|Cincinato Braga/i;

/**
 * Percorre a rota em TEMPO REAL: device_timestamp é sempre Date.now() no
 * momento exato do envio (zero manipulação), e há uma espera real
 * (page.waitForTimeout) entre pontos consecutivos. Ao contrário de
 * moveTowAlongRoute (que simula 120s "de relógio" por ponto em ~250ms de
 * teste real, útil pra testar rápido a máquina de estados mas incapaz de
 * pegar uma regressão no antifraude de velocidade/tempo), esta função
 * exercita LocationValidationService::validatePoint com dados 100% reais:
 * se o antifraude passar a aceitar, por exemplo, uma "viagem" de 50km
 * concluída em minutos, é aqui que isso apareceria.
 */
/**
 * Achado real (RJ-TOW-001/002, ambiente Windows/XAMPP, ver conversa de QA):
 * POR-VAL-007 ("gap grande demais desde o último ponto gravado") apareceu de
 * forma ISOLADA no meio de uma rota normal, sem ter vindo de nenhuma queda de
 * sinal proposital — ou seja, é sensível à latência real do ambiente (upload
 * de foto de evidência, Apache/MySQL do XAMPP sob carga, etc.), não só a
 * gaps que o teste cria de propósito. Isso é "degradação controlada": em
 * produção, ProofOfRoadService::ingestPoint grava o ponto mesmo rejeitado
 * (is_valid=0) e ele vira a nova referência de "último ponto" — o PRÓXIMO
 * ponto já volta a ter elapsed normal — e o atendimento em si (pedido,
 * status, chat) nunca dependeu de nenhum ponto de localização específico ser
 * aceito. Uma rejeição ISOLADA de POR-VAL-007 não deve derrubar o teste.
 *
 * O que continua fatal (proposital, é o antifraude de verdade que este teste
 * existe pra proteger): POR-VAL-006 (velocidade implausível) e POR-VAL-008
 * ("parado") sempre; e POR-VAL-007 quando se repete em pontos CONSECUTIVOS
 * (2+ seguidos) — isso deixaria de ser ruído de ambiente e passaria a
 * indicar rastreamento realmente quebrado.
 */
const CONSECUTIVE_POR_VAL_007_LIMIT = 1;

export async function moveTowAlongRouteRealTime(
  page: Page,
  pedidoId: string,
  route: RoutePoint[],
  intervalMs = 10_000,
  startSequence?: number,
  logicalTimestampStepMs?: number
): Promise<RoutePoint[]> {
  await pararGpsAutomaticoDaPagina(page);
  const meta = await fetchCurrentPointMeta(page, pedidoId);
  let sequence = startSequence ?? (meta.sequence + 1);
  const accepted: RoutePoint[] = [];
  const endpointPath = new URL(appPath('/guincho/localizacao'), 'http://localhost').pathname;
  let consecutiveGapRejections = 0;
  const MAX_NAV_RETRIES_PER_POINT = 3;

  // Quando o chamador passa logicalTimestampStepMs (ver driveRoute em
  // gps-simulator.ts), o device_timestamp deixa de ser Date.now() (relógio
  // real, que em modo acelerado avança quase nada entre pontos) e passa a
  // ser um relógio SIMULADO que avança a cada ponto — assim a rota continua
  // rodando rápido no computador, mas a distância/tempo que chega no
  // antifraude corresponde a uma velocidade real plausível, sem disparar
  // POR-VAL-006. Sem o parâmetro, o comportamento é 100% o mesmo de antes
  // (Date.now() a cada ponto) — não regride nenhum spec existente.
  let simulatedTimestamp = Math.max(meta.deviceTimestampMs || 0, Date.now());

  for (let i = 0; i < route.length; i++) {
    if (i > 0) {
      await page.waitForTimeout(intervalMs);
    }
    const point = route[i];

    let deviceTimestampForPoint: number;
    if (logicalTimestampStepMs) {
      // Achado real (POR-COLISAO-14pts/15): distribuir o tempo lógico
      // igualmente por ponto (logicalTimestampStepMs fixo) assume rotas com
      // trechos de tamanho parecido — falso para rotas amostradas via OSRM,
      // onde o ÚLTIMO segmento pode ser bem maior que os outros. Com o
      // mesmo passo "médio" pra um trecho maior, distance_m/elapsed_s
      // ultrapassa PorThresholds::maxSpeedKmh() (130 km/h) e o backend
      // rejeita com POR-VAL-006 — corretamente, porque de fato seria uma
      // velocidade implausível SE o tempo fosse mesmo aquele. A correção é
      // calcular o tempo mínimo NECESSÁRIO pra este trecho específico
      // (distância real do segmento / velocidade máxima segura) e só usar
      // esse valor quando ele for MAIOR que o passo uniforme — ou seja, só
      // alonga os trechos que realmente precisam (tipicamente o último de
      // uma amostragem OSRM), sem mexer no antifraude do backend nem no
      // tempo total dos trechos que já eram plausíveis.
      let step = logicalTimestampStepMs;
      if (i > 0) {
        const distanciaSegmentoM = haversineMetros(route[i - 1], point);
        const tempoMinimoSeguroMs = (distanciaSegmentoM / (POR_SAFE_MAX_SPEED_KMH / 3.6)) * 1000;
        if (tempoMinimoSeguroMs > step) {
          console.warn(
            `[QA] trecho ${i} (${distanciaSegmentoM.toFixed(0)}m) precisaria de mais tempo lógico que o passo uniforme ` +
            `(${step.toFixed(0)}ms) pra não ultrapassar ${POR_SAFE_MAX_SPEED_KMH}km/h — usando ${tempoMinimoSeguroMs.toFixed(0)}ms só para este trecho.`
          );
          step = tempoMinimoSeguroMs;
        }
      }
      simulatedTimestamp += step;
      deviceTimestampForPoint = simulatedTimestamp;
    } else {
      deviceTimestampForPoint = Date.now();
    }

    // §POR-QA-NAV-01: um reload disparado pelo SSE (status_update) pode
    // destruir o contexto de execução no meio do fetch (ver comentário em
    // postTowLocation). Em vez de deixar isso travar o teste inteiro até o
    // timeout global, detectamos o "evaluate_timeout_possible_navigation",
    // esperamos a página assentar de novo e reenviamos o MESMO ponto (sem
    // avançar sequence/accepted), com um limite de tentativas por ponto.
    // Reenviamos com o MESMO deviceTimestampForPoint (não recalculamos) —
    // é o mesmo ponto de rota sendo reenviado, não um novo ponto.
    let result = await postTowLocation(page, pedidoId, point, sequence, endpointPath, deviceTimestampForPoint, 6);
    let navRetries = 0;
    while (result.reason === 'evaluate_timeout_possible_navigation' && navRetries < MAX_NAV_RETRIES_PER_POINT) {
      navRetries += 1;
      console.warn(`[QA] ponto ${sequence}: page.evaluate não respondeu a tempo (possível reload disparado por SSE status_update) — tentativa ${navRetries}/${MAX_NAV_RETRIES_PER_POINT} após aguardar a página assentar.`);
      await page.waitForLoadState('domcontentloaded').catch(() => {});
      await page.waitForTimeout(500);
      result = await postTowLocation(page, pedidoId, point, sequence, endpointPath, deviceTimestampForPoint, 6);
    }

    // Achado real (30/07/2026, retry automático de RJ-COLISAO-001): entre a
    // aproximação e o reboque, os passos "confirmar chegada" e "evidência de
    // coleta" fazem a própria página guincho/atendimento.php recarregar
    // (SSE status_update — ver §POR-QA-NAV-01). Um reload reinicia TODO o JS
    // da página, inclusive o enviador automático de GPS em background
    // (iniciarGps()/watchPosition, ver comentário grande acima de
    // pararGpsAutomaticoDaPagina) — que volta a rodar até a PRÓXIMA chamada
    // de moveTowAlongRouteRealTime (a do reboque) pausá-lo de novo. Nesse
    // intervalo ele pode disparar pelo menos um POST próprio pro mesmo
    // pedido, "roubando" um número de sequência que o nosso driveRoute do
    // reboque ainda não sabia que estava ocupado (fetchCurrentPointMeta só é
    // consultado uma vez, no INÍCIO desta função) — daí o backend rejeitar
    // com "Falha ao registrar ponto de rastreamento." (Duplicate entry pra
    // uk_pedido_sequence, ver ProofOfRoadService::ingestPoint POR-ING-002).
    // Isso NÃO é o pedido ficando com estado corrompido nem é o antifraude
    // falhando — é o NOSSO contador de sequência local ficando desatualizado
    // por causa do emissor automático da própria página. Resolvido do mesmo
    // jeito que o resto desta função já resolve corridas parecidas: pausar o
    // emissor de novo, reconsultar a sequência REAL no servidor, e reenviar
    // o mesmo ponto com o número corrigido — nunca inventando um número,
    // sempre perguntando ao servidor qual é o próximo real.
    const MAX_SEQUENCE_RETRIES_PER_POINT = 3;
    let sequenceRetries = 0;
    while (
      (
        (result.ok === false &&
          (result as any).rejection_code === undefined &&
          result.reason !== 'evaluate_timeout_possible_navigation')
        // Bug real (STRESS-CHAOS-001, 31/07/2026): depois que o backend
        // passou a resolver a colisão de uk_pedido_sequence via fallback
        // idempotente (ProofOfRoadService::ingestPoint ->
        // buscarPorPedidoSequence), essa mesma corrida contra o emissor
        // automático da página (reiniciado pelo reload) passou a responder
        // `ok:true, accepted:false, idempotent_retry:true` em vez de
        // `ok:false` — resposta correta do backend (não duplicou nada), mas
        // é o PONTO DO OUTRO EMISSOR que ocupou essa sequência, não o nosso.
        // Sem tratar esse caso aqui, a asserção final via como se nosso
        // próprio ponto tivesse sido rejeitado de verdade.
        || (result.ok === true && (result as any).idempotent_retry === true)
      ) &&
      sequenceRetries < MAX_SEQUENCE_RETRIES_PER_POINT
    ) {
      sequenceRetries += 1;
      console.warn(
        `[QA] ponto ${sequence}: ${(result as any).idempotent_retry ? 'sequência já ocupada por outro emissor (resposta idempotente do backend)' : `falha genérica de ingestão ("${result.reason || result.raw_text || 'erro desconhecido'}")`} — ` +
        `provável colisão de sequência com o enviador automático da própria página após um reload. ` +
        `Pausando o enviador, reconsultando a sequência real no servidor e reenviando (tentativa ${sequenceRetries}/${MAX_SEQUENCE_RETRIES_PER_POINT}).`
      );
      await pararGpsAutomaticoDaPagina(page);
      const metaAtualizada = await fetchCurrentPointMeta(page, pedidoId);
      sequence = Math.max(sequence, metaAtualizada.sequence + 1);
      result = await postTowLocation(page, pedidoId, point, sequence, endpointPath, deviceTimestampForPoint, 6);
    }

    const rejectionCode = (result as any).rejection_code;

    // Ver comentário completo acima: reenviar a MESMA coordenada logo em
    // seguida (retry) criava, ele mesmo, um par de pontos quase idênticos e
    // próximos no tempo — o que o antifraude via LocationValidationService
    // interpreta (corretamente) como POR-VAL-008 ("parado"), já que ele
    // compara contra o último ponto GRAVADO, aceito ou rejeitado. Uma
    // rejeição isolada por artefato de relógio do teste (POR-VAL-005/009)
    // não é um bug de produção; o certo é seguir para o próximo ponto real
    // da rota em vez de insistir na mesma coordenada.
    if (result.ok && result.accepted === false && CLOCK_ARTIFACT_REJECTION_CODES.has(rejectionCode)) {
      console.warn(`[QA] ponto ${sequence} rejeitado por artefato de relógio (${rejectionCode}), seguindo para o próximo ponto da rota.`);
      sequence += 1;
      continue;
    }

    if (result.ok && result.accepted === false && rejectionCode === 'POR-VAL-007') {
      consecutiveGapRejections += 1;
      if (consecutiveGapRejections <= CONSECUTIVE_POR_VAL_007_LIMIT) {
        console.warn(`[QA] ponto ${sequence} rejeitado por POR-VAL-007 (gap isolado, ambiente/latência real) — degradação controlada, seguindo para o próximo ponto.`);
        sequence += 1;
        continue;
      }
      console.error(`[QA] ponto ${sequence} rejeitado por POR-VAL-007 pela ${consecutiveGapRejections}ª vez CONSECUTIVA — isso já indica rastreamento quebrado, não ruído de ambiente.`);
    } else {
      consecutiveGapRejections = 0;
    }

    expect(result.ok, result.raw_text || result.reason || 'location update failed').toBeTruthy();
    expect(result.accepted, result.raw_text || result.reason || 'location rejected').not.toBe(false);
    accepted.push(point);
    sequence += 1;
  }

  return accepted;
}

// ─────────────────────────────────────────────────────────────────────────
// Rotas reais do Rio de Janeiro (via OSRM) para os cenários RJ-TOW-001/002
// (qa/suites/atendimento-rj-tempo-real.spec.ts). Todas as três rotas foram
// amostradas (a cada 100m) de UM ÚNICO trecho contínuo da Avenida Ayrton
// Senna (Barra da Tijuca), emendadas na mesma origem/destino, para que as
// distâncias pedidas batam com precisão real de roteamento (não linha reta):
//   especialista -> origem: 1000m | guincho -> origem: 700m | origem -> destino: 1200m
// (ver /tmp/build_rj_route*.py no histórico da sessão que gerou estes pontos).
export const rjEspecialistaApproachRoute: RoutePoint[] = [
  { lat: -22.999814, lng: -43.365498, street: 'Avenida Ayrton Senna' },
  { lat: -22.9998, lng: -43.364521, street: 'Avenida Ayrton Senna' },
  { lat: -22.999838, lng: -43.363914, street: 'Avenida Ayrton Senna' },
  { lat: -22.999862, lng: -43.36489, street: 'Avenida Ayrton Senna' },
  { lat: -22.999896, lng: -43.365866, street: 'Avenida Ayrton Senna' },
  { lat: -22.999916, lng: -43.366842, street: 'Avenida Ayrton Senna' },
  { lat: -22.999894, lng: -43.367819, street: 'Avenida Ayrton Senna' },
  { lat: -22.999607, lng: -43.368434, street: 'Avenida Ayrton Senna' },
  { lat: -22.998918, lng: -43.367849, street: 'Avenida Ayrton Senna' },
  { lat: -22.998286, lng: -43.367155, street: 'Avenida Ayrton Senna' },
  { lat: -22.997682, lng: -43.36643, street: 'Avenida Ayrton Senna' }
];

export const rjGuinchoApproachRoute: RoutePoint[] = [
  { lat: -22.999862, lng: -43.36489, street: 'Avenida Ayrton Senna' },
  { lat: -22.999896, lng: -43.365866, street: 'Avenida Ayrton Senna' },
  { lat: -22.999916, lng: -43.366842, street: 'Avenida Ayrton Senna' },
  { lat: -22.999894, lng: -43.367819, street: 'Avenida Ayrton Senna' },
  { lat: -22.999607, lng: -43.368434, street: 'Avenida Ayrton Senna' },
  { lat: -22.998918, lng: -43.367849, street: 'Avenida Ayrton Senna' },
  { lat: -22.998286, lng: -43.367155, street: 'Avenida Ayrton Senna' },
  { lat: -22.997682, lng: -43.36643, street: 'Avenida Ayrton Senna' }
];

export const rjDeliveryRoute: RoutePoint[] = [
  { lat: -22.997682, lng: -43.36643, street: 'Avenida Ayrton Senna' },
  { lat: -22.997306, lng: -43.36563, street: 'Avenida Ayrton Senna' },
  { lat: -22.996413, lng: -43.365712, street: 'Avenida Ayrton Senna' },
  { lat: -22.995515, lng: -43.365697, street: 'Avenida Ayrton Senna' },
  { lat: -22.994615, lng: -43.365688, street: 'Avenida Ayrton Senna' },
  { lat: -22.993716, lng: -43.365679, street: 'Avenida Ayrton Senna' },
  { lat: -22.992817, lng: -43.36567, street: 'Avenida Ayrton Senna' },
  { lat: -22.991918, lng: -43.365661, street: 'Avenida Ayrton Senna' },
  { lat: -22.991018, lng: -43.36565, street: 'Avenida Ayrton Senna' },
  { lat: -22.990119, lng: -43.365635, street: 'Avenida Ayrton Senna' },
  { lat: -22.98922, lng: -43.36562, street: 'Avenida Ayrton Senna' },
  { lat: -22.988321, lng: -43.365606, street: 'Avenida Ayrton Senna' },
  { lat: -22.987421, lng: -43.36559, street: 'Avenida Ayrton Senna' }
];

export const rjExpectedStreetPattern = /Avenida Ayrton Senna/i;

/**
 * Igual a moveTowAlongRouteRealTime, mas insere UMA queda de sinal real no
 * meio da rota: para de enviar pontos por `gapMs` (sem device_timestamp
 * acelerado, é espera real do teste) exatamente depois de `gapAfterIndex`
 * pontos. Existe para provar, em corrida real, que a resiliência de GPS
 * implementada em public/assets/js/core/gps-resilience.js e a projeção
 * (dead reckoning) de cliente/pedidostatus.php realmente entram em ação:
 * o teste assere no meio do gap que o cliente passa a mostrar "posição
 * estimada" (ver #rotaFrescor) e que isso some assim que os pontos reais
 * voltam a chegar.
 */
export async function moveTowAlongRouteRealTimeComQueda(
  page: Page,
  pedidoId: string,
  route: RoutePoint[],
  intervalMs: number,
  gapAfterIndex: number,
  gapMs: number,
  onGap?: () => Promise<void>
): Promise<RoutePoint[]> {
  const before = route.slice(0, gapAfterIndex + 1);
  const after = route.slice(gapAfterIndex + 1);

  const acceptedBefore = await moveTowAlongRouteRealTime(page, pedidoId, before, intervalMs);

  if (onGap) {
    await page.waitForTimeout(gapMs);
    await onGap();
  } else {
    await page.waitForTimeout(gapMs);
  }

  if (after.length === 0) {
    return acceptedBefore;
  }

  // Sem startSequence explícito: moveTowAlongRouteRealTime busca a sequência
  // atual direto do servidor (fetchCurrentPointMeta), o que é mais confiável
  // do que contar localmente (algum ponto do trecho "before" pode ter sido
  // descartado por artefato de relógio — ver CLOCK_ARTIFACT_REJECTION_CODES).
  // O 1º ponto ao retomar pode legitimamente cair em POR-VAL-007 por causa do
  // gap que ACABAMOS de criar de propósito — moveTowAlongRouteRealTime já
  // tolera isso (rejeição isolada de POR-VAL-007), sem parâmetro extra.
  const acceptedAfter = await moveTowAlongRouteRealTime(page, pedidoId, after, intervalMs);
  return [...acceptedBefore, ...acceptedAfter];
}

export async function submitStatusWithOptionalImage(page: Page, filePath?: string): Promise<void> {
  const fileInput = page.locator('#statusForm input[type="file"]').first();
  if (await fileInput.count()) {
    await fileInput.setInputFiles(filePath || resolveEvidenceImage());
  }

  await page.locator('#statusForm').evaluate((form: HTMLFormElement) => {
    form.scrollIntoView({ block: 'center' });
  });
  await page.locator('#btnAtualizarStatus').click();
}
