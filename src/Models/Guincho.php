<?php
// File: guinchafacil/src/Models/Guincho.php
// Alinhado com o schema real: guinchos(id, usuario_id, cnh_numero, cnh_validade,
// placa_guincho, capacidade_ton, raio_cobertura_km, chave_pix, chave_pix_tipo,
// lat_atual, lng_atual, disponivel, aprovado, foto_veiculo, doc_cnh_frente,
// doc_cnh_verso, reputacao, total_avaliacoes, criado_em, atualizado_em)

class Guincho
{
    private const TBL = 'guinchos';

    /**
     * §5.4: Criptografa chave Pix em repouso (AES-256-CBC)
     */
    public static function encryptPix(string $value): string
    {
        if (empty($value)) return '';
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    /**
     * §5.4: Descriptografa chave Pix. Se não puder descriptografar, retorna o valor original
     * (compatibilidade com registros antigos ainda não criptografados).
     */
    public static function decryptPix(string $value): string
    {
        if (empty($value)) return '';
        $data = base64_decode($value, true);
        if ($data === false || strlen($data) < 17) return $value;
        $key        = hash('sha256', ENCRYPTION_KEY, true);
        $iv         = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $decrypted  = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $value;
    }

    private static function logErr(string $func, string $system, string $msg, array $ctx = []): void
    {
        $line = "Guincho - {$func} - {$system} - {$msg}";
        if ($ctx) {
            $line .= " | ctx=" . json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
    }

    /** Busca guincho pelo ID da tabela guinchos (retorna 1 row) */
    public static function buscarPorId(int $id): ?array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone, u.cpf
                 FROM " . self::TBL . " g
                 JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.id = ?"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) $row['chave_pix'] = self::decryptPix($row['chave_pix'] ?? ''); // §5.4
            return $row;
        } catch (PDOException $e) {
            self::logErr('buscarPorId', 'db/pdo/select', $e->getMessage(), ['id' => $id]);
            return null;
        }
    }

    /** Busca guincho pelo ID do usuário (retorna 1 row – guincho mais recente) */
    public static function buscarPorUsuario(int $usuario_id): ?array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone
                 FROM " . self::TBL . " g
                 JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.usuario_id = ?
                 ORDER BY g.criado_em DESC LIMIT 1"
            );
            $stmt->execute([$usuario_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) $row['chave_pix'] = self::decryptPix($row['chave_pix'] ?? ''); // §5.4
            return $row;
        } catch (PDOException $e) {
            self::logErr('buscarPorUsuario', 'db/pdo/select', $e->getMessage(), ['usuario_id' => $usuario_id]);
            return null;
        }
    }

    /** Cria registro de guincho a partir dos dados do formulário de registro */
    public static function criarDeRegistro(int $usuario_id, array $dados): int|false
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO " . self::TBL . " (
                    usuario_id, cnh_numero, cnh_validade, placa_guincho,
                    capacidade_ton, raio_cobertura_km, chave_pix, chave_pix_tipo,
                    foto_veiculo, doc_cnh_frente, doc_cnh_verso, 
                    aprovado, disponivel, criado_em
                 ) VALUES (
                    ?,?,?,?,
                    ?,?,?,?,
                    ?,?,?,
                    0,0,NOW()
                 )"
            );
            
            $stmt->execute([
                $usuario_id,
                $dados['cnh_numero']       ?? '',
                $dados['cnh_validade']     ?? date('Y-m-d', strtotime('+5 years')),
                strtoupper(trim($dados['placa_guincho'] ?? '')),
                (float)($dados['capacidade_ton']    ?? 0),
                (int)($dados['raio_cobertura_km']   ?? 20),
                self::encryptPix($dados['chave_pix'] ?? ''), // §5.4: criptografada em repouso
                $dados['chave_pix_tipo']   ?? 'cpf',
                $dados['foto_veiculo']     ?? null,
                $dados['doc_cnh_frente']   ?? null,
                $dados['doc_cnh_verso']    ?? null,
            ]);
            
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            self::logErr('criarDeRegistro', 'db/pdo/insert', $e->getMessage(), [
                'usuario_id' => $usuario_id,
                'dados' => $dados
            ]);
            return false;
        }
    }

    public static function aprovar(int $id): bool
    {
        try {
            $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET aprovado=1 WHERE id=?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            self::logErr('aprovar', 'db/pdo/update', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }

    public static function atualizarLocalizacao(int $id, float $lat, float $lng): bool
    {
        try {
            $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET lat_atual=?, lng_atual=? WHERE id=?");
            return $stmt->execute([$lat, $lng, $id]);
        } catch (PDOException $e) {
            self::logErr('atualizarLocalizacao', 'db/pdo/update', $e->getMessage(), [
                'id' => $id,
                'lat' => $lat,
                'lng' => $lng
            ]);
            return false;
        }
    }

    public static function atualizarDisponibilidade(int $id, bool $disponivel): bool
    {
        try {
            $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET disponivel=? WHERE id=?");
            return $stmt->execute([$disponivel ? 1 : 0, $id]);
        } catch (PDOException $e) {
            self::logErr('atualizarDisponibilidade', 'db/pdo/update', $e->getMessage(), [
                'id' => $id,
                'disponivel' => $disponivel
            ]);
            return false;
        }
    }

    public static function listarPendentes(): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone, u.cpf
                 FROM " . self::TBL . " g 
                 JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.aprovado = 0 AND u.ativo = 1
                 ORDER BY g.criado_em ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            self::logErr('listarPendentes', 'db/pdo/select', $e->getMessage());
            return [];
        }
    }

    public static function listarAprovados(): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone
                 FROM " . self::TBL . " g 
                 JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.aprovado = 1 AND g.disponivel = 1 AND u.ativo = 1
                 ORDER BY g.reputacao DESC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            self::logErr('listarAprovados', 'db/pdo/select', $e->getMessage());
            return [];
        }
    }

    public static function contarDisponiveis(): int
    {
        try {
            $stmt = getPDO()->query("SELECT COUNT(*) FROM " . self::TBL . " WHERE disponivel=1 AND aprovado=1");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            self::logErr('contarDisponiveis', 'db/pdo/count', $e->getMessage());
            return 0;
        }
    }

    public static function atualizarReputacao(int $id): bool
    {
        try {
            $pdo  = getPDO();
            
            $stmt = $pdo->prepare("SELECT AVG(estrelas) FROM avaliacoes WHERE guincho_id=?");
            $stmt->execute([$id]);
            $media = (float)($stmt->fetchColumn() ?? 0);
            
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM avaliacoes WHERE guincho_id=?");
            $stmt2->execute([$id]);
            $total = (int)$stmt2->fetchColumn();
            
            $pdo->prepare("UPDATE " . self::TBL . " SET reputacao=?, total_avaliacoes=? WHERE id=?")
                ->execute([round($media, 2), $total, $id]);
                
            return true;
        } catch (PDOException $e) {
            self::logErr('atualizarReputacao', 'db/pdo/update', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }

    /** Busca pedido ativo atribuído a este guincho */
    public static function buscarAtivoDoGuincho(int $guincho_id): ?array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT p.*, u.nome AS cliente_nome, u.telefone AS cliente_telefone
                 FROM pedidos p 
                 JOIN usuarios u ON p.cliente_id = u.id
                 WHERE p.guincho_id = ? AND p.status IN ('a_caminho','no_local','em_reboque')
                 ORDER BY p.criado_em DESC LIMIT 1"
            );
            $stmt->execute([$guincho_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            self::logErr('buscarAtivoDoGuincho', 'db/pdo/select', $e->getMessage(), ['guincho_id' => $guincho_id]);
            return null;
        }
    }
}