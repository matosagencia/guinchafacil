<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminPlanejamentoController.php
// Sistema de planejamento de lan�amento dentro do admin: calculadora de
// break-even + metadados de m�dia paga (Meta Ads / Google Ads) + compara��o
// de pra�as + runway. C�lculo ao vivo no navegador; par�metros persistidos
// em `configuracoes` (chaves plan_*). Controller pr�prio (n�o infla o AdminController).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Cidade.php';

class AdminPlanejamentoController extends BaseController
{
    /** Par�metros e seus defaults (valores de refer�ncia do mercado jul/2026).
     *  A comiss�o N�O fica aqui: vem sempre de `comissao_plataforma` (Configura��es). */
    private const PARAMS = [
        'plan_ticket'         => 175,   // ticket m�dio (R$)
        'plan_taxa_min'       => 0,     // taxa m�nima por atendimento (R$)
        'plan_custo_midia'    => 3000,  // m�dia paga (R$/m�s)
        'plan_custo_infra'    => 20,    // infraestrutura (R$/m�s)
        'plan_meta_cpl_esp'   => 14,    // Meta Ads - CPL cadastro especialista (R$)
        'plan_meta_cpl_gui'   => 25,    // Meta Ads - CPL cadastro guincho (R$)
        'plan_meta_orcamento' => 1200,  // Meta Ads - or�amento mensal (R$)
        'plan_google_cpc'     => 5.0,   // Google Ads - CPC m�dio (R$)
        'plan_google_conv'    => 17,    // Google Ads - convers�o da landing (% clique?chamado)
        'plan_google_orcamento' => 2000,// Google Ads - or�amento mensal (R$)
        'plan_fechamento'     => 60,    // % chamado aberto ? atendimento pago
        'plan_organico'       => 10,    // % de chamados SEM m�dia paga hoje
        'plan_meta_organico'  => 40,    // meta de independ�ncia de m�dia (%)
        'plan_midia_manutencao' => 800, // m�dia de manuten��o quando maduro (R$/m�s)
    ];

    /**
     * Chave de configura��o segmentada por cidade - cada cidade-alvo
     * cadastrada em /admin/cidades tem seu pr�prio conjunto de par�metros
     * de planejamento (ticket, m�dia, break-even etc.), sem cidade
     * selecionada usa a chave "global" original (compatibilidade com
     * quem j� tinha planejamento salvo antes desta segmenta��o).
     */
    private function chaveCidade(string $chave, int $cidadeId): string
    {
        return $cidadeId > 0 ? $chave . '__cidade_' . $cidadeId : $chave;
    }

    public function index(): void
    {
        AuthService::requireAuth('admin');
        $cidades = Cidade::listarTodas();
        $cidadeId = (int)($_GET['cidade_id'] ?? 0);
        // Sem cidade escolhida na URL, mas j� existe cidade-alvo cadastrada:
        // seleciona a primeira ativa (ou a primeira, se nenhuma ativa) por
        // padr�o em vez de cair no cen�rio "global" legado.
        if ($cidadeId <= 0 && !empty($cidades)) {
            $primeiraAtiva = null;
            foreach ($cidades as $c) {
                if (!empty($c['ativo'])) { $primeiraAtiva = $c; break; }
            }
            $cidadeId = (int)(($primeiraAtiva ?? $cidades[0])['id']);
        }
        $cidadeSelecionada = null;
        foreach ($cidades as $c) {
            if ((int)$c['id'] === $cidadeId) { $cidadeSelecionada = $c; break; }
        }

        $cfg = Configuracao::getAll();
        $p = [];
        foreach (self::PARAMS as $k => $def) {
            $chave = $this->chaveCidade($k, $cidadeId);
            $p[$k] = isset($cfg[$chave]) && $cfg[$chave] !== '' ? (float)$cfg[$chave] : $def;
        }
        // Comiss�o vem da configura��o real da plataforma (fra��o -> %) -
        // n�o � segmentada por cidade, � uma regra �nica da plataforma.
        $comissaoFrac = (float)($cfg['comissao_plataforma'] ?? 0.08);
        $comissaoPct = $comissaoFrac > 1 ? $comissaoFrac : $comissaoFrac * 100;
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/planejamento.php';
    }

    public function salvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $cidadeId = (int)($_POST['cidade_id'] ?? 0);
        foreach (array_keys(self::PARAMS) as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') {
                $val = (float)str_replace(',', '.', (string)$_POST[$k]);
                Configuracao::set($this->chaveCidade($k, $cidadeId), (string)$val, 'Planejamento de lan�amento');
            }
        }
        $this->setFlashMessage('Par�metros de planejamento salvos.', 'success');
        $this->redirect('/admin/planejamento' . ($cidadeId > 0 ? '?cidade_id=' . $cidadeId : ''));
    }
}
