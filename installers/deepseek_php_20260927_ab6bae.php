<?php
// install-v24.php — CSS do modal no arquivo certo + parser UF via display_name
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. CSS — adiciona o fix do modal em pages/tow-dashboard.css
//    (arquivo que o dashboard do guincho JÁ carrega)
// ═══════════════════════════════════════════════════════════════════
$cssPath = $root . '/public/assets/css/pages/tow-dashboard.css';
$css = file_get_contents($cssPath);
@copy($cssPath, $cssPath . '.bak-v24-' . date('Ymd-His'));

if (strpos($css, '/* v24: modal confirmação de localização') === false) {
    $bloco = <<<'CSS'

/* v24: modal confirmação de localização — força contraste correto
   (o modal do Bootstrap tem fundo branco; body.guincho tem texto claro.
   Sem isso, texto fica branco-no-branco) */
body.guincho #modalConfirmarLocalizacao .modal-content {
    background: #ffffff !important;
    color: #142018 !important;
}
body.guincho #modalConfirmarLocalizacao .modal-content * {
    color: #142018 !important;
}
body.guincho #modalConfirmarLocalizacao .modal-header {
    background: #f4f8f5 !important;
    border-bottom: 1px solid #e3ede5 !important;
}
body.guincho #modalConfirmarLocalizacao .modal-title {
    color: #248f3a !important;
    font-weight: 700 !important;
}
body.guincho #modalConfirmarLocalizacao .modal-body {
    background: #ffffff !important;
}
body.guincho #modalConfirmarLocalizacao .modal-body p,
body.guincho #modalConfirmarLocalizacao .modal-body span,
body.guincho #modalConfirmarLocalizacao .modal-body div,
body.guincho #modalConfirmarLocalizacao .modal-body strong,
body.guincho #modalConfirmarLocalizacao .modal-body i {
    color: #142018 !important;
}
body.guincho #modalConfirmarLocalizacao .modal-body p[style*="color:#607066"] {
    color: #607066 !important;
}
body.guincho #modalConfirmarLocalizacao .modal-footer {
    background: #f4f8f5 !important;
    border-top: 1px solid #e3ede5 !important;
}
body.guincho #modalConfirmarLocalizacao .btn-primary,
body.guincho #modalConfirmarLocalizacao #btnConfirmarLocalizacao {
    background: #2fb34a !important;
    border-color: #2fb34a !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}
body.guincho #modalConfirmarLocalizacao .btn-primary:hover,
body.guincho #modalConfirmarLocalizacao #btnConfirmarLocalizacao:hover {
    background: #248f3a !important;
    border-color: #248f3a !important;
    color: #ffffff !important;
}
body.guincho #modalConfirmarLocalizacao #confirmarLocalizacaoStatus {
    color: #607066 !important;
}
body.guincho #modalConfirmarLocalizacao #confirmarLocalizacaoErro {
    color: #842029 !important;
}
body.guincho #modalConfirmarLocalizacao button.btn-close {
    filter: none !important;
    opacity: .7 !important;
}
CSS;
    $css .= "\n" . $bloco;
    @file_put_contents($cssPath, $css);
    $report[] = "[OK] CSS do modal adicionado em pages/tow-dashboard.css";
} else {
    $report[] = "[SKIP] CSS v24 já existe";
}

// ═══════════════════════════════════════════════════════════════════
// 2. SERVICE — melhora ufDeCoordenada pra parsear display_name
// ═══════════════════════════════════════════════════════════════════
$svcPath = $root . '/src/Controllers/PedidoController.php';
$svc = file_get_contents($svcPath);
@copy($svcPath, $svcPath . '.bak-v24-' . date('Ymd-His'));

// Substitui o método ufDeCoordenada inteiro
$oldMetodo = '#private function ufDeCoordenada\(float \$lat, float \$lng\): \?string\s*\{.*?\n    \}#s';

$novoMetodo = <<<'PHPEOF'
private function ufDeCoordenada(float $lat, float $lng): ?string
    {
        try {
            // Chama o próprio /geocode/reverse
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
            $base = (defined('BASE_PATH') && BASE_PATH !== '') ? BASE_PATH : '';
            $url  = "http://{$host}{$base}/geocode/reverse?lat={$lat}&lng={$lng}";
            $ctx  = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $raw  = @file_get_contents($url, false, $ctx);
            if ($raw === false) return null;

            $j = json_decode($raw, true);
            if (!$j || empty($j['result'])) return null;

            $r = $j['result'];

            // 1) Caminho preferencial: se vier state_code direto
            $uf = $r['address']['state_code'] ?? $r['state_code'] ?? null;
            if ($uf && preg_match('/^[A-Z]{2}$/', strtoupper((string)$uf))) {
                return strtoupper((string)$uf);
            }

            // 2) Tenta pelo nome do estado
            $estadoNome = $r['address']['state'] ?? $r['state'] ?? null;
            if ($estadoNome) {
                $uf = $this->nomeEstadoParaUf((string)$estadoNome);
                if ($uf) return $uf;
            }

            // 3) FALLBACK: parseia o display_name
            //    Formato típico: "Rua X, Bairro, Cidade, Região, CEP, Brasil"
            $display = (string)($r['display_name'] ?? '');
            if ($display === '') return null;

            $partes = array_map('trim', explode(',', $display));

            // Procura em qualquer parte por nome de estado conhecido
            foreach ($partes as $p) {
                $uf = $this->nomeEstadoParaUf($p);
                if ($uf) return $uf;
            }

            // Procura por sigla de 2 letras isolada (ex: "RJ")
            foreach ($partes as $p) {
                if (preg_match('/^([A-Z]{2})$/', $p, $m)) {
                    return $m[1];
                }
            }

            return null;
        } catch (Throwable $e) {
            error_log('[ufDeCoordenada] ' . $e->getMessage());
            return null;
        }
    }

    /** Converte nome do estado em UF. Cobre todos os 27. */
    private function nomeEstadoParaUf(string $nome): ?string
    {
        $nome = trim($nome);
        if ($nome === '') return null;

        // Se já é uma sigla válida
        if (preg_match('/^[A-Z]{2}$/', strtoupper($nome))) {
            $uf = strtoupper($nome);
            $validas = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
            return in_array($uf, $validas, true) ? $uf : null;
        }

        // Mapa de nomes (com e sem acento, todas as variações comuns)
        static $mapa = [
            'acre' => 'AC',
            'alagoas' => 'AL',
            'amapá' => 'AP', 'amapa' => 'AP',
            'amazonas' => 'AM',
            'bahia' => 'BA',
            'ceará' => 'CE', 'ceara' => 'CE',
            'distrito federal' => 'DF',
            'espírito santo' => 'ES', 'espirito santo' => 'ES',
            'goiás' => 'GO', 'goias' => 'GO',
            'maranhão' => 'MA', 'maranhao' => 'MA',
            'mato grosso' => 'MT',
            'mato grosso do sul' => 'MS',
            'minas gerais' => 'MG',
            'pará' => 'PA', 'para' => 'PA',
            'paraíba' => 'PB', 'paraiba' => 'PB',
            'paraná' => 'PR', 'parana' => 'PR',
            'pernambuco' => 'PE',
            'piauí' => 'PI', 'piaui' => 'PI',
            'rio de janeiro' => 'RJ',
            'rio grande do norte' => 'RN',
            'rio grande do sul' => 'RS',
            'rondônia' => 'RO', 'rondonia' => 'RO',
            'roraima' => 'RR',
            'santa catarina' => 'SC',
            'são paulo' => 'SP', 'sao paulo' => 'SP',
            'sergipe' => 'SE',
            'tocantins' => 'TO',
        ];

        $chave = mb_strtolower($nome, 'UTF-8');
        return $mapa[$chave] ?? null;
    }
PHPEOF;

if (preg_match($oldMetodo, $svc)) {
    $svc = preg_replace($oldMetodo, $novoMetodo, $svc, 1);
    @file_put_contents($svcPath, $svc);
    $report[] = "[OK] ufDeCoordenada() reescrito + nomeEstadoParaUf() adicionado";
} else {
    $report[] = "[AVISO] Não achei o método antigo ufDeCoordenada — patch manual necessário";
}

$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($svcPath) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lint);

// ═══════════════════════════════════════════════════════════════════
// 3. Teste direto do parser
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── TESTE DE PARSE ──";

$testes = [
    'Avenida Barão de Tefé, Saúde, Rio de Janeiro, Região Sudeste, 20081-312, Brasil' => 'RJ',
    'Rua da Gamboa, 100, Rio de Janeiro, RJ, Brasil' => 'RJ',
    'Avenida Paulista, Bela Vista, São Paulo, Sudeste, 01310-100, Brasil' => 'SP',
    'Rua das Flores, 50, Niterói, RJ, Brasil' => 'RJ',
    'Praça da Sé, Centro, São Paulo, SP, Brasil' => 'SP',
];

// Recria a lógica de parse pra testar sem instanciar o controller
function _teste_parse(string $display): ?string
{
    $mapa = [
        'acre'=>'AC','alagoas'=>'AL','amapá'=>'AP','amapa'=>'AP','amazonas'=>'AM','bahia'=>'BA',
        'ceará'=>'CE','ceara'=>'CE','distrito federal'=>'DF','espírito santo'=>'ES','espirito santo'=>'ES',
        'goiás'=>'GO','goias'=>'GO','maranhão'=>'MA','maranhao'=>'MA','mato grosso'=>'MT',
        'mato grosso do sul'=>'MS','minas gerais'=>'MG','pará'=>'PA','para'=>'PA','paraíba'=>'PB','paraiba'=>'PB',
        'paraná'=>'PR','parana'=>'PR','pernambuco'=>'PE','piauí'=>'PI','piaui'=>'PI','rio de janeiro'=>'RJ',
        'rio grande do norte'=>'RN','rio grande do sul'=>'RS','rondônia'=>'RO','rondonia'=>'RO','roraima'=>'RR',
        'santa catarina'=>'SC','são paulo'=>'SP','sao paulo'=>'SP','sergipe'=>'SE','tocantins'=>'TO',
    ];
    $partes = array_map('trim', explode(',', $display));
    foreach ($partes as $p) {
        $k = mb_strtolower($p, 'UTF-8');
        if (isset($mapa[$k])) return $mapa[$k];
        if (preg_match('/^([A-Z]{2})$/', $p, $m)) return $m[1];
    }
    return null;
}

foreach ($testes as $display => $esperado) {
    $obtido = _teste_parse($display);
    $ok = $obtido === $esperado ? '✅' : '❌';
    $report[] = "$ok esperado=$esperado obtido=" . ($obtido ?? 'NULL') . " | \"$display\"";
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v24</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔧 Installer v24 — Modal + UF parser</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v24.php</p>
</body></html>