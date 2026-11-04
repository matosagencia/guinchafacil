<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$nomeCidade = (string)($cidade['nome'] ?? 'sua cidade');
$uf = strtoupper((string)($cidade['uf'] ?? ''));
$slugCidade = (string)($cidade['slug'] ?? '');
$heroCidade = $slugCidade === 'rio-de-janeiro-rj' ? 'hero-rio-de-janeiro-rj.png' : 'hero-niteroi-rj.png';
$titulo = 'Guincho e assistência veicular em ' . $nomeCidade . ($uf !== '' ? ' - ' . $uf : '');
$meta_title = $titulo . ' | GuinchaFácil';
$meta_description = 'Conte o que aconteceu, informe sua localização e veja as opções de guincho ou assistência veicular em ' . $nomeCidade . '.';
$meta_canonical = 'https://guinchafacil.com.br/guincho/' . rawurlencode($slugCidade);
$body_class = 'gf-landing gf-city-page';
$nav_atual = 'onde';
$show_utility_cidade = false;
$extra_css = ['/public/assets/css/marketing-pages.css'];
$meta_json_ld = ['@context'=>'https://schema.org','@type'=>'AutomotiveBusiness','name'=>'GuinchaFácil','url'=>$meta_canonical,'areaServed'=>['@type'=>'City','name'=>$nomeCidade,'address'=>['@type'=>'PostalAddress','addressLocality'=>$nomeCidade,'addressRegion'=>$uf,'addressCountry'=>'BR']],'description'=>$meta_description];
require __DIR__ . '/_header-public.php';
?>
<main>
    <section class="gf-city-hero" style="--gf-city-hero-image:url('<?= $e($bp) ?>/public/assets/img/cities/<?= $e($heroCidade) ?>')">
        <div class="gf-city-hero__wash"></div>
        <div class="gf-container gf-city-hero__inner">
            <div class="gf-city-hero__copy">
                <p class="gf-eyebrow">ASSISTÊNCIA VEICULAR LOCAL</p>
                <h1>Seu carro parou em <em><?= $e($nomeCidade) ?></em>?</h1>
                <p>Informe onde você está, conte o que aconteceu e veja a cotação antes de confirmar o atendimento.</p>
                <a class="gf-btn gf-btn--primary gf-btn--lg" href="<?= $e($bp) ?>/pre-cotacao?origem_seo=<?= rawurlencode($slugCidade) ?>">Calcular atendimento <span aria-hidden="true">&nearr;</span></a>
                <small>Sem cobrança nesta etapa. A cobertura é validada pela localização e pela rede disponível.</small>
            </div>
            <div class="gf-city-hero__card"><span class="gf-dot"></span><strong>Como funciona</strong><ol><li>Informe a localização.</li><li>Veja serviço, rota e valor estimado.</li><li>Acompanhe o atendimento até a solução.</li></ol></div>
        </div>
    </section>
    <section class="gf-section"><div class="gf-container"><p class="gf-eyebrow"><?= $e($nomeCidade) ?> EM FOCO</p><h2 class="gf-section-title">Uma jornada clara para resolver o imprevisto.</h2><div class="gf-feature-grid"><article class="gf-feature-item"><h3>Guincho e reboque</h3><p>Para transportar carro ou moto até o destino informado, quando essa opção estiver disponível.</p></article><article class="gf-feature-item"><h3>Assistência local</h3><p>Quando houver oficina elegível para o tipo de problema, o sistema compara essa alternativa.</p></article><article class="gf-feature-item"><h3>Fallback transparente</h3><p>Se não houver cobertura operacional, o atendimento segue pelo WhatsApp para tentativa manual.</p></article></div></div></section>
    <section class="gf-section gf-section--alt"><div class="gf-container"><div class="gf-city-grid"><div><p class="gf-eyebrow">MAPA E COBERTURA</p><h2 class="gf-section-title">A região é mapeada. A disponibilidade é real.</h2><p class="gf-section-description">A página local ajuda você a começar, mas não promete atendimento apenas por estar nesta cidade. A decisão usa parceiros aprovados, capacidade, distância, raio, rota e disponibilidade no momento da cotação.</p></div><div class="gf-city-proof"><strong><?= (int)count($zonasExibidas) ?></strong><span>áreas de referência cartográfica</span><small>O número não representa quantidade de parceiros nem garantia de atendimento.</small></div></div></div></section>
    <section class="gf-section"><div class="gf-container"><p class="gf-eyebrow">PRÓXIMO PASSO</p><h2 class="gf-section-title">Veja o que está disponível agora.</h2><p class="gf-section-description">A cotação leva poucos minutos e apresenta as condições antes do cadastro e do pagamento.</p><a class="gf-btn gf-btn--primary gf-btn--lg" href="<?= $e($bp) ?>/pre-cotacao?origem_seo=<?= rawurlencode($slugCidade) ?>">Começar minha cotação &nearr;</a></div></section>
</main>
<style>
.gf-city-hero{position:relative;min-height:620px;overflow:hidden;background:#09281d var(--gf-city-hero-image) center/cover no-repeat;color:#fff}.gf-city-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(5,24,17,.95),rgba(5,24,17,.67) 45%,rgba(5,24,17,.18))}.gf-city-hero__inner{position:relative;z-index:1;min-height:620px;display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,400px);gap:40px;align-items:center}.gf-city-hero__copy{max-width:700px}.gf-city-hero h1{font-size:clamp(2.8rem,6vw,5.6rem);line-height:1.02;color:#fff;margin:14px 0}.gf-city-hero h1 em{color:#8fe0a8;font-style:normal}.gf-city-hero p{max-width:620px;color:#e4f5e9;font-size:1.16rem;line-height:1.65}.gf-city-hero small{display:block;color:#c5dfcc;margin-top:14px}.gf-city-hero__card{padding:24px;border:1px solid rgba(255,255,255,.22);border-radius:22px;background:rgba(255,255,255,.1);backdrop-filter:blur(10px);color:#fff;box-shadow:0 20px 60px rgba(0,0,0,.16)}.gf-city-hero__card ol{margin:18px 0 0;padding-left:20px;color:#e4f5e9;line-height:2}.gf-city-grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:40px;align-items:center}.gf-city-proof{padding:28px;border-radius:22px;background:#fff;border:1px solid #dce8df;box-shadow:0 12px 30px rgba(15,54,30,.08)}.gf-city-proof strong{display:block;color:#0d5a2e;font-size:3rem}.gf-city-proof span,.gf-city-proof small{display:block;color:#52645a;line-height:1.5}.gf-city-proof small{margin-top:12px;font-size:.8rem}@media(max-width:760px){.gf-city-hero,.gf-city-hero__inner{min-height:720px}.gf-city-hero__inner{display:flex;flex-direction:column;align-items:stretch;justify-content:center;padding-top:40px;padding-bottom:40px}.gf-city-grid{grid-template-columns:1fr}.gf-city-hero__card{margin-top:8px}}
</style>
<?php require __DIR__ . '/_footer-public.php'; ?>
