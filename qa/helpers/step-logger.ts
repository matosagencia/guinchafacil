import { type Page, type TestInfo } from '@playwright/test';

/**
 * Registro estruturado de passos para testes E2E longos e multi-etapa (ex:
 * login externo + checkout de gateway de pagamento), onde uma falha no meio
 * do fluxo historicamente só mostrava UM stack trace da etapa que quebrou,
 * sem contexto de quanto do fluxo já tinha funcionado até ali. Isso obrigava
 * a reconstruir manualmente "até onde chegou" lendo screenshots/vídeo a cada
 * falha nova.
 *
 * Cada chamada a `passo()` fica registrada com sucesso/falha, duração e (se
 * falhar) a mensagem de erro e um screenshot automático — e o relatório
 * completo (todos os passos, não só o que quebrou) é sempre anexado ao
 * resultado do teste, tanto em caso de sucesso quanto de falha, via
 * `finalizar()`. Também imprime cada passo no console em tempo real
 * ("[PASSO] ▶/✔/✘ ..."), então mesmo colando só o output do terminal (sem
 * abrir screenshot nenhum) já dá pra ver exatamente até onde o fluxo
 * avançou e o que quebrou.
 */
export type StepMetadata = {
  system?: string;
  class?: string;
  function?: string;
  phase?: string;
  pedidoId?: number | string;
};

export type ResultadoPasso = {
  nome: string;
  sucesso: boolean;
  duracaoMs: number;
  detalhe?: string;
  erro?: string;
  metadata?: StepMetadata;
};

function formatarMetadata(metadata?: StepMetadata): string {
  if (!metadata) return '';
  const partes = [
    metadata.system ? `system=${metadata.system}` : null,
    metadata.class ? `class=${metadata.class}` : null,
    metadata.function ? `function=${metadata.function}` : null,
    metadata.phase ? `phase=${metadata.phase}` : null,
    metadata.pedidoId !== undefined ? `pedido=${metadata.pedidoId}` : null,
  ].filter(Boolean);
  return partes.length ? ` [${partes.join(' ')}]` : '';
}

export class RegistroPassos {
  private passos: ResultadoPasso[] = [];

  constructor(private testInfo: TestInfo, private page: Page) {}

  /**
   * Executa `fn` como um passo nomeado. Se `fn` lançar, o passo é marcado
   * como falho (com a mensagem de erro e um screenshot anexado), e a
   * exceção é relançada — quem chama continua tratando erro normalmente
   * (test.skip/fail), só que agora com o relatório completo já registrado
   * pra `finalizar()` anexar depois.
   */
  async passo<T>(nome: string, fn: () => Promise<T>, opcoes?: { detalhe?: string; metadata?: StepMetadata }): Promise<T> {
    const inicio = Date.now();
    const metaTxt = formatarMetadata(opcoes?.metadata);
    // eslint-disable-next-line no-console
    console.log(`[PASSO] ▶ ${nome}${metaTxt}`);
    try {
      const resultado = await fn();
      const duracaoMs = Date.now() - inicio;
      this.passos.push({ nome, sucesso: true, duracaoMs, detalhe: opcoes?.detalhe, metadata: opcoes?.metadata });
      // eslint-disable-next-line no-console
      console.log(`[PASSO] ✔ ${nome} (${duracaoMs}ms)${metaTxt}`);
      return resultado;
    } catch (erroBruto) {
      const duracaoMs = Date.now() - inicio;
      const erro = erroBruto instanceof Error ? erroBruto.message : String(erroBruto);
      this.passos.push({ nome, sucesso: false, duracaoMs, erro, detalhe: opcoes?.detalhe, metadata: opcoes?.metadata });
      // eslint-disable-next-line no-console
      console.error(`[PASSO] ✘ ${nome} (${duracaoMs}ms)${metaTxt} — ${erro}`);
      await this.testInfo
        .attach(`falha-${slug(nome)}.png`, { body: await this.page.screenshot(), contentType: 'image/png' })
        .catch(() => {});
      await this.testInfo
        .attach(`falha-${slug(nome)}.json`, {
          body: Buffer.from(JSON.stringify({ nome, erro, ...opcoes?.metadata }, null, 2), 'utf-8'),
          contentType: 'application/json',
        })
        .catch(() => {});
      throw erroBruto;
    }
  }

  /**
   * Registra um passo "manual" (já executado fora do wrapper, ex: um bloco
   * condicional com lógica própria) sem re-executar nada — só soma ao
   * relatório final.
   */
  registrar(nome: string, sucesso: boolean, detalheOuErro?: string, metadata?: StepMetadata): void {
    const entrada: ResultadoPasso = { nome, sucesso, duracaoMs: 0, metadata };
    if (sucesso) entrada.detalhe = detalheOuErro;
    else entrada.erro = detalheOuErro;
    this.passos.push(entrada);
    // eslint-disable-next-line no-console
    console.log(`[PASSO] ${sucesso ? '✔' : '✘'} ${nome}${formatarMetadata(metadata)}${detalheOuErro ? ' — ' + detalheOuErro : ''}`);
  }

  relatorioTexto(): string {
    const ok = this.passos.filter((p) => p.sucesso).length;
    const linhas = this.passos.map((p) => {
      const marca = p.sucesso ? '✔' : '✘';
      const extra = p.erro ? ` — ${p.erro}` : p.detalhe ? ` — ${p.detalhe}` : '';
      return `  ${marca} ${p.nome} (${p.duracaoMs}ms)${formatarMetadata(p.metadata)}${extra}`;
    });
    return `Relatório de passos (${ok}/${this.passos.length} ok):\n${linhas.join('\n')}`;
  }

  /** Sempre chamar num finally — anexa o relatório completo ao resultado do teste. */
  async finalizar(): Promise<void> {
    await this.testInfo
      .attach('relatorio-passos.txt', { body: Buffer.from(this.relatorioTexto(), 'utf-8'), contentType: 'text/plain' })
      .catch(() => {});
    // eslint-disable-next-line no-console
    console.log(this.relatorioTexto());
  }
}

function slug(texto: string): string {
  return texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/gi, '-')
    .toLowerCase()
    .slice(0, 60);
}
