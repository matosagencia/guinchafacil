<?php
namespace App\Controllers\Admin;

class TestesController {
    public function index() {
        if (empty($_SESSION['admin_logged'])) {
            header('Location: /login');
            exit;
        }
        require_once __DIR__ . '/../../Views/admin/testes/index.php';
    }

    public function executar() {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'message' => 'Runner inicializado']);
    }

    public function eventos() {
        header('Content-Type: application/json');
        echo json_encode(['events' => []]);
    }

    public function ingest() {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);
    }
}