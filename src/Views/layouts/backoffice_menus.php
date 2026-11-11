<?php
return [
 'admin' => [
  ['section'=>'PRINCIPAL'],
  ['label'=>'Criar pedido','href'=>'/admin/pedido/novo','icon'=>'plus-circle','match'=>['/admin/pedido/novo']],
  ['label'=>'Operação','href'=>'/admin/central','icon'=>'radio-tower','match'=>['/admin/central','/admin/pedidos','/admin/despacho','/admin/ocorrencias'],'children'=>[['label'=>'Pedidos','href'=>'/admin/pedidos','icon'=>'list-checks','match'=>['/admin/pedidos']],['label'=>'Despacho','href'=>'/admin/despacho','icon'=>'send','match'=>['/admin/despacho']],['label'=>'Ocorrências','href'=>'/admin/ocorrencias','icon'=>'triangle-alert','match'=>['/admin/ocorrencias']]]],
  ['label'=>'Pessoas e parceiros','href'=>'/admin/usuarios','icon'=>'users','match'=>['/admin/usuarios','/admin/guinchos','/admin/prestadores','/admin/oficinas-parceiras','/admin/especialistas']],
  ['label'=>'Financeiro','href'=>'/admin/financeiro','icon'=>'wallet','match'=>['/admin/financeiro','/admin/faturas','/admin/carteiras','/admin/saques'],'children'=>[['label'=>'Carteiras','href'=>'/admin/carteiras','icon'=>'wallet','match'=>['/admin/carteiras']]]],
  ['label'=>'Catálogo e preços','href'=>'/admin/catalogo-servicos/tipos','icon'=>'wrench','match'=>['/admin/catalogo-servicos','/admin/catalogo-veiculos','/admin/produtos','/admin/servicos','/admin/precificacao'],'children'=>[['label'=>'Serviços','href'=>'/admin/catalogo-servicos/tipos','icon'=>'wrench','match'=>['/admin/catalogo-servicos']],['label'=>'Veículos','href'=>'/admin/catalogo-veiculos','icon'=>'car','match'=>['/admin/catalogo-veiculos']],['label'=>'Produtos','href'=>'/admin/produtos','icon'=>'package','match'=>['/admin/produtos']]]],
  ['label'=>'Território','href'=>'/admin/cidades','icon'=>'map','match'=>['/admin/cidades','/admin/demanda-territorial','/admin/planejamento']],
  ['label'=>'Marketing','href'=>'/admin/marketing','icon'=>'megaphone','match'=>['/admin/marketing','/admin/prospeccao']],
  ['label'=>'Qualidade','href'=>'/admin/avaliacoes','icon'=>'shield-check','match'=>['/admin/avaliacoes','/admin/proof-of-road','/admin/checklists-incompletos'],'children'=>[['label'=>'Avaliações','href'=>'/admin/avaliacoes','icon'=>'star','match'=>['/admin/avaliacoes']],['label'=>'Proof-of-Road','href'=>'/admin/proof-of-road','icon'=>'route','match'=>['/admin/proof-of-road']],['label'=>'Checklists incompletos','href'=>'/admin/checklists-incompletos','icon'=>'clipboard-check','match'=>['/admin/checklists-incompletos']]]],
  ['section'=>'CONTA'],
  ['label'=>'Sistema','href'=>'/admin/configuracoes','icon'=>'settings','match'=>['/admin/configuracoes','/admin/logs','/admin/health'],'children'=>[
   ['label'=>'Logs','href'=>'/admin/logs','icon'=>'scroll-text','match'=>['/admin/logs']],
  ]],
 ],
 'funcionario' => [
  ['section'=>'PRINCIPAL'],['label'=>'Painel','href'=>'/funcionario/dashboard','icon'=>'layout-dashboard','match'=>['/funcionario/dashboard']],['label'=>'Pedidos','href'=>'/funcionario/pedidos','icon'=>'list-checks','match'=>['/funcionario/pedidos']],['label'=>'Financeiro','href'=>'/funcionario/financeiro','icon'=>'wallet','match'=>['/funcionario/financeiro']],['label'=>'Minhas demandas','href'=>'/funcionario/demandas','icon'=>'send','match'=>['/funcionario/demandas']],
 ],
 'gerente' => [
  ['section'=>'PRINCIPAL'],['label'=>'Painel','href'=>'/gerente/dashboard','icon'=>'layout-dashboard','match'=>['/gerente/dashboard']],['label'=>'Demandas pendentes','href'=>'/gerente/demandas','icon'=>'clipboard-check','match'=>['/gerente/demandas']],
 ],
 'guincho' => [
  ['section'=>'PRINCIPAL'],['label'=>'Painel','href'=>'/guincho/dashboard','icon'=>'layout-dashboard','match'=>['/guincho/dashboard']],['label'=>'Ofertas','href'=>'/guincho/pedidos','icon'=>'bell','match'=>['/guincho/pedidos','/guincho/pedidos-disponiveis']],['label'=>'Histórico','href'=>'/guincho/historico','icon'=>'history','match'=>['/guincho/historico']],['label'=>'Financeiro','href'=>'/guincho/financeiro','icon'=>'wallet','match'=>['/guincho/financeiro']],['section'=>'CONTA'],['label'=>'Operação','href'=>'/guincho/operacao','icon'=>'truck','match'=>['/guincho/operacao']],['label'=>'Serviços que ofereço','href'=>'/guincho/capacidades','icon'=>'wrench','match'=>['/guincho/capacidades']],['label'=>'Meu perfil','href'=>'/guincho/perfil','icon'=>'user-round','match'=>['/guincho/perfil','/guincho/bancario']],
 ],
 'oficina' => [
  ['section'=>'PRINCIPAL'],['label'=>'Painel','href'=>'/oficina/dashboard','icon'=>'layout-dashboard','match'=>['/oficina/dashboard']],['label'=>'Ofertas','href'=>'/oficina/pedidos','icon'=>'bell','match'=>['/oficina/pedidos']],['label'=>'Histórico','href'=>'/oficina/historico','icon'=>'history','match'=>['/oficina/historico']],['label'=>'Financeiro','href'=>'/oficina/financeiro','icon'=>'wallet','match'=>['/oficina/financeiro']],['section'=>'CONTA'],['label'=>'Meu perfil','href'=>'/oficina/perfil','icon'=>'user-round','match'=>['/oficina/perfil']],
 ],
 'cliente' => [
  ['section'=>'PRINCIPAL'],['label'=>'Painel','href'=>'/cliente/dashboard','icon'=>'layout-dashboard','match'=>['/cliente/dashboard']],['label'=>'Pedir socorro','href'=>'/cliente/pedido/novo','icon'=>'truck','match'=>['/cliente/pedido']],['label'=>'Histórico','href'=>'/cliente/historico','icon'=>'history','match'=>['/cliente/historico']],['label'=>'Financeiro','href'=>'/cliente/financeiro','icon'=>'wallet','match'=>['/cliente/financeiro']],['section'=>'CONTA'],['label'=>'Veículos','href'=>'/cliente/veiculos','icon'=>'car','match'=>['/cliente/veiculo','/cliente/veiculos']],['label'=>'Oficinas','href'=>'/cliente/oficinas','icon'=>'wrench','match'=>['/cliente/oficina','/cliente/oficinas']],['label'=>'Meu perfil','href'=>'/cliente/perfil','icon'=>'user-round','match'=>['/cliente/perfil']],
 ],
 'especialista' => [],
 'parceiro' => [],
];
