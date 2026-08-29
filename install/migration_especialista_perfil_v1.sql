-- Campos de alteração de PIX com janela de segurança de 24 horas.
ALTER TABLE especialistas
  ADD COLUMN IF NOT EXISTS chave_pix_pendente VARCHAR(150) NULL AFTER chave_pix,
  ADD COLUMN IF NOT EXISTS chave_pix_tipo_pendente ENUM('cpf','cnpj','email','telefone','aleatoria') NULL AFTER chave_pix_pendente,
  ADD COLUMN IF NOT EXISTS chave_pix_solicitada_em DATETIME NULL AFTER chave_pix_tipo_pendente;
