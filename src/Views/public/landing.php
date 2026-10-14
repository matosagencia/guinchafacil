<?php
/**
 * Landing publica (home) - Onda 5.2.
 * Layout Lovable + SEO completo (og, twitter, canonical, JSON-LD).
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$nomeCidade = 'Niteroi';
$uf = 'RJ';
$url = 'https://guinchafacil.com.br/';
$titulo = 'Guincho e Reboque 24 Horas | Assistencia Veicular';
$descricao = 'Precisa de guincho 24 horas ou reboque? O GuinchaFacil conecta voce a assistencia veicular, mecanica, eletrica, bateria e pneus, com cotacao clara.';

$meta_title = $titulo;
$meta_description = $descricao;
$meta_canonical = $url;
$meta_og_image = 'https://guinchafacil.com.br/public/assets/img/landing/tow-hero.jpg';
$body_class = 'gf-landing';
$nav_atual = 'inicio';
$utility_cidade = 'Niteroi, RJ';
$utility_cidade_url = $bp . '/guincho/niteroi-rj';

$schemaBusiness = [
    '@context' => 'https://schema.org',
    '@type' => 'AutomotiveBusiness',
    'name' => 'GuinchaFacil',
    'url' => $url,
    'logo' => 'https://guinchafacil.com.br/public/assets/img/logo-48.png',
    'image' => 'https://guinchafacil.com.br/public/assets/img/landing/tow-hero.jpg',
    'description' => 'Assistencia veicular 24h: guincho, mecanica leve, autoeletrica, bateria e pneus.',
    'areaServed' => ['@type' => 'Country', 'name' => 'Brasil'],
    'availableLanguage' => ['@type' => 'Language', 'name' => 'Portugues'],
    'serviceType' => ['Guincho', 'Mecanica 24h', 'Autoeletrica', 'Troca de bateria', 'Troca de pneu', 'Pane seca'],
];

$meta_json_ld = [$schemaBusiness];

require __DIR__ . '/_header-public.php';
?>

<main>
    <section class="gf-hero" aria-labelledby="hero-title">
        <img src="<?php echo $e($bp); ?>/public/assets/img/landing/tow-hero.jpg"
             alt="Guincho verde transportando um carro na orla do Rio de Janeiro"
             width="1920" height="1024" class="gf-hero__photo">
        <div class="gf-container">
            <div class="gf-hero__content">
                <div class="gf-hero__eyebrow">
                    <span class="gf-status-dot"></span> PRONTOS PARA AJUDAR, 24 HORAS POR DIA
                </div>
                <h1 id="hero-title">O imprevisto para.<br>Seu caminho <em>nao.</em></h1>
                <p>Guincho e socorro rapido, sem complicacao.<br>Conte com a GuinchaFacil para seguir em frente, a qualquer hora.</p>
                <div class="gf-hero__actions">
                    <a class="gf-btn gf-btn--primary gf-btn--lg"
                       href="<?php echo $e($bp); ?>/pre-cotacao"
                       data-marketing-event="begin_quote">
                        <i class="fas fa-truck"></i> Preciso de um guincho &nearr;
                    </a>
                    <a class="gf-btn gf-btn--outline gf-btn--lg" href="<?php echo $e($bp); ?>/pre-cotacao">
                        Fazer uma cotacao &rarr;
                    </a>
                </div>
                <div class="gf-hero__note">
                    <i class="fas fa-shield-halved"></i> Sem assinatura. Sem burocracia. So quando voce precisa.
                </div>
            </div>
        </div>
    </section>

    <section class="gf-trust-strip" aria-label="Compromissos do atendimento">
        <div class="gf-container">
            <div class="gf-trust-grid">
                <div class="gf-trust-item">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <div><strong>Atendimento 24h</strong><span>De dia, de noite, sempre.</span></div>
                </div>
                <div class="gf-trust-item">
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    <div><strong>Quem esta perto</strong><span>Parceiros na sua regiao.</span></div>
                </div>
                <div class="gf-trust-item">
                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                    <div><strong>Preco transparente</strong><span>Saiba antes de confirmar.</span></div>
                </div>
                <div class="gf-trust-item">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <div><strong>Parceiros verificados</strong><span>Cuidado em cada atendimento.</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="gf-section">
        <div class="gf-container">
            <div class="gf-eyebrow">RAPIDO. SIMPLES. DO SEU JEITO.</div>
            <h2 class="gf-section-title">Um imprevisto?<br>A solucao comeca aqui.</h2>
            <p class="gf-section-description">Diga onde voce esta e o que aconteceu. Nos conectamos voce ao socorro certo, com uma estimativa antes de comecar.</p>
            <a class="gf-btn gf-btn--primary gf-btn--lg" href="<?php echo $e($bp); ?>/pre-cotacao" style="margin-top:24px">
                Ver minha estimativa &rarr;
            </a>
        </div>
    </section>

    <section class="gf-section gf-section--alt">
        <div class="gf-container">
            <div class="gf-eyebrow">MAIS DO QUE UM GUINCHO</div>
            <h2 class="gf-section-title">Tranquilidade em cada quilometro.</h2>
            <div class="gf-feature-grid" style="margin-top:32px">
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-bolt"></i></div>
                    <h3>Socorro sem enrolacao</h3>
                    <p>Do pedido ao atendimento, um caminho simples para resolver seu imprevisto.</p>
                </article>
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-shield-halved"></i></div>
                    <h3>Voce no controle</h3>
                    <p>Uma estimativa clara antes de decidir. Nada de surpresas no meio do caminho.</p>
                </article>
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-location-arrow"></i></div>
                    <h3>Gente da sua regiao</h3>
                    <p>Uma rede local de prestadores que conhece os caminhos e cuida do seu veiculo.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="gf-partner-band">
        <div class="gf-container">
            <div>
                <div class="gf-eyebrow" style="color:var(--gf-verde)">VAMOS MAIS LONGE, JUNTOS</div>
                <h2>Seu trabalho move a nossa rede.</h2>
                <p>Tem um guincho ou uma oficina? Conecte seu negocio a novas oportunidades com a GuinchaFacil.</p>
            </div>
            <a class="gf-btn gf-btn--primary gf-btn--lg" href="<?php echo $e($bp); ?>/parceiros">
                <i class="fas fa-wrench"></i> Quero ser parceiro &nearr;
            </a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/_footer-public.php'; ?>