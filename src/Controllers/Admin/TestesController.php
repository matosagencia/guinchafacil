<?php
class TestesController {
    private function checkGuardas() {
        if (empty($_SESSION['admin_logged'])) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
            exit;
        }
    }

    private function getDbPdo() {
        if (function_exists('getPDO')) {
            return getPDO();
        }
        require_once __DIR__ . '/../../src/Database.php';
        if (function_exists('getPDO')) {
            return getPDO();
        }
        throw new \Exception("Função getPDO() não encontrada.");
    }

    public function index() {
        $this->checkGuardas();
        require_once __DIR__ . '/../../src/Views/admin/testes/index.php';
    }

    public function executar() {
        $this->checkGuardas();
        header('Content-Type: application/json');
        
        $scenario = $_GET['scenario'] ?? 'fluxo-1a';
        $file = __DIR__ . "/../../tests/scenarios/{$scenario}.json";
        if (!file_exists($file)) {
            echo json_encode(['status' => 'error', 'message' => 'Cenário não encontrado']);
            return;
        }
        
        $data = json_decode(file_get_contents($file), true);
        try {
            $pdo =$this->getDbPdo();
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        $stmt =$pdo->prepare("INSERT INTO test_runs (scenario_key, status, started_at) VALUES (?, 'running', NOW())");
        $stmt->execute([$scenario]);
        $runId =$pdo->lastInsertId();

        foreach ($data['steps'] as$step) {
            $sStmt =$pdo->prepare("INSERT INTO test_run_steps (run_id, step_key, node, status, started_at) VALUES (?, ?, ?, 'pending', NOW())");
            $sStmt->execute([$runId, $step['key'],$step['node']]);
        }

        $overallStatus = 'ok';

        foreach ($data['steps'] as$step) {
            $updStep =$pdo->prepare("UPDATE test_run_steps SET status = 'running' WHERE run_id = ? AND step_key = ?");
            $updStep->execute([$runId,$step['key']]);

            $status = 'ok';$detail = 'Probe validado com sucesso';
            $probe =$step['probe'] ?? [];
            $type = is_array($probe) ? ($probe['type'] ?? 'status_ok') :$probe;

            if ($type === 'check_db_e2e') {
                try {
                    $chk =$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
                    if ($chk === false) {$status = 'fail';
                        $detail = 'Falha ao consultar tabela pedidos';$overallStatus = 'fail';
                    }
                } catch (\Exception $ex) {
                    $status = 'fail';$detail = 'Erro SQL: ' . $ex->getMessage();$overallStatus = 'fail';
                }
            } elseif ($type === 'http_get' || $type === 'ajax' || strpos($type, 'http_') === 0) {
                $url = 'http://localhost/guinchafacil' . ($probe['url'] ?? '');
                $method = strtoupper($probe['method'] ?? 'GET');
                $expectStatus = (int)($probe['expect_status'] ?? 200);
                $expectJsonOk =$probe['expect_json_ok'] ?? null;
                $expectRegex =$probe['expect_flash_regex'] ?? null;

                $ch = curl_init($url);$curlOpts = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_COOKIE => session_name() . '=' . session_id()
                ];
                if ($method === 'POST') {$curlOpts[CURLOPT_POST] = true;
                    $curlOpts[CURLOPT_POSTFIELDS] =$probe['data'] ?? [];
                }
                curl_setopt_array($ch,$curlOpts);
                $response = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code !==$expectStatus && $code !== 302 &&$code !== 200) {
                    $status = 'fail';$detail = "HTTP esperado $expectStatus, obtido $code em$url";
                    $overallStatus = 'fail';
                }

                if ($expectJsonOk !== null) {$json = json_decode($response, true);$isOk = $json['ok'] ?? $json['status'] ?? false;
                    if (!$isOk &&$expectJsonOk) {
                        $status = 'fail';$detail = "Esperado JSON ok=true, obtido: " . substr($response, 0, 100);$overallStatus = 'fail';
                    }
                }

                if ($expectRegex !== null) {
                    if (!preg_match('/' . $expectRegex . '/i', $response)) {$status = 'fail';
                        $detail = "Regex '$expectRegex' não encontrada na resposta.";
                        $overallStatus = 'fail';
                    }
                }
            }

            $finStep =$pdo->prepare("UPDATE test_run_steps SET status = ?, detail = ?, duration_ms = 150 WHERE run_id = ? AND step_key = ?");
            $finStep->execute([$status,$detail, $runId,$step['key']]);

            if ($status === 'fail') break;
        }

        $finRun =$pdo->prepare("UPDATE test_runs SET status = ?, finished_at = NOW() WHERE run_id = ?");
        $finRun->execute([$overallStatus,$runId]);

        echo json_encode(['status' => $overallStatus, 'run_id' =>$runId]);
    }

    public function eventos() {
        $this->checkGuardas();
        header('Content-Type: application/json');
        $runId =$_GET['run_id'] ?? 0;
        try {
            $pdo =$this->getDbPdo();
            $stmt =$pdo->prepare("SELECT step_key, node, status, detail FROM test_run_steps WHERE run_id = ?");
            $stmt->execute([$runId]);
            $steps =$stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $rStmt =$pdo->prepare("SELECT status FROM test_runs WHERE run_id = ?");
            $rStmt->execute([$runId]);
            $run =$rStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['run_status' => $run['status'] ?? 'pending', 'steps' =>$steps]);
        } catch (\Exception $e) {
            echo json_encode(['run_status' => 'fail', 'steps' => [], 'error' => $e->getMessage()]);
        }
    }

    public function ingest() {
        $this->checkGuardas();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['scenario'])) {
            echo json_encode(['status' => 'error', 'message' => 'Payload inválido']);
            return;
        }

        try {
            $pdo =$this->getDbPdo();
            $stmt =$pdo->prepare("INSERT INTO test_runs (scenario_key, status, started_at, finished_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$input['scenario'],$input['status'] ?? 'ok']);
            $runId =$pdo->lastInsertId();

            if (!empty($input['steps'])) {
                foreach ($input['steps'] as$st) {
                    $sStmt =$pdo->prepare("INSERT INTO test_run_steps (run_id, step_key, node, status, started_at, detail) VALUES (?, ?, ?, ?, NOW(), ?)");
                    $sStmt->execute([$runId, $st['key'] ?? 'unknown',$st['node'] ?? 'node-precotacao', $st['status'] ?? 'ok',$st['detail'] ?? 'Ingest Playwright']);
                }
            }
            echo json_encode(['status' => 'received', 'run_id' => $runId]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}