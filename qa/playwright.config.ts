import { defineConfig, devices } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

// Loader manual (sem depender do pacote "dotenv") para
// qa/.env.test-users.local — contas de teste do MercadoPago (comprador/
// vendedor) usadas só em QA, nunca commitadas (ver .gitignore).
// Não sobrescreve variáveis já definidas no ambiente/shell.
function carregarEnvTestUsers(): void {
  const envPath = path.join(__dirname, '.env.test-users.local');
  if (!existsSync(envPath)) return;
  const linhas = readFileSync(envPath, 'utf-8').split(/\r?\n/);
  for (const linha of linhas) {
    const semComentario = linha.trim();
    if (!semComentario || semComentario.startsWith('#')) continue;
    const igual = semComentario.indexOf('=');
    if (igual === -1) continue;
    const chave = semComentario.slice(0, igual).trim();
    const valor = semComentario.slice(igual + 1).trim();
    if (chave && process.env[chave] === undefined) {
      process.env[chave] = valor;
    }
  }
}
carregarEnvTestUsers();

// §QA-BASEURL-01: XAMPP neste ambiente serve o GuinchaFácil na porta 8080
// (não a 80 padrão) — sem isso todo teste batia em "/login" da porta 80 e
// caía na página padrão do Apache (404), não no vhost da aplicação.
// PLAYWRIGHT_BASE_URL continua funcionando como override (ex: outro
// ambiente/porta), só o padrão mudou.
const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8080';
const locale = process.env.PLAYWRIGHT_LOCALE || 'pt-BR';
const timezoneId = process.env.PLAYWRIGHT_TIMEZONE || 'America/Sao_Paulo';
const artifactsDir = process.env.QA_ARTIFACTS_DIR || 'test-results';

export default defineConfig({
  testDir: './suites',
  timeout: 90_000,
  expect: { timeout: 10_000 },
  // Investigação (E2E-CAN-002, gate completo): descartei acúmulo de dados de
  // teste (tabelas de pedido com 23-63 linhas, nada perto de justificar lentidão
  // de query) e chamadas de rede real (o seed de cancelamento não cria linha em
  // `pagamentos`, então EstornoService::estornar() nem chega a sair pra
  // internet). O timeout ocasional em specs isoladas que sempre passam quando
  // rodadas sozinhas é mesmo contenção de CPU/IO real da máquina num gate
  // serializado de ~25-30min (documentado abaixo em `workers: 1`) — não um bug
  // de aplicação. 1 retry automático absorve esse ruído sem mascarar uma
  // falha que se repete de verdade.
  retries: 1,
  // Serializa a execução: o ambiente local (XAMPP/MySQL numa única máquina)
  // não aguenta duas specs pesadas (cadastro em lote com uploads reais,
  // simulações de concorrência) rodando em paralelo sem gerar timeouts em
  // cascata por contenção de CPU/IO — não é flakiness de UI, é o servidor
  // saturado. 1 worker é mais lento mas confiável neste ambiente.
  //
  // QA_STRESS_WORKERS é um opt-in explícito (nunca o padrão): só quando
  // alguém setar essa variável de propósito (ex.: rodando SÓ os specs de
  // stress-*.spec.ts num ambiente que aguenta mais carga) o número de
  // workers sobe. Sem a env var, continua 1 — não muda o comportamento do
  // gate normal.
  workers: process.env.QA_STRESS_WORKERS ? Number(process.env.QA_STRESS_WORKERS) : 1,
  reporter: [
    ['line'],
    ['./reporters/guinchafacil-reporter.ts']
  ],
  outputDir: artifactsDir,
  use: {
    baseURL,
    locale,
    timezoneId,
    trace: process.env.RECORD_TRACE === '0' ? 'off' : 'retain-on-failure',
    video: process.env.RECORD_VIDEO === '0' ? 'off' : 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] }
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] }
    }
  ]
});
