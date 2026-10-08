<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../src/Services/FaturaService.php';
try { $inicio=(new DateTimeImmutable('last sunday'))->setTime(0,0); $qtd=FaturaService::fecharCiclo($inicio); error_log('[Cron][fechar_ciclos_semanais][OK] ciclo='.$inicio->format('Y-m-d').' faturas='.$qtd); exit(0); }
catch(Throwable $e){error_log('[Cron][fechar_ciclos_semanais][ERRO] fase=fechar_ciclo erro='.$e->getMessage());exit(1);}
