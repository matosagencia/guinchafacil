<?php
declare(strict_types=1);
require_once __DIR__ . '/../Services/GeoService.php';
require_once __DIR__ . '/Logger.php';

final class EspecialistaProofOfRoadService
{
    public static function armazenarEvidencia(int $atendimentoId, int $especialistaId, string $tipo, array $file): int
    {
        if (!in_array($tipo, ['chegada','diagnostico'], true) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new DomainException('Foto de evidência obrigatória.');
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > (defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 5242880)) throw new DomainException('Arquivo acima do limite permitido.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
        if (!$ext) throw new DomainException('Envie uma imagem JPEG, PNG ou WEBP.');
        $dir = defined('UPLOAD_PATH_DOCS') ? UPLOAD_PATH_DOCS . DIRECTORY_SEPARATOR . 'especialistas' : dirname(__DIR__, 2) . '/storage/private/especialistas';
        if (!is_dir($dir) && !@mkdir($dir, 0770, true)) throw new RuntimeException('Não foi possível preparar o armazenamento.');
        $path = $dir . DIRECTORY_SEPARATOR . $tipo . '_' . $atendimentoId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $moved = is_uploaded_file((string)$file['tmp_name']) ? move_uploaded_file((string)$file['tmp_name'], $path) : rename((string)$file['tmp_name'], $path);
        if (!$moved) throw new RuntimeException('Falha ao armazenar evidência.');
        try { return self::registrarEvento($atendimentoId, $especialistaId, 'evidencia_' . $tipo, null, null, null, ['arquivo'=>basename($path),'sha256'=>hash_file('sha256',$path),'mime'=>$mime]); }
        catch (Throwable $e) { @unlink($path); throw $e; }
    }
    public static function registrarEvento(int $atendimentoId, int $especialistaId, string $evento, ?float $lat, ?float $lng, ?float $accuracy = null, array $metadata = []): int
    {
        Logger::log(Logger::LEVEL_DEBUG, __CLASS__, __FUNCTION__, 'especialista_gps', 'Iniciando registro de evento.', ['atendimento_id'=>$atendimentoId,'especialista_id'=>$especialistaId,'evento'=>$evento,'lat'=>$lat,'lng'=>$lng,'accuracy'=>$accuracy]);
        $pdo = getPDO();
        $st = $pdo->prepare('SELECT incidente_id FROM atendimentos_especialista WHERE id=? AND especialista_id=?');
        $st->execute([$atendimentoId,$especialistaId]);
        $incidenteId = (int)$st->fetchColumn();
        if (!$incidenteId) throw new DomainException('Atendimento não encontrado.');
        if ($evento === 'gps') {
            if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new DomainException('Coordenadas GPS inválidas.');
            if ($accuracy !== null && ($accuracy < 0 || $accuracy > 100)) throw new DomainException('Precisão GPS insuficiente.');
            $prev = $pdo->prepare("SELECT latitude,longitude,criado_em FROM atendimento_eventos WHERE atendimento_tipo='especialista' AND atendimento_id=? AND evento='gps' ORDER BY id DESC LIMIT 1");
            $prev->execute([$atendimentoId]); $p = $prev->fetch(PDO::FETCH_ASSOC);
            if ($p) { $elapsed=max(1,time()-strtotime((string)$p['criado_em'])); $kmh=GeoService::haversine((float)$p['latitude'],(float)$p['longitude'],$lat,$lng)/($elapsed/3600); if($kmh>180) throw new DomainException('Deslocamento GPS incompatível detectado.'); $metadata['velocidade_kmh']=round($kmh,1); }
            $pdo->prepare('UPDATE especialistas SET lat_atual=?, lng_atual=?, atualizado_em=NOW() WHERE id=?')->execute([$lat,$lng,$especialistaId]);
        }
        $stmt = $pdo->prepare('INSERT INTO atendimento_eventos (incidente_id,atendimento_tipo,atendimento_id,evento,latitude,longitude,accuracy_m,metadata_json) VALUES (?,\'especialista\',?,?,?,?,?,?)');
        $stmt->execute([$incidenteId,$atendimentoId,$evento,$lat,$lng,$accuracy,json_encode($metadata,JSON_UNESCAPED_UNICODE)]);
        $id=(int)$pdo->lastInsertId(); Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'especialista_gps', 'Evento registrado.', ['evento_id'=>$id,'atendimento_id'=>$atendimentoId,'evento'=>$evento]); return $id;
    }

    public static function validarChegada(int $atendimentoId, int $especialistaId, float $lat, float $lng, ?float $accuracy = null): void
    {
        $pdo = getPDO();
        $st = $pdo->prepare('SELECT a.*,i.lat_origem,i.lng_origem FROM atendimentos_especialista a JOIN incidentes i ON i.id=a.incidente_id WHERE a.id=? AND a.especialista_id=?');
        $st->execute([$atendimentoId,$especialistaId]);
        $a=$st->fetch(PDO::FETCH_ASSOC);
        if (!$a) throw new DomainException('Atendimento não encontrado.');
        $dist=GeoService::haversine((float)$a['lat_origem'],(float)$a['lng_origem'],$lat,$lng);
        $cfg=class_exists('Configuracao')?Configuracao::getAll():[];
        $limite=(float)($cfg['especialista_geofence_chegada_m']??300);
        $gps = $pdo->prepare("SELECT latitude,longitude FROM atendimento_eventos WHERE atendimento_tipo='especialista' AND atendimento_id=? AND evento='gps' ORDER BY id DESC LIMIT 1");
        $gps->execute([$atendimentoId]);
        $ultimoGps = $gps->fetch(PDO::FETCH_ASSOC);
        if (!$ultimoGps) throw new DomainException('Registre o deslocamento GPS antes de confirmar a chegada.');
        $distanciaAnterior = GeoService::haversine((float)$a['lat_origem'], (float)$a['lng_origem'], (float)$ultimoGps['latitude'], (float)$ultimoGps['longitude']) * 1000;
        if ($distanciaAnterior <= $limite) throw new DomainException('O GPS ainda não comprova deslocamento até o local.');
        if ($dist*1000>$limite) throw new DomainException('Você ainda está fora da área de chegada.');
        self::registrarEvento($atendimentoId,$especialistaId,'chegada',$lat,$lng,$accuracy,['distancia_m'=>round($dist*1000,1)]);
    }
}
