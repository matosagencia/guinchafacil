<?php
declare(strict_types=1);
require_once __DIR__ . '/Logger.php';

final class MarketingSpendService
{
    public static function salvar(array $dados, int $adminId): array
    {
        $data = (string)$dados['data']; $canal = MarketingAttributionService::normalizarCanal((string)$dados['canal']);
        $campanha = trim((string)($dados['campanha'] ?? '')); $valor = round((float)$dados['valor_gasto'], 2);
        $hash = hash('sha256', implode('|', [$data,$canal,$campanha,number_format($valor,2,'.',''),(int)($dados['cidade_id'] ?? 0)]));
        $stmt = getPDO()->prepare("INSERT INTO gastos_marketing (canal,campanha,data,valor_gasto,cidade_id,origem_lancamento,criado_por_admin_id,hash_idem) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $stmt->execute([$canal,$campanha,$data,$valor,(int)($dados['cidade_id'] ?? 0) ?: null,(string)($dados['origem_lancamento'] ?? 'manual'),$adminId,$hash]);
        $id = (int)getPDO()->lastInsertId();
        Logger::log(Logger::LEVEL_INFO, 'MarketingSpendService', 'salvar', 'financeiro', "Gasto de marketing #{$id} lançado", ['admin_id'=>$adminId,'hash_idem'=>$hash,'data'=>$data,'canal'=>$canal,'valor'=>$valor]);
        return ['id'=>$id,'hash_idem'=>$hash];
    }

    public static function listar(string $desde, string $ate): array
    {
        $s=getPDO()->prepare("SELECT gm.*,c.nome cidade_nome FROM gastos_marketing gm LEFT JOIN cidades c ON c.id=gm.cidade_id WHERE gm.ativo=1 AND gm.data BETWEEN ? AND ? ORDER BY gm.data DESC,gm.id DESC"); $s->execute([$desde,$ate]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
