# Inventário automatizado do backoffice

| Rota | Controller | Action | Perfil | Tipo | View localizada |
|---|---|---|---|---|---|
| /admin/alertas | AdminController | alertasOperacionais | admin | html | /../Views/admin/alertas_operacionais.php |
| /admin/avaliacoes | AdminController | avaliacoes | admin | html | /../Views/admin/avaliacoes.php |
| /admin/carteiras | AdminController | carteiras | admin | html | /../Views/admin/carteiras.php |
| /admin/catalogo-servicos/capacidades | AdminServiceCatalogController | capacidades | admin | html | /../Views/admin/catalogo_servicos_capacidades.php |
| /admin/catalogo-servicos/compatibilidade | AdminServiceCatalogController | compatibilidade | admin | html | /../Views/admin/catalogo_servicos_compatibilidade.php |
| /admin/catalogo-servicos/tarifas | AdminServiceCatalogController | tarifas | admin | html | /../Views/admin/catalogo_servicos_tarifas.php |
| /admin/catalogo-servicos/tipo/novo | AdminServiceCatalogController | tipoForm | admin | html | /../Views/admin/catalogo_servicos_tipo_form.php |
| /admin/catalogo-servicos/tipos | AdminServiceCatalogController | tipos | admin | html | /../Views/admin/catalogo_servicos_tipos.php |
| /admin/catalogo-veiculos | AdminVehicleCatalogController | marcas | admin | html | /../Views/admin/catalogo_veiculos_marcas.php |
| /admin/catalogo-veiculos/marca/novo | AdminVehicleCatalogController | marcaForm | admin | html | /../Views/admin/catalogo_veiculos_marca_form.php |
| /admin/catalogo-veiculos/modelo/novo | AdminVehicleCatalogController | modeloForm | admin | html | /../Views/admin/catalogo_veiculos_modelo_form.php |
| /admin/catalogo-veiculos/versao/novo | AdminVehicleCatalogController | versaoForm | admin | html | /../Views/admin/catalogo_veiculos_versao_form.php |
| /admin/central | AdminController | centralOperacional | admin | html | /../Views/admin/central_operacional.php |
| /admin/chat | AdminChatController | index | admin | html | /../Views/admin/pedidodetalhe.php |
| /admin/checklists-incompletos | AdminProofOfServiceController | checklistsIncompletos | admin | html | /../Views/admin/proof_of_service_incompletos.php |
| /admin/cidade/excluir | AdminController | cidadeExcluir | admin | html | não localizada |
| /admin/cidades | AdminController | cidades | admin | html | /../Views/admin/cidades.php |
| /admin/comunicado/novo | AdminComunicadoController | form | admin | html | /../Views/admin/comunicados/form.php |
| /admin/comunicados | AdminComunicadoController | index | admin | html | /../Views/admin/comunicados/index.php |
| /admin/configuracoes | AdminController | configuracoes | admin | html | /../Views/admin/configuracoes.php |
| /admin/dashboard | AdminController | dashboard | admin | html | /../Views/admin/dashboard.php |
| /admin/demanda-territorial | AdminPricingZoneController | demandaTerritorial | admin | html | /../Views/admin/demanda_territorial.php |
| /admin/despacho | AdminController | despacho | admin | html | /../Views/admin/despacho.php |
| /admin/documentos | AdminController | documentos | admin | html | /../Views/admin/documentos.php |
| /admin/env | AdminController | configuracoes | admin | html | /../Views/admin/configuracoes.php |
| /admin/env/auditoria | AdminEnvAuditController | index | admin | html | /../Views/admin/env_auditoria.php |
| /admin/especialista/documento-status | AdminController | especialistaDocumentoStatus | admin | html | não localizada |
| /admin/especialistas | AdminController | especialistas | admin | html | /../Views/admin/especialistas.php |
| /admin/especialistas/cadastrar | AdminController | especialistaCadastroForm | admin | html | /../Views/admin/especialista_form.php |
| /admin/faturas | AdminController | faturas | admin | html | /../Views/admin/faturas.php |
| /admin/feriados | AdminController | feriados | admin | html | /../Views/admin/feriados.php |
| /admin/financeiro | AdminController | financeiro | admin | html | /../Views/admin/financeiro.php |
| /admin/financeiro/bypass-alertas | OficinaParceriaController | bypassCasos | admin | json-or-mixed | não localizada |
| /admin/financeiro/bypass-alertas/decidir | OficinaParceriaController | bypassDecidir | admin | json-or-mixed | não localizada |
| /admin/financeiro/compensacao | Fin2ApuracaoController | index | admin | json-or-mixed | não localizada |
| /admin/financeiro/marketing/excluir | AdminFinanceAttributionController | gastoExcluir | admin | html | não localizada |
| /admin/financeiro/marketing/gasto | AdminFinanceAttributionController | gastoSalvar | admin | html | não localizada |
| /admin/financeiro/marketing/importar | AdminFinanceAttributionController | gastoImportar | admin | html | não localizada |
| /admin/financeiro/visao-unificada | AdminFinanceAttributionController | visaoUnificada | admin | html | /../Views/admin/financeiro_atribuicao.php |
| /admin/guincho/novo | AdminController | guinchoNovoForm | admin | html | /../Views/admin/guinchoform.php |
| /admin/guinchos | AdminController | guinchos | admin | html | /../Views/admin/guinchos.php |
| /admin/guinchospendentes | AdminController | guinchosPendentes | admin | html | /../Views/admin/guinchospendentes.php |
| /admin/health | AdminHealthController | health | admin | html | /../Views/admin/health.php |
| /admin/logs | AdminLogsController | index | admin | html | /../Views/admin/logs_v2.php |
| /admin/logs/export | AdminController | logsExport | admin | download | não localizada |
| /admin/marketing | AdminFinanceAttributionController | marketingCentral | admin | html | /../Views/admin/marketing_central.php |
| /admin/marketing/campanha/salvar | AdminFinanceAttributionController | campanhaSalvar | admin | html | não localizada |
| /admin/marketing/comissoes-indicacao | AdminMarketingReferralController | commissions | admin | html | /../Views/admin/marketing_comissoes_indicacao.php |
| /admin/marketing/eventos | AdminMarketingEventsController | index | admin | html | /../Views/admin/marketing_eventos.php |
| /admin/marketing/prospeccao/buscar | AdminFinanceAttributionController | prospeccaoBuscar | admin | html | não localizada |
| /admin/marketing/prospeccao/regioes/salvar | AdminFinanceAttributionController | prospeccaoRegiaoSalvar | admin | html | não localizada |
| /admin/marketing/prospeccao/sincronizar-zonas | AdminFinanceAttributionController | prospeccaoSincronizarZonas | admin | html | não localizada |
| /admin/marketing/referrals | AdminMarketingReferralController | index | admin | html | /../Views/admin/marketing_referrals.php |
| /admin/ocorrencia/criar | AdminController | ocorrenciaCriar | admin | html | não localizada |
| /admin/ocorrencia/resolver | AdminController | ocorrenciaResolver | admin | html | não localizada |
| /admin/ocorrencias | AdminController | ocorrencias | admin | html | /../Views/admin/ocorrencias.php |
| /admin/oficinas-parceiras | OficinaParceriaController | admin | admin | html | /../Views/admin/oficinas_parceiras.php |
| /admin/pedido/criar | AdminController | pedidoCriar | admin | html | não localizada |
| /admin/pedido/custo | AdminController | pedidoCalcularCusto | admin | json-or-mixed | não localizada |
| /admin/pedido/novo | AdminController | pedidoNovoV2 | admin | html | /../Views/admin/pedidonovo_etapa1.php |
| /admin/pedido/novo/funil | AdminController | pedidoNovoFunil | admin | html | /../Views/admin/pedidonovo_funil.php |
| /admin/pedido/novo/v2 | AdminController | pedidoNovoV2 | admin | html | /../Views/admin/pedidonovo_etapa1.php |
| /admin/pedidos | AdminController | pedidos | admin | html | /../Views/admin/pedidos.php |
| /admin/planejamento | AdminPlanejamentoController | index | admin | html | /../Views/admin/planejamento.php |
| /admin/precificacao/zonas | AdminPricingZoneController | zonas | admin | html | /../Views/admin/precificacao_zonas.php |
| /admin/prestadores | AdminController | prestadores | admin | html | /../Views/admin/prestadores.php |
| /admin/prestadores-moveis | AdminController | prestadoresMoveis | admin | html | /../Views/admin/prestadores_moveis.php |
| /admin/produto/novo | AdminProdutoController | form | admin | html | /../Views/admin/produtoform.php |
| /admin/produtos | AdminProdutoController | index | admin | html | /../Views/admin/produtos.php |
| /admin/proof-of-road | AdminController | proofOfRoad | admin | html | /../Views/admin/proof_of_road.php |
| /admin/prospeccao | AdminFinanceAttributionController | marketingCentral | admin | html | /../Views/admin/marketing_central.php |
| /admin/prospeccao/buscar | AdminFinanceAttributionController | prospeccaoBuscar | admin | html | não localizada |
| /admin/prospeccao/regioes | AdminFinanceAttributionController | marketingCentral | admin | html | /../Views/admin/marketing_central.php |
| /admin/prospeccao/regioes/salvar | AdminFinanceAttributionController | prospeccaoRegiaoSalvar | admin | html | não localizada |
| /admin/prospeccao/sincronizar-zonas | AdminFinanceAttributionController | prospeccaoSincronizarZonas | admin | html | não localizada |
| /admin/repasses/demanda | GFPayoutDecisionController | form | gerente | html | não localizada |
| /admin/repasses/demandas | GFPayoutDecisionController | listing | gerente | html | não localizada |
| /admin/repasses/recuperacao | GFPayoutRecoveryController | form | admin | html | /../Views/admin/fin3_recuperacao_repasse.php |
| /admin/repasses/triagem | GFPayoutReviewController | form | admin | html | /../Views/admin/fin3_triagem_repasse.php |
| /admin/revisoes-manuais | AdminManualReviewsController | index | admin | html | /../Views/admin/revisoes_manuais.php |
| /admin/saques | AdminController | saques | admin | html | /../Views/admin/saques.php |
| /admin/servico/novo | AdminController | servicoForm | admin | html | /../Views/admin/servicoform.php |
| /admin/servicos | AdminController | servicos | admin | html | não localizada |
| /admin/simulador | AdminController | simulador | admin | html | /../Views/admin/simulacao.php |
| /admin/usuario/novo | AdminController | usuarioForm | admin | html | /../Views/admin/usuarioform.php |
| /admin/usuario/suspender | AdminController | usuariosSuspenderGet | admin | html | não localizada |
| /admin/usuarios | AdminController | usuarios | admin | html | /../Views/admin/usuarios.php |
| /cliente/dashboard | ClienteController | dashboard | cliente | html | /../Views/cliente/dashboard.php |
| /cliente/financeiro | ClienteController | financeiro | cliente | html | /../Views/cliente/financeiro.php |
| /cliente/historico | ClienteController | historico | cliente | html | /../Views/cliente/historico.php |
| /cliente/oficina/nova | ClienteController | oficinaForm | cliente | html | /../Views/cliente/oficinaform.php |
| /cliente/oficinas | ClienteController | oficinas | cliente | html | /../Views/cliente/oficinas.php |
| /cliente/oficinas-parceiras | OficinaParceriaController | clienteOficinas | cliente | json-or-mixed | não localizada |
| /cliente/oficinas/nova | ClienteController | oficinaForm | cliente | html | /../Views/cliente/oficinaform.php |
| /cliente/pedido/custo | ClienteController | calcularCusto | cliente | json-or-mixed | não localizada |
| /cliente/pedido/novo | ClienteController | pedidoNovo | cliente | html | /../Views/cliente/pedidonovo.php |
| /cliente/pedido/rascunho | ClienteController | pedidoRascunho | cliente | json-or-mixed | não localizada |
| /cliente/perfil | ClienteController | perfilForm | cliente | html | /../Views/cliente/perfil.php |
| /cliente/triagem | ClienteController | triagem | cliente | html | /../Views/cliente/triagem.php |
| /cliente/triagem/resultado | ClienteController | triagemResultado | cliente | html | /../Views/cliente/triagem_resultado.php |
| /cliente/veiculo/novo | ClienteController | veiculoForm | cliente | html | /../Views/cliente/veiculoform.php |
| /cliente/veiculos | ClienteController | veiculos | cliente | html | /../Views/cliente/veiculos.php |
| /cliente/veiculos/novo | ClienteController | veiculoForm | cliente | html | /../Views/cliente/veiculoform.php |
| /guincho/bancario | GuinchoController | perfilBancarioForm | guincho | html | /../Views/guincho/perfil_bancario.php |
| /guincho/capacidades | GuinchoController | capacidades | guincho | html | /../Views/guincho/capacidades.php |
| /guincho/dashboard | GuinchoController | dashboard | guincho | html | /../Views/guincho/dashboard.php |
| /guincho/estoque | GuinchoController | estoque | guincho | html | /../Views/guincho/estoque.php |
| /guincho/financeiro | GuinchoController | financeiro | guincho | html | /../Views/guincho/financeiro.php |
| /guincho/historico | GuinchoController | historico | guincho | html | /../Views/guincho/historico.php |
| /guincho/operacao | GuinchoController | perfilOperacaoForm | guincho | html | /../Views/guincho/perfil_operacao.php |
| /guincho/pedidos | GuinchoController | pedidosDisponiveis | guincho | json-or-mixed | não localizada |
| /guincho/pedidos-disponiveis | GuinchoController | pedidosDisponiveis | guincho | json-or-mixed | não localizada |
| /guincho/perfil | GuinchoController | perfilForm | guincho | html | /../Views/guincho/perfil.php |
| /guincho/tornar-se-guincho | GuinchoController | tornarSeGuincho | guincho | html | /../Views/guincho/tornarse_guincho.php |
| /oficina/dashboard | OficinaController | dashboard | oficina | html | /../Views/oficina/dashboard.php |
| /oficina/financeiro | OficinaController | financeiro | oficina | html | /../Views/oficina/financeiro.php |
| /oficina/historico | OficinaController | historico | oficina | html | /../Views/oficina/historico.php |
| /oficina/pedidos | OficinaController | pedidosPage | oficina | html | /../Views/oficina/pedidos.php |
| /oficina/pedidos-disponiveis | OficinaController | pedidosDisponiveis | oficina | json-or-mixed | não localizada |
| /oficina/perfil | OficinaController | perfilForm | oficina | html | /../Views/oficina/perfil.php |