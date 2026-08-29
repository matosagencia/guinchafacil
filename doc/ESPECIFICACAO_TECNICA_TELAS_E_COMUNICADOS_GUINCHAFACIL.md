# GuinchaFácil — Especificação Técnica de Layout, CSS e Central de Comunicados

**Base analisada:** `guinchafacil(5).zip`  
**Escopo:** painéis e fluxo de pedido do cliente, oferta e atendimento do guincho, campo rápido de socorro e gerenciamento administrativo de banners/informes.  
**Objetivo visual:** transportar para o GuinchaFácil a densidade, hierarquia e comportamento das referências CabME sem copiar a identidade gráfica nem enfraquecer o fluxo operacional e o Proof-of-Road.

---

## 1. Resultado da auditoria do projeto

### 1.1 Arquitetura encontrada

O projeto usa:

- PHP MVC próprio, com rotas declaradas em `index.php`;
- Bootstrap 5.3, Font Awesome 6 e Leaflet;
- tema por perfil via classe no `<body>`;
- `public/assets/css/style.css` como folha global;
- CSS e JavaScript extensos embutidos diretamente nas views;
- dashboards do cliente e do guincho renderizados no servidor, com atualização por AJAX/SSE;
- `Logger` estruturado em JSONL e banco, com os campos `system`, `class`, `function`, `file`, `phase` e `code`;
- suíte Playwright já integrada ao projeto e ao painel administrativo;
- Proof-of-Road e evidências já presentes no backend.

### 1.2 Verificações executadas

| Verificação | Resultado |
|---|---:|
| PHP lint em `src`, `install`, `tools` e `tests` | **169 arquivos sem erro de sintaxe** |
| TypeScript da suíte QA (`tsc --noEmit`) | **Aprovado** |
| Parse estrutural de `style.css` | **Sem erro CSS de nível superior** |
| Views com BOM UTF-8 | **2** |
| Ocorrências aparentes de mojibake (`Ã...`) | **30 em 8 arquivos** |
| Literal indevido `` `r`n `` | **1 em `guincho/dashboard.php`** |
| Atributos `style="..."` nas views | **428** |
| CSS local em `cliente/dashboard.php` | **aprox. 392 linhas** |
| CSS local em `guincho/dashboard.php` | **aprox. 379 linhas** |

A sintaxe está válida, mas a camada visual está fragmentada. O navegador aceita; a manutenção é que está pagando o guincho.

### 1.3 Pontos concretos encontrados

#### Cliente — `src/Views/cliente/dashboard.php`

- O hero contém o card completo do pedido ativo. Quando há atendimento, ele deixa de ser uma faixa compacta e cresce de forma imprevisível.
- Há duas representações do mesmo atendimento: `#pedidoAtivoClienteCard` e `#statusBannerCliente`.
- O mapa herda `.map-container { height:70vh }`; o `min-height:320px` inline não limita a altura. Em desktop alto, o mapa fica desproporcional.
- O botão “Pedir Socorro” é apenas um link para a tela completa; não existe o campo rápido de origem visto na referência.
- O dashboard não possui mecanismo de banners/informes.
- Há textos com codificação quebrada em títulos e labels.
- A view concentra aproximadamente 392 linhas de CSS e JavaScript inline.

#### Nova solicitação — `src/Views/cliente/pedidonovo.php`

- A estrutura mapa 7/12 + formulário 5/12 é adequada.
- O formulário já suporta origem, destino, veículo, problema, custo e validação.
- A geocodificação é feita também diretamente do navegador contra o Nominatim, apesar de o projeto já possuir `GeocodingService` no servidor.
- Não há mecanismo formal para receber um rascunho iniciado pelo campo rápido do dashboard.
- O mapa e o formulário não estão organizados como etapas de leitura/revisão.

#### Acompanhamento — `src/Views/cliente/pedidostatus.php`

- Já existe um card forte de status, foto do guincho, ETA, distância, rota, timeline, chat e cancelamento.
- A tela está funcionalmente mais próxima da referência do que o dashboard.
- O conteúdo ainda usa muitas regras locais e estilos inline.
- Não deve receber publicidade: durante atendimento ativo, prioridade absoluta é status, rota, contato e segurança.

#### Guincho — `src/Views/guincho/dashboard.php`

- O hero já moveu o toggle para dentro, mas ainda repete chips e status.
- Há duas representações da corrida ativa: `#pedidoAtivoGuinchoCard` e `#statusBannerGuincho`.
- O texto técnico “Mesmo container e mesma inicialização JavaScript...” ainda está visível.
- Existe um literal `` `r`n `` dentro do HTML da fila.
- A fila renderizada por JavaScript mostra apenas problema, origem e preço; não mostra ETA até a coleta, veículo, distância até a origem nem rota total.
- O CSS local possui aproximadamente 379 linhas.
- O mapa usa CDN, apesar de o projeto já ter Leaflet local em `public/assets/vendor`.

#### Oferta ao guincho — `src/Views/guincho/pedidoaceitar.php`

- A view possui somente 67 linhas e é muito inferior ao escopo da referência.
- Não possui mapa.
- Não mostra distância/ETA do guincho até o cliente.
- Não mostra dados do cliente nem botão de chamada.
- Não mostra situação do pagamento.
- Não mostra validade/expiração da oferta.
- Os botões estão em ordem inadequada: **Aceitar à esquerda e Recusar à direita**. O padrão operacional deve ser **Recusar à esquerda e Aceitar à direita**.

#### Atendimento do guincho — `src/Views/guincho/atendimento.php`

- Já possui mapa, dados do cliente, chat, status e botão único de avanço.
- Já exige fotos para coleta/entrega.
- O backend possui Proof-of-Road, geofencing e rastreamento.
- A interface ainda não apresenta claramente ao operador o estado das validações do POR antes de permitir o avanço.

#### Administração

- `AdminController.php` já possui aproximadamente 1.827 linhas.
- Não existe módulo de banners, campanhas ou informes.
- `AdminController::processarUpload()` valida tamanho e extensão, mas não valida MIME real, dimensões nem reprocessa a imagem. **Não deve ser reutilizado como está para banners.**
- A sidebar administrativa não possui “Comunicados”.

---

## 2. Decisão arquitetural

### 2.1 Criar uma Central de Comunicados, não inserir tudo no `AdminController`

A recomendação é criar um controlador administrativo dedicado:

```text
src/Controllers/AdminComunicadoController.php
```

E separar a leitura pública/telemetria em:

```text
src/Controllers/ComunicadoController.php
```

Motivos:

1. `AdminController.php` já está grande demais.
2. CRUD, upload, agendamento, preview e métricas constituem um subsistema próprio.
3. Cliente e guincho precisam consultar os mesmos dados por um contrato único.
4. A separação facilita testes, logs e manutenção.

### 2.2 Nome funcional no painel

Usar **Central de Comunicados** em vez de “Publicidade”. O mesmo mecanismo poderá publicar:

- novidades da plataforma;
- campanhas promocionais;
- alertas de manutenção;
- avisos de segurança;
- orientações para clientes;
- treinamentos e campanhas para guinchos;
- informações patrocinadas, futuramente, sob regras próprias.

### 2.3 Regra de prioridade operacional

Um comunicado nunca pode ficar acima de conteúdo crítico.

#### Cliente

1. Pedido ativo / emergência em andamento;
2. campo rápido “Onde está o veículo?”;
3. comunicado;
4. estatísticas e histórico.

#### Guincho

1. atendimento ativo;
2. nova oferta disponível;
3. disponibilidade online/offline;
4. comunicado;
5. métricas e histórico.

Não exibir comunicados em:

- checkout/pagamento;
- acompanhamento de pedido ativo;
- tela de aceite do guincho;
- atendimento em andamento;
- upload de evidências;
- modais de cancelamento.

---

## 3. Sistema métrico global

### 3.1 Escala de espaçamento

Adotar escala base de 4px, com uso predominante de múltiplos de 8px:

```css
:root {
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-5: 20px;
    --space-6: 24px;
    --space-8: 32px;
    --space-10: 40px;
    --space-12: 48px;
}
```

### 3.2 Dimensões estruturais

```css
:root {
    --navbar-height: 64px;
    --sidebar-width: 230px;
    --content-max: 1440px;
    --content-pad-desktop: 32px;
    --content-pad-tablet: 20px;
    --content-pad-mobile: 16px;
    --grid-gap-desktop: 24px;
    --grid-gap-mobile: 16px;
    --touch-min: 44px;
}
```

Aplicar um limitador interno ao conteúdo:

```css
.screen-container {
    width: min(100%, var(--content-max));
    margin-inline: auto;
}
```

Isso impede que heros, listas e mapas se estiquem indefinidamente em monitores de 1920px ou maiores.

### 3.3 Raios

```css
:root {
    --radius-control: 12px;
    --radius-card: 20px;
    --radius-panel: 24px;
    --radius-hero: 28px;
    --radius-pill: 999px;
}
```

Não criar novos raios por página. A variação atual de 8, 12, 14, 16, 18, 20, 22, 24 e 30px deve ser reduzida.

### 3.4 Sombras

```css
:root {
    --shadow-card: 0 10px 30px rgba(15, 23, 42, .08);
    --shadow-panel: 0 20px 55px rgba(15, 23, 42, .10);
    --shadow-float: 0 28px 80px rgba(15, 23, 42, .12);
    --shadow-primary: 0 10px 24px rgba(47, 179, 74, .32);
}
```

### 3.5 Tipografia

| Papel | Desktop | Mobile | Peso | Linha |
|---|---:|---:|---:|---:|
| Título principal | 32px | 26px | 800 | 1.15 |
| Título de página | 23px | 21px | 800 | 1.2 |
| Título de card | 17px | 16px | 800 | 1.25 |
| Corpo | 15px | 15px | 400/500 | 1.5 |
| Label | 13px | 13px | 600 | 1.3 |
| Microtexto | 12px | 12px | 500/700 | 1.35 |
| KPI | 26px | 23px | 800 | 1 |

### 3.6 Alvos de toque

- botão comum: mínimo 44 × 44px;
- CTA principal: mínimo 48px de altura;
- botão operacional do guincho: 56px de altura;
- campo de texto: 48px de altura;
- itens de navegação mobile: mínimo 56px de altura.

---

## 4. Arquitetura CSS proposta

### 4.1 Problema atual

Os dashboards mantêm centenas de linhas de CSS dentro das views. Isso gera:

- duplicação entre `.dash-*` e `.tow-*`;
- regras com especificidade conflitante;
- dificuldade para validar contraste;
- impossível reaproveitamento entre telas;
- arquivos PHP visualmente gigantes.

### 4.2 Estrutura recomendada

```text
public/assets/css/style.css
public/assets/css/components/dashboard.css
public/assets/css/components/request-flow.css
public/assets/css/components/communications.css
public/assets/css/components/mobile-nav.css
```

#### `style.css`

Somente:

- tokens;
- temas;
- shell;
- navbar/sidebar/footer;
- botões, forms, cards e badges globais;
- acessibilidade global.

#### `dashboard.css`

- hero;
- quick rescue;
- active request;
- stats;
- map/list dashboard;
- variantes cliente/guincho apenas quando necessárias.

#### `request-flow.css`

- nova solicitação;
- acompanhamento;
- oferta ao guincho;
- atendimento;
- timeline e POR.

#### `communications.css`

- carousel;
- banner largo;
- card de informe;
- dots, progress bar, close e skeleton.

### 4.3 Nomenclatura

Usar componentes neutros, não duplicar cliente/guincho:

```text
.app-dashboard
.app-hero
.app-stat
.app-stat__icon
.app-stat__value
.app-panel
.active-request
.quick-rescue
.communication-carousel
.communication-card
```

Variações:

```text
.app-dashboard--client
.app-dashboard--tow
.communication-card--light
.communication-card--dark
```

### 4.4 Proibição de estilo inline

Nas telas afetadas:

- nenhum `style="..."` novo;
- nenhuma tag `<style>` dentro da view;
- valores dinâmicos via classe ou custom property permitida e sanitizada;
- JavaScript em arquivo próprio.

---

## 5. Dashboard do cliente

### 5.1 Ordem da tela

#### Sem pedido ativo

```text
Page header compacto
Campo rápido de socorro
Carousel de comunicados
Stats
Mapa + últimos pedidos
```

#### Com pedido ativo

```text
Page header compacto
Card de pedido ativo
Carousel de comunicados opcional abaixo do status
Stats
Mapa + últimos pedidos
```

O card ativo não deve ficar dentro do hero.

### 5.2 Campo rápido de socorro

A ideia da referência deve ser adaptada para a pergunta correta do negócio:

> **Onde está o veículo?**

Não perguntar primeiro “para onde deseja ir?”. Em um socorro, a primeira necessidade é localizar a ocorrência.

#### Desktop

- largura: 100%;
- altura mínima: 76px;
- padding: 14px 16px;
- raio: 24px;
- layout: ícone + input + GPS + CTA;
- input: altura 48px;
- botão GPS: 48 × 48px;
- CTA: 152 × 48px;
- gap: 12px.

```text
[ícone 44] [ Onde está o veículo?........................ ] [GPS 48] [Pedir socorro]
```

#### Mobile

- card de 100%;
- padding: 16px;
- input na primeira linha;
- GPS acoplado ao input;
- CTA full-width na segunda linha;
- altura aproximada: 132–148px.

#### Estados

| Estado | Comportamento |
|---|---|
| vazio | CTA “Pedir socorro” abre a tela completa sem origem pré-preenchida |
| digitando | debounce de 350ms; mínimo 3 caracteres |
| localizado | mostra endereço resumido e check verde |
| GPS buscando | spinner e texto “Localizando veículo...” |
| GPS negado | mensagem “Digite o endereço ou marque no mapa” |
| pedido ativo | campo não aparece; entra o card do atendimento ativo |
| sem veículo cadastrado | abre pedido, mas destaca etapa “Cadastrar veículo” |

### 5.3 Integração técnica do campo rápido

Criar rota:

```text
POST /cliente/pedido/rascunho
```

Método:

```text
ClienteController::pedidoRascunho()
```

Payload:

```text
csrf_token
endereco_origem
lat_origem
lng_origem
```

O método deve:

1. validar CSRF;
2. validar coordenadas dentro dos limites aceitos pelo projeto;
3. limitar endereço a 220 caracteres;
4. salvar em `$_SESSION['pedido_rascunho']`;
5. responder JSON com `redirect` para `/cliente/pedido/novo`.

`ClienteController::pedidoNovo()` deve carregar o rascunho, passá-lo à view e mantê-lo por no máximo 30 minutos.

Não criar pedido no dashboard. O campo apenas adianta a primeira etapa.

### 5.4 Geocodificação

Adicionar ao `GeocodeController`:

```text
GET /geocode/reverse?lat=...&lng=...
```

Usar `GeocodingService::reverseGeocode()`.

Remover chamadas diretas do navegador ao Nominatim nas telas tocadas. Benefícios:

- cache central;
- User-Agent controlado;
- logs;
- menor exposição a bloqueios/CSP;
- comportamento único.

### 5.5 Hero/saudação

A saudação pode permanecer, mas deve ser compacta:

- altura máxima: 180px;
- padding desktop: 28px 32px;
- padding mobile: 20px;
- título: 30–32px;
- subtítulo: uma linha em desktop, duas no mobile;
- máximo de 2 chips úteis;
- não repetir o CTA se o campo rápido já estiver presente.

Recomendação: o campo rápido substitui o botão isolado “Pedir Socorro”.

### 5.6 Card de pedido ativo

- fora do hero;
- altura esperada desktop: 180–220px;
- grid interno: dados do guincho + status + rota + ações;
- avatar/foto: 64 × 64px;
- badge de status no canto superior direito;
- ações: Acompanhar, Ligar e Chat;
- cancelamento permanece somente na tela de acompanhamento;
- evitar duplicar `#statusBannerCliente` e `#pedidoAtivoClienteCard`.

**Contrato recomendado:** preservar `#pedidoAtivoClienteCard` como componente principal e remover o segundo banner do dashboard.

### 5.7 Stats

- desktop: 4 colunas;
- tablet: 2 colunas;
- mobile: 2 colunas;
- altura mínima: 142px;
- padding: 20px;
- ícone: 48 × 48px;
- valor: 26px;
- label: 13px;
- gap: 16px mobile / 24px desktop.

### 5.8 Mapa e últimos pedidos

Desktop:

```text
Mapa: col-7, altura fixa 380px
Últimos pedidos: col-5, altura 380px
```

Não usar `70vh` no dashboard.

Mobile:

- mapa: 300px;
- lista: máximo 5 itens;
- item: mínimo 72px;
- badge alinhado ao topo;
- botão “Ver histórico” no rodapé do card.

---

## 6. Carousel e banners do cliente

### 6.1 Posição

Slot inicial recomendado:

```text
cliente_dashboard_top
```

Local:

- depois do campo rápido ou do card de pedido ativo;
- antes dos stats;
- margem superior: 20px;
- margem inferior: 24px.

### 6.2 Formato renderizado

#### Banner largo

- desktop: proporção 24:7;
- largura: 100%;
- altura máxima renderizada: 320px;
- tablet: 240px;
- mobile: proporção 16:9, máximo 220px;
- raio: 24px;
- overflow: hidden.

#### Cards duplos

- desktop: 2 cards por linha;
- proporção 16:9;
- gap: 16px;
- mobile: 1 por vez em carousel.

### 6.3 Conteúdo sobre o banner

Não gravar texto importante dentro da imagem. O card deve ter campos reais:

- tag opcional;
- título, máximo 60 caracteres;
- subtítulo, máximo 120 caracteres;
- CTA, máximo 24 caracteres;
- alt text;
- imagem decorativa.

Isso preserva acessibilidade, tradução e responsividade.

---

## 7. Nova solicitação de socorro

### 7.1 Estrutura desktop

```text
Header + stepper
┌──────────────────────────────┬──────────────────────┐
│ mapa 7/12                    │ formulário 5/12     │
│ 560px de altura              │ resumo sticky       │
└──────────────────────────────┴──────────────────────┘
```

### 7.2 Etapas visuais

Mesmo que continue sendo um único formulário, apresentar três blocos:

1. **Localização** — origem e destino;
2. **Veículo e problema**;
3. **Revisão e valor**.

A etapa não precisa alterar o backend. É organização visual e acessível.

### 7.3 Métricas

- mapa desktop: 560px;
- mapa tablet: 420px;
- mapa mobile: `min(420px, 46vh)`;
- formulário: padding 24px;
- campos: 48px;
- textarea: mínimo 88px;
- resumo de custo: 2 cards de 104px;
- CTA: 56px de altura;
- coluna direita pode usar `position:sticky; top:88px` apenas em desktop.

### 7.4 Rascunho vindo do dashboard

A view deve receber:

```php
$pedidoRascunho = [
    'endereco_origem' => '',
    'lat_origem' => null,
    'lng_origem' => null,
    'criado_em' => null,
];
```

O JavaScript deve:

- preencher os hidden fields;
- posicionar o marcador de origem;
- ajustar o mapa;
- mudar automaticamente o modo para “Destino”;
- manter toda validação atual.

### 7.5 Revisão final

Antes do CTA, mostrar:

```text
Origem
Destino
Veículo
Problema
Distância estimada
Valor estimado
Modo de pagamento/ambiente
```

Em `freeflow`/sandbox, informar “Pagamento simulado no ambiente de testes”, sem expor nomenclatura técnica ao cliente real.

---

## 8. Acompanhamento do cliente

### 8.1 Estrutura

Desktop:

```text
Status card
Timeline
Mapa 7/12 | Coluna operacional 5/12
```

### 8.2 Métricas

- status card: mínimo 112px;
- foto: 72 × 72px;
- timeline: 88px desktop;
- mapa: altura `calc(100vh - 270px)`, mínimo 480px, máximo 720px;
- coluna direita: cards com gap 16px;
- chat: 280–340px;
- CTA secundário/cancelamento: 48px.

### 8.3 Regra de conteúdo

Nenhum comunicado comercial nessa tela. Avisos de segurança podem entrar apenas como alertas operacionais do próprio pedido.

---

## 9. Dashboard do guincho

### 9.1 Ordem operacional

```text
Hero compacto + toggle
Atendimento ativo OU oferta disponível
Stats
Comunicados
Mapa + fila/últimos atendimentos
```

Se existir oferta, ela deve ficar acima do comunicado.

### 9.2 Hero

- altura máxima: 190px;
- título: 30px;
- toggle: 52 × 28px, preservando `#toggleDisponivel`;
- rótulo textual único: Online/Offline;
- remover badge redundante “Disponível” se o rótulo já estiver ao lado do switch;
- máximo de 2 chips; não usar “Disponibilidade em tempo real” se o toggle já comunica isso.

### 9.3 Oferta resumida no dashboard

O card gerado no servidor e no JavaScript deve usar o mesmo template e mostrar:

- problema;
- veículo;
- distância do guincho até a origem;
- ETA até a origem;
- origem resumida;
- distância total do serviço;
- valor;
- relógio de expiração, quando aplicável;
- CTA “Ver pedido”.

Altura esperada: 240–300px.

O endpoint `/guincho/pedidos-disponiveis` já retorna `distancia_km` calculada para o score. Renomear semanticamente na resposta para não confundir:

```text
distancia_ate_origem_km
```

E acrescentar:

```text
eta_ate_origem_min
veiculo_label
distancia_servico_km
expira_em
```

### 9.4 Stats

Mesmo padrão do cliente, usando superfície clara com tokens de contraste locais.

### 9.5 Mapa e coluna lateral

Desktop:

- mapa: col-8, altura 440px;
- lateral: col-4;
- fila: altura mínima 230px;
- últimos atendimentos: restante da coluna;
- gap entre cards: 24px.

### 9.6 Comunicados do guincho

Slot recomendado:

```text
guincho_dashboard_after_stats
```

Tipos adequados:

- nova política operacional;
- treinamento de fotos/evidências;
- campanha de bônus;
- manutenção programada;
- atualização de regras;
- segurança e prevenção de fraude.

Não exibir acima de uma oferta disponível.

---

## 10. Tela de aceite do guincho

Essa é a tela que mais precisa ser reconstruída para alcançar o escopo da referência.

### 10.1 Desktop

```text
┌──────────────────────────────┬────────────────────────┐
│ mapa/rota 7/12               │ pedido 5/12            │
│ 440px                        │ dados + preço + ações  │
└──────────────────────────────┴────────────────────────┘
```

### 10.2 Conteúdo obrigatório

#### Mapa

- posição atual do guincho;
- origem;
- destino;
- rota do guincho até a origem;
- rota prevista da origem até o destino;
- legenda clara.

#### Card do pedido

- pedido e contador de expiração;
- cliente: nome e telefone;
- veículo: marca, modelo, placa;
- problema e descrição;
- origem;
- destino;
- distância até a coleta;
- ETA até a coleta;
- distância do serviço;
- valor estimado;
- pagamento confirmado/sandbox/freeflow em linguagem operacional;
- indicador de concorrência: “A oferta pode ser aceita por outro guincho”.

### 10.3 Métricas

- mapa: 440px;
- avatar cliente: 56 × 56px;
- rota origem/destino: cards de 72px;
- KPIs: 3 colunas de 88px;
- preço: 28px/800;
- botões: 52px;
- gap: 12px;
- card principal: raio 24px.

### 10.4 Ordem das ações

```text
[Recusar] [Aceitar pedido]
```

- Recusar: esquerda, outline/neutro;
- Aceitar: direita, verde preenchido;
- aceitar deve ter maior largura ou peso visual;
- mobile: barra sticky inferior, `padding-bottom: env(safe-area-inset-bottom)`.

### 10.5 Concorrência

Em falha de aceite simultâneo:

- não redirecionar silenciosamente;
- mostrar mensagem “Este pedido já foi aceito por outro guincho”;
- registrar `GUINCHO_OFFER_ALREADY_TAKEN`;
- CTA “Voltar à fila”.

---

## 11. Atendimento do guincho e Proof-of-Road

### 11.1 Layout

Desktop:

- mapa: col-7;
- operação: col-5;
- botão de avanço: 56px de altura;
- chat abaixo da operação ou em aba própria no mobile.

### 11.2 Painel POR visível

Adicionar um bloco compacto acima do botão de avanço:

```text
Validação do trajeto
✓ GPS ativo
✓ Pontos de rota válidos
✓ Proximidade da origem/destino
○ Evidência obrigatória pendente
```

### 11.3 Estados

| Estado | Validações exibidas | CTA |
|---|---|---|
| `a_caminho` | GPS, rota e geofence de origem | Cheguei ao Local |
| `no_local` | chegada validada + foto de coleta | Iniciar Reboque |
| `em_reboque` | rota até destino + foto de entrega | Finalizar Corrida |
| `concluido` | resumo e integridade | Sem CTA |

### 11.4 Bloqueio

Quando o backend recusar transição por POR:

- manter a tela no mesmo status;
- mostrar motivo legível;
- não apagar foto selecionada sem necessidade;
- registrar código técnico no log;
- disponibilizar “Tentar validar novamente”.

---

## 12. Central de Comunicados — dados

### 12.1 Tabela principal

Criar:

```text
install/migration_comunicados_v1.sql
```

Especificação SQL:

```sql
CREATE TABLE IF NOT EXISTS comunicados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(120) NOT NULL,
    subtitulo VARCHAR(220) NULL,
    etiqueta VARCHAR(40) NULL,
    publico ENUM('cliente','guincho','ambos') NOT NULL,
    placement VARCHAR(64) NOT NULL,
    formato ENUM('wide','card') NOT NULL DEFAULT 'wide',
    tema ENUM('auto','light','dark','success','warning','info') NOT NULL DEFAULT 'auto',
    imagem_desktop VARCHAR(255) NOT NULL,
    imagem_mobile VARCHAR(255) NULL,
    imagem_alt VARCHAR(180) NOT NULL DEFAULT '',
    object_position_x TINYINT UNSIGNED NOT NULL DEFAULT 50,
    object_position_y TINYINT UNSIGNED NOT NULL DEFAULT 50,
    cta_label VARCHAR(40) NULL,
    cta_url VARCHAR(500) NULL,
    cta_target ENUM('self','blank') NOT NULL DEFAULT 'self',
    status ENUM('rascunho','publicado','pausado','arquivado') NOT NULL DEFAULT 'rascunho',
    prioridade SMALLINT NOT NULL DEFAULT 100,
    inicio_em DATETIME NULL,
    fim_em DATETIME NULL,
    duracao_slide_seg TINYINT UNSIGNED NOT NULL DEFAULT 8,
    frequencia ENUM('sempre','sessao','dia') NOT NULL DEFAULT 'sempre',
    dismissivel TINYINT(1) NOT NULL DEFAULT 0,
    dismiss_ttl_horas SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    criado_por INT NULL,
    atualizado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comunicados_publicacao (status, publico, placement, inicio_em, fim_em),
    KEY idx_comunicados_prioridade (placement, prioridade, id),
    KEY idx_comunicados_criado_por (criado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12.2 Métricas diárias

```sql
CREATE TABLE IF NOT EXISTS comunicado_metricas_diarias (
    comunicado_id BIGINT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    perfil ENUM('cliente','guincho') NOT NULL,
    impressoes INT UNSIGNED NOT NULL DEFAULT 0,
    cliques INT UNSIGNED NOT NULL DEFAULT 0,
    fechamentos INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (comunicado_id, data, perfil),
    KEY idx_comunicado_metricas_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

A FK pode ser adicionada somente após confirmar que os tipos e a política de deleção do banco de produção são compatíveis. Arquivar é preferível a excluir.

### 12.3 Placements aceitos na primeira versão

```text
cliente_dashboard_top
guincho_dashboard_after_stats
```

Não aceitar strings arbitrárias vindas do POST. Definir constantes em `ComunicadoService`.

---

## 13. Central de Comunicados — classes e arquivos

### 13.1 Novos arquivos

```text
src/Controllers/AdminComunicadoController.php
src/Controllers/ComunicadoController.php
src/Models/Comunicado.php
src/Services/ComunicadoService.php
src/Services/MediaUploadService.php
src/Views/admin/comunicados/index.php
src/Views/admin/comunicados/form.php
src/Views/admin/comunicados/preview.php
src/Views/components/communication_carousel.php
public/assets/css/components/communications.css
public/assets/js/communications.js
install/migration_comunicados_v1.sql
qa/suites/comunicados-admin.spec.ts
qa/suites/comunicados-render.spec.ts
```

### 13.2 Métodos administrativos

```text
AdminComunicadoController::index()
AdminComunicadoController::form(?int $id)
AdminComunicadoController::save()
AdminComunicadoController::publish(int $id)
AdminComunicadoController::pause(int $id)
AdminComunicadoController::archive(int $id)
AdminComunicadoController::preview(int $id)
AdminComunicadoController::metrics(int $id)
```

### 13.3 Métodos de leitura

```text
Comunicado::findById(int $id)
Comunicado::listAdmin(array $filters, int $page, int $limit)
Comunicado::listActive(string $profile, string $placement, int $limit = 5)
Comunicado::save(array $data)
Comunicado::setStatus(int $id, string $status, int $adminId)
Comunicado::incrementMetric(int $id, string $profile, string $event)
```

### 13.4 Serviço

```text
ComunicadoService::validatePayload(array $input)
ComunicadoService::validateSchedule(?string $start, ?string $end)
ComunicadoService::sanitizeTargetUrl(?string $url)
ComunicadoService::resolveActiveForProfile(string $profile, string $placement)
ComunicadoService::toViewModel(array $row)
```

---

## 14. Upload e dimensionamento de banners

### 14.1 Não reutilizar o upload atual sem reforço

O helper atual verifica apenas extensão e tamanho. O novo `MediaUploadService` deve usar:

- `finfo_file()` para MIME;
- `getimagesize()` para dimensões;
- nome aleatório com `random_bytes()`;
- subdiretório por ano/mês;
- reprocessamento por GD quando disponível;
- remoção de metadados por reencode;
- bloqueio de SVG/GIF na primeira versão;
- permissões 0644;
- proteção do diretório contra execução de PHP.

### 14.2 Formatos aceitos

```text
image/jpeg
image/png
image/webp
```

### 14.3 Limites

| Variante | Recomendado | Mínimo | Proporção | Tamanho máximo |
|---|---:|---:|---:|---:|
| Wide desktop | 1440 × 420 | 1200 × 350 | 24:7 ± 6% | 3 MB antes / 1.2 MB alvo |
| Wide mobile | 1080 × 608 | 720 × 405 | 16:9 ± 6% | 2 MB antes / 700 KB alvo |
| Card | 640 × 360 | 480 × 270 | 16:9 ± 6% | 1.5 MB antes / 500 KB alvo |

Dimensão máxima absoluta: 4096 × 4096.

### 14.4 Focal point

O admin deve ajustar `object_position_x` e `object_position_y` em preview. Isso evita cortar rosto, veículo ou produto em telas diferentes.

### 14.5 Texto na imagem

Avisar no formulário:

> Evite inserir títulos e botões dentro da arte. O sistema renderiza texto e CTA de forma responsiva e acessível.

---

## 15. Regras de exibição e duração

### 15.1 Quantidade

- máximo de 5 comunicados ativos por placement;
- ordenar por `prioridade ASC, id DESC`;
- prioridade menor aparece primeiro;
- se houver apenas um, não mostrar dots nem autoplay.

### 15.2 Duração

- padrão: 8 segundos;
- mínimo permitido: 5 segundos;
- máximo permitido: 20 segundos;
- transição: 350ms;
- pausar em hover, foco, toque prolongado e quando a aba não estiver visível;
- `prefers-reduced-motion: reduce`: sem autoplay e sem transição animada.

### 15.3 Frequência

- `sempre`: exibe em toda visita;
- `sessao`: uma impressão por sessão;
- `dia`: uma impressão por usuário/dispositivo por dia.

Usar `localStorage` apenas como controle de UX. O servidor continua sendo a fonte de verdade sobre publicação.

### 15.4 Agendamento

Um comunicado está ativo quando:

```text
status = publicado
AND inicio_em é NULL ou inicio_em <= NOW()
AND fim_em é NULL ou fim_em > NOW()
```

Validar `fim_em > inicio_em`.

### 15.5 Clique

- URL interna: aceitar somente caminho iniciado por `/`;
- URL externa: somente `https://`;
- bloquear `javascript:`, `data:`, protocolo relativo e caracteres de controle;
- `target=blank` deve usar `rel="noopener noreferrer"`.

---

## 16. Layout do painel administrativo de comunicados

### 16.1 Sidebar

Adicionar na seção “Sistema”:

```text
Comunicados
```

Ícone:

```text
fa-bullhorn
```

### 16.2 Listagem

Desktop:

- header com “Novo comunicado” à direita;
- stats: ativos, agendados, encerrados e CTR 30 dias;
- filtros em uma linha;
- tabela/cards com miniatura 160 × 72px;
- status, público, placement, período e prioridade;
- ações: editar, preview, publicar/pausar, arquivar.

Mobile:

- cards verticais;
- miniatura 100% 16:9;
- ações em menu.

### 16.3 Formulário

Duas colunas:

```text
Formulário 7/12 | Preview sticky 5/12
```

Campos:

1. título/subtítulo/tag;
2. público;
3. placement;
4. formato/tema;
5. desktop/mobile image;
6. focal point;
7. CTA;
8. agenda;
9. duração/frequência;
10. prioridade/fechamento;
11. status.

### 16.4 Preview

Abas:

```text
Desktop 1440
Tablet 768
Mobile 390
```

O preview deve usar o mesmo partial e CSS da área real, não uma imitação.

---

## 17. Rotas

### 17.1 Admin — GET

```text
/admin/comunicados
/admin/comunicado/novo
/admin/comunicado/{id}/editar
/admin/comunicado/{id}/preview
/admin/comunicado/{id}/metricas
```

### 17.2 Admin — POST

```text
/admin/comunicado/salvar
/admin/comunicado/{id}/publicar
/admin/comunicado/{id}/pausar
/admin/comunicado/{id}/arquivar
```

### 17.3 Métricas

```text
POST /comunicado/{id}/impressao
POST /comunicado/{id}/clique
POST /comunicado/{id}/fechamento
```

Aplicar autenticação, rate limit leve e validação do perfil da sessão.

---

## 18. Integração nos controllers existentes

### 18.1 `ClienteController::dashboard()`

Acrescentar:

```php
$comunicados = ComunicadoService::resolveActiveForProfile(
    'cliente',
    ComunicadoService::PLACEMENT_CLIENT_DASHBOARD_TOP
);
```

Também gerar CSRF para o campo rápido e verificar pedido ativo antes de exibir o composer.

### 18.2 `ClienteController::pedidoNovo()`

- carregar `$_SESSION['pedido_rascunho']`;
- rejeitar rascunho expirado;
- passar o dado à view;
- limpar somente após o formulário ser inicializado ou após o pedido ser criado.

### 18.3 `GuinchoController::dashboard()`

Carregar comunicados depois de determinar:

- pedido ativo;
- pedido pendente;
- disponibilidade.

A view decide a posição, respeitando prioridade operacional.

---

## 19. JavaScript

### 19.1 Novos arquivos

```text
public/assets/js/client-dashboard.js
public/assets/js/tow-dashboard.js
public/assets/js/quick-rescue.js
public/assets/js/communications.js
public/assets/js/request-form.js
```

Mover os scripts inline dos dashboards para os respectivos arquivos.

### 19.2 Contratos por `data-*`

Exemplo:

```html
<section
    class="quick-rescue"
    data-draft-url="/cliente/pedido/rascunho"
    data-geocode-url="/geocode"
    data-reverse-url="/geocode/reverse"
    data-csrf="...">
```

### 19.3 Logs do frontend

Erros importantes devem ir ao console com prefixo estruturado e, quando necessário, a endpoint de observabilidade:

```text
[QUICK_RESCUE][geocode][QUICK_RESCUE_GEOCODE_FAILED]
[COMMUNICATIONS][render][COMMS_RENDER_FAILED]
[TOW_DASHBOARD][offers][TOW_OFFERS_FETCH_FAILED]
```

Não engolir todos os `.catch(() => {})` silenciosamente.

---

## 20. Logs de backend

O projeto já possui a estrutura certa. Usar os campos completos.

### 20.1 Sistemas

```text
QUICK_RESCUE
COMUNICADOS_ADMIN
COMUNICADOS_RENDER
COMUNICADOS_METRICS
CLIENT_DASHBOARD
TOW_DASHBOARD
TOW_OFFER
```

### 20.2 Códigos mínimos

| Código | Classe/função esperada | Fase |
|---|---|---|
| `QUICK_RESCUE_DRAFT_SAVED` | `ClienteController::pedidoRascunho` | save_draft |
| `QUICK_RESCUE_INVALID_COORDS` | `ClienteController::pedidoRascunho` | validation |
| `COMMS_SAVE_OK` | `AdminComunicadoController::save` | persist |
| `COMMS_VALIDATION_FAILED` | `ComunicadoService::validatePayload` | validation |
| `COMMS_MEDIA_REJECTED` | `MediaUploadService::storeCommunicationImage` | upload_validation |
| `COMMS_MEDIA_STORED` | `MediaUploadService::storeCommunicationImage` | upload_persist |
| `COMMS_PUBLISH_OK` | `AdminComunicadoController::publish` | publish |
| `COMMS_ACTIVE_QUERY_FAILED` | `Comunicado::listActive` | query |
| `COMMS_METRIC_UPSERT_FAILED` | `Comunicado::incrementMetric` | metrics |
| `TOW_OFFER_ALREADY_TAKEN` | `GuinchoController::aceitar` | concurrency |

### 20.3 Exemplo

```php
Logger::event([
    'level' => Logger::LEVEL_ERROR,
    'system' => 'COMUNICADOS_ADMIN',
    'class' => AdminComunicadoController::class,
    'function' => __FUNCTION__,
    'file' => __FILE__,
    'phase' => 'upload_validation',
    'code' => 'COMMS_MEDIA_REJECTED',
    'usuario_id' => $adminId,
    'message' => 'Imagem desktop rejeitada.',
    'context' => [
        'mime' => $mime,
        'width' => $width,
        'height' => $height,
        'reason' => $reason,
    ],
]);
```

---

## 21. Acessibilidade

- contraste mínimo 4.5:1 para texto comum;
- foco visível em todos os controles;
- carousel com botões anteriores/próximos rotulados;
- aria-live somente para mudanças importantes, sem anunciar cada rotação automática;
- autoplay pausável;
- alt text obrigatório quando a imagem comunica conteúdo;
- CTA nunca depender apenas da cor;
- skeleton não deve ser lido por screen reader;
- campo rápido deve ter `<label>` real, mesmo que visualmente oculto;
- erros associados via `aria-describedby`;
- não usar banners piscantes.

---

## 22. Responsividade

### Desktop ≥ 1200px

- conteúdo máximo 1440px;
- gap 24px;
- grids 7/5 ou 8/4;
- banners largos;
- preview e formulários lado a lado.

### Tablet 768–1199px

- padding 20px;
- grids permanecem em duas colunas somente quando cada coluna mantiver pelo menos 360px;
- caso contrário, empilhar;
- sidebar atual pode virar faixa horizontal.

### Mobile < 768px

- padding 16px;
- uma coluna;
- CTA full-width;
- mapa 300–420px conforme tela;
- carousel 16:9;
- barra de ações sticky em aceite/atendimento;
- navegação inferior recomendada:

Cliente:

```text
Painel | Socorro | Pedidos | Conta
```

Guincho:

```text
Painel | Pedidos | Atendimento | Conta
```

A barra inferior deve ter 64px + safe area e alvos mínimos de 56px.

---

## 23. Arquivos existentes afetados

### Alteração obrigatória

```text
index.php
src/Controllers/ClienteController.php
src/Controllers/GuinchoController.php
src/Controllers/GeocodeController.php
src/Views/cliente/dashboard.php
src/Views/cliente/pedidonovo.php
src/Views/guincho/dashboard.php
src/Views/guincho/pedidoaceitar.php
src/Views/guincho/atendimento.php
src/Views/layouts/sidebar_admin.php
public/assets/css/style.css
```

### Alteração recomendada

```text
src/Views/layouts/header.php
src/Views/cliente/pedidostatus.php
public/assets/js/app.js
```

### Não alterar contratos sem necessidade

Preservar:

```text
#toggleDisponivel
#pedidosDisponiveisContainer
#pedidoAtivoClienteCard
#pedidoAtivoGuinchoCard
#map
status-json endpoints
SSE de pedidos
Proof-of-Road
máquina de estados do atendimento
```

---

## 24. Correções preliminares antes do desenvolvimento visual

Executar primeiro:

1. remover BOM de `cliente/dashboard.php` e `guincho/dashboard.php`;
2. corrigir mojibake nos arquivos afetados;
3. remover o literal `` `r`n `` do dashboard do guincho;
4. remover texto técnico visível no card do mapa;
5. trocar Leaflet CDN por assets locais nas views tocadas;
6. eliminar banners de status duplicados no dashboard;
7. remover CSS local das duas views para `dashboard.css`;
8. remover JavaScript inline para arquivos próprios;
9. padronizar encoding UTF-8 sem BOM;
10. manter `style.css` válido e reduzir seletores genéricos `[class*="..."]`, que podem atingir elementos não previstos.

---

## 25. Testes Playwright

### 25.1 Novas suítes

```text
qa/suites/layout-dashboard.spec.ts
qa/suites/quick-rescue.spec.ts
qa/suites/comunicados-admin.spec.ts
qa/suites/comunicados-render.spec.ts
qa/suites/tow-offer-layout.spec.ts
qa/suites/por-ui.spec.ts
```

### 25.2 Casos do campo rápido

- origem por texto;
- origem por GPS permitido;
- GPS negado;
- geocode sem resultado;
- rascunho preenche nova solicitação;
- rascunho expira;
- pedido ativo oculta o composer;
- CSRF inválido;
- coordenadas inválidas.

### 25.3 Casos dos comunicados

- criar rascunho;
- validar dimensões erradas;
- validar MIME falso;
- publicar para cliente;
- não renderizar para guincho;
- agendamento futuro;
- expiração;
- prioridade;
- desktop/mobile source;
- autoplay 8s;
- reduced motion;
- dismissível;
- CTA interno e externo;
- URL maliciosa rejeitada;
- métricas incrementadas.

### 25.4 Casos visuais

Viewports:

```text
390 × 844
768 × 1024
1366 × 768
1920 × 1080
```

Asserções:

- nenhum overflow horizontal;
- hero ≤ 220px sem pedido ativo;
- quick rescue visível na primeira dobra;
- CTA com altura ≥ 48px;
- mapa do dashboard não excede 420px;
- botão Aceitar à direita;
- comunicado não precede pedido/oferta ativa;
- texto com contraste AA;
- nenhum card sobrepõe footer ou barra inferior.

### 25.5 Screenshots de regressão

Gerar snapshots por perfil e viewport. Tolerância visual baixa nos componentes estáticos e maior apenas no mapa.

---

## 26. Ordem de execução

### Fase 0 — Higiene

- encoding;
- texto técnico;
- CSS/JS inline;
- Leaflet local;
- duplicações.

### Fase 1 — Base visual

- tokens métricos;
- `screen-container`;
- `dashboard.css`;
- componentes compartilhados;
- responsividade.

### Fase 2 — Campo rápido

- componente;
- rota de rascunho;
- reverse geocode;
- prefill da solicitação;
- testes.

### Fase 3 — Fluxos críticos

- dashboard cliente;
- dashboard guincho;
- aceite do pedido;
- POR visível no atendimento.

### Fase 4 — Central de Comunicados

- migration;
- model/service/controllers;
- admin CRUD;
- upload seguro;
- carousel;
- métricas.

### Fase 5 — QA e hardening

- Playwright;
- contraste;
- mobile;
- segurança de upload/URL;
- logs;
- performance.

---

## 27. Critérios de aceite finais

### Cliente

- campo “Onde está o veículo?” aparece na primeira dobra quando não há pedido ativo;
- origem selecionada chega pré-preenchida à nova solicitação;
- exatamente um componente principal de pedido ativo;
- mapa do dashboard tem altura controlada;
- comunicado não compete com emergência;
- mobile tem CTA e navegação alcançáveis com o polegar.

### Guincho

- toggle único e funcional;
- oferta mostra informação suficiente antes do aceite;
- Recusar à esquerda, Aceitar à direita;
- mapa e ETA disponíveis na oferta;
- comunicado fica abaixo da prioridade operacional;
- POR é visível e explica bloqueios de transição.

### Admin

- CRUD separado em `AdminComunicadoController`;
- preview desktop/mobile usa o componente real;
- agendamento e expiração funcionam;
- upload valida MIME e dimensões;
- todos os erros identificam sistema, classe, função, arquivo, fase e código;
- métricas de impressão/clique disponíveis.

### CSS

- nenhum CSS novo dentro das views afetadas;
- nenhum novo `style="..."`;
- tokens e componentes reutilizados;
- contraste AA;
- sem overflow em 390, 768, 1366 e 1920px;
- `style.css` permanece como base, sem crescer com regras específicas de uma única tela.

---

## 28. Parecer final

A base já possui os recursos mais difíceis — estados de pedido, SSE, chat, pagamento por ambiente, rastreamento, evidências, POR, logs e Playwright. O maior desvio está na apresentação e na separação de responsabilidades.

As duas decisões mais valiosas são:

1. transformar o botão isolado do dashboard em um **composer rápido de origem**, sem criar pedido prematuramente;
2. criar uma **Central de Comunicados modular**, com prioridade inferior aos estados operacionais e com upload/agendamento/preview próprios.

Isso entrega o escopo visual da referência sem converter uma aplicação de socorro em vitrine de banners. Primeiro a pane, depois a propaganda.
