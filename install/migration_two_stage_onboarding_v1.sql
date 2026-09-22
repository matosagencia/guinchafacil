-- Two-stage onboarding: make profile fields nullable during registration.
ALTER TABLE guinchos MODIFY capacidade_ton DECIMAL(5,2) NULL;
ALTER TABLE guinchos MODIFY chave_pix VARCHAR(100) NULL;
ALTER TABLE guinchos MODIFY chave_pix_tipo ENUM('cpf','email','telefone','aleatoria') NULL;
