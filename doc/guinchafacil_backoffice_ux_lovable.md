# GuinchaFácil — Inventário do backoffice e proposta de reorganização de UX/UI

**Documento de briefing para o Lovable**  
**Base analisada:** snapshot do repositório enviado em ZIP, com leitura das rotas em `index.php`, sidebars, views PHP e folhas de estilo referenciadas.  
**Objetivo:** mapear páginas e funções dos perfis Admin, Guincho e Oficina; registrar o design atual que é possível inferir pelo código; propor uma arquitetura de navegação e consolidação sem alterar regras de negócio.

> **Limite da análise:** não foi executada a aplicação nem foram fornecidas capturas de tela. “Design atual” descreve estrutura e padrões encontrados nos templates/CSS, não uma auditoria visual pixel a pixel. Algumas views existem sem rota inequívoca no roteador; nesses casos, a rota é marcada para confirmação. O ZIP contém configurações locais e dados que não foram usados nesta análise.

## 1. Resumo executivo

- O sistema já possui uma base visual comum: Bootstrap, Font Awesome, `style.css`, shell/sidebar por perfil, tokens/base/shell e temas de perfil. O cabeçalho define temas de backoffice: Admin `executive`, Oficina `workshop`, Guincho `driver`.
- O Admin tem muitas páginas que se sobrepõem: Central Operacional × Pedidos × Despacho × Detalhe do Pedido; Prestadores × Guinchos Pendentes × Documentos × detalhes individuais; Financeiro × Faturas × Saques × Carteiras; catálogo legado × catálogo estruturado; Marketing × Prospecção × Demanda Territorial × Planejamento.
- Guincho e Oficina têm estruturas semelhantes para operação: Painel, Ofertas, Atendimento, Histórico, Financeiro e Perfil. Devem compartilhar o design system e padrões de fluxo, sem forçar os mesmos campos de negócio.
- A maior oportunidade não é simplesmente apagar rotas: é criar páginas-hub com abas, filtros predefinidos, workspaces de detalhe e ações contextuais, mantendo endpoints e permissões existentes.
- Não alterar regras de preço, matching, status, permissões, pagamentos ou contratos de API como parte do redesenho. Fazer primeiro um mapa de equivalência URL atual → destino novo.

## 2. Design atual — padrões encontrados

### 2.1 Fundação visual
- `layouts/header.php` carrega Bootstrap local, Font Awesome e `public/assets/css/style.css`; também referencia `tokens.css`, `base.css`, `shell.css`, `gf-layout.css`, temas por perfil e CSS específico do Admin. Parte das referências está dentro de comentários no snapshot, portanto confirmar o que realmente é carregado no navegador.
- Admin: sidebar operacional (`sidebar_admin.php` inclui `admin_nav_operacional.php`), topbar própria com breadcrumb/ordenação e CSS específico `gf-admin-topbar-crumb-v1.css`; existe persistência local para sidebar recolhida (`gf_admin_sidebar_collapsed`).
- Guincho: sidebar dividida em “Operação” e “Conta”; links Painel, Ofertas, Histórico, Financeiro, Operação, Serviços que ofereço, Tornar-se guincheiro, Meu estoque e Meu Perfil.
- Oficina: sidebar “Operação” (Painel, Ofertas, Histórico, Financeiro) e “Conta” (Meu Perfil).
- Views PHP misturam páginas clássicas, tabelas densas, cards, formulários longos, componentes JS dinâmicos e páginas de operação em tempo real. Existem páginas/versões paralelas e formulários que parecem duplicados.
- Design de campo: guincho/oficina precisam funcionar em celular com rede instável e uso com uma mão; Admin é desktop-first, mas responsivo.

### 2.2 Problemas de consistência a validar no navegador
- Nomes e conceitos não são uniformes: “Pedidos”, “Ofertas”, “Atendimentos”, “Despacho”, “Central operacional”. A navegação deve explicar a diferença e evitar duas entradas para a mesma fila.
- Há páginas grandes e densas (detalhe de pedido, dashboard guincho, atendimento guincho) que precisam de hierarquia visual, seções recolhíveis/abas e CTA persistente.
- O Admin contém views alternativas (`logs.php`/`logs_v2.php`) e catálogo legado (`servicos.php`) junto do catálogo estruturado (`catalogo-servicos/*`). Validar uso real antes de descontinuar.
- Os estados críticos devem ser visualmente distintos: aguardando, disponível, atribuído, em deslocamento, no local, em execução, aguardando orçamento, concluído, cancelado, pendência documental, pagamento pendente, falha de repasse.

## 3. Inventário detalhado — perfil Admin

### 3.1 Operação e pedidos

#### Dashboard / visão geral
- **Rota/entrada:** `/admin/dashboard`
- **O que contém e faz:** Visão em tempo real da plataforma; filtros por cidade/zona e indicadores/mapa. É a visão analítica de entrada, distinta da central de operação em tempo real.
- **Design atual observado:** Visão executiva com KPIs, filtros e área de mapa/gráficos; tende a concentrar informação, podendo competir com a Central Operacional.

#### Central operacional
- **Rota/entrada:** `/admin/central`
- **O que contém e faz:** Painel operacional com pedidos ativos, estados e ações rápidas para acompanhar atendimentos que estão em andamento.
- **Design atual observado:** Fila operacional em cards/listas dinâmicas; deve ser a tela principal de despacho e acompanhamento ao vivo.

#### Alertas operacionais
- **Rota/entrada:** `/admin/alertas`
- **O que contém e faz:** Agrupa alertas por categoria/grupo e permite localizar ocorrências que exigem atenção.
- **Design atual observado:** Interface de grupos/itens pesquisáveis, com badges e ações contextuais.

#### Despacho
- **Rota/entrada:** `/admin/despacho`
- **O que contém e faz:** Localiza guincho para atribuir a um pedido; permite selecionar prestador e efetivar atribuição.
- **Design atual observado:** Tela de tarefa focada em pedido + busca/seleção de prestador; muito próxima da central operacional e do detalhe do pedido.

#### Pedidos — gestão
- **Rota/entrada:** `/admin/pedidos`
- **O que contém e faz:** Lista e filtra pedidos; inclui informações de estado e ferramentas como link de pagamento/cópia de link.
- **Design atual observado:** Tabela/lista densa, filtros e ações por pedido. Há também detalhe de pedido separado.

#### Detalhe do pedido
- **Rota/entrada:** `/admin/pedido/{id} (navegação/handler deve ser conferido)`
- **O que contém e faz:** Visão detalhada do pedido, status, atribuição, comprovantes, revisão manual, conclusão assistida e reprocessamento de repasse.
- **Design atual observado:** Workspace longo com várias seções e formulários; pode ser excessivamente extenso e juntar operações, auditoria e financeiro.

#### Criar pedido — fluxo legado/alternativo
- **Rota/entrada:** `/admin/pedido/novo; /admin/pedido/novo/v2`
- **O que contém e faz:** Cadastro de solicitação por admin, com tipo de serviço, condições do veículo, origem/destino e dados necessários à cotação.
- **Design atual observado:** Formulário rico com mapa/endereço e muitos campos; existem variantes de tela/rota e etapa inicial.

#### Criar pedido — etapa 1
- **Rota/entrada:** `/admin/pedido/novo/contexto ou fluxo associado`
- **O que contém e faz:** Identificação do cliente e veículo, cadastro rápido de cliente/veículo e avanço para o funil.
- **Design atual observado:** Wizard com etapas e formulários auxiliares; sobrepõe-se ao fluxo de criação do pedido e ao funil.

#### Criar pedido — funil
- **Rota/entrada:** `/admin/pedido/novo/funil`
- **O que contém e faz:** Coleta localização, disponibilidade e informações de atendimento para construir o pedido.
- **Design atual observado:** Etapa visual de funil; deve compartilhar componentes e estado com o formulário principal.

#### Trilha do pedido / POR
- **Rota/entrada:** `/admin/pedido/{id}/trilha (rota exata a confirmar)`
- **O que contém e faz:** Linha de auditoria e validações Proof-of-Road para um pedido, com fases e critérios.
- **Design atual observado:** Tabela cronológica/técnica, mais indicada como aba do detalhe do pedido.

#### Ocorrências
- **Rota/entrada:** `/admin/ocorrencias`
- **O que contém e faz:** Registra e consulta incidentes ligados a pedidos, com tipo, severidade e descrição; inclui resolução/edição.
- **Design atual observado:** Lista com filtros e formulário; relacionado a alertas e ao detalhe do pedido.

#### Avaliações
- **Rota/entrada:** `/admin/avaliacoes`
- **O que contém e faz:** Consulta avaliações, com filtros por guincho e nota.
- **Design atual observado:** Tabela simples com filtros; relaciona-se à qualidade/reputação do prestador.

#### Documentos
- **Rota/entrada:** `/admin/documentos`
- **O que contém e faz:** Consulta documentos enviados por prestadores, estado de análise e tipos como CNH.
- **Design atual observado:** Fila documental; há também documentos/abas dentro de prestadores e especialistas.

#### Proof-of-Road
- **Rota/entrada:** `/admin/proof-of-road`
- **O que contém e faz:** Consulta métricas/qualidade POR por período e pedido.
- **Design atual observado:** Tela de relatório/indicadores; pode ser consolidada com trilha e checklists.

### 3.2 Prestadores, usuários e parceiros

#### Checklists incompletos
- **Rota/entrada:** `/admin/checklists-incompletos`
- **O que contém e faz:** Fila de checklists/evidências incompletos por pedido.
- **Design atual observado:** Tabela de exceções operacionais; deve alimentar alertas e abrir a seção relevante no detalhe do pedido.

#### Prestadores — hub
- **Rota/entrada:** `/admin/prestadores`
- **O que contém e faz:** Hub com abas de guinchos e documentos, pendências e detalhes do prestador.
- **Design atual observado:** Interface com abas internas, busca e cards/listas; sobrepõe-se a Guinchos, Pendentes e Documentos.

#### Guinchos
- **Rota/entrada:** `/admin/guinchos`
- **O que contém e faz:** Lista de guincheiros cadastrados, estado e reputação; acesso ao detalhe.
- **Design atual observado:** Listagem de prestadores com busca e status.

#### Guinchos pendentes
- **Rota/entrada:** `/admin/guinchospendentes`
- **O que contém e faz:** Fila de guinchos aguardando aprovação, com aprovar/rejeitar.
- **Design atual observado:** Fila de revisão; deveria ser uma visão filtrada do hub Prestadores.

#### Detalhe do guincho
- **Rota/entrada:** `/admin/guincho/{id} (rota exata a confirmar)`
- **O que contém e faz:** Workspace do guincho com status de aprovação, dados e ações aprovar/rejeitar.
- **Design atual observado:** Detalhe por abas/seções; compartilha estrutura com detalhes de usuário/especialista.

#### Cadastrar guincho
- **Rota/entrada:** `/admin/guincho/novo`
- **O que contém e faz:** Cadastro administrativo de guincheiro e dados de acesso/identificação.
- **Design atual observado:** Formulário dedicado; deve ser uma ação dentro de Prestadores.

#### Usuários
- **Rota/entrada:** `/admin/usuarios`
- **O que contém e faz:** Lista usuários e seus tipos/status; abre detalhe e edição.
- **Design atual observado:** Tabela pesquisável com ações por registro.

#### Criar usuário
- **Rota/entrada:** `/admin/usuario/novo`
- **O que contém e faz:** Cadastro de usuário por tipo, dados pessoais e credenciais.
- **Design atual observado:** Formulário; pode ser ação de Usuários, mantendo variação por perfil.

#### Editar usuário
- **Rota/entrada:** `/admin/usuario/{id}/editar (rota exata a confirmar)`
- **O que contém e faz:** Edita dados pessoais, status e relacionamentos de cadastro; inclui alteração de senha e dados específicos de guincho.
- **Design atual observado:** Formulário longo com seções; risco de misturar identidade global e dados específicos do prestador.

#### Detalhe do usuário
- **Rota/entrada:** `/admin/usuario/{id} (rota exata a confirmar)`
- **O que contém e faz:** Consulta dados, relacionamentos e ações de ativar/suspender/aprovar/rejeitar.
- **Design atual observado:** Workspace; semelhante ao detalhe de guincho e especialista.

#### Especialistas
- **Rota/entrada:** `/admin/especialistas`
- **O que contém e faz:** Lista especialistas, status e acesso a cadastro/detalhe.
- **Design atual observado:** Listagem com ações e estado documental.

### 3.3 Financeiro

#### Cadastrar especialista
- **Rota/entrada:** `/admin/especialistas/cadastrar`
- **O que contém e faz:** Cadastro de especialista com dados pessoais/profissionais e identificação.
- **Design atual observado:** Formulário dedicado.

#### Oficinas parceiras
- **Rota/entrada:** `/admin/oficinas-parceiras`
- **O que contém e faz:** Cadastro, lista de oficinas, fila de revisão, aprovação/suspensão/reativação e regras de parceria.
- **Design atual observado:** Página de gestão de parceiros; mistura cadastro, revisão e configuração de regras.

#### Prestadores móveis
- **Rota/entrada:** `/admin/prestadores-moveis`
- **O que contém e faz:** Gerencia prestadores móveis e regra de orçamento prévio (mínimo, teto e diagnóstico), com aprovar/suspender/reativar.
- **Design atual observado:** Tela administrativa híbrida: regras de preço e ciclo de vida do prestador.

#### Financeiro
- **Rota/entrada:** `/admin/financeiro`
- **O que contém e faz:** Gestão financeira e repasses; filtros de pagamentos/jobs, reprocessamento, aprovar/estornar e revisão de oficina.
- **Design atual observado:** Página ampla com abas/áreas; mistura conciliação de pagamentos, jobs, repasses e revisão de parceiros.

#### Visão financeira unificada / atribuição
- **Rota/entrada:** `/admin/financeiro/visao-unificada`
- **O que contém e faz:** Receita e margem, canais/campanhas e importação/registro de gastos para atribuição de marketing.
- **Design atual observado:** Relatório analítico com formulários de gasto/importação; distinto do financeiro transacional, embora compartilhe navegação.

#### Faturas
- **Rota/entrada:** `/admin/faturas`
- **O que contém e faz:** Lista faturas de parceiros com filtros por status, tipo e período de ciclo.
- **Design atual observado:** Tabela financeira com filtros.

### 3.4 Marketing, território e planejamento

#### Detalhe da fatura
- **Rota/entrada:** `/admin/fatura/{id}`
- **O que contém e faz:** Itens, PIX, pagamentos e ações marcar como paga/desbloquear.
- **Design atual observado:** Detalhe financeiro; deve se ligar à lista de faturas e ao extrato do parceiro.

#### Saques / repasses PIX
- **Rota/entrada:** `/admin/saques`
- **O que contém e faz:** Fila de repasses por pedido, valor, espera e ação de reprocessamento.
- **Design atual observado:** Tabela de exceções/transações; próximo de Financeiro.

#### Carteiras
- **Rota/entrada:** `/admin/carteiras`
- **O que contém e faz:** Lista carteiras de guincheiros, valores e compensações; busca por prestador.
- **Design atual observado:** Visão de saldo/carteira com seleção de prestador.

#### Detalhe da carteira
- **Rota/entrada:** `/admin/carteiras/{id} (rota exata a confirmar)`
- **O que contém e faz:** Detalhamento do saldo/movimentos de uma carteira.
- **Design atual observado:** Tela de detalhe que deve ser acessada a partir de Carteiras.

#### Marketing central
- **Rota/entrada:** `/admin/marketing`
- **O que contém e faz:** Central territorial de marketing: campanhas, canais, serviços procurados e regiões/zones.
- **Design atual observado:** Dashboard/quadros de campanha e território.

#### Prospecção de parceiros
- **Rota/entrada:** `/admin/marketing/prospeccao`
- **O que contém e faz:** Busca de parceiros por região, fila diária e histórico de contatos/operações; sincroniza zonas.
- **Design atual observado:** Ferramenta operacional de prospecção com abas (operação, fila, histórico).

### 3.5 Catálogos, preço e configuração do negócio

#### Planejamento de lançamento
- **Rota/entrada:** `/admin/planejamento`
- **O que contém e faz:** Modela lançamento por cidade com comissão, ticket, taxa mínima, infraestrutura e mídia mensal.
- **Design atual observado:** Formulário/calculadora de cenário; complementar a marketing e demanda territorial.

#### Demanda territorial
- **Rota/entrada:** `/admin/demanda-territorial`
- **O que contém e faz:** Analisa sinais de demanda, contexto municipal, vias e bairros com incidência.
- **Design atual observado:** Relatório territorial com blocos analíticos; próximo de Marketing e Zonas de precificação.

#### Zonas de precificação
- **Rota/entrada:** `/admin/precificacao/zonas`
- **O que contém e faz:** Gerencia zonas, polígonos GeoJSON, fases e tarifas associadas para guincho/especialista.
- **Design atual observado:** Mapa + editor de zonas/regras; contém componentes de preço que também aparecem no catálogo.

#### Regras por zona
- **Rota/entrada:** `/admin/precificacao/zonas/{id}/regras (rota exata a confirmar)`
- **O que contém e faz:** Configura tipo de serviço, categoria de veículo, base, mínimo/máximo e quilômetros incluídos.
- **Design atual observado:** Formulário/tabela de regra versionada; deveria ser uma aba da zona.

#### Catálogo de serviços
- **Rota/entrada:** `/admin/servicos`
- **O que contém e faz:** Catálogo legado de serviços visíveis, com edição/ativação/remoção.
- **Design atual observado:** Listagem simples e formulários; sobrepõe-se ao novo Catálogo de Serviços.

#### Tipos de serviço
- **Rota/entrada:** `/admin/catalogo-servicos/tipos`
- **O que contém e faz:** Gerencia tipos/códigos, categoria, modo de atendimento e descrição.
- **Design atual observado:** Listagem e formulário; raiz do catálogo.

#### Formulário de tipo de serviço
- **Rota/entrada:** `/admin/catalogo-servicos/tipo/novo`
- **O que contém e faz:** Cria/edita tipo de serviço e propriedades como duração estimada.
- **Design atual observado:** Formulário dedicado.

#### Capacidades dos prestadores
- **Rota/entrada:** `/admin/catalogo-servicos/capacidades`
- **O que contém e faz:** Configura serviços/capacidades por guincho/prestador.
- **Design atual observado:** Tabela de associação de capacidades; configuração operacional.

#### Tarifas por tipo de serviço
- **Rota/entrada:** `/admin/catalogo-servicos/tarifas`
- **O que contém e faz:** Gerencia tarifas globais e overrides por tipo de serviço.
- **Design atual observado:** Tabela de preço e ação para remover override.

#### Compatibilidade prestador × veículo
- **Rota/entrada:** `/admin/catalogo-servicos/compatibilidade`
- **O que contém e faz:** Configura requisitos por serviço, capacidade mínima, certificação para elétrico, prestador e categoria veicular.
- **Design atual observado:** Matriz de compatibilidade; próxima de capacidades e catálogo de veículos.

### 3.6 Configuração técnica, observabilidade e comunicação

#### Marcas de veículos
- **Rota/entrada:** `/admin/catalogo-veiculos`
- **O que contém e faz:** Lista marcas e logos, visibilidade/estado.
- **Design atual observado:** Tabela/lista de marcas.

#### Formulário de marca
- **Rota/entrada:** `/admin/catalogo-veiculos/marca/novo`
- **O que contém e faz:** Cria/edita marca, logo e visibilidade no cadastro do cliente.
- **Design atual observado:** Formulário.

#### Modelos de veículos
- **Rota/entrada:** `/admin/catalogo-veiculos/modelo/novo e listagem`
- **O que contém e faz:** Lista modelos por marca e permite imagem/estado.
- **Design atual observado:** Listagem/formulário.

#### Versões de veículos
- **Rota/entrada:** `/admin/catalogo-veiculos/versao/novo e listagem`
- **O que contém e faz:** Gerencia versões, ano inicial/final, motorização, combustível e câmbio.
- **Design atual observado:** Listagem/formulário.

#### Produtos
- **Rota/entrada:** `/admin/produtos`
- **O que contém e faz:** Catálogo de produtos/peças com SKU, categoria, preço de referência e descrição.
- **Design atual observado:** Listagem com busca e ações.

#### Formulário de produto
- **Rota/entrada:** `/admin/produto/novo`
- **O que contém e faz:** Cadastro/edição de produto de referência.
- **Design atual observado:** Formulário.

#### Configurações
- **Rota/entrada:** `/admin/configuracoes`
- **O que contém e faz:** Configurações gerais da plataforma; o arquivo de view do snapshot está vazio, então o conteúdo visual efetivo depende de renderização/partials ou está incompleto.
- **Design atual observado:** Rota administrativa; aparência não pode ser inferida com segurança apenas pelo arquivo de view vazio.

#### Governança do ambiente
- **Rota/entrada:** `/admin/env`
- **O que contém e faz:** Gestão de variáveis/configuração sensível, confirmação explícita e auditoria.
- **Design atual observado:** Formulário de alto risco com fluxo de confirmação.

#### Auditoria do ambiente
- **Rota/entrada:** `/admin/env/auditoria`
- **O que contém e faz:** Consulta trilha/auditoria de mudanças de ambiente.
- **Design atual observado:** Tabela de auditoria.

#### Logs do sistema
- **Rota/entrada:** `/admin/logs`
- **O que contém e faz:** Consulta logs por intervalo, nível, sistema, código e texto; alterna fontes App/Webhooks/Arquivo.
- **Design atual observado:** Console de diagnóstico com filtros.

#### Logs v2 (view alternativa)
- **Rota/entrada:** `(view alternativa; rota atual a confirmar)`
- **O que contém e faz:** Segunda view de logs presente no código; verificar se é usada ou legado.
- **Design atual observado:** Duplicidade visual potencial com Logs do Sistema.

#### Health Check
- **Rota/entrada:** `/admin/health`
- **O que contém e faz:** Verifica saúde técnica/integrações e dependências.
- **Design atual observado:** Painel técnico com verificações e estados.

#### Simulador ponta a ponta
- **Rota/entrada:** `/admin/simulador`
- **O que contém e faz:** Executa simulações de fluxo, suíte/navegador/viewport/ambiente/URL; PIX em dry-run e fila Playwright.
- **Design atual observado:** Ferramenta QA técnica.

#### Resultado da simulação
- **Rota/entrada:** `/admin/simulacao/resultado (rota exata a confirmar)`
- **O que contém e faz:** Mostra execução concluída/com falha e resultado de teste.
- **Design atual observado:** Tela de resultado ligada ao simulador.

#### Console de testes
- **Rota/entrada:** `/admin/testes`
- **O que contém e faz:** Executa cenários e exibe diagrama do funil administrativo.
- **Design atual observado:** Console técnico, próximo do simulador/health.

#### Comunicados
- **Rota/entrada:** `/admin/comunicados`
- **O que contém e faz:** Lista comunicados com público, placement, status e ações.
- **Design atual observado:** Listagem com filtros/estados.

#### Criar/editar comunicado
- **Rota/entrada:** `/admin/comunicado/novo`
- **O que contém e faz:** Edita título, subtítulo, público, placement, status e imagens desktop/mobile.
- **Design atual observado:** Formulário; preview separado.

#### Preview de comunicado
- **Rota/entrada:** `/admin/comunicados/preview`
- **O que contém e faz:** Pré-visualiza o comunicado antes da publicação.
- **Design atual observado:** Tela de preview.

#### Cidades-alvo
- **Rota/entrada:** `/admin/cidades`
- **O que contém e faz:** Gerencia cidade/UF, centro geográfico, raio, ativação e vínculo de guincheiros.
- **Design atual observado:** Tabela/formulário com parâmetros geográficos.

#### Feriados
- **Rota/entrada:** `/admin/feriados`
- **O que contém e faz:** Mantém feriados, recorrência anual e ativação.
- **Design atual observado:** Lista + formulário.

#### Produtos e regras operacionais do catálogo
- **Rota/entrada:** `/admin/catalogo-servicos/*`
- **O que contém e faz:** Conjunto de páginas para tipos, capacidades, tarifas e compatibilidade; fonte mais estruturada que o catálogo legado.
- **Design atual observado:** Família de telas administrativas.

## 4. Inventário detalhado — perfil Guincho

### Painel do guincheiro
- **Rota/entrada:** `/guincho/dashboard`
- **O que contém e faz:** Resumo operacional, estado de prontidão, nova solicitação e fila de ofertas; ações aceitar/recusar, alertas do navegador e localização.
- **Design atual observado:** Dashboard orientado a ação, com cards de pedido e fila; deve priorizar oferta atual, disponibilidade e CTA grande para uso móvel.

### Ofertas / pedidos disponíveis
- **Rota/entrada:** `/guincho/pedidos ou /guincho/pedidos-disponiveis`
- **O que contém e faz:** Lista ofertas disponíveis; no menu a entrada Ofertas aponta para âncora da fila no dashboard, enquanto existem rotas próprias de pedidos disponíveis.
- **Design atual observado:** Duplicidade entre aba/fila no dashboard e página própria; avaliar uma fila única com filtros.

### Aceitar pedido
- **Rota/entrada:** `/guincho/pedido/{id}/aceitar (rota/handler de ação)`
- **O que contém e faz:** Tela de confirmação/visualização de uma oferta com aceitar ou recusar.
- **Design atual observado:** Tela de decisão curta; ideal como modal/detalhe da oferta, mantendo confirmação explícita.

### Atendimento ativo
- **Rota/entrada:** `/guincho/atendimento/{id} (rota/handler a confirmar)`
- **O que contém e faz:** Fluxo de execução com foto de chegada obrigatória, diagnóstico, parecer/orçamento, itens complementares, chat, mudanças de status e conclusão.
- **Design atual observado:** Tela longa em etapas; precisa de stepper claro, checklist e ações fixas para celular.

### Histórico de atendimentos
- **Rota/entrada:** `/guincho/historico`
- **O que contém e faz:** Resumo/lista de corridas concluídas.
- **Design atual observado:** Lista histórica com consulta de detalhes; filtro por período/status recomendado.

### Financeiro
- **Rota/entrada:** `/guincho/financeiro`
- **O que contém e faz:** Extrato operacional e detalhamento por atendimento, com filtros.
- **Design atual observado:** Tabela/extrato; deve conectar com recibo e estado do repasse.

### Recibo
- **Rota/entrada:** `/guincho/recibo/{id} (rota/handler a confirmar)`
- **O que contém e faz:** Recibo com prestador, atendimento, valores e dados do repasse; impressão/salvar PDF.
- **Design atual observado:** Documento de leitura/ impressão, acessado pelo histórico/financeiro.

### Meu perfil
- **Rota/entrada:** `/guincho/perfil`
- **O que contém e faz:** Agrupa status operacional, dados pessoais/segurança, veículo/operação e recebimento PIX em uma página.
- **Design atual observado:** Formulário extenso dividido em seções; sobrepõe-se a telas específicas de conta, operação e bancário.

### Perfil da conta
- **Rota/entrada:** `/guincho/perfil/conta (view presente; rota atual a confirmar)`
- **O que contém e faz:** Dados pessoais, telefone/e-mail/CPF e troca de senha.
- **Design atual observado:** Formulário de identidade/segurança.

### Dados operacionais
- **Rota/entrada:** `/guincho/operacao`
- **O que contém e faz:** Placa, cidade/UF, capacidade, raio de cobertura e localização operacional.
- **Design atual observado:** Formulário de veículo e área atendida.

### Dados bancários / PIX
- **Rota/entrada:** `/guincho/bancario`
- **O que contém e faz:** Tipo e chave PIX/configuração de repasse.
- **Design atual observado:** Formulário financeiro sensível.

### Completar cadastro
- **Rota/entrada:** `/guincho/perfil/completar (view presente; rota atual a confirmar)`
- **O que contém e faz:** Complementa cadastro com placa, capacidade e documentos CNH frente/verso.
- **Design atual observado:** Wizard de onboarding; deve ser integrado ao status de cadastro.

### Tornar-se guincheiro
- **Rota/entrada:** `/guincho/tornar-se-guincho`
- **O que contém e faz:** Solicita habilitação como guincheiro com placa, cidade, capacidade e dados da CNH.
- **Design atual observado:** Onboarding para usuário ainda não aprovado; menu oculta o item quando reboque aprovado.

### Serviços que ofereço / capacidades
- **Rota/entrada:** `/guincho/capacidades`
- **O que contém e faz:** Seleciona serviços oferecidos e raio de atendimento, enviando para análise.
- **Design atual observado:** Configuração de capacidade/serviços; deveria estar dentro de Operação.

### Meu estoque
- **Rota/entrada:** `/guincho/estoque`
- **O que contém e faz:** Mantém produtos, quantidades e preços de venda disponíveis para o atendimento.
- **Design atual observado:** Tabela de inventário simples; deve ser contexto opcional do atendimento, não item prioritário se pouco usado.

## 5. Inventário detalhado — perfil Oficina

### Painel da oficina
- **Rota/entrada:** `/oficina/dashboard`
- **O que contém e faz:** Resumo e novas solicitações, cards de pedido e ações aceitar/recusar.
- **Design atual observado:** Dashboard de trabalho orientado a ofertas; visualmente semelhante ao dashboard do guincheiro.

### Ofertas / pedidos disponíveis
- **Rota/entrada:** `/oficina/pedidos; /oficina/pedidos-disponiveis`
- **O que contém e faz:** Lista de pedidos disponíveis para oficina; os cards usam partial compartilhado `_offer_card.php`.
- **Design atual observado:** Listagem curta; duplicidade com ofertas do dashboard.

### Atendimento da oficina
- **Rota/entrada:** `/oficina/pedido/{id}`
- **O que contém e faz:** Fluxo do pedido: dados do cliente/local, chegada, orçamento, início e conclusão do serviço; registrar motivo em ações aplicáveis.
- **Design atual observado:** Tela por etapas, com seções/CTAs; alinhar visualmente ao atendimento do guincheiro, mas manter campos específicos da oficina.

### Histórico
- **Rota/entrada:** `/oficina/historico`
- **O que contém e faz:** Consulta atendimentos anteriores.
- **Design atual observado:** Lista simples; filtros e acesso ao detalhe/recibo recomendados.

### Financeiro
- **Rota/entrada:** `/oficina/financeiro`
- **O que contém e faz:** Extrato operacional e detalhe financeiro por atendimento.
- **Design atual observado:** Tabela/extrato semelhante ao financeiro do guincheiro, com linguagem de oficina.

### Meu perfil
- **Rota/entrada:** `/oficina/perfil`
- **O que contém e faz:** Dados da oficina, endereço e geolocalização, raio de atendimento, PIX e serviços oferecidos.
- **Design atual observado:** Formulário em seções; mistura perfil, localização, recebimento e catálogo de serviços.

## 6. Proposta de nova arquitetura de informação

### 6.1 Admin — navegação recomendada
1. **Visão geral** — dashboard executivo (indicadores, tendências, mapa e filtros).
2. **Operação** — Central operacional como landing page; subabas **Ao vivo**, **Pedidos**, **Despacho**, **Alertas**, **Ocorrências**, **Qualidade & evidências**. Um pedido abre workspace lateral/página com abas: Resumo, Linha do tempo, Prestador, Cliente/local, Evidências/POR, Financeiro, Ocorrências.
3. **Rede de parceiros** — hub único para Guinchos, Oficinas, Especialistas e Prestadores móveis; filtros por tipo/status; abas **Ativos**, **Pendentes**, **Documentos**, **Capacidades**, **Avaliações**. Cadastro e detalhes como drawers/workspaces, sem duplicar páginas por estado.
4. **Financeiro** — hub com abas **Pagamentos**, **Repasses/Saques**, **Faturas**, **Carteiras**, **Exceções/Jobs**, **Conciliação/Exportação**. Visão financeira de marketing deve permanecer analítica, não misturada à fila transacional.
5. **Serviços & preços** — **Tipos de serviço**, **Capacidades**, **Compatibilidade**, **Tarifas globais**, **Zonas & regras**, **Catálogo de veículos**, **Produtos/peças**. Descontinuar a entrada antiga de Catálogo apenas após mapear dados e rotas.
6. **Crescimento & território** — Marketing/Campanhas, Prospecção, Demanda territorial, Planejamento de lançamento e Cidades-alvo; filtros geográficos compartilhados.
7. **Conteúdo & comunicação** — Comunicados, preview e público/placement.
8. **Sistema** — Usuários e permissões, Configurações, Governança/Auditoria do ambiente, Logs, Health, Simulador/QA/Testes.

### 6.2 Guincho — navegação recomendada
- **Painel** (status online/offline, posição, oferta atual, contagem de novas ofertas, resumo financeiro curto).
- **Ofertas** (uma fila única; filtros simples; aceitar/recusar com confirmação e prazo/expiração claros).
- **Atendimento ativo** (stepper: Aceito → Deslocamento → Cheguei → Diagnóstico/Orçamento → Execução → Comprovantes → Conclusão; chat e mapa como ferramentas contextuais; CTA fixo inferior no celular).
- **Histórico** (atendimentos concluídos/cancelados, busca e detalhe).
- **Financeiro** (extrato, estado do repasse, recibos e filtros; recibo abre em detalhe/impressão).
- **Minha operação** (dados do veículo, cobertura/raio, localização, serviços/capacidades, disponibilidade, estoque opcional).
- **Conta & recebimento** (dados pessoais, senha, PIX, documentos e status de aprovação).
- **Onboarding** (Tornar-se guincheiro/Completar cadastro vira fluxo condicional dentro de Conta & recebimento; só aparece se faltarem requisitos).

### 6.3 Oficina — navegação recomendada
- **Painel**.
- **Ofertas** (lista única compartilhada com cards padronizados).
- **Atendimento ativo** (stepper adaptado: Aceitar → Chegada/recepção → Diagnóstico → Orçamento → Aprovação/execução → Conclusão/evidências).
- **Histórico**.
- **Financeiro** (extrato, pagamentos e PIX).
- **Perfil da oficina** com abas **Dados**, **Localização/cobertura**, **Serviços**, **Recebimento/PIX**, **Status da parceria**.

## 7. Consolidações recomendadas (prioridade alta)

- **Central Operacional + Pedidos + Despacho + Detalhe do Pedido:** Unificar sob **Operação**. A central é a fila viva; filtros abrem a visão Pedidos; despacho e atribuição são ações contextuais; detalhe é um workspace com abas. Não eliminar ações/endpoints antes de mapear todos.

- **Alertas + Ocorrências + Checklists incompletos + Proof-of-Road + Trilha POR:** Criar **Central de Atenção/Qualidade** com filas por severidade/tipo e ligação direta à seção do pedido. Trilha e evidências ficam como abas do detalhe; relatórios POR permanecem em Qualidade.

- **Prestadores + Guinchos + Guinchos pendentes + Documentos + Avaliações + detalhes de usuário/prestador:** Criar **Rede de Parceiros** com filtro por tipo e estado. Pendentes/documentos são views filtradas do mesmo dataset; detalhes compartilham workspace por entidade com tabs específicas.

- **Financeiro + Faturas + Saques + Carteiras + jobs/repasses:** Criar hub Financeiro com abas e estados consistentes; ações irreversíveis com confirmação, motivo e auditoria. Separar transações de marketing attribution.

- **Catálogo legado + Catálogo de Serviços estruturado:** Uma única área Serviços & Preços. Manter um único modelo de dados/termo. Fazer inventário de uso e migração antes de esconder telas legadas.

- **Tipos + Capacidades + Tarifas + Compatibilidade + Catálogo de veículos + Zonas:** Navegação hierárquica dentro de Serviços & Preços; evitar telas independentes sem contexto. Zonas têm workspace próprio com abas Mapa, Regras e Tarifas.

- **Marketing + Prospecção + Demanda Territorial + Planejamento + Cidades:** Hub Crescimento & Território, com filtros compartilhados por cidade/zona; separar exploração analítica de ações de prospecção e parâmetros de simulação.

- **Logs + Logs v2 + Health + Simulador + Console de testes:** Área Sistema/Diagnóstico. Um único Logs com fontes/filtros; Health é status; Simulador e Testes são ferramentas QA. Confirmar qual view de logs está ativa.

- **Guincho: Perfil + Conta + Operação + Bancário + Capacidades + Completar cadastro + Tornar-se guincheiro:** Unificar em **Minha conta/operação** com abas e onboarding condicional. Separar dados pessoais, veículo, cobertura, serviços, documentos e recebimento.

- **Guincho e Oficina: Dashboard + Ofertas/Pedidos disponíveis:** Dashboard mostra resumo e previews; item Ofertas abre uma fila única. Evitar manter uma lista no dashboard e outra página com comportamento diferente.

- **Guincho e Oficina: Histórico + Financeiro + Recibo:** Histórico mantém a jornada do atendimento; Financeiro mantém saldos/lançamentos. Recibo é uma subview do lançamento/atendimento, não item principal da navegação.

- **Oficina: Perfil com Dados + Localização + Serviços + PIX:** Transformar em abas de perfil, com salvamento e validação por seção; não misturar catálogo/serviços com dados de contato.

## 8. Diretrizes visuais e de interação para o Lovable

- **Não redesenhar como site de marketing.** É um backoffice operacional, com densidade informacional controlada e foco em tarefas.
- Design system único: tipografia, escala de espaçamento, cores semânticas, raios, elevação, botões, campos, badges de status, tabelas, empty states, skeletons e mensagens de erro.
- Diferenciar visualmente os perfis por contexto, não por três produtos independentes: Admin = monitoramento/gestão; Guincho = uso em campo; Oficina = gestão do atendimento e orçamento.
- Navegação Admin com sidebar recolhível, grupos claros e breadcrumbs. Marcar item ativo por rota normalizada, não por `strpos` ambíguo.
- Páginas de lista: título + descrição curta, CTA principal, filtros relevantes, busca, chips de filtros ativos, tabela responsiva e paginação; seleção em massa apenas quando segura.
- Workspaces de detalhe: cabeçalho com ID/status/ações, resumo fixo, tabs, linha do tempo e painel de atividade; não repetir formulários inteiros em páginas diferentes.
- Estados críticos com texto + ícone + cor, nunca apenas cor. Exibir data/hora, responsável e motivo para alterações sensíveis.
- Ações críticas (aprovar/rejeitar, suspender, cancelar, marcar paga, desbloquear, estornar, concluir manualmente) exigem confirmação contextual; motivo obrigatório quando houver impacto financeiro/operacional; feedback e auditoria.
- Mobile para guincho/oficina: alvo de toque amplo, CTA primário fixo, sem tabelas horizontais como única forma de acesso, upload de fotos fácil, estados de rede/erro explícitos, evitar campos longos numa única página.
- Acessibilidade: contraste, foco visível, labels reais, navegação por teclado, mensagens de validação próximas ao campo, `aria-live` para atualizações da fila.
- Mapas: usar como apoio operacional e permitir alternativa em lista; não depender exclusivamente do mapa para localizar/atribuir prestadores.

## 9. Regras de segurança e compatibilidade

1. Preservar autenticação, autorização por perfil e validações no servidor; esconder item no menu não é controle de acesso.
2. Não mudar contratos de rota, métodos HTTP, payloads, CSRF, regras de negócio, preço, comissão, pagamentos ou estados do pedido apenas para acomodar o layout.
3. A camada visual pode consolidar várias URLs numa navegação, mas cada URL antiga deve continuar redirecionando/funcionando até que exista plano de migração testado.
4. Não expor dados bancários, CPF, documentos, chaves PIX ou informações sensíveis em cards/listas além do necessário.
5. Ações financeiras e mudanças de status devem ser auditáveis e idempotentes quando aplicável.
6. Não implementar dados falsos no protótipo como se fossem reais; usar fixtures claramente marcadas quando a integração não estiver ligada.

## 10. Entregáveis que o Lovable deve produzir antes de codificar

1. Sitemap novo dos três perfis e matriz de permissões por seção/ação.
2. Tabela de equivalência **rota atual → destino de navegação → componente novo → compatibilidade/redirect**.
3. Wireframes desktop e mobile para: Admin dashboard, Central Operacional, workspace de Pedido, Rede de Parceiros, Financeiro; Guincho dashboard/oferta/atendimento; Oficina dashboard/oferta/atendimento/perfil.
4. Inventário de componentes reutilizáveis e tokens visuais.
5. Plano por fases: (A) shell/design system; (B) hubs/listas; (C) workspaces e fluxos; (D) telas técnicas; (E) limpeza de duplicidades após validação.
6. Critérios de aceite para responsividade, acessibilidade, estados vazios/erro/loading, permissões e preservação de contratos.
7. Lista explícita de decisões pendentes e páginas cuja rota/uso real precisa ser confirmado.

## 11. Prompt pronto para enviar ao Lovable

> Analise este documento como briefing de UX/UI do GuinchaFácil. Antes de implementar, proponha uma nova arquitetura de informação para os backoffices Admin, Guincho e Oficina. Entregue sitemap, matriz de permissões, mapa rota atual → tela nova, wireframes desktop/mobile e plano de migração em fases. Priorize consolidação de páginas com objetivos próximos, mas não remova funcionalidades nem altere regras de negócio, contratos de API, endpoints, autorização, preço ou pagamento sem aprovação. Preserve os fluxos operacionais existentes e diferencie claramente fila de ofertas, atendimento ativo, histórico e financeiro. O Admin deve ter hubs para Operação, Rede de Parceiros, Financeiro, Serviços & Preços, Crescimento & Território, Conteúdo e Sistema. Guincho e Oficina devem compartilhar padrões de UI, com fluxos de atendimento adaptados ao negócio de cada perfil. Use dados reais apenas quando conectados; marque fixtures como demonstração. Liste ambiguidades e peça decisão antes de resolver qualquer conflito funcional.

## 12. Confirmações antes da implementação

- Qual dashboard deve ser a entrada principal do Admin: `/admin/dashboard` (analítico) ou `/admin/central` (operacional)? Recomendação: central como destino operacional padrão, dashboard como visão geral.
- Qual fluxo de novo pedido é canônico entre `/admin/pedido/novo`, `/admin/pedido/novo/v2`, etapa 1 e funil? Confirmar antes de esconder variantes.
- Qual view de logs está em uso (`logs.php` ou `logs_v2.php`)?
- Catálogo legado `/admin/servicos` ainda é usado ou é apenas compatibilidade?
- As views `perfil_conta.php`, `perfil_operacao.php`, `perfil_bancario.php`, `perfil_completar.php`, `pedidoaceitar.php`, `recibo.php`, `carteira_detalhe.php`, `pedido_trilha.php` têm rotas ativas ou são componentes/legado? Verificar no roteador completo e em links gerados dinamicamente.
- Confirmar nomes finais de status e permissões para cada perfil antes de desenhar badges e filtros.
