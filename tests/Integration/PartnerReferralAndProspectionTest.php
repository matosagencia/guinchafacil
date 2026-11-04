<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/PartnerReferralCommissionService.php';
require_once __DIR__ . '/../../src/Services/Logger.php';
require_once __DIR__ . '/../../src/Services/Prospeccao/SerpApiMapsClient.php';
require_once __DIR__ . '/../../src/Services/Prospeccao/RegiaoQuotaService.php';
require_once __DIR__ . '/../../src/Services/Prospeccao/MensagemPersuasaoService.php';
require_once __DIR__ . '/../../src/Services/Prospeccao/ProspeccaoParceirosService.php';

final class PartnerReferralAndProspectionTest extends TestCase
{
    public function testComissaoEStatusDaFilaSaoManuaisEAuditaveis(): void
    {
        $pdo = getPDO();
        $suffix = bin2hex(random_bytes(4));
        $pdo->prepare("INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo) VALUES (?,?,?,?,?,'admin',1)")
            ->execute(['QA Admin Referral', 'qa-ref-' . $suffix . '@example.invalid', 'hash', '11999990000', $suffix . '111111']);
        $adminId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO partner_referral_commission_rules (partner_profile,commission_percent,discount_percent,status,effective_from,created_by_admin_id) VALUES ('distribuicao',12,1,'ativo',NOW(),?)")
            ->execute([$adminId]);
        self::assertSame(12.0, PartnerReferralCommissionService::currentPercent('distribuicao'));

        $prospectId = 100000 + random_int(1, 900000);
        $pdo->prepare("INSERT INTO marketing_partner_prospect_queue (prospect_id,empresa,categoria,cidade,uf,status) VALUES (?,?,?,?,?,'descoberto')")
            ->execute([$prospectId, 'QA Oficina ' . $suffix, 'oficina', 'Niteroi', 'RJ']);
        $service = new ProspeccaoParceirosService(
            $pdo,
            new SerpApiMapsClient(''),
            new RegiaoQuotaService($pdo),
            new MensagemPersuasaoService('http://localhost:8080/parceiros')
        );
        foreach (['validado', 'contatado', 'respondeu', 'homologado'] as $status) {
            $service->atualizarStatusFila($prospectId, $status, $adminId);
        }
        $stmt = $pdo->prepare('SELECT status,revisado_por,revisado_em FROM marketing_partner_prospect_queue WHERE prospect_id=?');
        $stmt->execute([$prospectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertSame('homologado', $row['status']);
        self::assertSame($adminId, (int)$row['revisado_por']);
        self::assertNotEmpty($row['revisado_em']);
    }
}
