<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Cidade.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/Prospeccao/SerpApiMapsClient.php';
require_once __DIR__ . '/../Services/Prospeccao/RegiaoQuotaService.php';
require_once __DIR__ . '/../Services/Prospeccao/MensagemPersuasaoService.php';
require_once __DIR__ . '/../Services/Prospeccao/ProspeccaoParceirosService.php';

final class SeoPartnerController extends BaseController
{
    public function landing(string $slug): void
    {
        $cidade = Cidade::buscarPorSlug(strtolower(trim($slug)));
        if (!$cidade || (int)($cidade['ativo'] ?? 0) !== 1) {
            http_response_code(404);
            echo 'Página não encontrada.';
            return;
        }

        $regiao = $this->regiao((string)$cidade['nome'], (string)$cidade['uf']);
        $csrf_token = $this->generateCSRFToken();
        $enviado = (($_GET['enviado'] ?? '') === '1');
        $erro = (string)($_GET['erro'] ?? '');
        require __DIR__ . '/../Views/public/parceiros-oficinas.php';
    }

    public function capturar(string $slug): void
    {
        if (!$this->validateCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(419);
            $this->redirect('/parceiros/oficinas-' . rawurlencode($slug) . '?erro=sessao');
            return;
        }

        $cidade = Cidade::buscarPorSlug(strtolower(trim($slug)));
        if (!$cidade || (int)($cidade['ativo'] ?? 0) !== 1) {
            http_response_code(404);
            echo 'Página não encontrada.';
            return;
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!AuthService::verificarRateLimit((string)$ip, 'landing_oficina_' . (string)$cidade['slug'])) {
            $this->redirect('/parceiros/oficinas-' . rawurlencode((string)$cidade['slug']) . '?erro=limite');
            return;
        }

        try {
            $service = $this->prospeccaoService((string)$cidade['slug']);
            $service->registrarLeadEntrante([
                'nome_negocio' => $_POST['nome_oficina'] ?? '',
                'cidade' => $cidade['nome'],
                'uf' => $cidade['uf'],
                'telefone' => $_POST['telefone'] ?? '',
                'cnpj' => $_POST['cnpj'] ?? '',
            ]);
            $this->redirect('/parceiros/oficinas-' . rawurlencode((string)$cidade['slug']) . '?enviado=1');
        } catch (Throwable $e) {
            error_log('[seo_partner_lead] ' . $e->getMessage());
            $this->redirect('/parceiros/oficinas-' . rawurlencode((string)$cidade['slug']) . '?erro=invalid');
        }
    }

    private function regiao(string $cidade, string $uf): ?array
    {
        return (new RegiaoQuotaService(getPDO()))->buscarAtivaPorCidadeUf($cidade, $uf);
    }

    private function prospeccaoService(string $slug): ProspeccaoParceirosService
    {
        $pdo = getPDO();
        return new ProspeccaoParceirosService(
            $pdo,
            new SerpApiMapsClient((string)env('SERPAPI_KEY', '')),
            new RegiaoQuotaService($pdo),
            new MensagemPersuasaoService('/parceiros/oficinas-' . $slug)
        );
    }
}
