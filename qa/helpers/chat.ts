// File: guinchafacil/qa/helpers/chat.ts
//
// Centraliza o chat, que hoje aparece espalhado dentro dos specs. Os
// seletores abaixo foram conferidos direto no código real das duas telas
// (não inventados):
//   - src/Views/guincho/atendimento.php: input #msgInput + botão #btnEnviarMsg
//   - src/Views/cliente/pedidostatus.php: input #msgInput + botão #btnEnviar
// Ambas renderizam as mensagens em #chatBox (mesmo id nas duas telas).

import { type Page, expect } from '@playwright/test';

export type ChatRole = 'cliente' | 'guincho';

const BOTAO_POR_PAPEL: Record<ChatRole, string> = {
  cliente: '#btnEnviar',
  guincho: '#btnEnviarMsg',
};

/**
 * Mesma causa raiz de §POR-QA-NAV-01 (já corrigida em postTowLocation/
 * moveTowAlongRouteRealTime): guincho/atendimento.php dá
 * window.location.reload() quando recebe, via SSE, um status_update
 * diferente do status carregado (ver atendimento.php ~linha 834). O clique
 * em #btnEnviarMsg/#btnEnviar dispara um fetch() assíncrono (ver
 * atendimento.php:962) que só anexa a mensagem em #chatBox no próprio
 * `.then()` — se o reload disparar entre o clique e essa resposta (comum
 * logo depois de confirmarChegada(), que muda o status e pode empurrar o
 * SSE quase imediatamente), o fetch em voo é abortado pela navegação e a
 * mensagem nunca chega a ser persistida, então nem reaparece depois do
 * reload. Sem retry, isso derruba o teste inteiro por uma corrida de
 * timing do próprio ambiente, não por um bug real de chat. Reenviar a
 * MESMA mensagem depois que a página assentar resolve sem mascarar uma
 * falha de chat de verdade (que continuaria falhando em toda tentativa).
 */
export async function enviarMensagem(page: Page, papel: ChatRole, texto: string, maxRetries = 3): Promise<void> {
  const botaoSelector = BOTAO_POR_PAPEL[papel];

  for (let tentativa = 0; ; tentativa++) {
    try {
      const input = page.locator('#msgInput');
      await input.fill(texto);
      await page.locator(botaoSelector).click();

      // Confirma que a própria página mostrou a mensagem enviada antes de
      // seguir (evita depender de timing do SSE/polling do outro lado pra
      // considerar "enviado").
      await expect(page.locator('#chatBox')).toContainText(texto, { timeout: 10_000 });
      return;
    } catch (erro) {
      if (tentativa >= maxRetries) throw erro;
      console.warn(
        `[QA] envio de mensagem de chat não confirmado em #chatBox (tentativa ${tentativa + 1}/${maxRetries + 1}) — ` +
        `provável reload disparado por SSE logo após uma transição de status. Aguardando a página assentar e reenviando.`
      );
      await page.waitForLoadState('domcontentloaded').catch(() => {});
      await page.waitForTimeout(500);
    }
  }
}

/**
 * pedidoId é opcional (compatibilidade com chamadas existentes), mas
 * quando informado transforma um timeout genérico num diagnóstico real:
 * consulta chat_mensagens direto no banco (qaChatSnapshot) e diz, na
 * própria mensagem de erro, se a mensagem esperada FOI persistida (então o
 * problema é entrega/SSE do lado que está esperando — ver console.log
 * [pedidostatus][SSE] em pedidostatus.php) ou NUNCA foi persistida (então
 * o problema é no envio, não na espera). Investigação de 30/07/2026: sem
 * isso, "não apareceu em 15s" não diz qual das duas causas é.
 */
export async function esperarMensagem(page: Page, texto: string | RegExp, timeoutMs = 15_000, pedidoId?: string | number): Promise<void> {
  try {
    await expect(page.locator('#chatBox')).toContainText(texto, { timeout: timeoutMs });
  } catch (erroOriginal) {
    if (pedidoId === undefined) throw erroOriginal;

    const { qaChatSnapshot } = await import('./seed');
    const snapshot = qaChatSnapshot(pedidoId);
    const persistida = snapshot.ok && (snapshot.mensagens ?? []).some((m) => {
      const alvo = texto instanceof RegExp ? texto : new RegExp(texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
      return alvo.test(m.mensagem);
    });

    const diagnostico = persistida
      ? `mensagem JÁ ESTÁ em chat_mensagens (persistência ok) — problema é de ENTREGA (SSE não chegou/caiu a tempo; ver console.log [pedidostatus][SSE] no trace desta página).`
      : `mensagem NÃO está em chat_mensagens (${snapshot.total ?? 0} mensagem(ns) no pedido) — problema é no ENVIO, não na espera.`;

    throw new Error(`esperarMensagem(pedido ${pedidoId}) falhou: ${diagnostico}\nErro original: ${(erroOriginal as Error).message}`);
  }
}
