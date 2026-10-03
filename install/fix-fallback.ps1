$ErrorActionPreference = "Stop"
$svc = "C:\xampp\htdocs\guinchafacil\src\Services\Address\AddressAutocompleteService.php"
Copy-Item $svc "$svc.bak-fallbackfix" -Force
$c = Get-Content $svc -Raw -Encoding UTF8

$jaTem = $c.Contains("precisaFallback")

if ($jaTem) {
    Write-Host "[SKIP] suggest ja tem precisaFallback"
    exit 0
}

# ═══ 1. Substitui o return literal por bloco com fallback ═══
$oldReturn = "        return `$this->provider->suggest(`$q, `$biasLat, `$biasLng, null, `$limit);"

$newBlock = @"
        `$resultado = `$this->provider->suggest(`$q, `$biasLat, `$biasLng, null, `$limit);

        // Se Photon trouxe poucos OU o topo nao bate com a query, tenta Nominatim
        `$precisaFallback = false;
        if (count(`$resultado) < 2) {
            `$precisaFallback = true;
        } elseif (count(`$resultado) > 0 && !`$this->verificarQualidade(`$q, `$resultado)) {
            `$precisaFallback = true;
        }

        if (`$precisaFallback && `$this->fallbackProvider !== null) {
            try {
                `$alt = `$this->fallbackProvider->suggest(`$q, `$biasLat, `$biasLng, null, `$limit);
                if (count(`$alt) > 0 && (`$this->verificarQualidade(`$q, `$alt) || count(`$alt) > count(`$resultado))) {
                    return `$alt;
                }
            } catch (Throwable `$e) {
                error_log('[AddressAutocompleteService] fallback Nominatim: ' . `$e->getMessage());
            }
        }

        return `$resultado;
"@

if (-not $c.Contains($oldReturn)) {
    Write-Host "[FAIL] return literal nao encontrado"
    exit 1
}

$c = $c.Replace($oldReturn, $newBlock)
Write-Host "[OK] 1: suggest() com fallback"

# ═══ 2. Adiciona propriedade fallbackProvider ═══
$oldProp = "    private AddressProviderInterface `$provider;"
if (-not $c.Contains('$fallbackProvider')) {
    $c = $c.Replace($oldProp, $oldProp + "`n    private ?AddressProviderInterface `$fallbackProvider = null;")
    Write-Host "[OK] 2: propriedade fallbackProvider"
} else {
    Write-Host "[SKIP] 2: propriedade ja existe"
}

# ═══ 3. Instancia fallback no construtor ═══
$oldCtor = "        `$this->provider = `$provider;"
if (-not $c.Contains('$this->fallbackProvider = new NominatimAddressProvider')) {
    $newCtor = @"
        `$this->provider = `$provider;
        // Fallback: Nominatim quando Photon nao achar
        `$nomFile = __DIR__ . '/NominatimAddressProvider.php';
        if (is_file(`$nomFile)) {
            require_once `$nomFile;
            if (class_exists('NominatimAddressProvider')) {
                `$this->fallbackProvider = new NominatimAddressProvider();
            }
        }
"@
    $c = $c.Replace($oldCtor, $newCtor)
    Write-Host "[OK] 3: construtor instancia fallback"
}

# ═══ 4. Adiciona metodo verificarQualidade ═══
if (-not $c.Contains('function verificarQualidade')) {
    $metodo = @"

    /** Verifica se o TOPO do resultado bate com a query. */
    private function verificarQualidade(string `$q, array `$resultado): bool
    {
        if (empty(`$resultado)) return false;
        `$lower = mb_strtolower(`$q, 'UTF-8');
        `$words = preg_split('/[\s,]+/', `$lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        `$stopwords = ['rua', 'av', 'avenida', 'de', 'da', 'do', 'das', 'dos', 'e', 'para', 'com', 'rio', 'janeiro', 'sao', 'paulo'];
        `$words = array_filter(`$words, function (`$w) use (`$stopwords) {
            return mb_strlen(`$w, 'UTF-8') >= 4 && !in_array(`$w, `$stopwords, true);
        });
        if (empty(`$words)) return true;

        `$p = `$resultado[0] ?? null;
        if (!`$p) return false;
        `$hay = mb_strtolower((`$p['principal'] ?? '') . ' ' . (`$p['secundario'] ?? ''), 'UTF-8');
        foreach (`$words as `$w) {
            if (mb_strpos(`$hay, `$w) !== false) return true;
        }
        return false;
    }

    private function lookupCep(string `$cep): ?array
"@
    $anchor = "    private function lookupCep(string `$cep): ?array"
    $c = $c.Replace($anchor, $metodo)
    Write-Host "[OK] 4: metodo verificarQualidade"
}

# ═══ 5. Reverse usa fallback ═══
$oldRev = "        return `$this->provider->reverse(`$lat, `$lng);"
if ($c.Contains($oldRev)) {
    $newRev = @"
        `$r = `$this->provider->reverse(`$lat, `$lng);
        if (`$r !== null) return `$r;
        if (`$this->fallbackProvider !== null) {
            try { return `$this->fallbackProvider->reverse(`$lat, `$lng); } catch (Throwable `$e) {}
        }
        return null;
"@
    $c = $c.Replace($oldRev, $newRev)
    Write-Host "[OK] 5: reverse usa fallback"
}

[System.IO.File]::WriteAllText(`$svc, `$c, (New-Object System.Text.UTF8Encoding(`$false)))
Write-Host "[OK] arquivo salvo"