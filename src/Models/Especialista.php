<?php

class Especialista
{
    public static function buscarPorUsuarioId(int $usuarioId): ?array
    {
        self::aplicarPixPendente($usuarioId);
        $stmt = getPDO()->prepare('SELECT * FROM especialistas WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function atualizarPerfil(int $id, string $nome, string $bio, float $raio, string $pix, string $pixTipo): void
    {
        $pdo = getPDO();
        $atual = $pdo->prepare('SELECT chave_pix, chave_pix_tipo FROM especialistas WHERE id=? FOR UPDATE');
        $atual->execute([$id]);
        $row = $atual->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Especialista não encontrado.');
        $pixMudou = trim($pix) !== (string)$row['chave_pix'] || $pixTipo !== (string)$row['chave_pix_tipo'];
        if ($pixMudou) {
            $pdo->prepare('UPDATE especialistas SET nome_profissional=?, bio=?, raio_atendimento_km=?, chave_pix_pendente=?, chave_pix_tipo_pendente=?, chave_pix_solicitada_em=NOW() WHERE id=?')
                ->execute([$nome, $bio !== '' ? $bio : null, $raio, $pix, $pixTipo, $id]);
        } else {
            $pdo->prepare('UPDATE especialistas SET nome_profissional=?, bio=?, raio_atendimento_km=? WHERE id=?')
                ->execute([$nome, $bio !== '' ? $bio : null, $raio, $id]);
        }
    }

    private static function aplicarPixPendente(int $usuarioId): void
    {
        try {
            getPDO()->prepare("UPDATE especialistas SET chave_pix=chave_pix_pendente, chave_pix_tipo=chave_pix_tipo_pendente, chave_pix_pendente=NULL, chave_pix_tipo_pendente=NULL, chave_pix_solicitada_em=NULL WHERE usuario_id=? AND chave_pix_pendente IS NOT NULL AND chave_pix_solicitada_em <= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->execute([$usuarioId]);
        } catch (Throwable $e) { /* migration ainda não aplicada: leitura do perfil continua disponível */ }
    }

    public static function criar(array $dados, ?PDO $pdo = null): int
    {
        $pdo = $pdo ?? getPDO();
        $stmt = $pdo->prepare('INSERT INTO especialistas (usuario_id, nome_profissional, cpf_cnpj, documento_tipo, documento_numero, chave_pix, chave_pix_tipo, bio, raio_atendimento_km) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int)$dados['usuario_id'], $dados['nome_profissional'] ?: null, $dados['cpf_cnpj'], $dados['documento_tipo'], $dados['documento_numero'] ?: null, $dados['chave_pix'], $dados['chave_pix_tipo'], $dados['bio'] ?: null, (float)$dados['raio_atendimento_km']]);
        return (int)$pdo->lastInsertId();
    }

    public static function vincularServicos(int $especialistaId, array $codigos, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? getPDO();
        $codigos = array_values(array_unique(array_filter(array_map(static fn($codigo) => strtoupper(trim((string)$codigo)), $codigos))));
        if (!$codigos) throw new InvalidArgumentException('Selecione ao menos um servico.');
        $marks = implode(',', array_fill(0, count($codigos), '?'));
        $stmt = $pdo->prepare("SELECT id FROM servicos_especialista WHERE ativo=1 AND codigo IN ($marks)");
        $stmt->execute($codigos);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) throw new InvalidArgumentException('Nenhum servico selecionado esta disponivel.');
        $insert = $pdo->prepare('INSERT INTO especialista_servicos (especialista_id, servico_id, habilitado) VALUES (?, ?, 1)');
        foreach ($ids as $id) $insert->execute([$especialistaId, $id]);
    }

    public static function servicosComCatalogo(int $especialistaId): array
    {
        // O preço é sempre da plataforma. preco_pretendido é um campo legado
        // mantido apenas para compatibilidade de migrations e não participa
        // da leitura nem do cálculo comercial.
        $st = getPDO()->prepare("SELECT s.*, es.habilitado,
                GROUP_CONCAT(CONCAT(p.nome, '||', COALESCE(p.preco_referencia,0), '||', p.unidade) SEPARATOR '##') AS produtos_catalogo
            FROM especialista_servicos es JOIN servicos_especialista s ON s.id=es.servico_id
            LEFT JOIN especialista_servico_produtos esp ON esp.servico_id=s.id AND esp.ativo=1
            LEFT JOIN produtos p ON p.id=esp.produto_id AND p.active=1
            WHERE es.especialista_id=? GROUP BY s.id, es.habilitado ORDER BY s.categoria, s.nome");
        $st->execute([$especialistaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
