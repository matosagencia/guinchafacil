// File: guinchafacil/qa/helpers/capabilities.ts
//
// Fecha o gap real apontado na revisão de 30/07/2026: o onboarding-stress
// existente cria contas multisserviço/especialista, mas NUNCA passa pela
// tela real de "quais serviços você oferece" (GuinchoController::capacidades
// -> ProviderCapability::declarar, sempre PENDING) nem pela fila de
// aprovação do admin (AdminServiceCatalogController::capacidades ->
// ProviderCapability::aprovar). Sem isso, o matching por capacidade
// (ProviderCapability::possuiCapacidadeAprovada, usado tanto em
// GuinchoController::montarOfertasDisponiveis quanto em aceitarForm/aceitar)
// nunca é exercitado pela UI de verdade — só via seed direto no banco.
//
// Todos os seletores abaixo foram conferidos direto em:
//   - src/Views/guincho/capacidades.php (form: .card por serviço, checkbox
//     "service_type_id[]", botão "Enviar para análise")
//   - src/Views/admin/catalogo_servicos_capacidades.php (tabela: <tr> por
//     capacidade, botão title="Aprovar")
//   - src/Views/admin/guinchospendentes.php (cards ".pendente-item", form
//     action="/admin/guincho/aprovar")
//   - src/Controllers/GuinchoController.php::aceitarForm (redireciona pro
//     dashboard se a capacidade não estiver aprovada — é o próprio gate de
//     matching, não uma simulação dele)

import { type Page, expect } from '@playwright/test';
import { appPath } from './paths';

/**
 * Nome real exibido em /guincho/capacidades (src/Views/guincho/capacidades.php,
 * que mostra service_types.name) para cada código do catálogo — conferido
 * direto em install/migration_service_catalog_v1.sql. Só os serviços
 * ON_SITE fazem sentido aqui: TOW_CAR/TOW_MOTORCYCLE/TOW_UTILITY (reboque)
 * são aprovados via guinchos.reboque_aprovado, não via provider_capabilities
 * — não é o alvo desta homologação de capacidades.
 */
export const SERVICE_DISPLAY_NAME: Record<string, string> = {
  MECHANICAL_ASSISTANCE: 'Socorro Mecânico Emergencial',
  JUMP_START: 'Partida Auxiliar',
  BATTERY_TEST: 'Teste de Bateria',
  BATTERY_REPLACEMENT: 'Troca de Bateria',
  ELECTRICAL_DIAGNOSIS: 'Diagnóstico de Pane Elétrica',
  TIRE_CHANGE: 'Troca de Pneu',
  TIRE_INFLATION: 'Calibragem de Pneu',
  AUTOMOTIVE_LOCKSMITH: 'Chaveiro Automotivo',
};

/**
 * Aprovação da CONTA do guincho (cadastro em si) — anterior e independente
 * da aprovação de capacidades de serviço. Sem isso o guincho nem aparece
 * como elegível em lugar nenhum (Guincho::aprovado = 0).
 */
export async function aprovarContaGuinchoAdmin(adminPage: Page, emailOuNome: string): Promise<void> {
  await adminPage.goto(appPath('/admin/guinchospendentes'), { waitUntil: 'domcontentloaded' });
  const card = adminPage.locator('.pendente-item', { hasText: emailOuNome });
  const existe = await card.count();
  if (existe === 0) {
    // Idempotente: se já não está mais pendente, assume que já foi aprovado
    // (reexecução do teste ou aprovação anterior no mesmo run).
    return;
  }
  await Promise.all([
    adminPage.waitForURL(/\/admin\/guinchos(\?|$)/i, { timeout: 30000 }),
    card.first().locator('form[action*="/admin/guincho/aprovar"] button').click(),
  ]);
}

/**
 * Prestador declara, via UI real, quais serviços (pelo NOME exibido na
 * tela, ex.: "Partida auxiliar", "Troca de pneu") ele quer oferecer. Nasce
 * sempre PENDING (ProviderCapability::declarar) — nunca fica aprovado só
 * por isso.
 */
export async function declararCapacidadesReal(page: Page, nomesServico: string[]): Promise<void> {
  await page.goto(appPath('/guincho/capacidades'), { waitUntil: 'domcontentloaded' });
  for (const nome of nomesServico) {
    const card = page.locator('.card', { hasText: nome });
    await expect(card.first(), `serviço "${nome}" não encontrado em /guincho/capacidades`).toBeVisible({ timeout: 10000 });
    const checkbox = card.first().locator('input[type="checkbox"]');
    if (!(await checkbox.isChecked())) {
      await checkbox.check();
    }
  }
  await Promise.all([
    page.waitForURL(/\/guincho\/capacidades$/i, { timeout: 30000 }),
    page.locator('button[type="submit"]', { hasText: 'Enviar para análise' }).click(),
  ]);
  await expect(page.locator('body')).toContainText(/enviadas para an.lise/i);
}

/**
 * Admin aprova, via UI real (fila de homologação), todas as capacidades
 * PENDENTES de um prestador identificado por nome/e-mail (a coluna
 * "Prestador" da tabela mostra o nome). Pode haver mais de uma linha
 * (uma por serviço declarado) — aprova todas em sequência.
 */
export async function aprovarCapacidadesAdmin(adminPage: Page, prestadorNomeOuEmail: string): Promise<void> {
  await adminPage.goto(appPath('/admin/catalogo-servicos/capacidades'), { waitUntil: 'domcontentloaded' });
  let linha = adminPage.locator('tr', { hasText: prestadorNomeOuEmail }).filter({ hasText: 'Pendente' });
  let restantes = await linha.count();
  let voltas = 0;
  while (restantes > 0 && voltas < 20) {
    await Promise.all([
      adminPage.waitForLoadState('domcontentloaded'),
      linha.first().locator('button[title="Aprovar"]').click(),
    ]);
    linha = adminPage.locator('tr', { hasText: prestadorNomeOuEmail }).filter({ hasText: 'Pendente' });
    restantes = await linha.count();
    voltas++;
  }
  expect(restantes, `ainda restam ${restantes} capacidade(s) PENDENTE para "${prestadorNomeOuEmail}" após ${voltas} tentativas de aprovação`).toBe(0);
}

/**
 * Prova de matching real (não seed, não asserção de banco): navega direto
 * para a tela de aceite do pedido. GuinchoController::aceitarForm redireciona
 * pro dashboard se a capacidade não estiver aprovada para este service_type
 * — é o MESMO código que decide o matching em produção, não uma simulação.
 * Retorna true se a tela de aceite realmente abriu (capacidade reconhecida).
 */
export async function podeAcessarTelaDeAceite(page: Page, pedidoId: string | number): Promise<boolean> {
  await page.goto(appPath(`/guincho/aceitar/${pedidoId}`), { waitUntil: 'domcontentloaded' });
  return !/\/guincho\/dashboard(\?|$)/i.test(page.url());
}

/**
 * Aceite real do pedido (POST /guincho/aceitar/{id} via o único botão
 * "Aceitar" do form em pedidoaceitar.php) — assume que a tela de aceite já
 * está acessível (ver podeAcessarTelaDeAceite). Usa
 * PedidoTransitionService::acceptByGuincho por baixo, o mesmo código do
 * aceite em produção.
 */
export async function aceitarPedidoReal(page: Page, pedidoId: string | number): Promise<void> {
  await Promise.all([
    page.waitForURL(new RegExp(`/guincho/atendimento/${pedidoId}`), { timeout: 20000 }),
    page.locator(`form[action*="/guincho/aceitar/${pedidoId}"] button[type="submit"]`).click(),
  ]);
}
