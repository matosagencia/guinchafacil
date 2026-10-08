# CONTRATO_RESPOSTA - A para B

> **Extrato de doc/CONTRATOS.md.** Este arquivo e um atalho para consulta rapida. A fonte de verdade e doc/CONTRATOS.md secao "Contrato de Resolucao de service_type_id (A -> B)". Nao editar este arquivo em separado - editar o CONTRATOS.md.

    de: A
    para: B
    status: implementado_pendente_validacao_local
    metodo: ServiceTypeResolver::porSlug(string $slug): ?int
    namespace: App\Services\Catalog
    path: src/Services/Catalog/ServiceTypeResolver.php
    classe: final class ServiceTypeResolver
    bootstrap:
      arquivo: index.php
      quando: apos os requires de servicos e antes de set_exception_handler/dispatcher
      chamada: ServiceTypeResolver::definirLookupPorCodigo(callback)
      callback: lazy; getPDO() e chamado somente dentro do callback
      fail_closed: erro de banco registra STR-LOOKUP-FAIL e retorna null
      query: SELECT id FROM service_types WHERE code = ? AND active = 1 LIMIT 1

O resolver nao usa IDs fixos e retorna null para slug desconhecido, catalogo indisponivel ou codigo inativo.

**Referencia completa:** doc/CONTRATOS.md secao "Contrato de Resolucao de service_type_id (A -> B)".