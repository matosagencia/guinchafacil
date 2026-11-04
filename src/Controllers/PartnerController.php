<?php

class PartnerController extends BaseController
{
    public function dashboard(): void
    {
        $user = AuthService::requireAuth('parceiro');
        if (!$user) { $this->redirect('/login?retorno=' . rawurlencode('/parceiro/dashboard')); return; }
        if (!PartnerProfileService::isComplete((int)$user['id'])) {
            $this->redirect('/perfil/completar');
            return;
        }
        $referralCode = PartnerProfileService::ensureReferralCode((int)$user['id']);
        $commissionPercent = PartnerReferralCommissionService::currentPercent('distribuicao');
        $referralUrl = rtrim((string)APP_URL, '/') . '/?ref=' . rawurlencode($referralCode);
        $csrf_token = $this->generateCSRFToken();
        require __DIR__ . '/../Views/parceiro/dashboard.php';
    }
}
