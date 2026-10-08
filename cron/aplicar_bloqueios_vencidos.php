<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../src/Services/FaturaService.php';
try{$qtd=FaturaService::bloquearPorVencimento();error_log('[Cron][aplicar_bloqueios_vencidos][OK] bloqueadas='.$qtd);exit(0);}catch(Throwable $e){error_log('[Cron][aplicar_bloqueios_vencidos][ERRO] fase=bloquear erro='.$e->getMessage());exit(1);}
