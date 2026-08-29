import { existsSync } from 'fs';

export type ClienteBatchAccount = {
  nome: string;
  email: string;
  password: string;
  telefone: string;
  cpfDigits: string;
  cpfFormatted: string;
  cep: string;
  logradouro: string;
  numero: string;
  complemento: string;
  bairro: string;
  cidade: string;
  estado: string;
  veiculoMarca: string;
  veiculoModelo: string;
  veiculoAno: string;
  veiculoCor: string;
  veiculoTipo: 'carro' | 'moto' | 'caminhao' | 'van';
  veiculoPlaca: string;
  oficinaNome: string;
  oficinaTelefone: string;
  oficinaEndereco: string;
};

// Códigos reais de service_types, conferidos em
// install/migration_service_catalog_v1.sql — importante: é TOW_MOTORCYCLE,
// não "TOW_MOTO" (esse último não existe no catálogo real). TOW_* têm
// attendance_mode=TOWING; os demais são ON_SITE (atendimento no local, sem
// reboque).
export type ProviderService =
  | 'TOW_CAR'
  | 'TOW_MOTORCYCLE'
  | 'TOW_UTILITY'
  | 'ELECTRICAL_DIAGNOSIS'
  | 'BATTERY_REPLACEMENT'
  | 'TIRE_CHANGE'
  | 'MECHANICAL_ASSISTANCE';

export type GuinchoBatchAccount = {
  nome: string;
  email: string;
  password: string;
  telefone: string;
  cpfDigits: string;
  cpfFormatted: string;
  nascimento: string;
  cep: string;
  logradouro: string;
  numero: string;
  complemento: string;
  bairro: string;
  cidade: string;
  estado: string;
  placaGuincho: string;
  capacidadeTon: string;
  raioKm: string;
  cnhNumero: string;
  cnhValidade: string;
  pixTipo: 'cpf' | 'email' | 'telefone' | 'aleatoria';
  pixChave: string;
};

const imageCandidates = [
  'C:\\Windows\\Web\\Wallpaper\\Windows\\img0.jpg',
  'C:\\Windows\\Web\\Wallpaper\\Theme1\\img1.jpg',
];

export function resolveWindowsImage(): string {
  const found = imageCandidates.find((file) => existsSync(file));
  if (!found) {
    throw new Error('Nenhuma imagem padrão do Windows foi encontrada para upload.');
  }
  return found;
}

export function buildClienteBatch(count = 15, runTag = String(Date.now())): ClienteBatchAccount[] {
  return Array.from({ length: count }, (_, index) => {
    const ordinal = index + 1;
    const cpfDigits = generateValidCpf(110_000_000 + ordinal + hashSeed(runTag));
    return {
      nome: `Cliente Lote ${ordinal} ${runTag}`,
      email: `qa.cliente.${runTag}.${ordinal}@guinchafacil.com`,
      password: 'test12345',
      telefone: buildPhone(ordinal),
      cpfDigits,
      cpfFormatted: formatCpf(cpfDigits),
      cep: '20091-007',
      logradouro: 'Rua da Gamboa',
      numero: String(240 + ordinal),
      complemento: `Apto ${ordinal}`,
      bairro: 'Gamboa',
      cidade: 'Rio de Janeiro',
      estado: 'RJ',
      veiculoMarca: ordinal % 2 === 0 ? 'Toyota' : 'Fiat',
      veiculoModelo: ordinal % 2 === 0 ? `Corolla ${ordinal}` : `Mobi ${ordinal}`,
      veiculoAno: String(2020 + (ordinal % 5)),
      veiculoCor: ordinal % 2 === 0 ? 'Prata' : 'Branco',
      veiculoTipo: ordinal % 4 === 0 ? 'van' : 'carro',
      veiculoPlaca: generateMercosulPlate(ordinal + hashSeed(runTag)),
      oficinaNome: `Oficina QA ${ordinal}`,
      oficinaTelefone: buildPhone(70 + ordinal),
      oficinaEndereco: `Rua da Gamboa ${300 + ordinal}, Gamboa, Rio de Janeiro - RJ`,
    };
  });
}

export function buildGuinchoBatch(count = 15, runTag = String(Date.now())): GuinchoBatchAccount[] {
  return Array.from({ length: count }, (_, index) => {
    const ordinal = index + 1;
    const cpfDigits = generateValidCpf(210_000_000 + ordinal + hashSeed(runTag));
    const email = `qa.guincho.${runTag}.${ordinal}@guinchafacil.com`;
    return {
      nome: `Guincheiro Lote ${ordinal} ${runTag}`,
      email,
      password: 'test12345',
      telefone: buildPhone(200 + ordinal),
      cpfDigits,
      cpfFormatted: formatCpf(cpfDigits),
      nascimento: `198${ordinal % 10}-0${(ordinal % 9) + 1}-1${ordinal % 9}`,
      cep: '20091-007',
      logradouro: 'Rua da Gamboa',
      numero: String(400 + ordinal),
      complemento: `Base ${ordinal}`,
      bairro: 'Gamboa',
      cidade: 'Rio de Janeiro',
      estado: 'RJ',
      placaGuincho: generateMercosulPlate(500 + ordinal + hashSeed(runTag)),
      capacidadeTon: ordinal % 3 === 0 ? '8.0' : '6.5',
      raioKm: String(20 + (ordinal * 5)),
      cnhNumero: String(90000000000 + ordinal + hashSeed(runTag)).slice(0, 11),
      cnhValidade: `203${ordinal % 5}-1${ordinal % 2}-2${ordinal % 8}`,
      pixTipo: 'email',
      pixChave: email,
    };
  });
}

export type EspecialistaBatchAccount = GuinchoBatchAccount & {
  services: ProviderService[];
};

/**
 * Mesmas contas de buildGuinchoBatch, mas marcadas para configurar
 * múltiplas capacidades aprovadas (reboque + serviços ON_SITE) depois do
 * cadastro — usado pelos cenários de stress que precisam de um prestador
 * "multisserviço" de verdade, não só um guincho comum.
 */
export function buildGuinchoMultisservicoBatch(count = 5, runTag = String(Date.now())): EspecialistaBatchAccount[] {
  return buildGuinchoBatch(count, `${runTag}-multi`).map((account) => ({
    ...account,
    services: [
      'TOW_CAR',
      'TOW_MOTORCYCLE',
      'BATTERY_REPLACEMENT',
      'TIRE_CHANGE',
      'ELECTRICAL_DIAGNOSIS',
    ] as ProviderService[],
  }));
}

/**
 * Prestador "especialista puro" (sem capacidade de reboque aprovada — ver
 * seedAtendimentoSocorroSetup em seed.ts, que já usa esse perfil pro
 * cenário E2E-SOCORRO-001 de conversão pane->reboque). Reusa os mesmos
 * campos de GuinchoBatchAccount (cadastro de prestador é único no sistema;
 * "especialista" é uma configuração de capacidades, não um tipo de conta
 * diferente).
 */
export function buildEspecialistaBatch(count = 5, runTag = String(Date.now())): EspecialistaBatchAccount[] {
  return buildGuinchoBatch(count, `${runTag}-especialista`).map((account) => ({
    ...account,
    services: ['ELECTRICAL_DIAGNOSIS', 'BATTERY_REPLACEMENT'] as ProviderService[],
  }));
}

export function formatCpf(cpfDigits: string): string {
  return cpfDigits.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
}

export function isValidCpf(cpfDigits: string): boolean {
  const cpf = cpfDigits.replace(/\D/g, '');
  if (!/^\d{11}$/.test(cpf) || /^(\d)\1{10}$/.test(cpf)) {
    return false;
  }

  for (let t = 9; t < 11; t += 1) {
    let d = 0;
    for (let c = 0; c < t; c += 1) {
      d += Number(cpf[c]) * ((t + 1) - c);
    }
    d = ((10 * d) % 11) % 10;
    if (Number(cpf[t]) !== d) {
      return false;
    }
  }

  return true;
}

function generateValidCpf(seedBase: number): string {
  const base = String(seedBase).replace(/\D/g, '').padStart(9, '0').slice(-9);
  const digits = base.split('').map(Number);
  digits.push(cpfCheckDigit(digits));
  digits.push(cpfCheckDigit(digits));
  return digits.join('');
}

function cpfCheckDigit(partial: number[]): number {
  const factorStart = partial.length + 1;
  const sum = partial.reduce((acc, digit, index) => acc + digit * (factorStart - index), 0);
  return ((10 * sum) % 11) % 10;
}

function generateMercosulPlate(seed: number): string {
  // L1.11 (achado na triagem QA — falhas intermitentes de "Placa já
  // cadastrada"): a versão anterior derivava todos os 7 caracteres do MESMO
  // valor `normalized`, só somando offsets fixos antes do módulo. Como toda
  // posição é uma função linear do mesmo número, a placa inteira só depende
  // de `normalized mod 130` (mmc de 26 e 10) — ou seja, só existiam 130
  // placas possíveis no total. Depois de algumas rodadas do gate criando 15
  // contas cada, colisão de placa com uma conta já cadastrada em execução
  // anterior virou questão de tempo, não de browser (o teste falhava sempre
  // que o navegador daquela vez sorteava um seed que colidia).
  // A correção: cada posição usa um multiplicador primo distinto sobre o
  // seed antes do módulo, descorrelacionando as posições e ampliando o
  // espaço efetivo para ~45,6 milhões de placas distintas.
  const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const normalized = Math.abs(seed) | 0;
  // Math.imul faz multiplicação inteira de 32 bits sem o estouro de
  // precisão que `normalized * multiplier` teria em ponto flutuante para
  // seeds grandes (hashSeed chega a ~1e9).
  const mix = (salt: number) => Math.abs(Math.imul(normalized ^ salt, 2654435761));
  return [
    letters[mix(0x9e3779b1) % 26],
    letters[mix(0x85ebca6b) % 26],
    letters[mix(0xc2b2ae35) % 26],
    String(mix(0x27d4eb2f) % 10),
    letters[mix(0x165667b1) % 26],
    String(mix(0xd3a2646c) % 10),
    String(mix(0xfd7046c5) % 10),
  ].join('');
}

function buildPhone(seed: number): string {
  const suffix = String(10_000_000 + (seed % 89_999_999)).slice(-8);
  return `219${suffix}`;
}

function hashSeed(runTag: string): number {
  // L1.10 (achado na triagem QA): a versão anterior somava os char codes
  // e fazia `% 10_000` — espaço de apenas 10 mil valores. Como esta suíte
  // já foi reexecutada dezenas de vezes na mesma sessão de debug, runTags
  // diferentes colidiam no mesmo seed e geravam o mesmo CPF/e-mail de uma
  // conta já cadastrada em execução anterior, fazendo o registro ser
  // rejeitado com "Email ou CPF já cadastrado" mesmo com runTag novo.
  // Troca para um hash polinomial (base 31) com espaço bem maior, reduzindo
  // drasticamente a chance de colisão entre execuções.
  let hash = 0;
  for (let i = 0; i < runTag.length; i += 1) {
    hash = (hash * 31 + runTag.charCodeAt(i)) % 1_000_000_007;
  }
  return hash;
}
