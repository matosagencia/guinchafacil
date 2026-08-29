<?php
declare(strict_types=1);

require_once __DIR__ . '/../Services/PaymentJobService.php';

final class PixPayoutWorker
{
    public static function processOne(string $workerId = 'cron_pix'): array
    {
        return PaymentJobService::processNext($workerId);
    }

    public static function processBatch(int $limit = 10, string $workerId = 'cron_pix'): array
    {
        $processed = 0;
        $errors = 0;

        for ($i = 0; $i < $limit; $i++) {
            $result = self::processOne($workerId);
            if (!$result['ok'] && !($result['processed'] ?? false)) {
                $errors++;
                break;
            }
            if (empty($result['processed'])) {
                break;
            }
            $processed++;
            if (!$result['ok']) {
                $errors++;
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }
}
