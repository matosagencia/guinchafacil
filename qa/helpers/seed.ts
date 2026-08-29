import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

// qa/helpers/seed.ts -> sobe dois níveis para chegar na raiz do projeto
// (onde ficam config.php e a pasta tools/).
const PROJECT_ROOT = path.resolve(__dirname, '..', '..');

/**
 * Antes desta ficha, todo teste que precisava de um pedido em um estado
 * específico (atendimento completo, concorrência de aceite, cancelamento)
 * dependia de alguém rodar manualmente um script PHP e copiar o pedido_id
 * para uma variável de ambiente antes do gate — passo fácil de esquecer, e
 * que causou horas de investigação quando ficou stale (ver histórico de QA).
 * Esta função automatiza esse passo: roda o script seed PHP via CLI e
 * devolve o JSON já parseado, então os specs conseguem se auto-seedar sem
 * intervenção manual. Env vars continuam funcionando como override explícito
 * quando definidas (ver helpers/atendimento.ts e fixtures/test-data.fixture.ts).
 */
function resolvePhpBinary(): string {
  const fromEnv = process.env.QA_PHP_BIN;
  const candidates = [
    fromEnv,
    'C:\\xampp\\php\\php.exe',
    'C:\\xampp\\php8\\php.exe',
    'php',
  ].filter((value): value is string => Boolean(value && value.trim()));

  for (const candidate of candidates) {
    if (candidate === 'php' || existsSync(candidate)) {
      return candidate;
    }
  }
  return 'php';
}

function runSeedScript<T = any>(scriptRelativePath: string, args: string[] = []): T {
  const phpBin = resolvePhpBinary();
  const scriptPath = path.join(PROJECT_ROOT, scriptRelativePath);

  if (!existsSync(scriptPath)) {
    throw new Error(`Script de seed não encontrado: ${scriptPath}`);
  }

  let stdout: string;
  try {
    stdout = execFileSync(phpBin, [scriptPath, ...args], {
      encoding: 'utf8',
      cwd: PROJECT_ROOT,
      timeout: 60_000,
    });
  } catch (error: any) {
    const stderr = error?.stderr ? String(error.stderr) : '';
    const stdoutPartial = error?.stdout ? String(error.stdout) : '';
    throw new Error(
      `Falha ao rodar seed "${scriptRelativePath}" (php: ${phpBin}). ` +
      `Verifique se o MySQL/Apache do XAMPP estão no ar e se QA_PHP_BIN aponta ` +
      `para um php.exe válido caso o caminho padrão não sirva. Detalhe: ` +
      `${stderr || stdoutPartial || error?.message || 'erro desconhecido'}`
    );
  }

  const jsonLine = stdout.trim();
  try {
    return JSON.parse(jsonLine) as T;
  } catch {
    throw new Error(`Seed "${scriptRelativePath}" não devolveu JSON válido. Saída bruta: ${stdout}`);
  }
}

export type P1SeedResult = {
  ok: boolean;
  cliente_email: string;
  guincho_email: string;
  guincho_2_email: string;
  upload_pedido_id: number;
  checkout_pedido_id: number;
  concorrencia_pedido_id: number;
};

export type AtendimentoSeedResult = {
  ok: boolean;
  free_payment: boolean;
  pedido_id: number;
  status: string;
  guincho_email: string;
  cliente_email: string;
  cliente_url: string;
  guincho_url: string;
};

export type CancelamentoSeedResult = {
  ok: boolean;
  cliente_email: string;
  guincho_email: string;
  pedido_antes_aceite_id: number;
  pedido_cliente_taxa_id: number;
  pedido_irreversivel_id: number;
  pedido_guincho_cancela_id: number;
};

export type AtendimentoRealtimeSeedResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  guincho_email: string;
  cliente_email: string;
  cliente_url: string;
  guincho_url: string;
};

export function seedP1(): P1SeedResult {
  return runSeedScript<P1SeedResult>('tools/prepare_p1_qa_seeds.php');
}

export function seedAtendimentoCompleto(): AtendimentoSeedResult {
  return runSeedScript<AtendimentoSeedResult>('tools/prepare_atendimento_completo_qa_seed.php');
}

export function seedCancelamento(): CancelamentoSeedResult {
  return runSeedScript<CancelamentoSeedResult>('tools/prepare_cancelamento_qa_seed.php');
}

export function seedAtendimentoTempoReal(): AtendimentoRealtimeSeedResult {
  return runSeedScript<AtendimentoRealtimeSeedResult>('tools/prepare_atendimento_realtime_qa_seed.php');
}

export type AtendimentoRjTowSeedResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  guincho_email: string;
  cliente_email: string;
  cliente_url: string;
  guincho_url: string;
};

/**
 * RJ-TOW-001: guincho "comum" (nasce podendo rebocar), 700m de aproximação
 * até a origem + 1,2km de entrega, rota real (Av. Ayrton Senna, Barra da
 * Tijuca/RJ) — ver qa/helpers/atendimento.ts (rjGuinchoApproachRoute /
 * rjDeliveryRoute) e tools/prepare_atendimento_rj_tow_qa_seed.php.
 */
export function seedAtendimentoRjTow(): AtendimentoRjTowSeedResult {
  return runSeedScript<AtendimentoRjTowSeedResult>('tools/prepare_atendimento_rj_tow_qa_seed.php');
}

export type AtendimentoSocorroSetupResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  service_type_id: number;
  cliente_email: string;
  especialista_email: string;
  especialista_guincho_id: number;
  guincho_reboque_email: string;
  guincho_reboque_id: number;
  especialista_usuario_id: number;
  guincho_usuario_id: number;
  cliente_url: string;
  checkout_url: string;
  especialista_url: string;
};

export type AtendimentoSocorroAtribuirResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  guincho_id: number;
};

/**
 * Seed para E2E-SOCORRO-001 (qa/suites/atendimento-socorro-conversao.spec.ts)
 * — pane elétrica (ELECTRICAL_DIAGNOSIS, ON_SITE) atendida por especialista
 * puro (sem capacidade de reboque aprovada), diagnóstico real
 * REQUER_REBOQUE, conversão aprovada pelo cliente com destino real coletado
 * (dispara a cobrança complementar de reboque — ver ConversionService),
 * segundo pagamento real via Payment Brick, e um guincho comum assume o
 * reboque até o destino. Ver tools/prepare_atendimento_socorro_qa_seed.php.
 * setup() cria/reseta tudo e devolve o pedido em 'aguardando_pagamento';
 * atribuirEspecialista/atribuirReboque só funcionam depois que o pedido
 * já estiver em 'aguardando_guincho' (pagamento real aprovado).
 */
export function seedAtendimentoSocorroSetup(): AtendimentoSocorroSetupResult {
  return runSeedScript<AtendimentoSocorroSetupResult>('tools/prepare_atendimento_socorro_qa_seed.php', ['setup']);
}

export function seedAtendimentoSocorroAtribuirEspecialista(pedidoId: number | string): AtendimentoSocorroAtribuirResult {
  return runSeedScript<AtendimentoSocorroAtribuirResult>('tools/prepare_atendimento_socorro_qa_seed.php', ['atribuir-especialista', String(pedidoId)]);
}

export function seedAtendimentoSocorroAtribuirReboque(pedidoId: number | string): AtendimentoSocorroAtribuirResult {
  return runSeedScript<AtendimentoSocorroAtribuirResult>('tools/prepare_atendimento_socorro_qa_seed.php', ['atribuir-reboque', String(pedidoId)]);
}

export function seedAtendimentoSocorroLigarRoadMatch(): { ok: boolean; por_road_match_enabled: boolean } {
  return runSeedScript('tools/prepare_atendimento_socorro_qa_seed.php', ['ligar-road-match']);
}

export function seedAtendimentoSocorroDesligarRoadMatch(): { ok: boolean; por_road_match_enabled: boolean } {
  return runSeedScript('tools/prepare_atendimento_socorro_qa_seed.php', ['desligar-road-match']);
}

export type AtendimentoRjEspecialistaSeedResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  guincho_email: string;
  cliente_email: string;
  cliente_url: string;
  guincho_url: string;
  reboque_aprovado: number;
};

/**
 * RJ-TOW-002: especialista que virou guincho de verdade — o seed passa pelo
 * mesmo caminho de produção (Guincho::solicitarReboque + Guincho::aprovar),
 * não só liga a flag no banco. 1km de aproximação (mais longe que o guincho
 * comum) + a MESMA entrega de 1,2km do RJ-TOW-001 — ver
 * tools/prepare_atendimento_rj_especialista_qa_seed.php.
 */
export function seedAtendimentoRjEspecialista(): AtendimentoRjEspecialistaSeedResult {
  return runSeedScript<AtendimentoRjEspecialistaSeedResult>('tools/prepare_atendimento_rj_especialista_qa_seed.php');
}

export type AdminSeedResult = {
  ok: boolean;
  admin_email: string;
  admin_password: string;
};

export function seedAdmin(): AdminSeedResult {
  return runSeedScript<AdminSeedResult>('tools/prepare_admin_qa_seed.php');
}

export type SimularPagamentoResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  mensagem?: string;
};

/**
 * Simula a aprovação de pagamento de UM pedido específico (sem depender do
 * gateway real nem alterar a config global payment_required/system_mode, que
 * afetaria outros specs do gate). Usado por onboarding-completo.spec.ts
 * quando o pedido criado via UI nasce em 'aguardando_pagamento' porque o
 * ambiente atual exige pagamento antecipado.
 */
export function simularPagamentoAprovado(pedidoId: number | string): SimularPagamentoResult {
  return runSeedScript<SimularPagamentoResult>('tools/qa_simular_pagamento_aprovado.php', [String(pedidoId)]);
}

export type FindPedidoResult = {
  ok: boolean;
  pedido_id?: number;
  status?: string;
  cliente_id?: number;
  guincho_id?: number | null;
  erro?: string;
};

export function findPedidoByMarker(marker: string): FindPedidoResult {
  return runSeedScript<FindPedidoResult>('tools/qa_find_pedido_by_marker.php', [marker]);
}

export type FuncionarioGerenteSeedResult = {
  ok: boolean;
  funcionario_email: string;
  gerente_email: string;
  gerente2_email: string;
  password: string;
  guincho_id: number;
  pedido_cancelamento_id: number;
  pedido_conclusao_manual_id: number;
  pedido_pagamento_id: number;
  payment_job_id: number;
  pedido_reembolso_simples_id: number;
  pedido_reembolso_alto_id: number;
};

/**
 * Seed para funcionario-gerente-demandas.spec.ts — separação de deveres
 * (funcionário cria demanda, gerente aprova/rejeita). Requer que
 * tools/prepare_p1_qa_seeds.php já tenha rodado (reusa cliente/guincho
 * fixos de lá). Cria/garante 1 funcionário + 2 gerentes (dupla aprovação
 * exige dois gerentes DIFERENTES) e pedidos/pagamentos/jobs em cada estado
 * necessário para os 5 tipos de demanda.
 */
export function seedFuncionarioGerente(): FuncionarioGerenteSeedResult {
  return runSeedScript<FuncionarioGerenteSeedResult>('tools/prepare_funcionario_gerente_qa_seed.php');
}

export type QaPedidoStatusResult = {
  ok: boolean;
  pedido_id?: number;
  status?: string;
  guincho_id?: number | null;
  concluido_manualmente?: boolean;
  revisao_manual_status?: string | null;
  observacao_interna?: string | null;
  pagamento_status?: string | null;
  erro?: string;
};

/**
 * Consulta o status real de um pedido direto do banco via CLI — usado por
 * funcionario-gerente-demandas.spec.ts porque as rotas HTTP de status-json
 * são restritas a cliente/guincho/admin, e os testes dessa suíte logam como
 * funcionário/gerente.
 */
export function qaPedidoStatus(pedidoId: number | string): QaPedidoStatusResult {
  return runSeedScript<QaPedidoStatusResult>('tools/qa_pedido_status.php', [String(pedidoId)]);
}

export type QaGuinchoStatusResult = {
  ok: boolean;
  guincho_id?: number;
  chave_pix?: string | null;
  chave_pix_tipo?: string | null;
  disponivel?: number | null;
  aprovado?: number | null;
  erro?: string;
};

export function qaGuinchoStatus(guinchoId: number | string): QaGuinchoStatusResult {
  return runSeedScript<QaGuinchoStatusResult>('tools/qa_guincho_status.php', [String(guinchoId)]);
}

export type AdminSeedResultForFG = {
  ok: boolean;
  admin_email: string;
  admin_password: string;
};

/**
 * Reusa o mesmo seed de admin.php que onboarding-completo.spec.ts já usa.
 * Aqui serve pra exercitar o cenário de auto-aprovação bloqueada: uma conta
 * 'admin' passa tanto no check de perfil 'funcionario' quanto 'gerente'
 * (AuthService::requireAuth() trata admin como bypass universal — ver
 * AuthService.php:63), então é a forma mais direta de provar em teste que
 * DemandaService::decidir() bloqueia o solicitante de decidir a própria
 * demanda mesmo quando ele tecnicamente "passaria" no controle de rota.
 */
export function seedAdminForFuncionarioGerente(): AdminSeedResultForFG {
  return runSeedScript<AdminSeedResultForFG>('tools/prepare_admin_qa_seed.php');
}

export type ConfirmarWebhookMpResult = {
  ok: boolean;
  erro?: string;
  http_code?: number;
  payment_id?: string;
  pagamento_id?: number;
  pedido_id?: number;
  pedido_status?: string;
  pagamento_status?: string;
  status_pix?: string | null;
  valor_total?: number;
  valor_guincho?: number;
  valor_plataforma?: number;
  aprovado?: boolean;
};

/**
 * Reproduz o webhook real do Mercado Pago (assinatura HMAC + GET na API do MP
 * pelo payment_id) pro pagamento local — necessário porque em ambiente local
 * (sem túnel público) o MP não consegue chamar de volta a notification_url
 * depois de um checkout de sandbox de verdade. Usado por
 * pagamento-sandbox.spec.ts depois de completar o checkout real com cartão
 * de teste, usando o payment_id devolvido na URL de retorno do MP.
 */
export function confirmarWebhookMercadoPago(paymentId: string | number): ConfirmarWebhookMpResult {
  return runSeedScript<ConfirmarWebhookMpResult>('tools/qa_confirmar_webhook_mp.php', [String(paymentId)]);
}

export type EnvAuditoriaResult = {
  ok: boolean;
  admin_id?: number;
  chave?: string;
  valor_mascarado?: string;
  acao?: string;
  hash_alteracao?: string;
  criado_em?: string;
  erro?: string;
};

/**
 * Consulta direto no banco o registro de auditoria mais recente para uma
 * chave do .env — usado pela Suite E (E1) pra confirmar que salvar
 * /admin/env realmente gravou uma linha em env_auditoria, não só mostrou a
 * mensagem de sucesso na UI (a auditoria é best-effort no controller e não
 * bloqueia o save se falhar).
 */
export function envAuditoriaUltima(chave: string): EnvAuditoriaResult {
  return runSeedScript<EnvAuditoriaResult>('tools/qa_env_auditoria_ultima.php', [chave]);
}

export type GatewayAtivoResult = {
  ok: boolean;
  payment_gateway_active: string | null;
};

/**
 * Lê o PAYMENT_GATEWAY_ACTIVE atual do jeito que o app lê (via config.php,
 * reprocessando o .env gerenciado a cada request) — usado pela Suite E (E2)
 * pra confirmar que a troca de gateway feita pelo admin persistiu de fato.
 */
export function gatewayAtivo(): GatewayAtivoResult {
  return runSeedScript<GatewayAtivoResult>('tools/qa_gateway_ativo.php');
}

export type ConversaoHibridaSetupResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  service_type_onsite_id: number;
  service_type_reboque_id: number;
  cliente_email: string;
  hibrido_email: string;
  hibrido_guincho_id: number;
  hibrido_usuario_id: number;
  destino_lat: number;
  destino_lng: number;
  destino_endereco: string;
  cliente_url: string;
  checkout_url: string;
  hibrido_url: string;
};

export type ConversaoHibridaAtribuirResult = {
  ok: boolean;
  pedido_id: number;
  status: string;
  guincho_id: number;
};

export type ConversaoHibridaCapacidadeResult = {
  ok: boolean;
  guincho_id: number;
  capacidade_reboque: string;
};

/**
 * Seed para E2E-HIBRIDO-001/002 (qa/suites/conversao-hibrida-complementar.spec.ts)
 * — pane elétrica (ELECTRICAL_DIAGNOSIS, ON_SITE) atendida por um prestador
 * HÍBRIDO (já nasce com ProviderCapability aprovada em ELECTRICAL_DIAGNOSIS
 * + TOW_CAR). Diferente de seedAtendimentoSocorroSetup (especialista puro —
 * a conversão SOLTA o pedido pra fila comum), aqui
 * ConversionService::finalizarCaminhoHibrido mantém o MESMO guincho_id e
 * cobra um complementar de reboque sem reabrir matching — ver
 * install/migration_hibrido_complementar_v1.sql (§HIBRIDO-COMPLEMENTAR-01).
 * setup() cria/reseta tudo e devolve o pedido em 'aguardando_pagamento';
 * atribuirHibrido só funciona depois que o pedido já estiver em
 * 'aguardando_guincho' (primeiro pagamento real aprovado).
 */
export function seedConversaoHibridaSetup(): ConversaoHibridaSetupResult {
  return runSeedScript<ConversaoHibridaSetupResult>('tools/prepare_conversao_hibrida_qa_seed.php', ['setup']);
}

export function seedConversaoHibridaAtribuirHibrido(pedidoId: number | string): ConversaoHibridaAtribuirResult {
  return runSeedScript<ConversaoHibridaAtribuirResult>('tools/prepare_conversao_hibrida_qa_seed.php', ['atribuir-hibrido', String(pedidoId)]);
}

/**
 * Cenário E2E-HIBRIDO de downgrade: suspende a capacidade TOW_CAR do
 * prestador híbrido DEPOIS que ele já foi vinculado ao pedido mas ANTES do
 * complementar de reboque ser pago — força
 * PedidoTransitionService::guinchoAindaValidoParaHibrido() a falhar no
 * momento da aprovação do pagamento, rebaixando o pedido pra fila comum
 * ('aguardando_guincho' / attendance_mode='TOWING' / guincho_id=NULL).
 */
export function seedConversaoHibridaSuspenderCapacidadeReboque(guinchoId: number | string): ConversaoHibridaCapacidadeResult {
  return runSeedScript<ConversaoHibridaCapacidadeResult>('tools/prepare_conversao_hibrida_qa_seed.php', ['suspender-capacidade-reboque', String(guinchoId)]);
}

export function seedConversaoHibridaReaprovarCapacidadeReboque(guinchoId: number | string): ConversaoHibridaCapacidadeResult {
  return runSeedScript<ConversaoHibridaCapacidadeResult>('tools/prepare_conversao_hibrida_qa_seed.php', ['reaprovar-capacidade-reboque', String(guinchoId)]);
}

export type PagamentoIdExternoResult = {
  ok: boolean;
  pedido_id?: number;
  vivo_pagamento_id?: number | null;
  vivo_status?: string | null;
  vivo_id_externo?: string | null;
  vivo_payment_id_numerico?: string | null;
  arquivado_pagamento_id?: number | null;
  arquivado_status?: string | null;
  arquivado_id_externo?: string | null;
  arquivado_payment_id_numerico?: string | null;
  erro?: string;
};

/**
 * Devolve o id_externo (e o payment_id numérico do MP, sem prefixo "mp_")
 * do pagamento vivo e do arquivado mais recente de um pedido — usado por
 * E2E-HIBRIDO-001 pra recuperar o payment_id do pagamento ORIGINAL (que o
 * checkout transparente/Brick nunca devolve ao cliente, só aprova de forma
 * síncrona no servidor) e assim poder repetir de propósito o webhook
 * antigo depois que ele já foi arquivado, provando que
 * PagamentoAprovacaoService::aprovar() ignora o replay sem tocar no
 * pagamento complementar vivo.
 */
export function pagamentoIdExterno(pedidoId: number | string): PagamentoIdExternoResult {
  return runSeedScript<PagamentoIdExternoResult>('tools/qa_pagamento_id_externo.php', [String(pedidoId)]);
}

// ─────────────────────────────────────────────────────────────────────────
// Reforço QA (29/07/2026) — stress funcional + cenários reais da Gamboa.
// Os scripts tools/prepare_stress_accounts_qa.php e
// tools/prepare_atendimento_rj_gamboa_qa.php ainda serão criados na Fase 2
// (grounded no schema real, não inventado) — os tipos e wrappers abaixo já
// documentam o contrato esperado pra Fase 3 (specs) poder ser escrita em
// paralelo.

export type StressAccountsSeedResult = {
  ok: boolean;
  run_id: string;
  clientes: number[];
  guinchos: number[];
  multisservico: number[];
  especialistas: number[];
};

export function seedStressAccounts(): StressAccountsSeedResult {
  return runSeedScript<StressAccountsSeedResult>('tools/prepare_stress_accounts_qa.php');
}

export type AtendimentoGamboaSeedResult = {
  ok: boolean;
  pedido_id: number;
  tipo: 'colisao' | 'pane-eletrica';
  status: string;
  service_type_id: number;
  cliente_email: string;
  prestador_email: string;
  cliente_url: string;
  checkout_url: string;
};

/**
 * Cria um pedido determinístico com origem/prestador nas coordenadas reais
 * da Gamboa (ver qa/fixtures/stress-scenarios.fixture.ts) — 'colisao' usa
 * attendance_mode TOWING (reboque direto até a oficina), 'pane-eletrica'
 * usa ON_SITE (ELECTRICAL_DIAGNOSIS, sem destino obrigatório).
 */
export function seedAtendimentoGamboa(tipo: 'colisao' | 'pane-eletrica'): AtendimentoGamboaSeedResult {
  return runSeedScript<AtendimentoGamboaSeedResult>('tools/prepare_atendimento_rj_gamboa_qa.php', [tipo]);
}

export type QaPedidoSnapshotResult = {
  ok: boolean;
  pedido?: { id: number; status: string; guincho_id: number | null; attendance_mode?: string };
  pagamento?: { status: string; valor: number } | null;
  por?: { total_pontos: number; pontos_aceitos: number; rota_integra: boolean };
  chat?: { mensagens: number };
  evidencias?: { chegada: boolean; coleta: boolean; entrega: boolean };
  erro?: string;
};

/**
 * Snapshot direto do banco pro estado real de um pedido — mais forte que
 * checar só a UI, pelo mesmo motivo já documentado em qaPedidoStatus():
 * a UI pode "parecer" certa (ex.: um reload que mascara um erro anterior)
 * sem que o estado persistido realmente reflita sucesso.
 */
export function qaPedidoSnapshot(pedidoId: number | string): QaPedidoSnapshotResult {
  return runSeedScript<QaPedidoSnapshotResult>('tools/qa_get_pedido_snapshot.php', [String(pedidoId)]);
}

export type QaFinanceiroSnapshotResult = {
  ok: boolean;
  saldo_total?: number;
  saldo_em_compensacao?: number;
  saldo_liberado?: number;
  movimentos?: number;
  erro?: string;
};

export function qaFinanceiroSnapshot(guinchoId: number | string): QaFinanceiroSnapshotResult {
  return runSeedScript<QaFinanceiroSnapshotResult>('tools/qa_get_financeiro_snapshot.php', [String(guinchoId)]);
}

export type QaPorSnapshotResult = {
  ok: boolean;
  total_pontos?: number;
  aceitos?: number;
  rejeitados?: number;
  ultima_rejeicao_code?: string | null;
  tracking_quality?: string | null;
  erro?: string;
};

export function qaPorSnapshot(pedidoId: number | string): QaPorSnapshotResult {
  return runSeedScript<QaPorSnapshotResult>('tools/qa_get_por_snapshot.php', [String(pedidoId)]);
}

export function qaCleanupStressRun(runId: string): { ok: boolean; removidos?: number; erro?: string } {
  return runSeedScript('tools/qa_cleanup_stress_run.php', [runId]);
}

export type QaCapacidadeStatusResult = {
  ok: boolean;
  guincho_id?: number;
  service_code?: string;
  service_type_id?: number;
  approval_status?: string;
  enabled?: boolean;
  possui_capacidade_aprovada?: boolean;
  erro?: string;
};

/**
 * Lê o estado real de provider_capabilities pro par (guincho, serviço) —
 * usado para confirmar, depois de um fluxo 100% via UI (declarar +
 * aprovar), que o predicado que o matching de verdade consulta
 * (ProviderCapability::possuiCapacidadeAprovada) realmente mudou.
 */
export function qaCapacidadeStatus(guinchoId: number | string, serviceCode: string): QaCapacidadeStatusResult {
  return runSeedScript<QaCapacidadeStatusResult>('tools/qa_get_capacidade_status.php', [String(guinchoId), serviceCode]);
}

export type QaPedidoOnsiteGenericoResult = {
  ok: boolean;
  pedido_id?: number;
  service_code?: string;
  service_type_id?: number;
  status?: string;
  erro?: string;
};

/**
 * Cria/reseta um pedido ON_SITE já em 'aguardando_guincho' para qualquer
 * service_code do catálogo, sem vincular guincho — usado para provar
 * matching por capacidade fora dos dois cenários fixos de colisão/pane
 * elétrica (ver tools/prepare_pedido_onsite_generico_qa.php).
 */
export function seedPedidoOnsiteGenerico(serviceCode: string, tag = 'default'): QaPedidoOnsiteGenericoResult {
  return runSeedScript<QaPedidoOnsiteGenericoResult>('tools/prepare_pedido_onsite_generico_qa.php', [serviceCode, tag]);
}

export type QaOrcamentoSnapshotResult = {
  ok: boolean;
  existe?: boolean;
  status?: string;
  valor_total?: number;
  itens?: Array<{ descricao: string; valor: number }>;
  decidido_em?: string | null;
  erro?: string;
};

/**
 * Estado real de pedido_orcamentos — usado por VAR-004 (orçamento
 * complementar recusado pelo cliente) porque o status do PEDIDO não muda
 * quando o cliente recusa (decisão de propósito do DiagnosticoService), só
 * o orçamento em si vira RECUSADO.
 */
export function qaOrcamentoSnapshot(pedidoId: number | string): QaOrcamentoSnapshotResult {
  return runSeedScript<QaOrcamentoSnapshotResult>('tools/qa_get_orcamento_snapshot.php', [String(pedidoId)]);
}

export type QaChatSnapshotResult = {
  ok: boolean;
  total?: number;
  mensagens?: Array<{ id: number; usuario_id: number; mensagem: string; criado_em: string }>;
  erro?: string;
};

/**
 * Estado real de chat_mensagens direto no banco — usado pra separar duas
 * causas do mesmo sintoma "mensagem não apareceu no #chatBox a tempo":
 * (a) nunca foi persistida (bug de verdade no envio) vs (b) foi persistida
 * mas o SSE do lado que devia recebê-la não entregou a tempo (conexão
 * caiu/reconectando). Ver console.log [pedidostatus][SSE] em
 * pedidostatus.php para diagnosticar (b) especificamente.
 */
export function qaChatSnapshot(pedidoId: number | string): QaChatSnapshotResult {
  return runSeedScript<QaChatSnapshotResult>('tools/qa_get_chat_snapshot.php', [String(pedidoId)]);
}

export type QaGuinchoIdPorEmailResult = { ok: boolean; guincho_id?: number; erro?: string };

/**
 * Resolve usuarios.email -> guinchos.id para contas criadas via UI real
 * (onboarding-stress.spec.ts), onde o teste nunca teve o guincho_id de
 * antemão (diferente dos seeds fixos que sempre reusam o mesmo e-mail).
 */
export function qaGuinchoIdPorEmail(email: string): QaGuinchoIdPorEmailResult {
  return runSeedScript<QaGuinchoIdPorEmailResult>('tools/qa_guincho_id_por_email.php', [email]);
}

// §COBERTURA-RAIO-01 (05/08/2026) ---------------------------------------

export type TimeoutEstornoSeedResult = {
  ok: boolean;
  cliente_email: string;
  pedido_id: number;
  pagamento_id: number;
};

/**
 * Cria/reseta um pedido em aguardando_guincho com expiracao_aceite já
 * vencida (1 min atrás) e um pagamento aprovado com id_externo fictício —
 * ponto de partida de qa/suites/cobertura-timeout-estorno.spec.ts para
 * provar que ninguém aceitar em 30 min cancela o pedido e tenta o estorno
 * automaticamente (ver tools/cron_cancelar_pedidos_expirados.php /
 * ExpiracaoPedidosService).
 */
export function seedTimeoutEstorno(): TimeoutEstornoSeedResult {
  return runSeedScript<TimeoutEstornoSeedResult>('tools/prepare_timeout_estorno_qa_seed.php');
}

export type CronExpiracaoResult = {
  ok: boolean;
  expired_found?: number;
  cancelled?: number;
  refunds_ok?: number;
  refunds_failed?: number;
  errors?: number;
  erro?: string;
};

/**
 * Dispara ExpiracaoPedidosService::executar() imediatamente (mesma lógica
 * do cron real de produção, tools/cron_cancelar_pedidos_expirados.php) —
 * sem isso o teste teria que esperar o Task Scheduler do ambiente do
 * usuário rodar de verdade, o que não é determinístico o suficiente pra um
 * spec automatizado.
 */
export function executarCronExpiracao(): CronExpiracaoResult {
  return runSeedScript<CronExpiracaoResult>('tools/qa_executar_cron_expiracao.php');
}

export type GateCoberturaResult = {
  ok: boolean;
  fora_de_cobertura_bloqueou?: boolean;
  dentro_de_cobertura_liberou?: boolean;
  mensagem?: string;
  erro?: string;
};

/**
 * Teste de backend direto em CoberturaService::existeGuinchoAlcancavel —
 * complementa o spec de UI com uma checagem rápida e determinística da
 * função que decide o bloqueio, mesmo padrão de
 * tools/seed_qa_niteroi_celulas.php para validarDadosGuincho().
 */
export function testeGateCobertura(): GateCoberturaResult {
  return runSeedScript<GateCoberturaResult>('tools/qa_test_gate_cobertura.php');
}

export type QaEstoqueMovimento = {
  id: number;
  pedido_id: number | null;
  tipo: string;
  quantidade: number;
  saldo_apos: number;
  descricao: string | null;
  criado_em: string;
};

export type QaEstoqueSnapshotResult = {
  ok: boolean;
  provider_id?: number;
  produto_id?: number;
  existe?: boolean;
  saldo?: number;
  produto_nome?: string | null;
  produto_sku?: string | null;
  active?: boolean | null;
  movimentos?: QaEstoqueMovimento[];
  erro?: string;
};

/**
 * Saldo real de provider_produtos_estoque + últimos movimentos — usado pela
 * Fase 4 (E2E de socorro com bateria/estoque) pra confirmar que a aprovação
 * de um orçamento com produto_id realmente debitou estoque (ver
 * DiagnosticoService::decidirOrcamento(), §COBERTURA-RAIO-01 06/08/2026).
 */
export function qaEstoqueSnapshot(providerId: number | string, produtoId: number | string): QaEstoqueSnapshotResult {
  return runSeedScript<QaEstoqueSnapshotResult>('tools/qa_get_estoque_snapshot.php', [String(providerId), String(produtoId)]);
}
