<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminProdutoController.php
// ROADMAP socorro automotivo — Etapa 8 (produtos e estoque).
// Controller especializado (não sobrecarrega AdminController, já grande demais).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Produto.php';
require_once __DIR__ . '/../Services/Logger.php';

class AdminProdutoController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        AuthService::requireAuth('admin');
        $produtos = Produto::listarTodos();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/produtos.php';
    }

    public function form(): void
    {
        AuthService::requireAuth('admin');
        $id = (int)($_GET['id'] ?? 0);
        $produto = $id > 0 ? Produto::buscarPorId($id) : null;
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/produtoform.php';
    }

    public function salvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $categorias = ['bateria', 'pneu', 'combustivel', 'chaveiro', 'eletrica', 'fluido', 'outro'];
        $dados = [
            'sku' => preg_replace('/[^A-Z0-9\-]/', '', strtoupper(trim((string)($_POST['sku'] ?? '')))),
            'nome' => trim((string)($_POST['nome'] ?? '')),
            'categoria' => in_array($_POST['categoria'] ?? '', $categorias, true) ? $_POST['categoria'] : 'bateria',
            'descricao' => trim((string)($_POST['descricao'] ?? '')),
            'especificacao' => trim((string)($_POST['especificacao'] ?? '')),
            'preco_referencia' => str_replace(',', '.', (string)($_POST['preco_referencia'] ?? '')),
            'unidade' => trim((string)($_POST['unidade'] ?? 'un')),
            'active' => !empty($_POST['active']),
        ];
        if ($dados['sku'] === '' || $dados['nome'] === '') {
            $this->setFlashMessage('SKU e nome são obrigatórios.', 'error');
            $this->redirect('/admin/produtos');
        }

        try {
            if ($id > 0) {
                Produto::atualizar($id, $dados);
            } else {
                $id = Produto::criar($dados);
            }
        } catch (\PDOException $e) {
            Logger::exception('AdminProdutoController', 'salvar', 'estoque', $e, ['sku' => $dados['sku']]);
            $this->setFlashMessage('Já existe um produto com esse SKU.', 'error');
            $this->redirect('/admin/produtos');
        }

        Logger::log(Logger::LEVEL_INFO, 'AdminProdutoController', 'salvar', 'estoque',
            "Produto #{$id} ({$dados['sku']}) salvo por admin #{$user['id']}",
            ['produto_id' => $id, 'sku' => $dados['sku'], 'admin_id' => $user['id']]);
        $this->setFlashMessage('Produto salvo.', 'success');
        $this->redirect('/admin/produtos');
    }
}
