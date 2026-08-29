import { type Page, type TestInfo } from '@playwright/test';
import { fillFirstAvailable, clickFirstAvailable } from './auth';
import { mpBuyerTestUser } from '../fixtures/test-data.fixture';
import { cliqueRobustoAndes } from './click-andes';
import { type RegistroPassos } from './step-logger';

/**
 * Loga o Playwright no MercadoPago (mercadopago.com.br) como o usuário de
 * teste "Comprador" (Devsite > Suas integrações > app > Contas de teste),
 * ANTES de iniciar o checkout — evita o erro "Uma das partes com as quais
 * você está tentando efetuar o pagamento é de teste.", que ocorre quando o
 * navegador completa o checkout sandbox sem estar logado como uma
 * identidade distinta do vendedor (cujo token já está em
 * MP_ACCESS_TOKEN/MP_PUBLIC_KEY no .env real da aplicação).
 *
 * Credenciais vêm de qa/.env.test-users.local (git-ignorado, carregado
 * automaticamente pelo playwright.config.ts) via mpBuyerTestUser().
 *
 * AVISO: os seletores da tela de login do MP (fora do nosso app, domínio
 * deles) não foram inspecionados ao vivo — escritos a partir da estrutura
 * pública conhecida do fluxo "Entrar" do MercadoPago (usuário -> senha ->
 * código de verificação opcional). Se falhar, rode headed/--ui pra ver onde
 * diverge; a função anexa screenshot em cada etapa pra facilitar o ajuste.
 */
export async function loginAsMpBuyer(page: Page, testInfo?: TestInfo, registro?: RegistroPassos): Promise<void> {
  // Se um RegistroPassos for passado (uso normal a partir do teste E2E),
  // cada fase do login vira um passo nomeado e reportado individualmente
  // (sucesso/falha/duração) no relatório final do teste. Sem registro
  // (chamada avulsa/outros testes), roda a fase direto, sem overhead.
  // Função nomeada (não uma const ternária) pra preservar o genérico <T>
  // corretamente — bind() de método genérico perde a inferência de tipo.
  async function passo<T>(nome: string, fn: () => Promise<T>, opcoes?: { detalhe?: string }): Promise<T> {
    if (registro) return registro.passo(nome, fn, opcoes);
    return fn();
  }

  const buyer = mpBuyerTestUser();
  if (!buyer.username || !buyer.password) {
    throw new Error(
      'Credenciais do comprador de teste do MercadoPago não configuradas — ' +
      'preencha MP_BUYER_TEST_USERNAME/MP_BUYER_TEST_PASSWORD em qa/.env.test-users.local.'
    );
  }

  const campoIdentificacao = page.getByRole('textbox', { name: /cpf.*e-?mail.*telefone/i });

  await passo('MP login: abrir home e chegar na identificação', async () => {
    // Não chuta a URL de login direto (ex: "/hub/login" deu 404 — o MP muda
    // essas rotas com frequência). Em vez disso, entra pela home e clica no
    // link/botão "Entrar", deixando o próprio site levar pra URL de login
    // correta, seja qual for hoje.
    await page.goto('https://www.mercadopago.com.br/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    await testInfo?.attach('mp-login-home.png', { body: await page.screenshot(), contentType: 'image/png' });

    // Cookie banner pode cobrir a navegação — aceita se aparecer.
    const aceitarCookies = page.getByRole('button', { name: /aceitar cookies/i });
    if (await aceitarCookies.count().catch(() => 0)) {
      await aceitarCookies.click().catch(() => {});
      await page.waitForTimeout(300);
    }

    // A home às vezes mostra o botão "Iniciar sessão" (marketing), mas outras
    // vezes já redireciona direto pra tela de identificação (visto em
    // execuções reais — depende de estado/cookies não totalmente
    // previsível). Uma checagem única de count() logo após o goto é uma
    // corrida real: a página pode estar no meio da transição, sem o botão
    // "Iniciar sessão" nem o campo de identificação totalmente renderizados
    // ainda (confirmado via error-context.md real, "main" já mostrando a
    // tela de identificação mas nenhum seletor batendo no instante exato do
    // clique). Faz polling entre os dois estados possíveis por até 8s antes
    // de desistir.
    const botaoIniciarSessao = page.locator([
      'button:has-text("Iniciar sessão")',
      'a:has-text("Iniciar sessão")',
      'a[href*="/login"]',
      'button:has-text("Entrar")',
      'a:has-text("Entrar")'
    ].join(', ')).first();

    const deadlineHome = Date.now() + 8000;
    let jaNaIdentificacao = false;
    let botaoEncontrado = false;
    while (Date.now() < deadlineHome) {
      jaNaIdentificacao = !!(await campoIdentificacao.count().catch(() => 0));
      if (jaNaIdentificacao) break;
      botaoEncontrado = !!(await botaoIniciarSessao.count().catch(() => 0));
      if (botaoEncontrado) break;
      await page.waitForTimeout(300);
    }

    if (!jaNaIdentificacao) {
      if (!botaoEncontrado) {
        await testInfo?.attach('mp-login-home-travado.png', { body: await page.screenshot(), contentType: 'image/png' });
        throw new Error(
          'Nem o botão "Iniciar sessão" nem o campo de identificação apareceram na home do MP após 8s — ' +
          'veja mp-login-home-travado.png.'
        );
      }
      await botaoIniciarSessao.click();
      await page.waitForLoadState('domcontentloaded');
      // Depois do clique, espera o campo de identificação realmente aparecer
      // (mesmo motivo do polling acima — não confiar num timeout fixo).
      await campoIdentificacao.first().waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
    }
    await testInfo?.attach('mp-login-pagina-login.png', { body: await page.screenshot(), contentType: 'image/png' });
  });

  await passo('MP login: preencher usuário e continuar', async () => {
    // Etapa 1: usuário (e-mail, username ou CPF) — campo único, botão
    // "Continuar". Confirmado via error-context.md real: heading "Digite seu
    // CPF, e-mail ou telefone para iniciar sessão" com um único
    // textbox acessível de nome "CPF, e-mail ou telefone" (sem name/type
    // previsível na árvore de acessibilidade — os seletores por atributo
    // chutados antes bateram no campo errado e deixaram o real vazio,
    // causando "Erro: Preencha esse dado." ao submeter). Usa o role/nome
    // acessível, que é o dado confiável que realmente temos.
    if (await campoIdentificacao.count().catch(() => 0)) {
      await campoIdentificacao.first().fill(buyer.username);
    } else {
      await fillFirstAvailable(page, [
        'input[name="user_id"]',
        'input[type="email"]',
        'input#user_id',
        'input[type="text"]'
      ], buyer.username);
    }
    await testInfo?.attach('mp-login-usuario-preenchido.png', { body: await page.screenshot(), contentType: 'image/png' });
    // Confere que o campo realmente recebeu o valor antes de seguir — evita
    // repetir o erro de "clicar Continuar com o campo vazio" silenciosamente.
    if (await campoIdentificacao.count().catch(() => 0)) {
      const valorAtual = await campoIdentificacao.first().inputValue().catch(() => '');
      if (!valorAtual) {
        throw new Error('Campo de identificação (CPF/e-mail/telefone) do login MP ficou vazio após o fill — veja mp-login-usuario-preenchido.png.');
      }
    }

    await clickFirstAvailable(page, ['button:has-text("Continuar")', 'button[type="submit"]']);
    await page.waitForLoadState('domcontentloaded');
  });

  // Depois de enviar o usuário, o MP pode levar a 3 estados distintos, e
  // QUAL deles aparece — e QUANDO — varia de execução pra execução
  // (confirmado com error-context.md real de duas tentativas do mesmo
  // teste: uma mostrou a tela de identificação de volta, outra mostrou a
  // tela "Escolha um método de verificação" com o botão "Senha" já
  // presente). Um wait fixo de 600ms seguido de UMA checagem de
  // opcaoSenhaBotao é a mesma corrida de renderização já vista em outros
  // pontos deste fluxo — o SPA pode simplesmente não ter terminado de
  // trocar de tela ainda no instante do check. Faz polling pelos 3 estados
  // possíveis por até 10s antes de decidir o que fazer:
  //   (a) tela "Escolha um método de verificação" (botão "Senha" visível)
  //   (b) campo de senha direto (fluxo pulou a tela de método)
  //   (c) de volta pra tela de identificação (usuário de teste rejeitado)
  const opcaoSenhaBotao = page.getByRole('button', { name: /^Senha/i }).first();
  const campoSenha = page.locator('input[type="password"]')
    .or(page.getByRole('textbox', { name: /^senha$/i }));

  const estadoPosUsuario = await passo('MP login: detectar tela pós-usuário (método/senha/identificação)', async () => {
    const deadlinePosUsuario = Date.now() + 10000;
    let estado: 'metodo' | 'senha' | 'identificacao' | 'desconhecido' = 'desconhecido';
    while (Date.now() < deadlinePosUsuario) {
      if (await opcaoSenhaBotao.count().catch(() => 0)) { estado = 'metodo'; break; }
      if (await campoSenha.count().catch(() => 0)) { estado = 'senha'; break; }
      if (await campoIdentificacao.count().catch(() => 0)) { estado = 'identificacao'; break; }
      await page.waitForTimeout(300);
    }
    await testInfo?.attach('mp-login-pos-usuario.png', { body: await page.screenshot(), contentType: 'image/png' });

    if (estado === 'identificacao') {
      throw new Error(
        'Depois de enviar o usuário, o MP voltou pra tela de identificação (provável rejeição do usuário de teste, ' +
        'ou usuário/senha incorretos em qa/.env.test-users.local) — veja mp-login-pos-usuario.png.'
      );
    }
    if (estado === 'desconhecido') {
      throw new Error(
        'Depois de enviar o usuário, nem a tela "Escolha um método de verificação", nem o campo de senha, nem a ' +
        'identificação apareceram em 10s — veja mp-login-pos-usuario.png.'
      );
    }
    return estado;
  }, { detalhe: 'ver mp-login-pos-usuario.png' });

  // Preenche a senha assim que o campo aparecer, no MESMO passo em que
  // confirma que apareceu — sem checar visibilidade separado e preencher
  // depois. Visto na prática que o campo pode aparecer e "sumir" de novo
  // rapidamente (provável re-render do SPA logo após o clique), então
  // deixar um intervalo entre confirmar e preencher perde a janela. O
  // .fill() do Playwright já espera o elemento ficar acionável sozinho.
  const tentarPreencherSenha = async (timeoutMs: number): Promise<boolean> => {
    try {
      await campoSenha.first().fill(buyer.password, { timeout: timeoutMs });
      return true;
    } catch {
      return false;
    }
  };

  await passo(`MP login: sair da tela "${estadoPosUsuario === 'metodo' ? 'Escolha um método' : 'senha direta'}" e preencher senha`, async () => {
    let senhaPreenchida: boolean;

    if (estadoPosUsuario === 'metodo') {
      await testInfo?.attach('mp-login-metodo-verificacao.png', { body: await page.screenshot(), contentType: 'image/png' });

      // Mesmo padrão Andes/Fury (listitem clicável + botão de acessibilidade
      // sobreposto por CSS) já resolvido em parcelas/forma-de-pagamento no
      // checkout — reusa o helper compartilhado (foco+Enter primeiro, depois
      // clique de mouse real com trajetória de hover no <li> pai, depois no
      // botão, depois force, repetindo o ciclo inteiro 1x mais se preciso).
      const itemSenha = page.locator('li').filter({ hasText: /^Senha/ }).first();
      senhaPreenchida = await cliqueRobustoAndes(
        page,
        opcaoSenhaBotao,
        () => tentarPreencherSenha(1500),
        { elementoPai: itemSenha, timeoutPorTentativaMs: 6000, ciclos: 2 }
      );

      if (!senhaPreenchida) {
        await testInfo?.attach('mp-login-metodo-travado.png', { body: await page.screenshot(), contentType: 'image/png' });
        throw new Error(
          'Não consegui sair da tela "Escolha um método de verificação para continuar" (opção "Senha") do login MP ' +
          '— veja mp-login-metodo-travado.png.'
        );
      }
    } else {
      // estadoPosUsuario === 'senha': campo de senha já apareceu direto,
      // sem passar pela tela de escolha de método.
      senhaPreenchida = await tentarPreencherSenha(6000);
      if (!senhaPreenchida) {
        await testInfo?.attach('mp-login-senha-nao-encontrada.png', { body: await page.screenshot(), contentType: 'image/png' });
        throw new Error(
          'O campo de senha foi detectado mas o .fill() falhou — veja mp-login-senha-nao-encontrada.png.'
        );
      }
    }
    await testInfo?.attach('mp-login-senha-preenchida.png', { body: await page.screenshot(), contentType: 'image/png' });
  });

  await passo('MP login: clicar "Entrar" e resolver código de verificação (se pedido)', async () => {
    await clickFirstAvailable(page, ['button:has-text("Entrar")', 'button[type="submit"]']);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(800);

    // Etapa 3 (opcional): algumas contas de teste pedem um código de
    // verificação extra no login (o mesmo "cod" fornecido junto com
    // usuário/senha no painel "Contas de teste"). Só preenche se a tela
    // aparecer — não é garantido que sempre apareça.
    const campoCodigo = page.locator('input[name*="code" i], input[name*="codigo" i], input[type="tel"]').first();
    if (await campoCodigo.count().catch(() => 0)) {
      await campoCodigo.fill(buyer.verificationCode);
      await clickFirstAvailable(page, [
        'button:has-text("Continuar")',
        'button:has-text("Confirmar")',
        'button[type="submit"]'
      ]).catch(() => {});
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(600);
    }

    await testInfo?.attach('mp-login-comprador-final.png', { body: await page.screenshot(), contentType: 'image/png' });

    // Confirmação frouxa: o MP pode redirecionar pro hub, pra home, etc. — só
    // falha se ainda estiver visivelmente numa tela de login (login não
    // completou).
    if (/\/login/i.test(page.url())) {
      throw new Error(
        `Login como comprador de teste do MercadoPago não completou (URL ainda: ${page.url()}) — ` +
        'veja os screenshots anexados (mp-login-*) para ajustar os seletores.'
      );
    }
  });
}
