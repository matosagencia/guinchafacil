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
