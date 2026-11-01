# FIN.1 — Gestão financeira e repasses

Status: **pacote de implantação local pendente de homologação**. Ambiente alvo: Windows XAMPP, PHP 8.0, `guinchafacil_dev`. Referência visual: screenshot da página "Gestão financeira & repasses" (Lovable, 18/10/2026).

## Escopo executado

- Reutilizar a rota `/admin/financeiro`, controller, dados e operações atuais; **não duplicar endpoint**.
- Aplicar uma folha CSS de acabamento visual `gf-fin1-financeiro-repasses.css`, carregada **após** os CSS existentes.
- Manter sidebar escura com logotipo, altura `100dvh` em desktop, scroll interno com barra oculta, sem espaço à esquerda ou header legado.
- Manter título, filtros, exportação CSV, KPIs reais, lista e detalhes dos pagamentos, jobs PIX, indicador de oficinas e formulários existentes.
- Não inserir métricas simuladas ou dados fictícios.
- Não criar migration, não gravar no banco, não alterar controllers.

## Contrato financeiro aprovado (fases posteriores)

1. Cliente pode pagar via gateway à plataforma ou diretamente ao prestador.
2. Serviço pago à plataforma: reconhecer crédito do parceiro pelo valor que contratualmente lhe pertence, sem presumir que todo valor bruto é repassável.
3. Serviço pago ao prestador: reconhecer débito de comissão ou demais encargos contratualmente devidos à plataforma.
4. Consolidar e compensar semanalmente créditos e débitos individualizados, além de estornos/ajustes autorizados.
5. `saldo_parceiro = credito_parceiro - debito_parceiro`; positivo implica repasse; zero compensação; negativo implica obrigação de pagar à plataforma.
6. Inadimplência somente depois de dívida **líquida** a favor da plataforma, vencimento e carência, com conciliação, aviso e desbloqueio auditáveis.
7. Apuração em centavos, sem duplicação por webhook/reprocessamento, com referência única por lançamento, ciclo e parceiro.
8. Não há implementação de compensação bilateral neste patch visual; a definição contábil, origem dos ajustes e tabelas devem ser auditadas antes da FIN.2/FIN.3.

## Contrato operacional complementar

- Mapeamento anelar do raio mais próximo ao limite de cobertura.
- Elegibilidade exige presença online, especialidade/capacidade, atendimento na região e situação financeira regular.
- Dentro do anel selecionado, referência comum de deslocamento = maior custo de deslocamento dentre os elegíveis desse anel, conforme motor contratual.
- Guincho considera deadhead e demais custos do transporte; oficina considera deslocamento local e componentes contratados.
- Oficina que declarou capacidade de remoção é responsável por levar o veículo às próprias instalações quando a oferta incluir remoção sem cobrança adicional.
- Falta de oficina leva ao fluxo de reboque; falta de ambos leva ao suporte WhatsApp e alerta administrativo, com despacho manual auditado sem burlar bloqueios obrigatórios.

## Critérios de aceite visual e técnico

- GET `/admin/financeiro` mantém autenticação, filtros e exportação; nenhum erro PHP.
- Sidebar ocupa a altura da viewport desktop, rola sem barra visível e conserva marca e subtítulo corretos.
- Sem topo administrativo legado duplicado nem breadcrumb adicional.
- KPIs, pagamentos, jobs e ações de oficina continuam renderizados a partir dos dados reais.
- Interação, permissões, exportação e CSRF dos formulários preservados; testar em sessão administrativa e sem autenticação.
- Verificar 1366px, 1024px e 390px; mobile usa menu adaptativo existente.
- Dry-run → backup → apply → smoke tests; sem commit ou push até aceite.

## Próximas fases

- FIN.2: mapear origens de pagamentos, comissões, custos, faturas e conciliação; serviço read-only de agregação.
- FIN.3: livro bilateral e fechamento semanal idempotente.
- FIN.4: saldo líquido vencido, cobrança e sanções administrativas proporcionais.
- FIN.5: exportação auditada, relatórios de exceção e regressão ponta a ponta.
