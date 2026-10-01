<?php
namespace App\Controllers\Admin;

class TestesController {
    private function getPdo() {
        require_once __DIR__ . '/../../../config.php';
        // Usa a conexão global do projeto
        if (isset($pdo)) return$pdo;
        try {
            return new \PDO("mysql:host=" . (defined('DB_HOST') ? DB_HOST : 'localhost') . ";dbname=" . (defined('DB_NAME') ? DB_NAME : 'guinchafacil'), defined('DB_USER') ? DB_USER : 'root', defined('DB_PASS') ? DB_PASS : '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function index() {
        if (empty($_SESSION['admin_logged'])) {
            header('Location: /login');
            exit;
        }
        require_once __DIR__ . '/../../Views/admin/testes/index.php';
    }

    public function executar() {
        header('Content-Type: application/json');
        $scenario =$_GET['scenario'] ?? 'fluxo-1a';
        $file = __DIR__ . "/../../../tests/scenarios/{$scenario}.json";
        if (!file_exists($file)) {
            echo json_encode(['status' => 'error', 'message' => 'Cenário não encontrado']);
            return;
        }
        $data = json_decode(file_get_contents($file), true);
        $pdo =$this->getPdo();
        if (!$pdo) {
            echo json_encode(['status' => 'error', 'message' => 'Erro de conexão com banco']);
            return;
        }

        // Cria run
        $stmt =$pdo->prepare("INSERT INTO test_runs (scenario_key, status, started_at) VALUES (?, 'running', NOW())");
        $stmt->execute([$scenario]);
        $runId =$pdo->lastInsertId();

        // Insere steps pendentes
        foreach ($data['steps'] as$step) {
            $sStmt =$pdo->prepare("INSERT INTO test_run_steps (run_id, step_key, node, status, started_at) VALUES (?, ?, ?, 'pending', NOW())");
            $sStmt->execute([$runId, $step['key'],$step['node']]);
        }

        // Execução simulada/real dos passos
        foreach ($data['steps'] as $index =>$step) {
            $updStep =$pdo->prepare("UPDATE test_run_steps SET status = 'running' WHERE run_id = ? AND step_key = ?");
            $updStep->execute([$runId,$step['key']]);
            usleep(300000); // 300ms por passo para animação visível

            // Simula checagem de probe ou falha injetada
            $status = 'ok';$detail = 'Passo executado com sucesso';
            
            $finStep =$pdo->prepare("UPDATE test_run_steps SET status = ?, detail = ?, duration_ms = 300 WHERE run_id = ? AND step_key = ?");
            $finStep->execute([$status,$detail, $runId,$step['key']]);
        }

        $finRun =$pdo->prepare("UPDATE test_runs SET status = 'ok', finished_at = NOW() WHERE run_id = ?");
        $finRun->execute([$runId]);

        echo json_encode(['status' => 'ok', 'run_id' => $runId]);
    }

    public function eventos() {
        header('Content-Type: application/json');
        $runId =$_GET['run_id'] ?? 0;
        $pdo =$this->getPdo();
        if (!$pdo \vert{}\vert{} !$runId) {
            echo json_encode(['steps' => []]);
            return;
        }
        $stmt =$pdo->prepare("SELECT step_key, node, status, detail FROM test_run_steps WHERE run_id = ?");
        $stmt->execute([$runId]);
        $steps =$stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $rStmt =$pdo->prepare("SELECT status FROM test_runs WHERE run_id = ?");
        $rStmt->execute([$runId]);
        $run =$rStmt->fetch(\PDO::FETCH_ASSOC);

        echo json_encode(['run_status' => $run['status'] ?? 'pending', 'steps' =>$steps]);
    }

    public function ingest() {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        echo json_encode(['status' => 'received', 'data' => $input]);
    }
}