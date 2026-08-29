import { type Page, type Locator } from '@playwright/test';

/**
 * Clique de mouse real (move -> down -> pausa -> up) via coordenadas de
 * tela, replicando um clique humano de verdade. Usado como uma das
 * estratégias do design system Andes/Fury do MercadoPago, que renderiza
 * linhas clicáveis (<li id="algo-row" style="cursor:pointer">) com um
 * <button> de acessibilidade vazio sobreposto por CSS por cima — esse
 * botão costuma "intercepts pointer events" e trava o .click() comum do
 * Playwright em retry infinito até estourar o timeout do teste.
 */
export async function clicarConfiavel(page: Page, locator: Locator, opcoes?: { movimentoRealista?: boolean }): Promise<boolean> {
  if (!(await locator.count().catch(() => 0))) return false;
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  await page.waitForTimeout(150);
  const box = await locator.boundingBox().catch(() => null);
  if (!box || box.width <= 0 || box.height <= 0) return false;
  const x = box.x + box.width / 2;
  const y = box.y + box.height / 2;

  if (opcoes?.movimentoRealista) {
    // Alguns handlers do design system Andes (hover-dependente, ex:
    // destacar a linha antes de aceitar o clique) só armam depois de um
    // "mouseenter"/"mousemove" de verdade sobre o elemento — um único
    // page.mouse.move direto pro centro pula essa fase. Simula uma
    // trajetória com passos intermediários partindo de fora do elemento,
    // com uma pausa de "hover" antes do mousedown.
    const origemX = Math.max(0, x - 120);
    const origemY = Math.max(0, y - 40);
    await page.mouse.move(origemX, origemY);
    await page.waitForTimeout(30);
    await page.mouse.move(x, y, { steps: 12 });
    await page.waitForTimeout(220); // dwell de hover antes do down
  } else {
    await page.mouse.move(x, y);
  }

  await page.waitForTimeout(50);
  await page.mouse.down();
  await page.waitForTimeout(80);
  await page.mouse.up();
  return true;
}

/**
 * Clique robusto contra o padrão de listitem clicável do Andes/Fury do MP
 * (visto repetidamente no checkout: seleção de parcelas "1x", método de
 * verificação "Senha" no login, seleção de forma de pagamento "Cartão"/
 * "new_card_row"). Tenta, em ordem, até `verificarSucesso()` retornar true:
 *
 * 1. Foco no botão de acessibilidade + tecla Enter — o navegador converte
 *    isso num clique real nativo no botão focado, sem depender de
 *    coordenada/hit-testing (o que resolve o "intercepts pointer events").
 * 2. Clique de mouse real no <li> pai inteiro (se `elementoPai` for
 *    passado).
 * 3. Clique de mouse real no botão isolado.
 * 4. Clique sintético comum com force, como último recurso.
 *
 * `verificarSucesso` deve checar algo específico e direto (URL mudou, um
 * elemento da próxima tela apareceu, etc.) — nunca sumiço de texto, que se
 * mostrou instável por causa de re-render do SPA.
 */
export async function cliqueRobustoAndes(
  page: Page,
  botao: Locator,
  verificarSucesso: () => Promise<boolean>,
  opcoes?: {
    elementoPai?: Locator;
    timeoutPorTentativaMs?: number;
    /**
     * Quantas vezes repetir a cadeia inteira de estratégias (não só uma
     * estratégia isolada) antes de desistir de vez. Entre um ciclo e outro,
     * se `antesDeRepetir` for passado, ele roda primeiro (ex: sair e
     * reabrir a mesma tela) — útil quando a área pode ter ficado num
     * estado "meio clicado" que nem reload resolve sozinho. Default 1
     * (comportamento antigo, sem repetição).
     */
    ciclos?: number;
    antesDeRepetir?: () => Promise<void>;
  }
): Promise<boolean> {
  const timeoutTentativa = opcoes?.timeoutPorTentativaMs ?? 4000;
  const ciclos = Math.max(1, opcoes?.ciclos ?? 1);

  for (let ciclo = 1; ciclo <= ciclos; ciclo++) {
    if (await verificarSucesso()) return true;

    // Estratégia 1: foco no botão de acessibilidade + Enter. O navegador
    // converte isso num clique real nativo — não depende de
    // coordenada/hit-testing.
    await botao.focus().catch(() => {});
    await page.keyboard.press('Enter').catch(() => {});
    if (await esperarComTimeout(verificarSucesso, timeoutTentativa)) return true;

    // Antes de tentar a próxima estratégia (que dispara OUTRO clique), espera
    // um eventual spinner de carregamento sumir — visto na prática (screenshot
    // real: andes-progress-indicator-circular sobre a lista de pagamento) que
    // o primeiro clique pode ter funcionado e só estar demorando pra concluir;
    // clicar de novo em cima disso corrompe o estado da página em vez de
    // ajudar.
    await aguardarSpinnerSumir(page, timeoutTentativa);
    if (await verificarSucesso()) return true;

    // Estratégia 2: clique de mouse real (move -> down -> up) no <li> pai,
    // com trajetória/hover realistas — alguns handlers só armam depois de
    // um mouseenter de verdade.
    if (opcoes?.elementoPai) {
      await clicarConfiavel(page, opcoes.elementoPai, { movimentoRealista: true });
      if (await esperarComTimeout(verificarSucesso, timeoutTentativa)) return true;
      await aguardarSpinnerSumir(page, timeoutTentativa);
      if (await verificarSucesso()) return true;
    }

    // Estratégia 3: mesma coisa, mirando o botão isolado.
    await clicarConfiavel(page, botao, { movimentoRealista: true });
    if (await esperarComTimeout(verificarSucesso, timeoutTentativa)) return true;
    await aguardarSpinnerSumir(page, timeoutTentativa);
    if (await verificarSucesso()) return true;

    // Estratégia 4: clique de mouse simples (sem trajetória longa), caso a
    // trajetória realista da 2/3 tenha, por algum motivo, passado por cima
    // de outro elemento no caminho.
    if (opcoes?.elementoPai) {
      await clicarConfiavel(page, opcoes.elementoPai);
      if (await esperarComTimeout(verificarSucesso, timeoutTentativa)) return true;
      await aguardarSpinnerSumir(page, timeoutTentativa);
      if (await verificarSucesso()) return true;
    }
    await clicarConfiavel(page, botao);
    if (await esperarComTimeout(verificarSucesso, timeoutTentativa)) return true;
    await aguardarSpinnerSumir(page, timeoutTentativa);
    if (await verificarSucesso()) return true;

    // Estratégia 5: clique sintético comum com force, último recurso do ciclo.
    await botao.click({ force: true }).catch(() => {});
    if (await esperarComTimeout(verificarSucesso, timeoutTentativa)) return true;

    if (ciclo < ciclos) {
      await opcoes?.antesDeRepetir?.().catch(() => {});
    }
  }

  return false;
}

async function aguardarSpinnerSumir(page: Page, timeoutMs: number): Promise<void> {
  const spinner = page.locator('[class*="progress-indicator-circular"], [class*="loader"], [class*="spinner"]').first();
  if (await spinner.count().catch(() => 0)) {
    await spinner.waitFor({ state: 'hidden', timeout: timeoutMs }).catch(() => {});
  }
}

async function esperarComTimeout(verificar: () => Promise<boolean>, timeoutMs: number): Promise<boolean> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (await verificar().catch(() => false)) return true;
    await new Promise((resolve) => setTimeout(resolve, 200));
  }
  return verificar().catch(() => false);
}
