# onda52-A-parceiros-final.ps1
# Onda 5.2 - Refinamento final das 2 paginas de parceiros:
#   - Hero image real (guincho em operacao / oficina em operacao)
#   - Formulario de cadastro REAL (3 campos + Google OAuth)
#   - FAQ accordion com 10 perguntas por pagina
#   - JSON-LD FAQPage para SEO
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[O5.2F] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[O5.2F][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[O5.2F][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[O5.2F][FAIL] $m" -ForegroundColor Red }

function Save-PhpFile([string]$path, [string]$content, [string]$label) {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    $bak = "$path.bak-$ts"
    if (Test-Path $path) {
        Copy-Item -LiteralPath $path -Destination $bak -Force
        Write-Step "Backup: $bak"
    }
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllBytes($path, $utf8NoBom.GetBytes($content))
    if ((Get-Item $path).Length -eq 0) {
        Write-Fail "$label escrito vazio"
        if (Test-Path $bak) { Copy-Item -LiteralPath $bak -Destination $path -Force }
        return $false
    }
    $b = [System.IO.File]::ReadAllBytes($path)
    if ($b.Length -ge 3 -and $b[0] -eq 0xEF -and $b[1] -eq 0xBB -and $b[2] -eq 0xBF) {
        Write-Fail "$label com BOM"
        if (Test-Path $bak) { Copy-Item -LiteralPath $bak -Destination $path -Force }
        return $false
    }
    $out = & $PhpExe -l $path 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "php -l falhou em $label : $($out -join ' ')"
        if (Test-Path $bak) { Copy-Item -LiteralPath $bak -Destination $path -Force }
        return $false
    }
    Write-Ok "$label"
    return $true
}

# ===========================================================================
# 0. Sanidade
# ===========================================================================
$imgGuinchos  = Join-Path $ProjectRoot 'public\assets\img\landing\hero-parceiros-guinchos.jpg'
$imgOficinas  = Join-Path $ProjectRoot 'public\assets\img\landing\hero-parceiros-oficinas.jpg'
$guinchosView = Join-Path $ProjectRoot 'src\Views\public\parceiros\guinchos.php'
$oficinasView = Join-Path $ProjectRoot 'src\Views\public\parceiros\oficinas.php'
$cssLayout    = Join-Path $ProjectRoot 'public\assets\css\gf-layout.css'

Write-Host ""
Write-Step "=== 0. Sanidade ==="
foreach ($p in @($guinchosView, $oficinasView, $cssLayout)) {
    if (-not (Test-Path $p)) { Write-Fail "Nao existe: $p"; exit 1 }
}
if (-not (Test-Path $imgGuinchos)) { Write-Fail "Falta imagem: hero-parceiros-guinchos.jpg"; exit 1 }
if (-not (Test-Path $imgOficinas)) { Write-Fail "Falta imagem: hero-parceiros-oficinas.jpg"; exit 1 }
Write-Ok "Arquivos e imagens presentes"

# ===========================================================================
# 1. guinchos.php
# ===========================================================================
Write-Host ""
Write-Step "=== 1. Reescrevendo parceiros/guinchos.php ==="

$guinchosContent = @'
<?php
/**
 * Parceiros - Guinchos (Onda 5.2 refinada)
 * Hero real + formulario 3 campos + Google OAuth + FAQ accordion + JSON-LD
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e  = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$csrf_token_view = isset($_SESSION['_csrf_token']) ? (string)$_SESSION['_csrf_token'] : '';

$url = 'https://guinchafacil.com.br/parceiros/guinchos';
$titulo = 'Seja guincho parceiro - Receba chamados sem mensalidade';
$descricao = 'Cadastro gratuito para guincheiros. Receba chamados compativeis com sua regiao, sem mensalidade e com repasse via PIX em D+7.';

$faqs = [
    ['Como funciona a parceria com a GuinchaFacil?', 'Voce se cadastra, nossa equipe analisa seu perfil e, apos a aprovacao, comeca a receber chamados de reboque compativeis com sua regiao e capacidade. A plataforma cuida da triagem, do pagamento e do suporte para que voce foque no atendimento.'],
    ['Preciso pagar algo para participar?', 'Nao. O cadastro e 100% gratuito, sem taxa de adesao e sem mensalidade. Voce so ganha quando atende. As vagas sao limitadas por regiao, entao garanta a sua agora.'],
    ['Como recebo pelos chamados realizados?', 'O repasse e feito via PIX em D+7 apos a conclusao do atendimento, ja descontadas as taxas. Nossos parceiros ja recebem com regularidade e transparencia total.'],
    ['Posso escolher os chamados que aceito?', 'Sim. Voce tem total autonomia para aceitar ou recusar chamados antes do aceite, sem qualquer penalidade. A plataforma se adapta a sua rotina.'],
    ['E se o cliente cancelar ou nao pagar?', 'Voce nao fica no prejuizo. Temos garantias operacionais para proteger seu trabalho, incluindo taxa de saida em cancelamentos apos deslocamento e cobertura em casos de inadimplencia do cliente.'],
    ['Preciso ter CNPJ para ser parceiro?', 'Nao e obrigatorio. Aceitamos MEI, autonomos e empresas. Nosso objetivo e facilitar sua entrada e ampliar suas oportunidades.'],
    ['Como funciona o suporte ao parceiro?', 'Voce conta com suporte humano e dedicado, que entende a operacao de guincho. Fale com nossa equipe diretamente pelo painel para resolver qualquer duvida sobre chamados, pagamentos ou operacao.'],
    ['Quanto tempo leva para meu cadastro ser aprovado?', 'A analise e rapida, mas as vagas por regiao sao limitadas. Quanto antes voce se cadastrar, maiores as chances de garantir sua area de atuacao.'],
    ['Posso atender em mais de uma cidade ou regiao?', 'Sim. Voce define e ajusta suas areas de atendimento, horarios e raio de cobertura conforme sua capacidade. A plataforma respeita seus limites.'],
    ['Como funciona a reputacao na plataforma?', 'Sua nota e construida com base em avaliacoes reais de clientes, aceites e cancelamentos. Quanto melhor seu atendimento, mais chamados voce recebe e mais destaque ganha na rede.'],
];

$meta_title = $titulo;
$meta_description = $descricao;
$meta_canonical = $url;
$meta_og_image = 'https://guinchafacil.com.br/public/assets/img/landing/hero-parceiros-guinchos.jpg';
$body_class = 'gf-landing';
$nav_atual = 'parceiros';

$meta_json_ld = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $titulo,
        'url' => $url,
        'description' => $descricao,
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(
            static fn(array $f): array => [
                '@type' => 'Question',
                'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ],
            $faqs
        ),
    ],
];

require __DIR__ . '/_header-public.php';
?>

<main>
    <section class="gf-hero gf-hero--parceiros" aria-labelledby="hero-guinchos">
        <img src="<?php echo $e($bp); ?>/public/assets/img/landing/hero-parceiros-guinchos.jpg"
             alt="Guincheiro profissional ao lado do caminhao plataforma em rodovia brasileira ao por do sol"
             width="1920" height="1080" class="gf-hero__photo" loading="eager">
        <div class="gf-container">
            <div class="gf-hero__content">
                <div class="gf-hero__eyebrow">
                    <span class="gf-status-dot"></span> PARCEIROS GUINCHO
                </div>
                <h1 id="hero-guinchos">Seu guincho merece<br>novos <em>caminhos.</em></h1>
                <p>Mais oportunidades na sua regiao, autonomia para aceitar chamados e clareza em cada atendimento. Cadastro gratuito, sem mensalidade.</p>
                <div class="gf-hero__actions">
                    <a class="gf-btn gf-btn--primary gf-btn--lg" href="#quero-participar">
                        Quero fazer parte &nearr;
                    </a>
                    <a class="gf-btn gf-btn--outline gf-btn--lg" href="#faq">
                        Tirar duvidas
                    </a>
                </div>
                <div class="gf-hero__note">
                    <i class="fas fa-shield-halved"></i> Sem adesao. Sem mensalidade. Repasse via PIX.
                </div>
            </div>
        </div>
    </section>

    <section class="gf-section" id="simulador">
        <div class="gf-container">
            <div class="gf-eyebrow">SIMULADOR DE GANHOS</div>
            <h2 class="gf-section-title">Veja o potencial da sua operacao.</h2>
            <p class="gf-section-description">Uma projecao ilustrativa, sem promessa de renda ou volume de chamados.</p>

            <div class="gf-simulator">
                <label class="gf-simulator__field">
                    <span class="gf-field-label">Atendimentos por dia: <strong id="sim-atend">3</strong></span>
                    <input type="range" id="sim-atend-range" min="1" max="10" value="3" class="gf-simulator__range">
                </label>
                <label class="gf-simulator__field">
                    <span class="gf-field-label">Dias de trabalho por mes: <strong id="sim-dias">22</strong></span>
                    <input type="range" id="sim-dias-range" min="1" max="30" value="22" class="gf-simulator__range">
                </label>
                <div class="gf-price-result">
                    <span class="gf-price-result__eyebrow">Faturamento bruto mensal simulado</span>
                    <strong class="gf-price-result__value" id="sim-total">R$ 11.880</strong>
                    <p class="gf-form-note">Referencia ficticia: R$ 180 por atendimento. Nao inclui custos, impostos ou taxas. Nao constitui garantia de ganhos.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="gf-section gf-section--alt" id="quero-participar">
        <div class="gf-container">
            <div class="gf-form-panel">
                <div class="gf-eyebrow">CADASTRO RAPIDO</div>
                <h2 class="gf-section-title">Crie sua conta em 30 segundos.</h2>
                <p class="gf-section-description">Informe nome, WhatsApp e e-mail. Depois voce completa os dados do veiculo e documentos no painel.</p>

                <form method="POST" action="<?php echo $e($bp); ?>/registro/simples/guincho" class="gf-fields-stack">
                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrf_token_view); ?>">
                    <input type="hidden" name="tipo" value="guincho">

                    <div class="gf-field">
                        <label class="gf-field-label" for="g_nome">Seu nome completo *</label>
                        <input class="gf-field-control" type="text" id="g_nome" name="nome" required minlength="3" maxlength="120" autocomplete="name" placeholder="Como podemos te chamar?">
                    </div>
                    <div class="gf-field">
                        <label class="gf-field-label" for="g_tel">WhatsApp *</label>
                        <input class="gf-field-control" type="tel" id="g_tel" name="telefone" required inputmode="tel" autocomplete="tel" placeholder="(21) 99999-9999">
                    </div>
                    <div class="gf-field">
                        <label class="gf-field-label" for="g_email">E-mail *</label>
                        <input class="gf-field-control" type="email" id="g_email" name="email" required autocomplete="email" placeholder="voce@email.com">
                    </div>

                    <button class="gf-btn gf-btn--primary gf-btn--lg" type="submit" style="width:100%">
                        Criar minha conta gratis &rarr;
                    </button>
                </form>

                <div class="gf-form-divider"><span>ou</span></div>

                <a class="gf-btn gf-btn--outline gf-btn--lg" style="width:100%" href="<?php echo $e($bp); ?>/auth/google?retorno=/perfil/completar">
                    <i class="fab fa-google" aria-hidden="true"></i> Continuar com Google
                </a>

                <p class="gf-form-note" style="justify-content:center;margin-top:16px">
                    Ja tem conta? <a href="<?php echo $e($bp); ?>/login">Faca login</a>.
                </p>
            </div>
        </div>
    </section>

    <section class="gf-section" id="faq">
        <div class="gf-container">
            <div class="gf-eyebrow">PERGUNTAS FREQUENTES</div>
            <h2 class="gf-section-title">Antes de pegar a estrada.</h2>
            <p class="gf-section-description">Tudo o que voce precisa saber sobre a parceria GuinchaFacil.</p>

            <div class="gf-faq">
                <?php foreach ($faqs as $i => $faq): ?>
                    <details class="gf-faq__item"<?php echo $i === 0 ? ' open' : ''; ?>>
                        <summary class="gf-faq__question"><?php echo $e($faq[0]); ?></summary>
                        <div class="gf-faq__answer"><p><?php echo $e($faq[1]); ?></p></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<script>
(function(){
    var rAt = document.getElementById('sim-atend-range');
    var rDi = document.getElementById('sim-dias-range');
    var lAt = document.getElementById('sim-atend');
    var lDi = document.getElementById('sim-dias');
    var lTo = document.getElementById('sim-total');
    if (!rAt || !rDi || !lTo) return;
    function fmtBR(n){ return n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
    function recalc(){
        var a = parseInt(rAt.value, 10) || 1;
        var d = parseInt(rDi.value, 10) || 1;
        lAt.textContent = String(a);
        lDi.textContent = String(d);
        lTo.textContent = 'R$ ' + fmtBR(a * d * 180);
    }
    rAt.addEventListener('input', recalc);
    rDi.addEventListener('input', recalc);
    recalc();
})();
</script>

<?php require __DIR__ . '/_footer-public.php'; ?>
'@

$guinchosContent = $guinchosContent -replace "`r`n", "`n"
Save-PhpFile $guinchosView $guinchosContent "parceiros/guinchos.php" | Out-Null

# ===========================================================================
# 2. oficinas.php
# ===========================================================================
Write-Host ""
Write-Step "=== 2. Reescrevendo parceiros/oficinas.php ==="

$oficinasContent = @'
<?php
/**
 * Parceiros - Oficinas (Onda 5.2 refinada)
 * Hero real + formulario 3 campos + Google OAuth + FAQ accordion + JSON-LD
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e  = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$csrf_token_view = isset($_SESSION['_csrf_token']) ? (string)$_SESSION['_csrf_token'] : '';

$url = 'https://guinchafacil.com.br/parceiros/oficinas';
$titulo = 'Seja oficina parceira - Receba clientes qualificados';
$descricao = 'Cadastro gratuito para oficinas. Receba encaminhamentos de veiculos, envie orcamento pelo painel e receba via PIX em D+7.';

$faqs = [
    ['Como a minha oficina recebe clientes pela GuinchaFacil?', 'Motoristas que precisam de reparos sao encaminhados para oficinas parceiras da regiao. Voce recebe apenas chamados compativeis com sua especialidade e capacidade, sem precisar investir em anuncios.'],
    ['Preciso pagar para participar do programa?', 'Nao. O cadastro e 100% gratuito. Nao ha taxa de adesao nem mensalidade. Voce so ganha quando o servico e aprovado e concluido. Vagas limitadas por regiao.'],
    ['Como funciona o orcamento para o cliente?', 'Voce envia o orcamento pelo painel e o cliente aprova ou recusa de forma simples. Sem burocracia, sem negociacao por telefone. Tudo registrado na plataforma.'],
    ['Como recebo pelos servicos prestados?', 'O repasse e feito via PIX em D+7 apos a conclusao, ja com as taxas da plataforma deduzidas. Nossos parceiros recebem com regularidade e transparencia.'],
    ['E se o cliente nao aprovar o orcamento?', 'Sem penalidade. Voce so trabalha quando o cliente aprova. A plataforma nao cobra nada por orcamentos recusados.'],
    ['Posso recusar um encaminhamento de servico?', 'Sim. Voce tem total autonomia para aceitar apenas os servicos que desejar, de acordo com sua agenda e especialidade.'],
    ['Preciso ter CNPJ para ser oficina parceira?', 'Nao e obrigatorio. Aceitamos MEI, autonomos e empresas. Nosso objetivo e facilitar sua entrada na rede.'],
    ['Como funciona a concorrencia com outras oficinas?', 'Trabalhamos com um numero limitado de oficinas por regiao para garantir que todos os parceiros tenham boas oportunidades. Cadastre-se agora para garantir sua vaga.'],
    ['Como funciona a fatura semanal?', 'Quando aplicavel, a comissao e faturada via PIX no ciclo informado. Tudo transparente e registrado no painel, sem surpresas.'],
    ['Como entro em contato com o suporte?', 'Voce tem suporte humano e dedicado, com canais diretos no painel para resolver duvidas sobre orcamentos, pagamentos e operacao. Estamos aqui para ajudar seu negocio a crescer.'],
];

$meta_title = $titulo;
$meta_description = $descricao;
$meta_canonical = $url;
$meta_og_image = 'https://guinchafacil.com.br/public/assets/img/landing/hero-parceiros-oficinas.jpg';
$body_class = 'gf-landing';
$nav_atual = 'parceiros';

$meta_json_ld = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $titulo,
        'url' => $url,
        'description' => $descricao,
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(
            static fn(array $f): array => [
                '@type' => 'Question',
                'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ],
            $faqs
        ),
    ],
];

require __DIR__ . '/_header-public.php';
?>

<main>
    <section class="gf-hero gf-hero--parceiros" aria-labelledby="hero-oficinas">
        <img src="<?php echo $e($bp); ?>/public/assets/img/landing/hero-parceiros-oficinas.jpg"
             alt="Mecanico profissional trabalhando em oficina moderna no Brasil"
             width="1920" height="1080" class="gf-hero__photo" loading="eager">
        <div class="gf-container">
            <div class="gf-hero__content">
                <div class="gf-hero__eyebrow">
                    <span class="gf-status-dot"></span> OFICINAS PARCEIRAS
                </div>
                <h1 id="hero-oficinas">Encha sua agenda<br>com <em>clientes.</em></h1>
                <p>Receba encaminhamentos de veiculos que precisam dos seus servicos. Cadastro gratuito, sem mensalidade, com repasse via PIX.</p>
                <div class="gf-hero__actions">
                    <a class="gf-btn gf-btn--primary gf-btn--lg" href="#quero-participar">
                        Quero fazer parte &nearr;
                    </a>
                    <a class="gf-btn gf-btn--outline gf-btn--lg" href="#faq">
                        Tirar duvidas
                    </a>
                </div>
                <div class="gf-hero__note">
                    <i class="fas fa-shield-halved"></i> Orcamento pelo painel. Cliente aprova antes. Sem surpresas.
                </div>
            </div>
        </div>
    </section>

    <section class="gf-section" id="credenciamento">
        <div class="gf-container">
            <div class="gf-eyebrow">CREDENCIAMENTO</div>
            <h2 class="gf-section-title">Um lugar na rede.<br>Mais caminhos para crescer.</h2>
            <p class="gf-section-description">O modelo valoriza estrutura, capacidade tecnica e transparencia no atendimento ao motorista.</p>
            <ul class="gf-checklist">
                <li><i class="fas fa-check"></i> <span><strong>01</strong> Cadastro e documentacao do negocio</span></li>
                <li><i class="fas fa-check"></i> <span><strong>02</strong> Analise das especialidades e area de atuacao</span></li>
                <li><i class="fas fa-check"></i> <span><strong>03</strong> Validacao das condicoes de atendimento</span></li>
                <li><i class="fas fa-check"></i> <span><strong>04</strong> Aceite comercial e ativacao na rede</span></li>
            </ul>
        </div>
    </section>

    <section class="gf-section gf-section--alt" id="quero-participar">
        <div class="gf-container">
            <div class="gf-form-panel">
                <div class="gf-eyebrow">CADASTRO RAPIDO</div>
                <h2 class="gf-section-title">Crie sua conta em 30 segundos.</h2>
                <p class="gf-section-description">Informe nome, WhatsApp e e-mail. Depois voce completa CNPJ, razao social e endereco no painel.</p>

                <form method="POST" action="<?php echo $e($bp); ?>/registro/simples/oficina" class="gf-fields-stack">
                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrf_token_view); ?>">
                    <input type="hidden" name="tipo" value="oficina">

                    <div class="gf-field">
                        <label class="gf-field-label" for="o_nome">Seu nome completo *</label>
                        <input class="gf-field-control" type="text" id="o_nome" name="nome" required minlength="3" maxlength="120" autocomplete="name" placeholder="Como podemos te chamar?">
                    </div>
                    <div class="gf-field">
                        <label class="gf-field-label" for="o_tel">WhatsApp *</label>
                        <input class="gf-field-control" type="tel" id="o_tel" name="telefone" required inputmode="tel" autocomplete="tel" placeholder="(21) 99999-9999">
                    </div>
                    <div class="gf-field">
                        <label class="gf-field-label" for="o_email">E-mail *</label>
                        <input class="gf-field-control" type="email" id="o_email" name="email" required autocomplete="email" placeholder="voce@oficina.com.br">
                    </div>

                    <button class="gf-btn gf-btn--primary gf-btn--lg" type="submit" style="width:100%">
                        Criar minha conta gratis &rarr;
                    </button>
                </form>

                <div class="gf-form-divider"><span>ou</span></div>

                <a class="gf-btn gf-btn--outline gf-btn--lg" style="width:100%" href="<?php echo $e($bp); ?>/auth/google?retorno=/perfil/completar">
                    <i class="fab fa-google" aria-hidden="true"></i> Continuar com Google
                </a>

                <p class="gf-form-note" style="justify-content:center;margin-top:16px">
                    Ja tem conta? <a href="<?php echo $e($bp); ?>/login">Faca login</a>.
                </p>
            </div>
        </div>
    </section>

    <section class="gf-section">
        <div class="gf-container">
            <div class="gf-eyebrow">DA CHEGADA AO FATURAMENTO</div>
            <h2 class="gf-section-title">Cada atendimento, bem organizado.</h2>
            <div class="gf-feature-grid" style="margin-top:32px">
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-wrench"></i></div>
                    <h3>Receba e avalie</h3>
                    <p>Veiculos encaminhados conforme sua localizacao e especialidade.</p>
                </article>
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-clipboard-check"></i></div>
                    <h3>Orcamento aprovado</h3>
                    <p>Envie o orcamento pelo painel. O cliente aprova com um clique.</p>
                </article>
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-receipt"></i></div>
                    <h3>Fature com clareza</h3>
                    <p>Repasse via PIX em D+7, com toda a documentacao registrada.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="gf-section" id="faq">
        <div class="gf-container">
            <div class="gf-eyebrow">PERGUNTAS FREQUENTES</div>
            <h2 class="gf-section-title">Antes de se credenciar.</h2>
            <p class="gf-section-description">Tudo o que voce precisa saber sobre a parceria GuinchaFacil.</p>

            <div class="gf-faq">
                <?php foreach ($faqs as $i => $faq): ?>
                    <details class="gf-faq__item"<?php echo $i === 0 ? ' open' : ''; ?>>
                        <summary class="gf-faq__question"><?php echo $e($faq[0]); ?></summary>
                        <div class="gf-faq__answer"><p><?php echo $e($faq[1]); ?></p></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/_footer-public.php'; ?>
'@

$oficinasContent = $oficinasContent -replace "`r`n", "`n"
Save-PhpFile $oficinasView $oficinasContent "parceiros/oficinas.php" | Out-Null

# ===========================================================================
# 3. CSS adicional (gf-layout.css)
# ===========================================================================
Write-Host ""
Write-Step "=== 3. Anexando CSS no gf-layout.css ==="

$cssContent = New-Object System.Text.UTF8Encoding($false)
$cssCurrent = $cssContent.GetString([System.IO.File]::ReadAllBytes($cssLayout))

if ($cssCurrent.Contains('.gf-faq__item')) {
    Write-Skip "CSS do FAQ ja presente"
} else {
    $cssAppend = @'

/* Onda 5.2 — Hero parceiros + simulador + FAQ accordion + form simplificado */
.gf-hero--parceiros .gf-hero__photo {
    object-position: center right;
}
.gf-hero--parceiros .gf-hero__content {
    max-width: 620px;
}
.gf-hero--parceiros h1 {
    font-size: clamp(2rem, 4.5vw, 3.25rem);
}

.gf-simulator {
    display: grid;
    gap: 20px;
    margin-top: 32px;
    padding: 28px;
    background: var(--gf-branco);
    border: 1px solid var(--gf-borda-light);
    border-radius: var(--gf-radius-lg);
    box-shadow: var(--gf-shadow);
    max-width: 620px;
}
.gf-simulator__field {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.gf-simulator__range {
    width: 100%;
    height: 6px;
    accent-color: var(--gf-verde);
    cursor: pointer;
}
.gf-price-result {
    background: var(--gf-verde-light);
    border-radius: var(--gf-radius);
    padding: 20px 22px;
    margin-top: 8px;
}
.gf-price-result__eyebrow {
    display: block;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--gf-verde-dark);
    margin-bottom: 6px;
}
.gf-price-result__value {
    display: block;
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    font-weight: 800;
    color: var(--gf-verde-dark);
    line-height: 1.1;
    margin-bottom: 10px;
}

.gf-checklist {
    list-style: none;
    padding: 0;
    margin: 32px 0 0;
    display: grid;
    gap: 14px;
    max-width: 720px;
}
.gf-checklist li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: .95rem;
    color: var(--gf-cinza-700);
}
.gf-checklist li i {
    color: var(--gf-verde);
    font-size: .9rem;
    margin-top: 4px;
    flex-shrink: 0;
}
.gf-checklist li strong {
    display: inline-block;
    min-width: 32px;
    color: var(--gf-verde-dark);
    font-weight: 800;
}

.gf-form-divider {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 20px 0;
    color: var(--gf-cinza-500);
    font-size: .72rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    font-weight: 700;
}
.gf-form-divider::before,
.gf-form-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--gf-borda-light);
}

.gf-faq {
    margin-top: 32px;
    max-width: 820px;
}
.gf-faq__item {
    border-bottom: 1px solid var(--gf-borda-light);
    padding: 4px 0;
}
.gf-faq__item[open] {
    padding-bottom: 16px;
}
.gf-faq__question {
    list-style: none;
    cursor: pointer;
    padding: 18px 0;
    font-weight: 700;
    font-size: 1rem;
    color: var(--gf-preto);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    transition: color .15s;
    line-height: 1.4;
}
.gf-faq__question::-webkit-details-marker { display: none; }
.gf-faq__question:hover { color: var(--gf-verde-dark); }
.gf-faq__question::after {
    content: '+';
    font-size: 1.5rem;
    font-weight: 300;
    color: var(--gf-verde);
    transition: transform .2s;
    flex-shrink: 0;
    line-height: 1;
}
.gf-faq__item[open] .gf-faq__question::after {
    content: '\2212';
}
.gf-faq__answer {
    padding: 0 0 8px;
    color: var(--gf-cinza-700);
    line-height: 1.65;
    font-size: .95rem;
    max-width: 720px;
}
.gf-faq__answer p { margin: 0; }

.gf-fields-stack {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
'@
    $cssAppend = $cssAppend -replace "`r`n", "`n"
    $cssNew = $cssCurrent + $cssAppend
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    Copy-Item -LiteralPath $cssLayout -Destination "$cssLayout.bak-$ts" -Force
    Write-Step "Backup: $cssLayout.bak-$ts"
    [System.IO.File]::WriteAllBytes($cssLayout, $cssContent.GetBytes($cssNew))
    Write-Ok "gf-layout.css atualizado"
}

# ===========================================================================
# Sumario
# ===========================================================================
Write-Host ""
Write-Ok "Onda 5.2 refinada concluida."
Write-Host ""
Write-Host "PROXIMOS PASSOS MANUAIS:" -ForegroundColor Yellow
Write-Host "  1) Confirmar que as imagens estao em:" -ForegroundColor Yellow
Write-Host "       public/assets/img/landing/hero-parceiros-guinchos.jpg" -ForegroundColor Gray
Write-Host "       public/assets/img/landing/hero-parceiros-oficinas.jpg" -ForegroundColor Gray
Write-Host "  2) O formulario POSTA para /registro/simples/{tipo}." -ForegroundColor Yellow
Write-Host "     Se a rota nao existir no index.php, adicionar:" -ForegroundColor Yellow
Write-Host "       GET  /registro/simples/guincho -> AuthController::registroGuinchoForm" -ForegroundColor Gray
Write-Host "       GET  /registro/simples/oficina -> AuthController::registroOficinaForm" -ForegroundColor Gray
Write-Host "       POST /registro/simples/{tipo}  -> AuthController::registroSimples" -ForegroundColor Gray
Write-Host "  3) O CSRF vem de \$_SESSION['_csrf_token'] (BaseController ja popula)." -ForegroundColor Yellow
Write-Host "  4) Reverter: cada arquivo tem .bak-<timestamp> ao lado." -ForegroundColor Yellow
exit 0