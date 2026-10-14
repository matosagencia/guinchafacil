CONTRATO_RESPOSTA:
  de: A
  para: B

  oficina_id:
    existe: sim
    tabela: pedidos
    tipo: INT(11) NULL
    origem: DecisaoAtendimentoService::oficina_mais_proxima.provider_id
    exposição: Pedido::buscarPorId() já retorna p.*; disponível no array.

  oficina_nome:
    existe: não
    definição: VARCHAR(255) NULL
    origem: snapshot de DecisaoAtendimentoService::oficina_mais_proxima.nome
    exposição: p.* após migration.

  triagem:
    existe: não
    definição: VARCHAR(50) NULL
    origem: decisão operacional do motor (acao ou recomendacao)
    exposição: p.* após migration.

  custo_assistencia:
    existe: não
    definição: DECIMAL(10,2) NULL
    origem: decisao.opcao_assistencia.custo_saida
    exposição: p.* após migration.

  custo_reboque:
    existe: não
    definição: DECIMAL(10,2) NULL
    origem: decisao.opcao_reboque.custo_total
    exposição: p.* após migration.

  recomendacao:
    existe: não
    definição: ENUM('assistencia','reboque') NULL
    origem: decisao.recomendacao
    exposição: p.* após migration.

  origem_funil:
    existe: não
    definição: ENUM('oficina','especialista') NULL
    origem: fluxo que iniciou/criou o pedido
    legado: NULL; a UI deve exibir “—”.
    exposição: p.* em Pedido::buscarPorId() e Pedido::listarPorStatus() após migration.

  worklist_admin:
    confirmação: Pedido::listarPorStatus() seleciona p.*; origem_funil ficará disponível
    sem JOIN adicional após a migration.
ESTADO_FAIXA_A:
  atualizado_em: 2026-10-03
  pagamento_admin_v1:
    status: entregue_para_integracao
    implementacao:
      classe: Pagamento
      metodo: aplicarDestinoPagamento
      assinatura: "aplicarDestinoPagamento(int $pedidoId, string $destino, ?string $provedor = null, ?int $adminId = null): array"
      destinos: [pago_agora, pago_na_chegada, online, sem_cobranca]
      gateway_online: PaymentProviderFactory::gatewayAtivoRaw
      idempotencia: "mesma entrada nao duplica pagamento nem evento; troca de destino e bloqueada"
      link: "somente online: /pagamento/checkout/{pedidoId}"
      eventos:
        pago_agora: pagamento_baixa_manual_registrada
        pago_na_chegada: pagamento_na_chegada_definido
        online: pagamento_online_definido
        sem_cobranca: pagamento_isento
    arquivos:
      alterar: [src/Models/Pagamento.php]
      criar:
        - install/migration_admin_payment_destination_v1.sql
        - install/rollback_admin_payment_destination_v1.sql
    pendencias:
      - "Aplicar a migration no banco antes da integracao da faixa B."
      - "B consome somente o retorno do metodo; A nao altera AdminController, views, CSS ou rotas."

  financeiro_parceiros_v1:
    status: entregue_para_validacao
    data: 2026-10-08
    preambulo_6_0: "Aplicado: congelar valores financeiros na transição canônica; operações repetidas não podem recalcular ou duplicar lançamento."
    implementacao:
      migration: install/migration_comissao_v1.sql
      rollback: "install/rollback_comissao_v1.sql (idempotente; só remove estrutura se não houver dados)"
      comissao: ComissaoService::calcular e ::persistirParaPedido
      ponto_canonico: "PedidoTransitionService::transition(targetStatus=concluido)."
      fatura: "FaturaService::fecharCiclo, ::marcarPaga, ::bloquearPorVencimento e ::desbloquear"
      pix: PixFaturaService::gerar
      webhook: FaturaWebhookController::mercadoPagoPix
      crons:
        - "domingo 00:01: php cron/fechar_ciclos_semanais.php"
        - "sábado 00:01: php cron/aplicar_bloqueios_vencidos.php"
    rota_pedida_ao_dono: |
      ROTA_PEDIDA:
        faixa: A
        metodo: POST
        caminho: /webhook/mercadopago/pix
        controller: FaturaWebhookController::mercadoPagoPix
        motivo: baixa idempotente de fatura PIX após HMAC Mercado Pago
## Pendência: Execução de `relevo_v1` + `prioridade_v1` (Faixa A)

**Data:** 2026-10-10
**Solicitante:** Dono (via D15, D16, D17)
**Faixa executora:** A
**Contratos base:** `doc/CONTRATOS.md` §"Contrato: Fator de Relevo" (relevo_v1) e §"Contrato: Regras de Vulnerabilidade" (prioridade_v1)
**Status:** Aguardando liberação do dono para iniciar.

### Escopo autorizado

1. Criar `install/migration_via_segmentos_v1.sql` (idempotente via `INFORMATION_SCHEMA`).
2. Criar `install/migration_auditoria_prioridade_v1.sql` (adiciona os campos de snapshot em `pedidos`).
3. Criar `src/Services/RelevoService.php` com a interface abaixo.
4. Criar `src/Services/PrioridadeService.php` com a interface abaixo.
5. Integrar `RelevoService` em `PricingService::calcularReboque()`.
6. Integrar `PrioridadeService` em `PedidoCoreService::criar()` (persistência dos campos de prioridade).
7. Escrever testes unitários em `tests/Unit/` para `RelevoService` e `PrioridadeService` (a Faixa C valida E2E).

### Interface pública — `RelevoService`

```php
namespace App\Services;

final class RelevoService
{
    /**
     * Retorna o fator de relevo para as coordenadas de origem.
     *
     * - Busca em `via_segmentos` num raio de 50m.
     * - Se não encontrar, retorna 1.00 com `segmento_encontrado = false`.
     * - Se a tabela estiver indisponível, loga RELEVO-LOOKUP-FAIL e retorna 1.00.
     *
     * @return array{
     *   fator_relevo: float,
     *   inclinacao_graus: ?float,
     *   segmento_encontrado: bool,
     *   origem_fator: string,
     *   via_segmento_id: ?int
     * }
     */
    public function fatorPorOrigem(float $lat, float $lng): array;
}
## Estado — Contrato Pedido v1 e motor de preço

- `DecisaoAtendimentoService` consolidado como motor de decisão; reboque delega
  para `TarifaService` e `PricingService` atua somente como adaptador legado.
- `Pedido::registrarSnapshotDecisao()` persiste oficina, triagem, custos e
  recomendação sem sobrescrever o snapshot inicial.
- Migration idempotente preparada para o contrato do pedido, unicidade de
  `triage_session_id` e de `(provedor, event_id)` em `logs_webhook`.
- `PagamentoController` libera MP-CONFIG-03 somente para `localhost` e
  `127.0.0.1`; o checkout transparente usa `ped{id}-t{tentativa}`.
- `WebhookController` valida HMAC antes de processar e usa a unicidade de
  `(provedor, event_id)` para responder `200` a eventos repetidos sem nova
  aprovação.

## Estado — Precificação §5/§6 v20261013

- Motor oficial em `PrecificacaoService`, com regra por zona, `service_type_id`
  e `vehicle_category`.
- ORS integrado via `RoutingService`, com fallback OSRM/Haversine e
  `fonte_km` no detalhamento.
- Pedágio fora da comissão: `base_comissao` exclui `pedagio`; `preco_cliente`
  soma ambos.
- Regra de carro validada com `service_price_rules.id=35`, versão `20261013`;
  regra de moto validada com `id=37` e fator 0,70.
- Vetor Nazaré Paulista -> Campinas validado com ORS retornando 80,63 km:
  `200 + (80,63 * 5,80) + 27,20 = 694,85`.
- Divergência conhecida contra auditor antigo: auditor esperava 93 km
  registrado na `PRECIFICACAO.md` v1.0; ORS escolheu rota mais curta. Aceito
  como comportamento correto para cobrança por km salvo decisão futura por
  waypoints.

