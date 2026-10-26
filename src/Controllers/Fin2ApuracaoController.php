<?php
declare(strict_types=1);

use App\Services\Finance\Fin2ApuracaoReadOnly;

final class Fin2ApuracaoController extends BaseController
{
    public function index(): void
    {
        $user = AuthService::requireAuth('admin', false);
        $raw = $_GET['guincho_id'] ?? null;
        if ($raw !== null && (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}$/D', $raw))) {
            http_response_code(422); echo json_encode(['ok' => false, 'erro' => 'parametro_invalido'], JSON_UNESCAPED_UNICODE); return;
        }
        require_once __DIR__ . '/../Services/Finance/CompensacaoBilateralCalculator.php';
        require_once __DIR__ . '/../Services/Finance/Fin2ReadOnlySource.php';
        require_once __DIR__ . '/../Services/Finance/Fin2ApuracaoReadOnly.php';
        $rows = Fin2ApuracaoReadOnly::porGuincho(getPDO(), $raw === null ? null : (int)$raw);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'version' => 'FIN2-READONLY-1', 'actor_id' => (int)$user['id'], 'rows' => $rows, 'mutations' => false], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
