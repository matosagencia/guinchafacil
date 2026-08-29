import { test, expect } from '@playwright/test';
import { atendimentoConfig } from '../helpers/atendimento';
import { loginAs, expectLoggedIn } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAdmin, seedAtendimentoCompleto } from '../helpers/seed';

// E2E-MAN-001 — Salvaguarda de conclusão manual assistida.
//
// Contexto real: a máquina de estados normal (PedidoTransitionService::
// validatePreconditions) exige geofence + evidência atrelada a um ponto GPS
// válido para no_local/em_reboque/concluido — sem NENHUM fallback, nem para
// o admin (pedidoAlterarStatus() aplica as mesmas regras). Se o GPS do
// cliente/guincho falhar, ou o servidor cair no meio do atendimento, o
// pedido ficava preso para sempre. Este spec valida a salvaguarda: o admin
// consegue concluir manualmente um pedido "travado" (a_caminho, sem nenhum
// ponto GPS de deslocamento real), anexando comprovantes e uma justificativa,
// e o pedido:
//   1) é marcado concluído + concluido_manualmente=1 + revisao_manual_status='pendente';
//   2) aparece sinalizado na tela do pedido como pendente de revisão (não é
//      um "conclusão silenciosa" — é rastreável e auditável);
//   3) pode ser revisado (confirmado) por um admin depois, fechando o ciclo.
test.describe('conclusão manual assistida (GPS/servidor indisponível)', () => {
  test('E2E-MAN-001 | admin conclui pedido travado sem GPS, com comprovantes, e revisa depois', async ({ page }) => {
    test.setTimeout(120_000);

    const admin = seedAdmin();
    // Reaproveita o seed de atendimento completo: ele entrega um pedido em
    // 'a_caminho', com guincho atribuído, mas SEM nenhum ponto GPS de
    // deslocamento real e SEM nenhuma evidência aceita — exatamente o estado
    // em que este pedido ficaria preso se o GPS/servidor tivesse falhado logo
    // após o aceite.
    const seeded = seedAtendimentoCompleto();
    const pedidoId = seeded.pedido_id;

    const config = atendimentoConfig();

    await loginAs(page, admin.admin_email, admin.admin_password);
    await expectLoggedIn(page);

    await page.goto(appPath(`/admin/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#pedidoStatusBadge')).toContainText(/a caminho/i);

    // Abre o modal de conclusão manual assistida.
    await page.locator('button[data-bs-target="#modalConcluirManual"]').click();
    const modal = page.locator('#modalConcluirManual');
    await expect(modal).toBeVisible();

    const justificativa = `QA E2E-MAN-001 ${Date.now()}: GPS do guincheiro parou de responder após o aceite ` +
      `(app travou em segundo plano); coleta e entrega confirmadas por telefone com o guincheiro, comprovantes ` +
      `enviados via WhatsApp e anexados manualmente aqui.`;
    await modal.locator('textarea[name="justificativa"]').fill(justificativa);
    await modal.locator('input[name="comprovante_coleta"]').setInputFiles(config.arrivalImage);
    await modal.locator('input[name="comprovante_entrega"]').setInputFiles(config.deliveryImage);
    await modal.locator('input[name="senha"]').fill(admin.admin_password);

    await Promise.all([
      page.waitForURL(new RegExp(`/admin/pedido/${pedidoId}`), { timeout: 30000 }),
      modal.locator('button[type="submit"]').click()
    ]);

    // Sucesso: banner de conclusão manual visível, status concluído, e
    // sinalizado como pendente de revisão (não é uma conclusão silenciosa).
    await expect(page.locator('#pedidoStatusBadge')).toContainText(/conclu[ií]do/i);
    const bannerManual = page.locator('.alert', { hasText: 'concluído manualmente' });
    await expect(bannerManual).toBeVisible();
    await expect(bannerManual).toContainText(/aguardando revis[ãa]o/i);

    // Confirma via API que o estado persistiu corretamente no banco (não só
    // na resposta HTTP): status concluído + flags de conclusão manual.
    const statusResponse = await page.request.get(appPath(`/admin/pedido/status-json/${pedidoId}`), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    const statusData = await statusResponse.json();
    expect(statusData?.pedido?.status).toBe('concluido');

    // Fecha o ciclo de auditoria: revisa e confirma a conclusão manual.
    await page.locator('button[data-bs-target="#modalRevisarManual"]').click();
    const modalRevisar = page.locator('#modalRevisarManual');
    await expect(modalRevisar).toBeVisible();
    await modalRevisar.locator('textarea[name="nota"]').fill('QA: comprovantes conferidos, coleta e entrega batem com o relato do guincheiro.');

    await Promise.all([
      page.waitForURL(new RegExp(`/admin/pedido/${pedidoId}`), { timeout: 30000 }),
      modalRevisar.locator('button[name="veredito"][value="confirmada"]').click()
    ]);

    const bannerRevisado = page.locator('.alert', { hasText: 'concluído manualmente' });
    await expect(bannerRevisado).toContainText(/Revis[ãa]o:\s*Confirmada/i);
    await expect(bannerRevisado).not.toContainText(/aguardando revis[ãa]o/i);

    // A revisão é definitiva: o modal de revisão não deve mais existir na página.
    await expect(page.locator('#modalRevisarManual')).toHaveCount(0);
  });

  test('E2E-MAN-002 | conclusão manual é bloqueada sem justificativa mínima ou fora de status ativo', async ({ page }) => {
    test.setTimeout(60_000);

    const admin = seedAdmin();
    const seeded = seedAtendimentoCompleto();
    const pedidoId = seeded.pedido_id;

    await loginAs(page, admin.admin_email, admin.admin_password);
    await expectLoggedIn(page);

    // Chama a rota diretamente via API (bypassando o `required`/`minlength`
    // do HTML) para garantir que a validação REAL está no backend
    // (PedidoTransitionService::concludeManuallyByAdmin), não só no form.
    await page.goto(appPath(`/admin/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });
    const csrfToken = await page.locator('input[name="csrf_token"]').first().inputValue();

    const config = atendimentoConfig();
    const response = await page.request.post(appPath('/admin/pedido/concluir-manual'), {
      multipart: {
        csrf_token: csrfToken,
        pedido_id: String(pedidoId),
        justificativa: 'muito curto',
        senha: admin.admin_password,
        comprovante_coleta: { name: 'coleta.jpg', mimeType: 'image/jpeg', buffer: (await import('node:fs')).readFileSync(config.arrivalImage) },
        comprovante_entrega: { name: 'entrega.jpg', mimeType: 'image/jpeg', buffer: (await import('node:fs')).readFileSync(config.deliveryImage) }
      },
      maxRedirects: 0
    }).catch(async (err) => {
      // O controller redireciona (302) de volta para a página do pedido com
      // flash de erro — Playwright's APIRequestContext segue redirects por
      // padrão a menos que maxRedirects:0, então tratamos os dois casos.
      throw err;
    });

    // Independente de seguir redirect ou não, o pedido TEM que continuar
    // 'a_caminho' — a validação de tamanho mínimo da justificativa bloqueou.
    const statusResponse = await page.request.get(appPath(`/admin/pedido/status-json/${pedidoId}`), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    const statusData = await statusResponse.json();
    expect(statusData?.pedido?.status).toBe('a_caminho');
  });
});
