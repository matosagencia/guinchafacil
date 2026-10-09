git commit -m "B: faturas de parceiros + B-008 fechado + financeiro oficina refatorado

Fecha o B-008 (paliativo validado):
- [B-STID-02] AdminController consulta service_types.attendance_mode
  via ServiceType::isTowing() e passa modalidade_socorro explicito no
  PedidoCreateRequest.
- _precotacao_funil_admin.php (Solucao B) sincroniza tipo_problema,
  categoria, valor_cotado e decisao_atendimento via gf:flow-stage.
- pedidonovo_funil.php pula o Passo 2 (veiculo) via window.__gfFlowOptions.

Tela /admin/faturas + /admin/fatura/{id}:
- AdminController: faturas, faturaDetalhe, faturaMarcarPaga, faturaDesbloquear.
- 2 views (faturas.php, fatura_detalhe.php) com layout do guincho.
- 4 rotas em index.php (formato associativo).
- Item 'Faturas dos parceiros' em admin_nav_operacional.php (secao Financeiro).
- Card 'Comissao' em _pedido_oficina_detalhe.php (comissao_valor,
  comissao_tipo, valor_liquido_parceiro, fatura_id).

Financeiro da oficina refatorado (layout do guincho):
- OficinaController::financeiro() com JOIN oficina_repasses x pedidos x usuarios.
- financeiroPage() duplicado removido.
- View reescrita com 8 stat cards + tabela de 9 colunas.
- Reusa tow-financeiro.css.

Docs:
- doc/BLOCKERS.md: B-008 RESOLVIDO; B-010 aberto (bloqueio financeiro).
- doc/CONTRATOS.md: Contrato de Bloqueio Financeiro (A->B).
- doc/lanes/B.md: sessao 5 no historico.

Backups: .bak-* (ignorados por .gitignore)
"

git push origin main