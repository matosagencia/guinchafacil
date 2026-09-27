<?php
// install-v21.php — regra UF origem=destino + fix do teste
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. FIX DO TESTE — corrige #reboqueDeslocamento → #precoReboqueDestino
// ═══════════════════════════════════════════════════════════════════
$testPath = $root . '/tests/e2e/fluxo-1a.spec.js';
$test = file_get_contents($testPath);
@copy($testPath, $testPath . '.bak-v21-' . date('Ymd-His'));

// Substitui o passo 13 inteiro
$old13 = <<<'JSEOF'
    // ═══ PASSO 13: verificar que preço do reboque foi recalculado ═══
    await check('preço do reboque calculado após marcar destino', async () => {
      const txt = await page.locator('#reboqueDeslocamento').textContent();
      expect(txt).toMatch(/R\$\s*\d/);
      return txt.trim();
    });
JSEOF;

$new13 = <<<'JSEOF'
    // ═══ PASSO 13: verificar que preço do reboque foi recalculado (no destinoBox) ═══
    await check('preço do reboque recalculado após marcar destino', async () => {
      const wrap = page.locator('#precoDestinoWrap');
      await expect(wrap).toBeVisible({ timeout: 10000 });
      const txt = await page.locator('#precoReboqueDestino').textContent();
      expect(txt).toMatch(/R\$\s*\d/);
      return txt.trim();
    });

    // ═══ PASSO 14: validar UF origem=destino (regra de negócio) ═══
    await check('UF do destino = UF da origem', async () => {
      const ufOrigem = await page.locator('#uf_origem_detectada').evaluate(el => el.value).catch(() => '');
      const ufDestino = await page.locator('#uf_destino_detectada').evaluate(el => el.value).catch(() => '');
      if (ufOrigem && ufDestino) {
        expect(ufDestino).toBe(ufOrigem);
        return `${ufOrigem} = ${ufDestino}`;
      }
      return '(UF não detectada — pulando)';
    });
JSEOF;

if (strpos($test, $old13) !== false) {
    $test = str_replace($old13, $new13, $test);
    @file_put_contents($testPath, $test);
    $report[] = "[OK] Teste corrigido: passo 13 usa #precoReboqueDestino + novo passo 14";
} else {
    // Fallback: só troca o ID
    $test = str_replace('#reboqueDeslocamento', '#precoReboqueDestino', $test);
    @file_put_contents($testPath, $test);
    $report[] = "[OK] Teste corrigido via substituição simples";
}

// ═══════════════════════════════════════════════════════════════════
// 2. BACKEND — endpoint validar-uf-destino
// ═══════════════════════════════════════════════════════════════════
$ctrlPath = $root . '/src/Controllers/PedidoController.php';
$ctrl = file_get_contents($ctrlPath);
@copy($ctrlPath, $ctrlPath . '.bak-v21-' . date('Ymd-His'));

if (strpos($ctrl, 'function validarUfDestino') === false) {
    $metodo = <<<'PHPEOF'

    /** GET /api/pre-cotacao/validar-uf-destino?lat_origem=&lng_origem=&lat_destino=&lng_destino= */
    public function validarUfDestino(): void
    {
        $this->json(function (): array {
            $latO = filter_var($_GET['lat_origem']  ?? null, FILTER_VALIDATE_FLOAT);
            $lngO = filter_var($_GET['lng_origem']  ?? null, FILTER_VALIDATE_FLOAT);
            $latD = filter_var($_GET['lat_destino'] ?? null, FILTER_VALIDATE_FLOAT);
            $lngD = filter_var($_GET['lng_destino'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($latO === false || $lngO === false || $latD === false || $lngD === false) {
                throw new InvalidArgumentException('Informe lat/lng de origem e destino.');
            }

            $ufO = $this->ufDeCoordenada((float)$latO, (float)$lngO);
            $ufD = $this->ufDeCoordenada((float)$latD, (float)$lngD);

            // Se não conseguir detectar alguma das UFs, permite (não bloqueia)
            $ok = ($ufO === null || $ufD === null || $ufO === $ufD);

            return [
                'ok' => $ok,
                'uf_origem' => $ufO,
                'uf_destino' => $ufD,
                'mensagem' => $ok
                    ? ''
                    : "O veículo só pode ser levado para uma oficina no mesmo estado. Sua origem está em {$ufO} e o destino em {$ufD}.",
            ];
        });
    }

    /** Reverse geocode local — retorna UF (ex: "RJ") ou null. */
    private function ufDeCoordenada(float $lat, float $lng): ?string
    {
        try {
            // Tenta via GeocodingService (se tiver reverse nativo)
            $svcFile = __DIR__ . '/../Services/GeocodingService.php';
            if (is_file($svcFile)) {
                require_once $svcFile;
                if (class_exists('GeocodingService')) {
                    $svc = new \GeocodingService();
                    if (method_exists($svc, 'reverse')) {
                        $r = $svc->reverse($lat, $lng);
                        $uf = $r['state_code'] ?? $r['uf'] ?? ($r['address']['state_code'] ?? null);
                        if ($uf) return strtoupper((string)$uf);
                    }
                }
            }

            // Fallback: chamada HTTP ao próprio /geocode/reverse (mesmo host)
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
            $base = (defined('BASE_PATH') && BASE_PATH !== '') ? BASE_PATH : '';
            $url = "http://{$host}{$base}/geocode/reverse?lat={$lat}&lng={$lng}";
            $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) return null;
            $j = json_decode($raw, true);
            if (!$j) return null;
            // Formato esperado: {ok:true, result:{address:{state_code:"RJ", state:"Rio de Janeiro"}}}
            $addr = $j['result']['address'] ?? $j['address'] ?? [];
            $uf = $addr['state_code'] ?? $addr['ISO3166-2-lvl4'] ?? null;
            if (!$uf && !empty($addr['state'])) {
                // Fallback: mapa de nome → UF
                $mapa = [
                    'Rio de Janeiro' => 'RJ', 'São Paulo' => 'SP', 'Minas Gerais' => 'MG',
                    'Espírito Santo' => 'ES', 'Bahia' => 'BA', 'Paraná' => 'PR',
                ];
                $uf = $mapa[$addr['state']] ?? null;
            }
            return $uf ? strtoupper((string)$uf) : null;
        } catch (Throwable $e) {
            error_log('[validarUfDestino] ' . $e->getMessage());
            return null;
        }
    }
PHPEOF;

    $pos = strrpos($ctrl, '}');
    $ctrl = substr($ctrl, 0, $pos) . $metodo . "\n}\n";
    @file_put_contents($ctrlPath, $ctrl);
    $report[] = "[OK] PedidoController: validarUfDestino() + ufDeCoordenada()";
} else {
    $report[] = "[SKIP] PedidoController: método já existe";
}
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($ctrlPath) . ' 2>&1');
$report[] = "[LINT] PedidoController: " . trim((string)$lint);

// ═══════════════════════════════════════════════════════════════════
// 3. INDEX.PHP — rotas GET e POST
// ═══════════════════════════════════════════════════════════════════
$indexPath = $root . '/index.php';
$index = file_get_contents($indexPath);
@copy($indexPath, $indexPath . '.bak-v21-' . date('Ymd-His'));

// GET (adiciona depois da rota oficinas-proximas no GET)
$rotaGet = "        '/api/pre-cotacao/oficinas-proximas' => ['PedidoController', 'oficinasProximas', null],";
$rotaGetNova = $rotaGet . "\n        '/api/pre-cotacao/validar-uf-destino' => ['PedidoController', 'validarUfDestino', null],";

if (strpos($index, 'validar-uf-destino') === false) {
    // Só adiciona na PRIMEIRA ocorrência (que é o GET)
    $pos = strpos($index, $rotaGet);
    if ($pos !== false) {
        $index = substr_replace($index, $rotaGetNova, $pos, strlen($rotaGet));
        @file_put_contents($indexPath, $index);
        $report[] = "[OK] index.php: rota GET validar-uf-destino";
    } else {
        $report[] = "[AVISO] Não achei anchor da rota no index.php";
    }
} else {
    $report[] = "[SKIP] Rota já existe";
}

// ═══════════════════════════════════════════════════════════════════
// 4. VIEW — campos hidden para o teste poder ler as UFs
// ═══════════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v21-' . date('Ymd-His'));

if (strpos($view, 'uf_origem_detectada') === false) {
    // Adiciona 2 inputs hidden perto do lat/lng de origem
    $anchor = '<input type="hidden" id="lat_origem" name="lat_origem">';
    $novos = $anchor . "\n" . '<input type="hidden" id="uf_origem_detectada" value="">' . "\n" . '<input type="hidden" id="uf_destino_detectada" value="">';
    if (strpos($view, $anchor) !== false) {
        $view = str_replace($anchor, $novos, $view);
        @file_put_contents($viewPath, $view);
        $report[] = "[OK] View: inputs hidden uf_origem/uf_destino";
    } else {
        $report[] = "[AVISO] Não achei anchor lat_origem";
    }
} else {
    $report[] = "[SKIP] Inputs UF já existem";
}

// ═══════════════════════════════════════════════════════════════════
// 5. JS — validação UF no calcCota()
// ═══════════════════════════════════════════════════════════════════
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
$js = file_get_contents($jsPath);
@copy($jsPath, $jsPath . '.bak-v21-' . date('Ymd-His'));

if (strpos($js, 'validar-uf-destino') === false) {
    // Injeta uma chamada dentro de calcCota, após calcular distância
    $oldCalc = "log('distância origem→destino:', dist.toFixed(2), 'km');";
    $newCalc = <<<'JSNEW'
log('distância origem→destino:', dist.toFixed(2), 'km');

        // Validar UF origem = UF destino
        fetch('/api/pre-cotacao/validar-uf-destino?lat_origem=' + encodeURIComponent(latO.value) +
              '&lng_origem=' + encodeURIComponent(lngO.value) +
              '&lat_destino=' + encodeURIComponent(latDest) +
              '&lng_destino=' + encodeURIComponent(lngDest),
              { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var d = j.data || j;
                var ufO = $('uf_origem_detectada'); if (ufO) ufO.value = d.uf_origem || '';
                var ufD = $('uf_destino_detectada'); if (ufD) ufD.value = d.uf_destino || '';
                var aviso = document.getElementById('ufDestinoAviso');
                if (!d.ok) {
                    if (!aviso) {
                        aviso = document.createElement('div');
                        aviso.id = 'ufDestinoAviso';
                        aviso.className = 'alert alert-warning mt-2';
                        var box = $('destinoBox');
                        if (box) box.appendChild(aviso);
                    }
                    aviso.textContent = d.mensagem || 'O destino precisa ser no mesmo estado da origem.';
                    aviso.style.display = 'block';
                    var btn = $('btnCotacao');
                    if (btn) btn.disabled = true;
                } else {
                    if (aviso) aviso.style.display = 'none';
                    var btn2 = $('btnCotacao');
                    if (btn2) btn2.disabled = false;
                }
            })
            .catch(function () { /* silencioso */ });
JSNEW;

    if (strpos($js, $oldCalc) !== false) {
        $js = str_replace($oldCalc, $newCalc, $js);
        @file_put_contents($jsPath, $js);
        $report[] = "[OK] JS: validação UF dentro de calcCota()";
    } else {
        $report[] = "[AVISO] Não achei anchor do calcCota no JS — patch manual";
    }
} else {
    $report[] = "[SKIP] JS já tem validação UF";
}

// ═══════════════════════════════════════════════════════════════════
// Diagnóstico
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── VERIFICAÇÃO ──";

// Confirma endpoint no index
$indexCheck = file_get_contents($indexPath);
if (strpos($indexCheck, 'validar-uf-destino') !== false) {
    $report[] = "[✅] Rota validar-uf-destino presente no index.php";
} else {
    $report[] = "[❌] Rota NÃO está no index.php";
}

// Confirma método
$ctrlCheck = file_get_contents($ctrlPath);
if (strpos($ctrlCheck, 'function validarUfDestino') !== false) {
    $report[] = "[✅] Método validarUfDestino() presente no PedidoController";
} else {
    $report[] = "[❌] Método NÃO está no PedidoController";
}

$report[] = "";
$report[] = "── TESTE ──";
$report[] = "1. Reinicia Apache (painel XAMPP: Stop → Start)";
$report[] = "2. npx playwright test tests/e2e/fluxo-1a.spec.js --reporter=list --headed";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v21</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v21 — UF origem=destino + fix do teste</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v21.php</p>
</body></html>