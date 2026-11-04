# QA consolidado - 2026-11-03

## Suite executada

Comando: `php tools/phpunit.phar --configuration phpunit.xml --filter 'SplitRepasseConclusaoTest|DiagnosticoOrcamentoEstoqueTest|ConversionServiceHibridoTest|ConversionServiceCreditoTest|DecisaoOficinasMariaDbTest|PagamentoAprovacaoServiceCoberturaTest|SimulationFlowTest|PedidoIdempotenciaTest|PartnerReferralAndProspectionTest'`

Resultado: **PASS** - 28 testes, 189 assercoes, 1 warning e 3 deprecacoes do PHPUnit.

Cobertura incluida:

- split financeiro e fallback liquido;
- diagnostico, orcamento complementar e estoque idempotente;
- conversao hibrida e credito;
- expansao de raio e catalogo de oficinas;
- cobertura com oficina/guincho, somente reboque e sem cobertura;
- simulacao cliente -> veiculo -> pedido -> pagamento -> guincho -> chat -> avaliacao -> webhook;
- idempotencia por triagem;
- idempotencia de webhook de pagamento;
- comissao e fila manual de prospeccao.

## Gates adicionais

- `tests/b011_regra_territorial.php`: PASS, 0 falhas.
- `tools/qa_test_gate_cobertura.php`: PASS.
- `tools/agents/gf-funil-checkout-qa.ps1`: PASS em lint PHP, contrato financeiro e mojibake.

## Correcao do gate de encoding

O detector do gate foi ajustado para ler os bytes originais do `git diff`, evitando a conversao de UTF-8 pelo pipeline do Windows PowerShell. A regra continua bloqueando caractere de substituicao e sequencias de dupla codificacao, mas aceita acentos legitimos do portugues.

## Protocolo documental

Backups e rascunhos obsoletos foram preservados em `_archive/docs-obsoletos-20261103`. A precedencia atual esta em `doc/INDICE-AUTORIDADE-DOCUMENTAL.md`.

## Limite de aceite

Os testes locais e gates tecnicos passaram. Isso nao constitui prova de pagamento real, webhook externo ou deploy remoto; essas etapas continuam exigindo evidencia autenticada no sandbox/producao correspondente.
