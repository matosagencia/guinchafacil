# FIN.2 — Compensação bilateral e shell Financeiro

## Escopo entregue
- Correção incremental do shell `/admin/financeiro`: marca, menu, altura total, topbar legado oculto, CSS somente desta rota.
- Serviço matemático isolado `App\Services\Finance\CompensacaoBilateralCalculator` (centavos, IDs únicos, dois canais, validação de valores).
- Cenários de teste read-only em `tests/fin2_apuracao.php`.

## Contrato de compensação
- `plataforma`: crédito ao parceiro = bruto conciliado - comissão da plataforma - taxas expressamente atribuídas ao parceiro. Não deduzir a comissão novamente.
- `parceiro`: débito ao parceiro = comissão autorizada; dinheiro recebido no local não gera repasse.
- Saldo = créditos - débitos; positivo repasse, zero compensado, negativo obrigação do parceiro.
- **Dívida negativa não significa inadimplência imediata**: exige fechamento, vencimento, carência e ausência de conciliação; bloqueios não são acionados por este pacote.
- Eventos de estorno, ajustes, adiantamentos, taxas, pedágios e diferenças dependem de contratos e fontes auditadas. Proibido incluí-los silenciosamente no cálculo.
- Cada fechamento deve trabalhar com valores e IDs reais conciliados; a classe **não** lê a base de dados.

## Importante: FIN.2 não está conectada ao financeiro ativo
A integração transacional de compensação em `FaturaService`, `ComissaoService` e persistência será o próximo patch após coleta das fontes XAMPP ATUAIS e conferência do esquema. Não alterar cobrança, PIX ou matching nesta instalação.

## Anel tarifário e oficina
Busca progressiva; tarifa comum usa o maior custo entre candidatos elegíveis do anel selecionado. Remoção para a própria oficina, quando incluída na oferta, é responsabilidade da oficina e depende da capacidade declarada em cadastro.

## Documentação canônica
Antes de consolidar os contratos, reconciliar `doc/CONTRATOS.md`, `doc/DECISOES.md`, `doc/BLOCKERS.md`, `doc/CONTEXT_AI.md`, `doc/lanes/A.md` e atribuição das faixas. Este pacote cria documento dedicado; não reescreve os demais sem ler suas revisões locais.
