<?php

namespace App\Controllers;

use App\Services\Prospeccao\MensagemPersuasaoService;
use App\Services\Prospeccao\ProspeccaoParceirosService;
use App\Services\Prospeccao\RegiaoQuotaService;
use App\Services\Prospeccao\SerpApiMapsClient;

/**
 * Rotas sugeridas (ajustar à sintaxe real do roteador em index.php):
 *
 *   GET  /admin/prospeccao                -> index()
 *   POST /admin/prospeccao/buscar         -> buscar()
 *   POST /admin/prospeccao/lead/{id}/enviado     -> marcarEnviado()
 *   POST /admin/prospeccao/lead/{id}/cadastrado  -> confirmarCadastro()
 *   GET  /admin/prospeccao/regioes        -> regioes()
 *   POST /admin/prospeccao/regioes/salvar -> regiaoSalvar()
 */
class AdminProspeccaoController
{
    private ProspeccaoParceirosService $service;
    private RegiaoQuotaService $quotaService;

    public function __construct(\PDO $pdo)
    {
        $apiKey = env('SERPAPI_KEY', '');
        $this->quotaService = new RegiaoQuotaService($pdo);
        $this->service = new ProspeccaoParceirosService(
            $pdo,
            new SerpApiMapsClient($apiKey),
            $this->quotaService,
            new MensagemPersuasaoService(
                urlPreCadastro: (defined('APP_URL') ? APP_URL : '') . '/parceiros/interesse',
            ),
        );
    }

    public function index(): void
    {
        $fila = $this->service->gerarFilaDoDia(20);
        $regioes = $this->quotaService->listarRegioesAtivas();

        require __DIR__ . '/../Views/admin/prospeccao/index.php';
    }

    public function regioes(): void
    {
        $regioes = $this->quotaService->listarRegioesAtivas();

        require __DIR__ . '/../Views/admin/prospeccao/regioes.php';
    }

    public function regiaoSalvar(): void
    {
        $this->validarCsrf();

        $this->quotaService->criar([
            'nome' => trim($_POST['nome'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'uf' => strtoupper(trim($_POST['uf'] ?? '')),
            'lat' => (float) ($_POST['lat'] ?? 0),
            'lng' => (float) ($_POST['lng'] ?? 0),
            'raio_km' => (int) ($_POST['raio_km'] ?? 15),
            'categorias_alvo' => trim($_POST['categorias_alvo'] ?? ''),
            'quota_alvo' => (int) ($_POST['quota_alvo'] ?? 5),
            'prioridade_fuseki' => (int) ($_POST['prioridade_fuseki'] ?? 100),
        ]);

        header('Location: /admin/prospeccao/regioes');
    }

    public function buscar(): void
    {
        $this->validarCsrf();

        $regiaoId = (int) ($_POST['regiao_id'] ?? 0);
        $paginas = max(1, min(3, (int) ($_POST['paginas'] ?? 1)));

        try {
            $inseridos = $this->service->buscarLeadsParaRegiao($regiaoId, $paginas);
            $_SESSION['flash_success'] = "{$inseridos} lead(s) novo(s) coletado(s).";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Falha ao buscar na SerpApi: ' . $e->getMessage();
        }

        header('Location: /admin/prospeccao');
    }

    public function marcarEnviado(int $leadId): void
    {
        $this->validarCsrf();

        $mensagem = $_POST['mensagem_texto'] ?? '';
        $waLink = $_POST['wa_link'] ?? null;
        $usuarioId = (int) ($_SESSION['user']['id'] ?? 0);

        $this->service->marcarComoEnviado($leadId, $mensagem, $waLink, $usuarioId);

        header('Location: /admin/prospeccao');
    }

    public function confirmarCadastro(int $leadId): void
    {
        $this->validarCsrf();

        $this->service->confirmarCadastro($leadId);

        header('Location: /admin/prospeccao');
    }

    private function validarCsrf(): void
    {
        // TODO: plugar na validação de CSRF já usada pelos outros
        // controllers admin do projeto (ex: AuthService::validateCsrf()).
    }
}
