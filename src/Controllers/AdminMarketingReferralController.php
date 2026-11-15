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
        try { $rows = getPDO()->query("SELECT * FROM partner_referral_commission_rules WHERE partner_profile = 'distribuicao' ORDER BY effective_from DESC, id DESC LIMIT 1")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $erro = 'A migration de comissões de indicação ainda não foi aplicada.'; }
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
        try { $pdo=getPDO(); $id=$pdo->query("SELECT id FROM partner_referral_commission_rules WHERE partner_profile='distribuicao' ORDER BY id DESC LIMIT 1")->fetchColumn(); if($id){$pdo->prepare("UPDATE partner_referral_commission_rules SET commission_percent=?,discount_percent=?,status='ativo',effective_from=NOW(),created_by_admin_id=? WHERE id=?")->execute([$share,$discount,(int)$admin['id'],(int)$id]);}else{$pdo->prepare("INSERT INTO partner_referral_commission_rules (partner_profile,commission_percent,discount_percent,status,effective_from,created_by_admin_id) VALUES ('distribuicao',?,?, 'ativo',NOW(),?)")->execute([$share,$discount,(int)$admin['id']]);}$this->redirect('/admin/marketing/comissoes-indicacao?ok=1'); } catch (Throwable $e) { $this->redirect('/admin/marketing/comissoes-indicacao?erro=schema'); }
    }

    public function promotions(): void
    {
        AuthService::requireAuth('admin');
        $rows = [];
        try { $rows = getPDO()->query('SELECT * FROM marketing_promotion_rules ORDER BY status DESC, priority DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { $erro = 'A migration de promoções ainda não foi aplicada.'; }
        $editId=(int)($_GET['edit']??0);$editRow=null;foreach($rows as $candidate){if((int)$candidate['id']===$editId){$editRow=$candidate;break;}}
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/marketing_cupons.php';
    }

    public function storePromotion(): void
    {
        $admin = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); return; }
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['discount_type'] ?? 'percent');
        $value = max(0, (float)str_replace(',', '.', (string)($_POST['discount_value'] ?? 0)));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/', $code) || $name === '' || !in_array($type, ['percent','fixed'], true) || ($type === 'percent' && $value > 100)) { $this->redirect('/admin/marketing/cupons?erro=validacao'); return; }
        try {
            getPDO()->prepare("INSERT INTO marketing_promotion_rules (code,name,discount_type,discount_value,partner_profile,vehicle_type,vehicle_make,region,service_type_id,priority,starts_at,ends_at,max_uses,created_by_admin_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),discount_type=VALUES(discount_type),discount_value=VALUES(discount_value),partner_profile=VALUES(partner_profile),vehicle_type=VALUES(vehicle_type),vehicle_make=VALUES(vehicle_make),region=VALUES(region),service_type_id=VALUES(service_type_id),priority=VALUES(priority),starts_at=VALUES(starts_at),ends_at=VALUES(ends_at),max_uses=VALUES(max_uses),status='ativo'")->execute([$code,$name,$type,$value,trim((string)($_POST['partner_profile'] ?? '')) ?: null,trim((string)($_POST['vehicle_type'] ?? '')) ?: null,trim((string)($_POST['vehicle_make'] ?? '')) ?: null,trim((string)($_POST['region'] ?? '')) ?: null,(int)($_POST['service_type_id'] ?? 0) ?: null,(int)($_POST['priority'] ?? 0),($_POST['starts_at'] ?? '') ?: null,($_POST['ends_at'] ?? '') ?: null,(int)($_POST['max_uses'] ?? 0) ?: null,(int)$admin['id']]);
            $this->redirect('/admin/marketing/cupons?ok=1');
        } catch (Throwable $e) { $this->redirect('/admin/marketing/cupons?erro=schema'); }
    }

    public function togglePromotion(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); return; }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/marketing/cupons?erro=id'); return; }
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("UPDATE marketing_promotion_rules
                SET status = CASE WHEN status = 'ativo' THEN 'inativo' ELSE 'ativo' END
                WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                $this->redirect('/admin/marketing/cupons?erro=nao_encontrado');
                return;
            }
            $this->redirect('/admin/marketing/cupons?ok=status');
        } catch (Throwable $e) {
            $this->redirect('/admin/marketing/cupons?erro=schema');
        }
    }

    public function deletePromotion(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $confirmation = strtoupper(trim((string)($_POST['confirmacao'] ?? $_POST['confirm_delete'] ?? '')));
        if ($id <= 0) {
            $this->redirect('/admin/marketing/cupons?erro=id');
            return;
        }
        if ($confirmation !== '' && $confirmation !== 'EXCLUIR') { $this->redirect('/admin/marketing/cupons?erro=confirmacao'); return; }

        try {
            $pdo = getPDO();
            $check = $pdo->prepare('SELECT used_count FROM marketing_promotion_rules WHERE id = ? LIMIT 1');
            $check->execute([$id]);
            $promotion = $check->fetch(PDO::FETCH_ASSOC);
            if (!$promotion) {
                $this->redirect('/admin/marketing/cupons?erro=nao_encontrado');
                return;
            }
            if ((int)$promotion['used_count'] > 0) {
                $this->redirect('/admin/marketing/cupons?erro=usado');
                return;
            }

            $delete = $pdo->prepare('DELETE FROM marketing_promotion_rules WHERE id = ? AND used_count = 0');
            $delete->execute([$id]);
            $this->redirect($delete->rowCount() === 1
                ? '/admin/marketing/cupons?ok=excluido'
                : '/admin/marketing/cupons?erro=nao_excluido');
        } catch (Throwable $e) {
            $this->redirect('/admin/marketing/cupons?erro=schema');
        }
    }

    public function toggleCommission(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); return; }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->redirect('/admin/marketing/comissoes-indicacao?erro=id'); return; }
        try { getPDO()->prepare("UPDATE partner_referral_commission_rules SET status = IF(status = 'ativo', 'pausado', 'ativo') WHERE id = ?")->execute([$id]); $this->redirect('/admin/marketing/comissoes-indicacao?ok=status'); }
        catch (Throwable $e) { $this->redirect('/admin/marketing/comissoes-indicacao?erro=schema'); }
    }
}
