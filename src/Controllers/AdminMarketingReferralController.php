<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';

final class AdminMarketingReferralController extends BaseController
{
    public function index(): void
    {
        $admin = AuthService::requireAuth('admin');
        $rows = [];
        $erro = '';
        try {
            $stmt = getPDO()->query('SELECT * FROM marketing_referral_codes ORDER BY id DESC');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $erro = 'A migration marketing_referrals ainda nao foi aplicada neste ambiente.';
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/marketing_referrals.php';
    }

    public function store(): void
    {
        $admin = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            return;
        }
        $code = strtolower(trim((string)($_POST['code'] ?? '')));
        $label = trim((string)($_POST['label'] ?? ''));
        $type = trim((string)($_POST['partner_type'] ?? 'distribuicao'));
        $name = trim((string)($_POST['partner_name'] ?? ''));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,79}$/', $code) || $label === '') {
            $this->redirect('/admin/marketing/referrals?erro=validacao');
            return;
        }
        try {
            getPDO()->prepare('INSERT INTO marketing_referral_codes (code,label,partner_type,partner_name,status,criado_por_admin_id) VALUES (?,?,?,?,\'rascunho\',?)')->execute([$code, $label, $type, $name ?: null, (int)$admin['id']]);
            $this->redirect('/admin/marketing/referrals?ok=1');
        } catch (Throwable $e) {
            $this->redirect('/admin/marketing/referrals?erro=schema');
        }
    }

    public function commissions(): void
    {
        AuthService::requireAuth('admin');
        $rows = [];
        try { $rows = getPDO()->query('SELECT * FROM partner_referral_commission_rules ORDER BY partner_profile, effective_from DESC')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $erro = 'A migration de comissões de indicação ainda não foi aplicada.'; }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/marketing_comissoes_indicacao.php';
    }

    public function storeCommission(): void
    {
        $admin = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); return; }
        $profile = (string)($_POST['partner_profile'] ?? 'distribuicao');
        $share = max(0.0, min(100.0, (float)($_POST['referral_share_percent'] ?? 0)));
        $discount = max(0.0, min(100.0, (float)($_POST['discount_percent'] ?? 0)));
        if (!in_array($profile, ['guincho','oficina','distribuicao'], true)) { $this->redirect('/admin/marketing/comissoes-indicacao?erro=validacao'); return; }
        try { getPDO()->prepare("INSERT INTO partner_referral_commission_rules (partner_profile,commission_percent,discount_percent,status,effective_from,created_by_admin_id) VALUES (?,?,?,'ativo',NOW(),?)")->execute([$profile,$share,$discount,(int)$admin['id']]); $this->redirect('/admin/marketing/comissoes-indicacao?ok=1'); } catch (Throwable $e) { $this->redirect('/admin/marketing/comissoes-indicacao?erro=schema'); }
    }
}
