// File: guinchafacil/qa/helpers/evidence.ts
//
// Nomes de campo conferidos direto em src/Views/guincho/atendimento.php e em
// GuinchoController::atualizarStatus (linhas ~903-925): a transição
// a_caminho -> no_local ("chegada") NÃO exige foto — só geofence (ver
// PedidoTransitionService::validatePreconditions). Evidência com foto só é
// exigida em duas transições:
//   em_reboque  -> campo de upload `foto_plataforma` ("coleta")
//   concluido   -> campo de upload `foto_destino`     ("entrega")
// O nonce (`evidence_token`) é renovado automaticamente pelo próprio
// atendimento.php antes de cada submit (ver comentário "Bug real" na função
// de submit do #statusForm) — não precisa ser buscado aqui de novo.
//
// Este arquivo só dá nomes claros ao que já existe em
// submitStatusWithOptionalImage (atendimento.ts), evitando repetir a lógica
// grande de nonce/csrf/multipart em cada spec novo.

import type { Page } from '@playwright/test';
import { submitStatusWithOptionalImage, resolveEvidenceImage } from './atendimento';

/** a_caminho -> no_local: sem foto, só clique (bloqueável por geofence). */
export async function confirmarChegada(page: Page): Promise<void> {
  await submitStatusWithOptionalImage(page);
}

/** no_local -> em_reboque: exige foto_plataforma (evidência de coleta). */
export async function enviarEvidenciaColeta(page: Page, imagePath?: string): Promise<void> {
  await submitStatusWithOptionalImage(page, imagePath || resolveEvidenceImage());
}

/** em_reboque -> concluido: exige foto_destino (evidência de entrega). */
export async function enviarEvidenciaEntrega(page: Page, imagePath?: string): Promise<void> {
  await submitStatusWithOptionalImage(page, imagePath || resolveEvidenceImage());
}
