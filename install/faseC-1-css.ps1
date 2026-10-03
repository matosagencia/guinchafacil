$ErrorActionPreference = "Stop"
$file = "C:\xampp\htdocs\guinchafacil\public\assets\css\components\address-picker.css"
Copy-Item $file "$file.bak-css-sintoma" -Force
$c = Get-Content $file -Raw -Encoding UTF8

if ($c.Contains('#stage-sintoma .choice-card')) {
    Write-Host "[SKIP] CSS do sintoma ja existe"
    exit 0
}

$bloco = @"

/* ═══════════════════════════════════════════════════════════════════
   FASE C: cards do sintoma no estilo choice-card (igual ao veiculo)
   + card-price maior + economia dentro do grid
   ═══════════════════════════════════════════════════════════════════ */

#stage-sintoma .choice-grid-sintoma {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
    gap: 10px !important;
}
#stage-sintoma .choice-card {
    min-height: 110px !important;
    padding: 14px 12px !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
    border: 2px solid #cfe3d3 !important;
    border-radius: 12px !important;
    background: #fff !important;
    cursor: pointer !important;
    transition: transform .15s, border-color .15s, box-shadow .15s !important;
    box-sizing: border-box !important;
}
#stage-sintoma .choice-card i {
    font-size: 1.6rem !important;
    color: #2fb34a !important;
    margin-bottom: 6px !important;
}
#stage-sintoma .choice-card strong {
    display: block !important;
    font-size: .95rem !important;
    color: #142018 !important;
    margin-bottom: 2px !important;
    font-weight: 700 !important;
}
#stage-sintoma .choice-card small {
    display: block !important;
    font-size: .75rem !important;
    color: #607066 !important;
    line-height: 1.25 !important;
}
#stage-sintoma .choice-card:hover {
    transform: translateY(-2px) !important;
    border-color: #2fb34a !important;
    box-shadow: 0 6px 20px rgba(47,179,74,.15) !important;
}
#stage-sintoma .choice-card.is-selected,
#stage-sintoma .choice-card[aria-pressed="true"] {
    border-color: #2fb34a !important;
    background: #edf8ef !important;
    box-shadow: 0 0 0 2px rgba(47,179,74,.15) !important;
}

/* ─── card-price maior + melhor organizacao ─── */
#opcoes-wrap .decision-card .card-price {
    font-size: 2.6rem !important;
    line-height: 1.05 !important;
    letter-spacing: -.03em !important;
    margin: 6px 0 2px !important;
}
#opcoes-wrap .decision-card h3 {
    font-size: 1.35rem !important;
    margin: 0 0 4px !important;
}
#opcoes-wrap .decision-card .card-prazo {
    font-size: .9rem !important;
    color: #405247 !important;
    margin-bottom: 18px !important;
    font-weight: 500 !important;
}

/* ─── economia-box dentro do grid (ocupa linha inteira) ─── */
#opcoes-wrap .decision-grid .economia-box {
    grid-column: 1 / -1 !important;
    margin-top: 8px !important;
    margin-bottom: 0 !important;
}
"@

$c = $c.TrimEnd() + $bloco + "`n"
[System.IO.File]::WriteAllText($file, $c, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "[OK] CSS do sintoma + card-price + economia-box adicionado"