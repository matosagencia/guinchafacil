# Diretriz Oficial do Novo Visual do Backoffice GuinchaFácil

**Documento Normativo de Engenharia e UX/UI**  
**Versão:** 2.0 (Master Consolidação de Telas e Design System)  
**Status:** Aprovado para Execução Multi-Agente (Faixas A, B e C)  
**Referência Homologada:** `roadside-connect-hub.lovable.app/admin`

---

## 1. Princípios e Fundamentos do Design System Lovable

Esta diretriz estabelece a padronização definitiva da interface do Backoffice GuinchaFácil, integrando a hierarquia visual homologada no Lovable com o inventário completo de funcionalidades e rotas do backend PHP legado.

### 1.1. Premissas Arquiteturais Inegociáveis
1. **Preservação Absoluta das Regras de Negócio e Endpoints:**  
   Nenhum cálculo de preço (`PrecificacaoService`), matching de guinchos (`GuinchoDisponibilidade`), regra de triagem (`DecisaoAtendimentoService`), persistência (`PedidoCoreService`), chamada de pagamento ao Mercado Pago ou repasse PIX deve ser alterado.
2. **Preservação de Contratos de Formulários:**  
   Todos os atributos `id`, `name`, classes operacionais JS (como `data-*`), tokens CSRF e confirmações sensíveis com senha de administrador (cancelamento e conclusão manual) devem ser rigorosamente mantidos.
3. **Consolidação em Páginas-Hub:**  
   Em vez de 25+ telas dispersas que sobrecarregam o menu lateral, o sistema adota **7 módulos consolidados com navegação em abas secundárias (`admin_module_tabs`)** e **Fichas em Gaveta/Split View (Drawer Lateral)**.

---

## 2. Matriz Cromática e Design Tokens por Perfil

O backoffice possui um shell unificado (`.bo`), mas adapta dinamicamente suas cores, contrastes e densidade de informação conforme o papel autenticado:

```
┌─────────────────┬──────────────────────┬────────────────────────┬────────────────────────┐
│ Perfil          │ Cor de Acento        │ Fundo Base / Superfície│ Objetivo Ergonômico     │
├─────────────────┼──────────────────────┼────────────────────────┼────────────────────────┤
│ 1. Admin Geral  │ Verde (#2fb34a)      │ Preto (#090d0b /       │ Alta densidade, controle│
│    e Operação   │ Hover (#1f8a36)      │ #131a15)               │ de rota e triagem rápida│
├─────────────────┼──────────────────────┼────────────────────────┼────────────────────────┤
│ 2. Guincheiro   │ Verde Vivo (#22c55e) │ Verde Floresta Escuro  │ Alto contraste para tela│
│    (Driver)     │ Alerta (#eab308)     │ (#0a1f11 / #000000)    │ sob sol no painel       │
├─────────────────┼──────────────────────┼────────────────────────┼────────────────────────┤
│ 3. Área Cliente │ Verde Marca (#2fb34a)│ Branco Puro (#ffffff / │ Acolhimento, clareza,   │
│                 │ Badges (#e8f7ec)     │ #f8fafc)               │ status e suporte rápido │
├─────────────────┼──────────────────────┼────────────────────────┼────────────────────────┤
│ 4. Oficina      │ Laranja Mecânico     │ Grafite Escuro         │ Gestão de pátio, ordem  │
│    Parceira     │ (#f97316 / #ea580c)  │ (#0f141c / #1c2430)    │ de serviço e orçamentos │
└─────────────────┴──────────────────────┴────────────────────────┴────────────────────────┘
```

### Tokens CSS Globais (Implementação em `public/assets/css/admin-themes.css`)

```css
/* Shell Base */
.bo {
  display: flex;
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
  font-size: 12px;
  line-height: 1.5;
  transition: background-color 0.2s ease, color 0.2s ease;
}

/* 1. ADMIN & OPERAÇÃO: Preto com Verde Operacional */
.bo-admin, .bo-operator {
  --bo-bg: #090d0b;
  --bo-surface: #131a15;
  --bo-surface-elevated: #1b261f;
  --bo-border: #223628;
  --bo-text-main: #f0fdf4;
  --bo-text-muted: #86efac;
  --bo-accent: #2fb34a;
  --bo-accent-hover: #1f8a36;
  --bo-accent-soft: rgba(47, 179, 74, 0.12);
  --bo-danger: #ef4444;
  --bo-warning: #f59e0b;
}

/* 2. GUINCHO / MOTORISTA: Verde Escuro com Branco e Alto Contraste */
.bo-driver {
  --bo-bg: #000000;
  --bo-surface: #0a1f11;
  --bo-surface-elevated: #12331c;
  --bo-border: #1d4d2b;
  --bo-text-main: #ffffff;
  --bo-text-muted: #a7f3d0;
  --bo-accent: #22c55e;
  --bo-accent-hover: #16a34a;
  --bo-accent-soft: rgba(34, 197, 94, 0.2);
  --bo-warning: #eab308;
  --bo-font-size-cta: 16px;
  --bo-touch-height: 52px;
}

/* 3. CLIENTE: Fundo Claro com Acentos Verdes */
.bo-client {
  --bo-bg: #f8fafc;
  --bo-surface: #ffffff;
  --bo-surface-elevated: #ffffff;
  --bo-border: #e2e8f0;
  --bo-text-main: #0f172a;
  --bo-text-muted: #64748b;
  --bo-accent: #2fb34a;
  --bo-accent-hover: #1f8a36;
  --bo-accent-soft: #e8f7ec;
  --bo-danger: #dc2626;
  --bo-warning: #d97706;
}

/* 4. OFICINA PARCEIRA: Grafite Escuro com Laranja Mecânico */
.bo-workshop {
  --bo-bg: #0f141c;
  --bo-surface: #1c2430;
  --bo-surface-elevated: #283344;
  --bo-border: #3b485d;
  --bo-text-main: #fff7ed;
  --bo-text-muted: #fed7aa;
  --bo-accent: #f97316;
  --bo-accent-hover: #ea580c;
  --bo-accent-soft: rgba(249, 115, 22, 0.15);
  --bo-danger: #ef4444;
  --bo-warning: #f59e0b;
}
```

---

## 3. O Shell de Interface Lovable (Estrutura Física de Layout)

Todas as telas do backoffice encaixam-se no esqueleto de 4 zonas visuais:

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│ TOPBAR GLOBAL (.bo-header)                                                      │
│ [Recolher Menu]  Breadcrumb: Backoffice > Operação > Ao Vivo    [Tema] [🔔] [Perfil]│
├──────────────┬───────────────────────────────────────────────────────────────────┤
│ SIDEBAR      │ STRIP DE KPIs (.bo-metrics) — 8 métricas tabulares em tempo real │
│ (.bo-sidebar)│ Ativos | Sob Prestador | A Caminho | Atendimento | Alertas | ...  │
│              ├─────────────────────────────────┬─────────────────────────────────┤
│ [Logo GF]    │ COLUNA ESQUERDA (.bo-orders)    │ ÁREA DE TRABALHO (.bo-incident) │
│              │ (310px - Busca e Fila)          │ (Split View ou Tela Cheia)      │
│ [Workspace]  │                                 │                                 │
│              │ - Campo de Busca de Pedido      │ - Cabeçalho: ID, Status, Ações  │
│ - Operação   │ - Abas Rápidas:                 │ - Visualizador Mapa Leaflet     │
│ - Parceiros  │   [Todos] [Urgentes] [Concluídos]│ - Detalhes da Rota / Origem/Dest │
│ - Financeiro │                                 │ - Abas: [Mapa] [Linha do Tempo] │
│ - Catálogo   │ - Lista de Cards:               │         [Chat Operacional]      │
│ - Território │   * GF-72 - Buscando Prestador  │ - Ações: [Despachar] [Status]   │
│ - Qualidade  │   * GF-49 - A Caminho (ETA 8m)  │          [Atribuir] [Cancelar]  │
│ - Sistema    │   * GF-33 - Aguardando Pgto     │                                 │
└──────────────┴─────────────────────────────────┴─────────────────────────────────┘
```

### Componentes Chave do Shell:
1. **Sidebar Responsiva (`.bo-sidebar`):**
   - Largura padrão: 220px; quando recolhida: 68px (ícones centralizados, tooltips automáticos).
   - Destaque no item ativo com barra lateral em `--bo-accent`.
2. **Strip de Indicadores Tabulares (`.bo-metrics`):**
   - 8 colunas de largura idêntica, bordas divisórias sutis e números em fonte tabular (`tabular-nums`).
   - Destaque em vermelho suave (`--bo-danger`) automático quando houver alertas críticos ou prestadores pendentes.
3. **Split Grid Operacional (`.bo-operation-grid`):**
   - Proporção: 310px fixos para lista/fila e `minmax(0, 1fr)` flexível para a ficha detalhada.

---

## 4. Reorganização dos Módulos do ADMIN (De 25 Telas para 7 Hubs)

### Módulo 1: Operação (`/admin` ou `/admin/operacao`)
*Funde: Central Operacional + Gestão de Pedidos + Despacho + Alertas + Ocorrências + Resumo Executivo + Detalhe do Pedido + Trilha POR.*

- **Abas do Módulo:**
  1. **Ao Vivo (Padrão):** O layout split da Central Lovable (Fila de pedidos ativos à esquerda + Mapa ao vivo com pins e ações de despacho à direita).
  2. **Pedidos:** Tabela completa com filtros salvos (*Sem Prestador*, *A Caminho*, *Concluídos Hoje*, *Cancelados*, *Aguardando Pagamento*).
  3. **Exceções:** Agrupamento unificado de chamados com SLA violado, alertas de rede e ocorrências abertas pelo cliente/motorista.
  4. **Resumo Executivo:** Visão compacta de performance do dia (volume de chamados, faturamento bruto e tempo médio de resposta).
- **Ficha do Pedido (Drawer / Painel Lateral):**
  - Aberto sem recarregar a página ao clicar em qualquer pedido.
  - Abas internas da ficha: *Resumo*, *Prestador & Despacho*, *Rota & Proof-of-Road (POR)*, *Evidências Fotográficas*, *Financeiro & Repasse*, *Chat* e *Auditoria*.
  - Ações críticas de alteração de status, cancelamento e conclusão manual exigem confirmação modal com senha do admin.

### Módulo 2: Pessoas & Rede de Parceiros (`/admin/parceiros`)
*Funde: Pessoas + Guinchos + Guinchos Pendentes + Documentos + Especialistas + Oficinas Parceiras + Cadastro de Guincho + Edição de Usuário.*

- **Abas do Módulo:**
  1. **Guincheiros:** Base cadastrada com filtro por status (*Ativo*, *Em Corrida*, *Offline*, *Bloqueado*), tipo de caminhão (Plataforma, Asa Delta, Pesado) e reputação.
  2. **Oficinas:** Lista de oficinas credenciadas, capacidade de pátio, serviços aceitos e taxa de comissão contratada.
  3. **Clientes:** Cadastro de clientes com histórico de solicitações e veículos registrados.
  4. **Equipe Interna:** Administradores, operadores de despacho e gerentes com suas respectivas alçadas.
  5. **Fila de Aprovações (Inbox Único):** Caixa unificada de cadastros novos, documentos vencendo/vencidos e solicitações de novos serviços pelas oficinas, com botões de *Aprovar* ou *Recusar* em um clique.
- **Ficha Única de Pessoa/Prestador (Drawer):**
  - Dados Pessoais & Contato · Veículos & Equipamentos · Documentação (CNH, CRLV, ANTT) · Histórico de Corridas · Saldo & Chave PIX.

### Módulo 3: Financeiro & Repasses (`/admin/financeiro`)
*Funde: Financeiro Geral + Faturas de Parceiros + Saques PIX + Carteiras + Conciliação Mercado Pago.*

- **Abas do Módulo:**
  1. **Visão Geral:** KPIs do dia/mês (Receita Aprovada, Comissão Plataforma, Repasse Pendente, Ticket Médio).
  2. **Pagamentos de Clientes:** Conciliação Mercado Pago Brick (PIX e Cartão), status de aprovação, estornos e retentativas.
  3. **Repasses a Prestadores:** Fila de liquidação PIX automática/manual com status de webhook.
  4. **Faturas de Oficinas:** Fechamento quinzenal/mensal de comissões geradas por indicações e reparos.
  5. **Extrato Detalhado do Pedido (Drawer Lateral):**
     - O mesmo componente já desenhado no Lovable:  
       `Valor Cobrado = Base (km e tarifa) + Intermediação + Relevo/Pedágio`  
       `Deduções = Comissão GuinchaFácil (ex: 8.00%) + Reserva de Risco (4.98%)`  
       `Líquido a Repassar via PIX = R$ 193,94` (com botão de disparo imediato).

### Módulo 4: Catálogo, Serviços & Precificação (`/admin/servicos`)
*Funde: Catálogo de Serviços Legado + Catálogo Estruturado + Matriz de Preços + Fatores de Relevo.*

- **Abas do Módulo:**
  1. **Tabela de Preços Base:** Tarifa de saída por tipo de veículo (Passeio, Moto, SUV, Pesado) e valor por KM excedente.
  2. **Fatores Adicionais:** Regras de relevo (aclive/serra), pedágio estimado, adicional noturno e horários de pico.
  3. **Serviços de Socorro no Local:** Cadastro de serviços leves (Troca de Pneu, Recarga de Bateria, Chaveiro, Combustível) com valores fixos.

### Módulo 5: Território & Planejamento (`/admin/territorio`)
*Funde: Demanda Territorial + Prospecção + Cobertura de Praças + Geofences.*

- **Abas do Módulo:**
  1. **Mapa de Calor & Demanda:** Visualização de solicitações atendidas e recusadas por região geográfica.
  2. **Praças Ativas & Geofencing:** Definição dos anéis de busca (2km a 50km) e limites operacionais por município (ex: Niterói, São Gonçalo, Rio de Janeiro).
  3. **Prospecção de Parceiros:** Leads e mapeamento de guincheiros e oficinas para credenciamento onde há escassez de oferta.

### Módulo 6: Qualidade, Reputação & Auditoria (`/admin/qualidade`)
*Funde: Avaliações + Auditoria do Ambiente + Logs de Conclusão Manual + Histórico de Alçadas.*

- **Abas do Módulo:**
  1. **Avaliações:** Feedback deixado pelos clientes após cada atendimento (notas 1 a 5, comentários e tags de elogio/reclamação).
  2. **Proof-of-Road (Trilhas GPS):** Auditoria de rotas percorridas e fotos tiradas pelo guincheiro no embarque e desembarque do veículo.
  3. **Logs de Auditoria:** Rastreabilidade de ações sensíveis executadas por funcionários e gerentes.

### Módulo 7: Sistema & Configurações (`/admin/sistema`)
*Funde: Servidores + Configurações de API + Monitoramento de Cron Jobs.*

- Parâmetros gerais da plataforma, credenciais do Mercado Pago e rotinas em segundo plano (Worker de PIX, polling de fila e SSE).

---

## 5. Diretrizes para o App do GUINCHO (`.bo-driver`)

O guincheiro utiliza a aplicação majoritariamente em smartphone montado no painel do caminhão, operando sob sol forte ou durante a noite.

1. **Tela Inicial / Modo Ronda (Disponível para Chamados):**
   - Toggle gigante: `[ FICAR ONLINE ]` (Verde) / `[ OFFLINE ]` (Cinza).
   - Resumo rápido de ganhos do dia no topo.
   - Mapa com localização atual e raio de chamada ativo.
2. **Modal de Oferta de Corrida (Tempo Real - Som + Contagem):**
   - Cronômetro regressivo de 30 segundos.
   - Origem, destino e distância até o cliente.
   - Tipo de veículo e problema relatado.
   - **Valor Líquido da Corrida em destaque:** ex: `R$ 185,00 à vista via PIX`.
   - Botão de ação gigante: `[ ACEITAR CORRIDA ]` (Verde `#22c55e`).
3. **Corrida em Andamento (Passo a Passo com Proof-of-Road):**
   - Botão 1: `A Caminho do Cliente` (abre Waze/Google Maps com 1 clique).
   - Botão 2: `Cheguei ao Local` (dispara notificação automática ao cliente).
   - Botão 3: `Veículo Embarcado` (exige envio obrigatório de 2 fotos do carro no guincho).
   - Botão 4: `Concluir Atendimento no Destino` (libera o pagamento instantâneo).
4. **Extrato de Ganhos e Chave PIX:**
   - Saldo disponível para saque imediato.
   - Lista de corridas do dia com comprovante de repasse.

---

## 6. Diretrizes para o Painel da OFICINA (`.bo-workshop`)

A oficina opera no computador da recepção ou tablet na entrada da oficina.

1. **Pátio Digital (Kanban de Veículos):**
   - Coluna 1: *A Caminho pelo Guincho* (com previsão de chegada e dados do carro).
   - Coluna 2: *Recebidos no Pátio / Aguardando Check-in* (vistoria de entrada).
   - Coluna 3: *Em Orçamento / Diagnóstico*.
   - Coluna 4: *Prontos para Retirada pelo Cliente*.
2. **Check-in Rápido de Recepção:**
   - Validação da placa e confirmação de chegada do guincho (gera o repasse automático ao guincheiro).
3. **Comissões e Indicações:**
   - Tabela de valores a receber pelas indicações originadas na plataforma.

---

## 7. Diretrizes para a Área do CLIENTE (`.bo-client`)

Interface limpa, serena e orientada a tranquilizar o motorista em momento de estresse.

1. **Card de Acompanhamento ao Vivo:**
   - Status em destaque: *"Guincho a caminho — Chegada estimada em 12 minutos"*.
   - Mapa interativo com posição do guincho em tempo real.
   - Card do motorista: Foto, Nome, Placa do Guincho, Telefone e botão de WhatsApp direto.
2. **Histórico e Recibos:**
   - Comprovantes de pagamento detalhados do Mercado Pago e laudos de socorro.

---

## 8. Guia de Execução para as Faixas A, B e C

### Faixa A (Backend, Banco de Dados & APIs - ChatGPT)
1. **View Shell Compartilhada:** Criar `src/Views/layouts/backoffice_shell.php` implementando a estrutura HTML e classes do Lovable.
2. **Endpoints de Consolidação:** Alimentar as abas dos 7 módulos com as queries reais já existentes nos controllers atuais, sem alterar nenhuma regra de precificação ou banco.
3. **Injeção de Tema por Sessão:** Passar a variável `$theme_class` (`bo-admin`, `bo-driver`, `bo-client`, `bo-workshop`) baseada em `$_SESSION['user_role']`.

### Faixa B (Views PHP, CSS e Frontend - DeepSeek)
1. **Folhas de Estilo:** Integrar o CSS do Lovable em `public/assets/css/admin-themes.css` e `public/assets/css/backoffice-layout.css`.
2. **Componentização de Abas e Drawer:** Converter as telas individuais de detalhe em painéis retráteis (`drawer`) acionados via JS sem recarregar a página inteira.
3. **Preservação de IDs e Forms:** Garantir que cada campo de input e botão de ação preserve os mesmos IDs que o backend espera.

### Faixa C (Qualidade, QA e Regressão - Gemini)
1. **Teste de Não-Regressão de Rotas:** Garantir que todas as URLs antigas continuem funcionando via redirecionamento interno ou renderização direta dentro das novas abas.
2. **Validação E2E do Fluxo Crítico:** Testar o ciclo completo: Abertura de chamado → Despacho na Central Operacional → Notificação ao Guincho → Conclusão e Repasse Financeiro PIX.
