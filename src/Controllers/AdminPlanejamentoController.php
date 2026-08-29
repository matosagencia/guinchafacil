<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminPlanejamentoController.php
// Sistema de planejamento de lançamento dentro do admin: calculadora de
// break-even + metadados de mídia paga (Meta Ads / Google Ads) + comparação
// de praças + runway. Cálculo ao vivo no navegador; parâmetros persistidos
// em `configuracoes` (chaves plan_*). Controller próprio (não infla o AdminController).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Cidade.php';

class AdminPlanejamentoController extends BaseController
{
    /** Parâmetros e seus defaults (valores de referência do mercado jul/2026).
     *  A comissão NÃO fica aqui: vem sempre de `comissao_plataforma` (Configurações). */
    private const PARAMS = [
        'plan_ticket'         => 175,   // ticket médio (R$)
        'plan_taxa_min'       => 0,     // taxa mínima por atendimento (R$)
        'plan_custo_midia'    => 3000,  // mídia paga (R$/mês)
        'plan_custo_infra'    => 20,    // infraestrutura (R$/mês)
        'plan_meta_cpl_esp'   => 14,    // Meta Ads — CPL cadastro especialista (R$)
        'plan_meta_cpl_gui'   => 25,    // Meta Ads — CPL cadastro guincho (R$)
        'plan_meta_orcamento' => 1200,  // Meta Ads — orçamento mensal (R$)
        'plan_google_cpc'     => 5.0,   // Google Ads — CPC médio (R$)
        'plan_google_conv'    => 17,    // Google Ads — conversão da landing (% clique→chamado)
        'plan_google_orcamento' => 2000,// Google Ads — orçamento mensal (R$)
        'plan_fechamento'     => 60,    // % chamado aberto → atendimento pago
        'plan_organico'       => 10,    // % de chamados SEM mídia paga hoje
        'plan_meta_organico'  => 40,    // meta de independência de mídia (%)
        'plan_midia_manutencao' => 800, // mídia de manutenção quando maduro (R$/mês)
    ];

    /**
     * Chave de configuração segmentada por cidade — cada cidade-alvo
     * cadastrada em /admin/cidades tem seu próprio conjunto de parâmetros
     * de planejamento (ticket, mídia, break-even etc.), sem cidade
     * selecionada usa a chave "global" original (compatibilidade com
     * quem já tinha planejamento salvo antes desta segmentação).
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
        // Sem cidade escolhida na URL, mas já existe cidade-alvo cadastrada:
        // seleciona a primeira ativa (ou a primeira, se nenhuma ativa) por
        // padrão em vez de cair no cenário "global" legado.
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
        // Comissão vem da configuração real da plataforma (fração -> %) —
        // não é segmentada por cidade, é uma regra única da plataforma.
        $comissaoFrac = (float)($cfg['comissao_plataforma'] ?? 0.15);
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
                Configuracao::set($this->chaveCidade($k, $cidadeId), (string)$val, 'Planejamento de lançamento');
            }
        }
        $this->setFlashMessage('Parâmetros de planejamento salvos.', 'success');
        $this->redirect('/admin/planejamento' . ($cidadeId > 0 ? '?cidade_id=' . $cidadeId : ''));
    }
}
