export type QaCredentials = {
  email: string;
  password: string;
};

export function clienteCreds(): QaCredentials {
  return {
    email: process.env.TEST_CLIENTE_EMAIL || process.env.TEST_USER_EMAIL || 'pw_teste@guinchafacil.com',
    password: process.env.TEST_CLIENTE_PASSWORD || process.env.TEST_USER_PASSWORD || 'test123'
  };
}

export function guinchoCreds(): QaCredentials {
  return {
    email: process.env.TEST_GUINCHO_EMAIL || 'pw_guincho@guinchafacil.com',
    password: process.env.TEST_GUINCHO_PASSWORD || 'test123'
  };
}

export function guincho2Creds(): QaCredentials {
  return {
    email: process.env.TEST_GUINCHO_2_EMAIL || '',
    password: process.env.TEST_GUINCHO_2_PASSWORD || ''
  };
}

export function adminCreds(): QaCredentials {
  return {
    email: process.env.TEST_ADMIN_EMAIL || '',
    password: process.env.TEST_ADMIN_PASSWORD || ''
  };
}

export type MpTestUser = {
  userId: string;
  username: string;
  password: string;
  verificationCode: string;
};

/**
 * Conta de teste "Comprador" do MercadoPago (Devsite > Contas de teste),
 * carregada de qa/.env.test-users.local via playwright.config.ts. Usada
 * pelo suite de pagamento sandbox para logar como uma identidade diferente
 * do vendedor antes do checkout (evita erro "comprador == vendedor").
 */
export function mpBuyerTestUser(): MpTestUser {
  return {
    userId: process.env.MP_BUYER_TEST_USER_ID || '',
    username: process.env.MP_BUYER_TEST_USERNAME || '',
    password: process.env.MP_BUYER_TEST_PASSWORD || '',
    verificationCode: process.env.MP_BUYER_TEST_VERIFICATION_CODE || ''
  };
}

/**
 * Conta de teste "Vendedor" do MercadoPago — o User ID normalmente já bate
 * com o sufixo de MP_ACCESS_TOKEN/MP_PUBLIC_KEY configurados no .env real
 * da aplicação (guinchafacil-secrets/.env.local); mantida aqui só como
 * referência/diagnóstico, não é usada para login no fluxo de teste.
 */
export function mpSellerTestUser(): MpTestUser {
  return {
    userId: process.env.MP_SELLER_TEST_USER_ID || '',
    username: process.env.MP_SELLER_TEST_USERNAME || '',
    password: process.env.MP_SELLER_TEST_PASSWORD || '',
    verificationCode: process.env.MP_SELLER_TEST_VERIFICATION_CODE || ''
  };
}
