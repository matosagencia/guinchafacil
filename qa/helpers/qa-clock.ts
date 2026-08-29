// File: guinchafacil/qa/helpers/qa-clock.ts
//
// Resolve o problema real que derrubou E2E-SOCORRO-001 por timeout (18min):
// os prazos de negócio (ex.: "5 minutos para aceitar", "10 minutos para
// chegar") não podem, por padrão, virar espera REAL de 15+ minutos em todo
// gate — mas a homologação temporal de verdade (Admin Health / simulado
// oficial, ver constituição do projeto) precisa poder rodar em tempo real
// quando isso importa. Dois modos, controlados por env var:
//
//   QA_TIME_MODE=accelerated (padrão) + QA_TIME_SCALE=0.02
//     -> 5 minutos lógicos viram ~6 segundos reais
//
//   QA_TIME_MODE=realtime (+ QA_TIME_SCALE ignorado, sempre 1)
//     -> 5 minutos lógicos viram 5 minutos reais de verdade
//
// A lógica de negócio (prazos reais gravados no banco, cron de expiração
// etc.) não muda nada — só o tempo que O TESTE espera antes de agir.

export type QaTimeMode = 'accelerated' | 'realtime';

export function qaTimeMode(): QaTimeMode {
  return process.env.QA_TIME_MODE === 'realtime' ? 'realtime' : 'accelerated';
}

export function qaTimeScale(): number {
  if (qaTimeMode() === 'realtime') {
    return 1;
  }

  const configured = Number(process.env.QA_TIME_SCALE ?? '0.02');

  if (!Number.isFinite(configured) || configured <= 0) {
    throw new Error(`[QaClock][qaTimeScale] QA_TIME_SCALE inválido: ${process.env.QA_TIME_SCALE}`);
  }

  return configured;
}

/**
 * Converte minutos "lógicos" (de negócio) em milissegundos reais de espera,
 * já aplicando o modo/escala atual. Piso de 100ms para não zerar esperas
 * curtas em modo acelerado (evita race condition com o próprio backend
 * ainda processando a escrita anterior).
 */
export function qaMinutes(minutes: number): number {
  return Math.max(100, Math.round(minutes * 60_000 * qaTimeScale()));
}

/**
 * Espera `minutes` minutos lógicos (acelerados ou reais, conforme
 * QA_TIME_MODE), logando o motivo e a conversão real aplicada — essencial
 * pro diagnóstico "onde o teste está agora" em specs longos (ver
 * step-logger.ts, que usa o mesmo princípio de log estruturado).
 */
export async function waitQaMinutes(minutes: number, reason: string): Promise<void> {
  const ms = qaMinutes(minutes);

  // eslint-disable-next-line no-console
  console.log(`[QaClock][waitQaMinutes] reason="${reason}" logical=${minutes}min real=${ms}ms mode=${qaTimeMode()}`);

  await new Promise((resolve) => setTimeout(resolve, ms));
}
