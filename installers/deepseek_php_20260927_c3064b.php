<?php
// install-v25.php — força update do preço no destinoBox + UF via GeocodingService
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. JS — FORÇA atualização do preço no destinoBox
// ═══════════════════════════════════════════════════════════════════
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
$js = file_get_contents($jsPath);
@copy($jsPath, $jsPath . '.bak-v25-' . date('Ymd-His'));

// Regex robusto: acha o bloco .then(function (j) { ... }) dentro de calcCota
$pattern = '#(function calcCota\(latDest, lngDest\) \{.*?\.then\(function \(j\) \{\s*)(var d = j\.data \|\| j;\s*var tow = d\.opcao_reboque \|\| \{\};\s*)(var rd = .*?;\s*if \(rd && tow\.custo_total\) rd\.textContent = money\(tow\.custo_total\);)(.*?)(\.catch\(function \(e\) \{ log\(\'erro cota:\', e\); \}\);\s*\})#s';

$novoBloco = <<<'JSNEW'
$1$2var rd = $('reboqueDeslocamento'); if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);
            // v25: FORÇA atualização do preço no destinoBox
            var prd = $('precoReboqueDestino'); if (prd && tow.custo_total) prd.textContent = money(tow.custo_total);
            var prW = $('precoDestinoWrap'); if (prW) prW.hidden = false;
            // Atualiza também o preço no card B (não mais exibido, mas mantém consistência)
            var apB = $('precoReboqueCard'); if (apB && tow.custo_total) apB.textContent = money(tow.custo_total);$4$5
JSNEW;

if (preg_match($pattern, $js)) {
    $js = preg_replace($pattern, $novoBloco, $js, 1);
    @file_put_contents($jsPath, $js);
    $report[] = "[OK] JS: update de precoReboqueDestino forçado em calcCota()";
} else {
    // Fallback: substitui o bloco simples
    $oldSimple = "var rd = \$('reboqueDeslocamento'); if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);";
    $newSimple = $oldSimple . "\n            var prd = \$('precoReboqueDestino'); if (prd && tow.custo_total) prd.textContent = money(tow.custo_total);\n            var prW = \$('precoDestinoWrap'); if (prW) prW.hidden = false;";

    if (strpos($js, $oldSimple) !== false) {
        $js = str_replace($oldSimple, $newSimple, $js);
        @file_put_contents($jsPath, $js);
        $report[] = "[OK] JS: update forçado via fallback";
    } else {
        $report[] = "[AVISO] JS: não achei o ponto de injeção — inspeção manual";
    }
}

// Confirma
$jsFinal = file_get_contents($jsPath);
if (preg_match("#precoReboqueDestino'\);\s*if\s*\(prd\s*&&\s*tow\.custo_total\)#", $jsFinal)) {
    $report[] = "[✅] JS confirmado: precoReboqueDestino é atualizado";
} else {
    $report[] = "[❌] JS: precoReboqueDestino NÃO é atualizado — patch falhou";
}

// ═══════════════════════════════════════════════════════════════════
// 2. CONTROLLER — ufDeCoordenada SEM HTTP interno
// ═══════════════════════════════════════════════════════════════════
$ctrlPath = $root . '/src/Controllers/PedidoController.php';
$ctrl = file_get_contents($ctrlPath);
@copy($ctrlPath, $ctrlPath . '.bak-v25-' . date('Ymd-His'));

// Substitui o método ufDeCoordenada inteiro por versão SEM HTTP
$oldMetodo = '#private function ufDeCoordenada\(float \$lat, float \$lng\): \?string\s*\{.*?\n    \}#s';

$novoMetodo = <<<'PHPEOF'
private function ufDeCoordenada(float $lat, float $lng): ?string
    {
        try {
            // 1) Tenta usar GeocodingService diretamente (SEM HTTP)
            $svcFile = __DIR__ . '/../Services/GeocodingService.php';
            if (is_file($svcFile)) {
                require_once $svcFile;
                if (class_exists('GeocodingService')) {
                    $svc = new \GeocodingService();
                    foreach (['reverse', 'reverseGeocode', 'reverseGeocoding', 'geocodificarReverso'] as $method) {
                        if (method_exists($svc, $method)) {
                            try {
                                $r = $svc->$method($lat, $lng);
                                if (is_array($r)) {
                                    $uf = $this->extrairUfDeResultado($r);
                                    if ($uf) return $uf;
                                }
                            } catch (Throwable $e) {
                                error_log("[ufDeCoordenada] método $method falhou: " . $e->getMessage());
                            }
                        }
                    }
                }
            }

            // 2) Fallback: chama Nominatim DIRETAMENTE (sem passar pelo nosso servidor)
            $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&addressdetails=1&accept-language=pt-BR";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'GuinchaFacil/1.0 (contato@guinchafacil.com.br)',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $caPath = defined('CA_BUNDLE_PATH') ? CA_BUNDLE_PATH : null;
            if ($caPath && is_file($caPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            }
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $err) {
                error_log('[ufDeCoordenada] cURL Nominatim: ' . $err);
                return null;
            }

            $j = json_decode($raw, true);
            if (!$j) return null;

            return $this->extrairUfDeResultado($j);
        } catch (Throwable $e) {
            error_log('[ufDeCoordenada] ' . $e->getMessage());
            return null;
        }
    }

    /** Extrai UF de um array de resposta (Nominatim ou serviço interno). */
    private function extrairUfDeResultado(array $r): ?string
    {
        // address.state_code (ex: "RJ")
        $uf = $r['address']['state_code'] ?? $r['state_code'] ?? null;
        if ($uf && preg_match('/^[A-Z]{2}$/', strtoupper((string)$uf))) {
            return strtoupper((string)$uf);
        }

        // address.state (nome completo)
        $nome = $r['address']['state'] ?? $r['state'] ?? null;
        if ($nome) {
            $uf = $this->nomeEstadoParaUf((string)$nome);
            if ($uf) return $uf;
        }

        // display_name
        $display = (string)($r['display_name'] ?? '');
        if ($display !== '') {
            foreach (array_map('trim', explode(',', $display)) as $p) {
                $uf = $this->nomeEstadoParaUf($p);
                if ($uf) return $uf;
            }
        }

        return null;
    }

    /** Converte nome do estado em UF. Cobre todos os 27. */
    private function nomeEstadoParaUf(string $nome): ?string
    {
        $nome = trim($nome);
        if ($nome === '') return null;

        if (preg_match('/^[A-Z]{2}$/', strtoupper($nome))) {
            $uf = strtoupper($nome);
            $validas = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
            return in_array($uf, $validas, true) ? $uf : null;
        }

        static $mapa = [
            'acre'=>'AC','alagoas'=>'AL','amapá'=>'AP','amapa'=>'AP','amazonas'=>'AM','bahia'=>'BA',
            'ceará'=>'CE','ceara'=>'CE','distrito federal'=>'DF','espírito santo'=>'ES','espirito santo'=>'ES',
            'goiás'=>'GO','goias'=>'GO','maranhão'=>'MA','maranhao'=>'MA','mato grosso'=>'MT',
            'mato grosso do sul'=>'MS','minas gerais'=>'MG','pará'=>'PA','para'=>'PA','paraíba'=>'PB','paraiba'=>'PB',
            'paraná'=>'PR','parana'=>'PR','pernambuco'=>'PE','piauí'=>'PI','piaui'=>'PI','rio de janeiro'=>'RJ',
            'rio grande do norte'=>'RN','rio grande do sul'=>'RS','rondônia'=>'RO','rondonia'=>'RO','roraima'=>'RR',
            'santa catarina'=>'SC','são paulo'=>'SP','sao paulo'=>'SP','sergipe'=>'SE','tocantins'=>'TO',
        ];
        return $mapa[mb_strtolower($nome, 'UTF-8')] ?? null;
    }
PHPEOF;

if (preg_match($oldMetodo, $ctrl)) {
    $ctrl = preg_replace($oldMetodo, $novoMetodo, $ctrl, 1);
    @file_put_contents($ctrlPath, $ctrl);
    $report[] = "[OK] ufDeCoordenada() reescrito SEM HTTP interno + extrairUfDeResultado()";
} else {
    $report[] = "[AVISO] Não achei ufDeCoordenada() antigo — inspeção manual";
}

$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($ctrlPath) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lint);

// Confirma
$ctrlFinal = file_get_contents($ctrlPath);
if (strpos($ctrlFinal, 'extrairUfDeResultado') !== false && strpos($ctrlFinal, 'nominatim.openstreetmap.org') !== false) {
    $report[] = "[✅] Controller: novo parser + cURL Nominatim presentes";
} else {
    $report[] = "[❌] Controller: patch pode não ter aplicado";
}

// ═══════════════════════════════════════════════════════════════════
// 3. DIAGNÓSTICO
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── TESTES ──";
$report[] = "1. Reinicia Apache (painel XAMPP: Stop → Start) — OBRIGATÓRIO (OPcache)";
$report[] = "2. Testa UF: http://localhost:8080/api/pre-cotacao/validar-uf-destino?lat_origem=-22.897&lng_origem=-43.187&lat_destino=-22.9339&lng_destino=-43.1958";
$report[] = "3. Teste Playwright: npx playwright test tests/e2e/fluxo-1a.spec.js --reporter=list --headed";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v25</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v25 — Preço + UF sem HTTP interno</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v25.php</p>
</body></html>