CREATE TABLE IF NOT EXISTS partner_profiles (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    chave_pix VARCHAR(500) NOT NULL DEFAULT '',
    chave_pix_tipo ENUM('cpf','cnpj','email','telefone','aleatoria') NOT NULL DEFAULT 'aleatoria',
    status ENUM('pendente','completo','bloqueado') NOT NULL DEFAULT 'pendente',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partner_profiles_usuario (usuario_id),
    CONSTRAINT fk_partner_profiles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
