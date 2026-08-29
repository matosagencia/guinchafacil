// Dados oficiais de teste do Mercado Pago (documentação do desenvolvedor).
// O "titular do cartão" (nome e sobrenome) é a palavra-código que dispara o
// cenário — não é um nome de pessoa de verdade. O CPF de documento só é
// exigido/documentado para os cenários APRO e OTHE; nos demais o MP ignora
// esse campo para fins de cenário (mantém um CPF de teste válido mesmo assim,
// pra não travar em validação de formulário).

export type MpTestCard = {
  tipo: 'credito' | 'debito';
  bandeira: string;
  numero: string;
  cvv: string;
  vencimento: string; // MM/AA
};

export const MP_TEST_CARDS: Record<string, MpTestCard> = {
  mastercard: { tipo: 'credito', bandeira: 'Mastercard', numero: '5480 8328 0103 3311', cvv: '123', vencimento: '11/30' },
  visa:       { tipo: 'credito', bandeira: 'Visa',        numero: '4235 6477 2802 5682', cvv: '123', vencimento: '11/30' },
  amex:       { tipo: 'credito', bandeira: 'American Express', numero: '3753 651535 56885', cvv: '1234', vencimento: '11/30' },
  elo_debito: { tipo: 'debito',  bandeira: 'Elo',          numero: '5067 7667 8388 8311', cvv: '123', vencimento: '11/30' },
};

export type MpScenario = {
  codigo: string;
  descricao: string;
  documento: string | null; // CPF exigido pelo cenário; null = qualquer CPF de teste válido
};

// Tabela oficial "status de pagamento" -> nome do titular do cartão.
export const MP_SCENARIOS: Record<string, MpScenario> = {
  aprovado:                  { codigo: 'APRO', descricao: 'Pagamento aprovado', documento: '12345678909' },
  recusado_erro_geral:       { codigo: 'OTHE', descricao: 'Recusado por erro geral', documento: '12345678909' },
  pendente:                  { codigo: 'CONT', descricao: 'Pagamento pendente', documento: null },
  recusado_validacao:        { codigo: 'CALL', descricao: 'Recusado com validação para autorizar', documento: null },
  recusado_fundos:           { codigo: 'FUND', descricao: 'Recusado por quantia insuficiente', documento: null },
  recusado_cvv:              { codigo: 'SECU', descricao: 'Recusado por código de segurança inválido', documento: null },
  recusado_vencimento:       { codigo: 'EXPI', descricao: 'Recusado por problema com a data de vencimento', documento: null },
  recusado_formulario:       { codigo: 'FORM', descricao: 'Recusado por erro no formulário', documento: null },
  rejeitado_sem_numero:      { codigo: 'CARD', descricao: 'Rejeitado por falta de card_number', documento: null },
  rejeitado_parcelas:        { codigo: 'INST', descricao: 'Rejeitado por parcelas inválidas', documento: null },
  rejeitado_duplicado:       { codigo: 'DUPL', descricao: 'Rejeitado por pagamento duplicado', documento: null },
  rejeitado_cartao_bloqueado:{ codigo: 'LOCK', descricao: 'Rejeitado por cartão desabilitado', documento: null },
  rejeitado_tipo_nao_permitido: { codigo: 'CTNA', descricao: 'Rejeitado por tipo de cartão não permitido', documento: null },
  rejeitado_tentativas_pin:  { codigo: 'ATTE', descricao: 'Rejeitado por tentativas excedidas de pin', documento: null },
  rejeitado_lista_negra:     { codigo: 'BLAC', descricao: 'Rejeitado por estar na lista negra', documento: null },
  nao_suportado:             { codigo: 'UNSU', descricao: 'Não suportado', documento: null },
  regra_de_valores:          { codigo: 'TEST', descricao: 'Usado para aplicar regra de valores', documento: null },
};

export const CPF_TESTE_PADRAO = '12345678909';
