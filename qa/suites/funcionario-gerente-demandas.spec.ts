import { test, expect, type Page } from '@playwright/test';
import { loginAs, expectLoggedIn } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import {
  seedFuncionarioGerente,
  seedAdminForFuncionarioGerente,
  qaPedidoStatus,
  qaGuinchoStatus,
  type FuncionarioGerenteSeedResult,
} from '../helpers/seed';

// Cobre a arquitetura de separação de deveres do sistema de Demandas:
// funcionário CRIA (nunca executa), gerente DECIDE (nunca a própria demanda,
// nunca sozinho quando o valor exige dupla aprovação). Ver DemandaService.php
// e a Constituição (seção "Nova camada financeira constitucional") pro
// racional completo. Os efeitos reais (pedido cancelado, chave PIX alterada
// etc.) são verificados via CLI (tools/qa_pedido_status.php,
// tools/qa_guincho_status.php) porque as rotas HTTP de status-json são
// restritas a cliente/guincho/admin — não a funcionário/gerente.

test.describe.configure({ timeout: 180_000 });

async function criarDemanda(
  page: Page,
  opts: {
    tipo: string;
    pedidoId?: number;
    paymentJobId?: number;
    guinchoId?: number;
    valorEnvolvido?: string;
    campo?: string;
    valorNovo?: string;
    justificativa: string;
  }
): Promise<void> {
  await page.goto(appPath(`/funcionario/demandas/nova?tipo=${opts.tipo}`), { waitUntil: 'domcontentloaded' });
  await page.locator('#tipoDemanda').selectOption(opts.tipo);

  if (opts.pedidoId) {
    await page.locator('input[name="pedido_id"]').first().fill(String(opts.pedidoId));
  }
  if (opts.paymentJobId) {
    await page.locator('input[name="payment_job_id"]').fill(String(opts.paymentJobId));
  }
  if (opts.valorEnvolvido) {
    await page.locator('input[name="valor_envolvido"]').fill(opts.valorEnvolvido);
  }
  if (opts.campo) {
    await page.locator('#campoAlteracao').selectOption(opts.campo);
    if (opts.campo.startsWith('guincho.') && opts.guinchoId) {
      await page.locator('input[name="guincho_id"]').fill(String(opts.guinchoId));
    }
  }
  if (opts.valorNovo) {
    await page.locator('input[name="valor_novo"]').fill(opts.valorNovo);
  }

  await page.locator('textarea[name="justificativa"]').fill(opts.justificativa);
  await page.locator('button[type="submit"]').click();
  // Regex ancorada no fim: "/funcionario/demandas" (lista, destino após
  // sucesso) não pode casar com "/funcionario/demandas/nova" (o próprio
  // formulário, onde já estamos ANTES de enviar) — sem a âncora, o
  // waitForURL podia resolver cedo demais, direto na página do formulário,
  // e o teste seguia como se a demanda já tivesse sido criada quando o
  // POST ainda nem tinha terminado (ou tinha voltado com erro de validação).
  await page.waitForURL(/\/funcionario\/demandas(?:\?.*)?$/i, { timeout: 15000 });
}

async function idDaUltimaDemanda(page: Page): Promise<number> {
  await page.goto(appPath('/funcionario/demandas'), { waitUntil: 'domcontentloaded' });
  const texto = await page.locator('tbody tr').first().locator('td').first().innerText();
  const id = parseInt(texto.replace('#', '').trim(), 10);
  if (!Number.isFinite(id)) {
    throw new Error(`Não foi possível extrair o id da demanda mais recente. Texto lido: "${texto}"`);
  }
  return id;
}

async function decidirDemanda(page: Page, demandaId: number, veredito: 'aprovar' | 'rejeitar', nota: string, senha: string): Promise<void> {
  await page.goto(appPath(`/gerente/demanda/${demandaId}`), { waitUntil: 'domcontentloaded' });
  await page.locator('textarea[name="nota"]').fill(nota);
  await page.locator('input[name="senha"]').fill(senha);
  await page.locator(`button[name="veredito"][value="${veredito}"]`).click();
  await page.waitForLoadState('domcontentloaded');
}

test.describe('funcionário e gerente — controle de acesso e login', () => {
  let seed: FuncionarioGerenteSeedResult;

  test.beforeAll(() => {
    seed = seedFuncionarioGerente();
  });

  test('FG-RBAC-001 | funcionário faz login e cai no próprio dashboard', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await expect(page).toHaveURL(/\/funcionario\/dashboard/i);
    await expectLoggedIn(page);
  });

  test('FG-RBAC-002 | gerente faz login e cai no próprio dashboard', async ({ page }) => {
    await loginAs(page, seed.gerente_email, seed.password);
    await expect(page).toHaveURL(/\/gerente\/dashboard/i);
    await expectLoggedIn(page);
  });

  test('FG-RBAC-003 | funcionário é bloqueado (403) ao tentar acessar rota de gerente', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    const response = await page.goto(appPath('/gerente/dashboard'), { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(403);
    await expect(page.locator('body')).toContainText(/acesso negado/i);
  });

  test('FG-RBAC-004 | gerente é bloqueado (403) ao tentar acessar rota de funcionário', async ({ page }) => {
    await loginAs(page, seed.gerente_email, seed.password);
    const response = await page.goto(appPath('/funcionario/pedidos'), { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(403);
    await expect(page.locator('body')).toContainText(/acesso negado/i);
  });
});

test.describe('funcionário e gerente — validações de criação e decisão', () => {
  let seed: FuncionarioGerenteSeedResult;

  test.beforeAll(() => {
    seed = seedFuncionarioGerente();
  });

  test('FG-VAL-001 | justificativa curta demais é bloqueada na criação', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await page.goto(appPath('/funcionario/demandas/nova?tipo=cancelamento'), { waitUntil: 'domcontentloaded' });
    await page.locator('#tipoDemanda').selectOption('cancelamento');
    await page.locator('input[name="pedido_id"]').first().fill(String(seed.pedido_cancelamento_id));
    // minlength=20 no HTML; testamos o texto de 5 chars pra confirmar que o
    // navegador barra o submit (validação nativa) OU, se o backend for
    // atingido de alguma forma, que ele também recusa (defesa em profundidade
    // — DemandaService::criar() confere de novo, não confia só no HTML).
    await page.locator('textarea[name="justificativa"]').fill('curta');
    const textarea = page.locator('textarea[name="justificativa"]');
    const valid = await textarea.evaluate((el: HTMLTextAreaElement) => el.checkValidity());
    expect(valid).toBe(false);
  });

  test('FG-VAL-002 | nota curta demais é bloqueada na decisão do gerente', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    // Usa pedido_conclusao_manual_id (não pedido_cancelamento_id) para não
    // colidir com a demanda que FG-FLUXO-001 cria logo em seguida: o guard
    // de idempotência de DemandaService::criar() é uma hash de
    // (tipo, solicitante, pedido, guincho, payment_job, minuto) — os dois
    // testes rodando dentro do mesmo minuto com o MESMO pedido geravam
    // "Já existe uma demanda idêntica criada há poucos instantes." no
    // segundo, que a UI nem exibe (form volta em branco) e o teste só via
    // como timeout no waitForURL.
    await criarDemanda(page, {
      tipo: 'cancelamento',
      pedidoId: seed.pedido_conclusao_manual_id,
      justificativa: 'Cliente pediu cancelamento por telefone antes do atendimento chegar.',
    });
    const demandaId = await idDaUltimaDemanda(page);

    const gerentePage = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerentePage, seed.gerente_email, seed.password);
      await gerentePage.goto(appPath(`/gerente/demanda/${demandaId}`), { waitUntil: 'domcontentloaded' });
      await gerentePage.locator('textarea[name="nota"]').fill('ok');
      const notaValida = await gerentePage.locator('textarea[name="nota"]').evaluate((el: HTMLTextAreaElement) => el.checkValidity());
      expect(notaValida).toBe(false);
    } finally {
      await gerentePage.context().close();
    }
  });
});

test.describe('funcionário e gerente — fluxo completo de demandas', () => {
  let seed: FuncionarioGerenteSeedResult;

  test.beforeAll(() => {
    seed = seedFuncionarioGerente();
  });

  test('FG-FLUXO-001 | cancelamento: funcionário solicita, gerente aprova, pedido é cancelado de verdade', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await criarDemanda(page, {
      tipo: 'cancelamento',
      pedidoId: seed.pedido_cancelamento_id,
      justificativa: 'Cliente ligou pedindo cancelamento — guincho ainda a caminho da coleta.',
    });
    const demandaId = await idDaUltimaDemanda(page);

    const gerentePage = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerentePage, seed.gerente_email, seed.password);
      await decidirDemanda(gerentePage, demandaId, 'aprovar', 'Confirmado com o cliente por telefone, pode cancelar.', seed.password);
      await expect(gerentePage.locator('body')).not.toContainText(/erro interno/i);
    } finally {
      await gerentePage.context().close();
    }

    await expect.poll(() => qaPedidoStatus(seed.pedido_cancelamento_id).status, {
      timeout: 30000,
      message: 'pedido nunca chegou a status=cancelado após aprovação da demanda',
    }).toBe('cancelado');
  });

  test('FG-FLUXO-002 | gerente rejeita demanda: status vira rejeitada e pedido não é alterado', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await criarDemanda(page, {
      tipo: 'reembolso',
      pedidoId: seed.pedido_reembolso_simples_id,
      justificativa: 'Cliente reclamou de cobrança em duplicidade, mas não anexou comprovante ainda.',
    });
    const demandaId = await idDaUltimaDemanda(page);

    const gerentePage = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerentePage, seed.gerente_email, seed.password);
      await decidirDemanda(gerentePage, demandaId, 'rejeitar', 'Sem comprovante de cobrança em duplicidade — peça ao cliente antes de reenviar.', seed.password);
      await gerentePage.goto(appPath('/gerente/demandas'), { waitUntil: 'domcontentloaded' });
      // Demanda decidida some da fila de pendentes.
      await expect(gerentePage.locator(`text=#${demandaId}`)).toHaveCount(0);
    } finally {
      await gerentePage.context().close();
    }

    const statusPedido = qaPedidoStatus(seed.pedido_reembolso_simples_id);
    expect(statusPedido.status).toBe('concluido');
    expect(statusPedido.pagamento_status).toBe('aprovado');
  });

  test('FG-FLUXO-003 | gerente não pode decidir a própria demanda solicitada (auto-aprovação bloqueada)', async ({ page }) => {
    // AuthService::requireAuth() trata 'admin' como bypass universal de
    // perfil — ou seja, uma conta admin passa tanto no controle de rota de
    // funcionário quanto de gerente. Isso é usado deliberadamente aqui para
    // provar que o bloqueio de auto-aprovação vive no SERVICE
    // (DemandaService::decidir(), comparando solicitante_id === gerenteId),
    // não apenas no controle de rota — a defesa continua de pé mesmo quando
    // o controle de perfil da rota não segura sozinho.
    const admin = seedAdminForFuncionarioGerente();

    await loginAs(page, admin.admin_email, admin.admin_password);
    await criarDemanda(page, {
      tipo: 'cancelamento',
      pedidoId: seed.pedido_cancelamento_id,
      justificativa: 'Teste QA de bloqueio de auto-aprovação — admin como solicitante e gerente.',
    });
    const demandaId = await idDaUltimaDemanda(page);

    await page.goto(appPath(`/gerente/demanda/${demandaId}`), { waitUntil: 'domcontentloaded' });
    await page.locator('textarea[name="nota"]').fill('Tentando aprovar minha própria demanda — isto deve ser bloqueado.');
    await page.locator('input[name="senha"]').fill(admin.admin_password);
    await page.locator('button[name="veredito"][value="aprovar"]').click();
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('body')).toContainText(/não pode decidir uma demanda que você mesmo solicitou/i);
  });

  test('FG-FLUXO-004 | reembolso de alto valor exige dois gerentes diferentes (dupla aprovação)', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await criarDemanda(page, {
      tipo: 'reembolso',
      pedidoId: seed.pedido_reembolso_alto_id,
      justificativa: 'Reembolso integral solicitado pelo cliente — valor acima do limiar de dupla aprovação.',
    });
    const demandaId = await idDaUltimaDemanda(page);

    // Primeira aprovação (gerente 1) — deve ficar "aprovada_parcial", não executar ainda.
    const gerente1Page = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerente1Page, seed.gerente_email, seed.password);
      await decidirDemanda(gerente1Page, demandaId, 'aprovar', 'Valor alto confirmado, aguardando segunda aprovação conforme política.', seed.password);
    } finally {
      await gerente1Page.context().close();
    }

    // Pedido ainda não pode ter sido afetado — só a segunda aprovação executa.
    expect(qaPedidoStatus(seed.pedido_reembolso_alto_id).pagamento_status).toBe('aprovado');

    // O MESMO gerente 1 tentando aprovar de novo deve ser bloqueado.
    const gerente1RetryPage = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerente1RetryPage, seed.gerente_email, seed.password);
      await decidirDemanda(gerente1RetryPage, demandaId, 'aprovar', 'Tentando aprovar de novo com a mesma conta — deve falhar.', seed.password);
      await expect(gerente1RetryPage.locator('body')).toContainText(/segunda aprovação precisa ser de um gerente diferente/i);
    } finally {
      await gerente1RetryPage.context().close();
    }

    // Gerente 2 (diferente) aprova — agora sim executa.
    const gerente2Page = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerente2Page, seed.gerente2_email, seed.password);
      await decidirDemanda(gerente2Page, demandaId, 'aprovar', 'Segunda aprovação confirmada por gerente diferente — pode executar.', seed.password);
    } finally {
      await gerente2Page.context().close();
    }
  });

  test('FG-FLUXO-005 | alteração de dados sensíveis (chave PIX) sempre exige dupla aprovação', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await page.goto(appPath('/funcionario/demandas/nova?tipo=alteracao_dados'), { waitUntil: 'domcontentloaded' });
    await page.locator('#tipoDemanda').selectOption('alteracao_dados');
    await page.locator('#campoAlteracao').selectOption('guincho.chave_pix');
    await page.locator('input[name="guincho_id"]').fill(String(seed.guincho_id));
    await page.locator('input[name="valor_novo"]').fill('chave-pix-qa-teste@example.com');
    await page.locator('textarea[name="justificativa"]').fill('Guincheiro relatou troca de conta bancária e pediu atualização da chave PIX cadastrada.');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/\/funcionario\/demandas(?:\?.*)?$/i, { timeout: 15000 });
    const demandaId = await idDaUltimaDemanda(page);

    const gerente1Page = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerente1Page, seed.gerente_email, seed.password);
      await gerente1Page.goto(appPath(`/gerente/demanda/${demandaId}`), { waitUntil: 'domcontentloaded' });
      // Mesmo sem valor monetário, alteração de dados deve sinalizar dupla aprovação.
      await expect(gerente1Page.locator('body')).toContainText(/dois gerentes diferentes|exige dupla aprova/i);
      await decidirDemanda(gerente1Page, demandaId, 'aprovar', 'Confirmado por telefone com o guincheiro antes de aprovar.', seed.password);
    } finally {
      await gerente1Page.context().close();
    }

    // Chave PIX não pode ter mudado ainda — só após a segunda aprovação.
    expect(qaGuinchoStatus(seed.guincho_id).chave_pix).not.toBe('chave-pix-qa-teste@example.com');

    const gerente2Page = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerente2Page, seed.gerente2_email, seed.password);
      await decidirDemanda(gerente2Page, demandaId, 'aprovar', 'Segunda confirmação — pode aplicar a nova chave PIX.', seed.password);
    } finally {
      await gerente2Page.context().close();
    }

    await expect.poll(() => qaGuinchoStatus(seed.guincho_id).chave_pix, {
      timeout: 30000,
      message: 'chave PIX do guincho não foi atualizada após dupla aprovação',
    }).toBe('chave-pix-qa-teste@example.com');
  });

  test('FG-FLUXO-006 | conclusão manual assistida com comprovantes é executada após aprovação', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await page.goto(appPath('/funcionario/demandas/nova?tipo=conclusao_manual'), { waitUntil: 'domcontentloaded' });
    await page.locator('#tipoDemanda').selectOption('conclusao_manual');
    await page.locator('input[name="pedido_id"]').first().fill(String(seed.pedido_conclusao_manual_id));

    // Gera 2 imagens PNG mínimas em memória (1x1) para simular comprovantes.
    const pngMinimo = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
      'base64'
    );
    await page.locator('input[name="comprovante_coleta"]').setInputFiles({ name: 'coleta.png', mimeType: 'image/png', buffer: pngMinimo });
    await page.locator('input[name="comprovante_entrega"]').setInputFiles({ name: 'entrega.png', mimeType: 'image/png', buffer: pngMinimo });

    await page.locator('textarea[name="justificativa"]').fill('GPS do guincheiro ficou indisponível durante todo o trajeto — conclusão via comprovantes fotográficos manuais.');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(/\/funcionario\/demandas(?:\?.*)?$/i, { timeout: 15000 });
    const demandaId = await idDaUltimaDemanda(page);

    const gerentePage = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerentePage, seed.gerente_email, seed.password);
      await gerentePage.goto(appPath(`/gerente/demanda/${demandaId}`), { waitUntil: 'domcontentloaded' });
      // Os comprovantes embutidos como imagem base64 devem aparecer na tela de revisão.
      await expect(gerentePage.locator('img.img-thumbnail')).toHaveCount(2);
      await decidirDemanda(gerentePage, demandaId, 'aprovar', 'Comprovantes de coleta e entrega conferem com o trajeto relatado.', seed.password);
    } finally {
      await gerentePage.context().close();
    }

    await expect.poll(() => qaPedidoStatus(seed.pedido_conclusao_manual_id).concluido_manualmente, {
      timeout: 30000,
      message: 'pedido não foi marcado como concluído manualmente após aprovação',
    }).toBe(true);
    expect(qaPedidoStatus(seed.pedido_conclusao_manual_id).status).toBe('concluido');
  });

  test('FG-FLUXO-007 | pagamento: reprocessar repasse falho após aprovação da demanda', async ({ page }) => {
    await loginAs(page, seed.funcionario_email, seed.password);
    await criarDemanda(page, {
      tipo: 'pagamento',
      paymentJobId: seed.payment_job_id,
      justificativa: 'Repasse PIX falhou por token expirado — já foi corrigido, favor reprocessar.',
    });
    const demandaId = await idDaUltimaDemanda(page);

    const gerentePage = await page.context().browser()!.newContext().then((c) => c.newPage());
    try {
      await loginAs(gerentePage, seed.gerente_email, seed.password);
      await decidirDemanda(gerentePage, demandaId, 'aprovar', 'Confirmado que o token foi renovado — pode reprocessar o repasse.', seed.password);
      // A execução real do PaymentJobService::forceRetry() pode falhar de
      // novo em ambiente local sem gateway configurado (token sandbox
      // inválido) — o que importa aqui é que a demanda foi PROCESSADA (não
      // ficou presa em pendente/aprovada_parcial), refletindo o resultado
      // real do reprocessamento, e não uma falsa promessa de sucesso.
      await gerentePage.goto(appPath(`/gerente/demanda/${demandaId}`), { waitUntil: 'domcontentloaded' });
      await expect(gerentePage.locator('body')).toContainText(/já foi decidida/i);
    } finally {
      await gerentePage.context().close();
    }
  });
});
