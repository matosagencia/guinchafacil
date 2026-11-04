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
$titulo = 'Assistencia veicular 24 horas | GuinchaFacil';
$descricao = 'Encontre guincho, reboque e assistencia veicular perto de voce. Compare o atendimento, veja a estimativa e acompanhe seu pedido.';

$meta_title = $titulo;
$meta_description = $descricao;
$meta_canonical = $url;
$meta_og_image = 'https://guinchafacil.com.br/public/assets/img/landing/tow-hero.jpg';
$body_class = 'gf-landing';
$nav_atual = 'inicio';
$utility_cidade = 'Niteroi, RJ';
$utility_cidade_url = $bp . '/guincho/niteroi-rj';
$show_utility_cidade = false;

$schemaBusiness = [
    '@context' => 'https://schema.org',
    '@type' => 'AutomotiveBusiness',
    'name' => 'GuinchaFacil',
    'url' => $url,
    'logo' => 'https://guinchafacil.com.br/public/assets/img/logo-48.png',
    'image' => 'https://guinchafacil.com.br/public/assets/img/landing/tow-hero.jpg',
    'description' => 'Rede digital de assistencia veicular 24h com guincho, reboque, oficinas e servicos emergenciais.',
    'areaServed' => ['@type' => 'Country', 'name' => 'Brasil'],
    'availableLanguage' => ['@type' => 'Language', 'name' => 'Portugues'],
    'serviceType' => ['Guincho', 'Mecanica 24h', 'Autoeletrica', 'Troca de bateria', 'Troca de pneu', 'Pane seca'],
];

$faq = [
    ['q' => 'Como pedir assistencia veicular?', 'a' => 'Informe sua localizacao, conte o que aconteceu e veja as opcoes disponiveis antes de confirmar o atendimento.'],
    ['q' => 'O valor aparece antes do atendimento?', 'a' => 'O sistema calcula uma estimativa com base no servico, localizacao, destino e regras de precificacao aplicaveis.'],
    ['q' => 'Posso pagar na chegada?', 'a' => 'Quando essa opcao estiver disponivel para o atendimento, voce pode escolher pagar na chegada ou seguir pelo pagamento online.'],
    ['q' => 'E se nao houver parceiro perto de mim?', 'a' => 'O sistema amplia a busca conforme as regras de cobertura e pode encaminhar voce para atendimento alternativo quando a rede nao estiver disponivel.'],
];
$schemaFaq = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn(array $item): array => [
        '@type' => 'Question', 'name' => $item['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
    ], $faq),
];
$meta_json_ld = [$schemaBusiness, $schemaFaq];

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
                <h1 id="hero-title">Seu carro parou?<br>A ajuda certa <em>comeca aqui.</em></h1>
                <p>Encontre guincho, reboque ou assistencia veicular perto de voce, veja a estimativa antes de decidir e acompanhe o atendimento.</p>
                <div class="gf-hero__actions">
                    <a class="gf-btn gf-btn--primary gf-btn--lg"
                       href="<?php echo $e($bp); ?>/pre-cotacao"
                       data-marketing-event="begin_quote" data-marketing-label="hero_cliente">
                        <i class="fas fa-truck"></i> Preciso de ajuda agora &nearr;
                    </a>
                    <a class="gf-btn gf-btn--outline gf-btn--lg" href="<?php echo $e($bp); ?>/parceiros" data-marketing-event="partner_interest" data-marketing-label="hero_parceiro">
                        Quero ser parceiro &rarr;
                    </a>
                </div>
                <div class="gf-hero__note">
                    <i class="fas fa-shield-halved"></i> Sem assinatura. Voce decide antes de confirmar.
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
            <div class="gf-eyebrow">COMO FUNCIONA</div>
            <h2 class="gf-section-title">Do imprevisto ao atendimento<br>em um caminho claro.</h2>
            <p class="gf-section-description">Informe onde voce esta, conte o que aconteceu e veja as opcoes disponiveis para decidir com seguranca.</p>
            <a class="gf-btn gf-btn--primary gf-btn--lg" href="<?php echo $e($bp); ?>/pre-cotacao" style="margin-top:24px" data-marketing-event="begin_quote" data-marketing-label="how_it_works">
                Ver minha estimativa &rarr;
            </a>
        </div>
    </section>

    <section class="gf-section" aria-labelledby="services-title">
        <div class="gf-container">
            <div class="gf-eyebrow">SERVICOS DE ASSISTENCIA</div>
            <h2 class="gf-section-title" id="services-title">Uma rede para resolver o que aconteceu.</h2>
            <p class="gf-section-description">Escolha o tipo de ajuda ou comece pela cotacao. A disponibilidade depende da sua localizacao e dos parceiros ativos.</p>
            <div class="gf-feature-grid" style="margin-top:32px">
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-truck-pickup" aria-hidden="true"></i></div>
                    <h3>Guincho e reboque</h3>
                    <p>Transporte do seu veiculo para o destino informado, com rota e estimativa de valor.</p>
                    <a href="<?php echo $e($bp); ?>/pre-cotacao" data-marketing-event="service_view" data-marketing-label="guincho_reboque">Calcular atendimento &rarr;</a>
                </article>
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-wrench" aria-hidden="true"></i></div>
                    <h3>Oficina parceira</h3>
                    <p>Quando o veiculo precisa de reparo, a rede ajuda a encontrar uma oficina compativel.</p>
                    <a href="<?php echo $e($bp); ?>/pre-cotacao" data-marketing-event="service_view" data-marketing-label="oficina">Encontrar oficina &rarr;</a>
                </article>
                <article class="gf-feature-item">
                    <div class="gf-icon-tile"><i class="fas fa-bolt" aria-hidden="true"></i></div>
                    <h3>Bateria, pneu e pane</h3>
                    <p>Descreva o problema e receba orientacao sobre o proximo passo para o seu caso.</p>
                    <a href="<?php echo $e($bp); ?>/pre-cotacao" data-marketing-event="service_view" data-marketing-label="assistencia_emergencial">Pedir assistencia &rarr;</a>
                </article>
            </div>
        </div>
    </section>

    <section class="gf-section" aria-labelledby="price-title">
        <div class="gf-container">
            <div class="gf-eyebrow">COMO O PREÇO É CALCULADO</div>
            <h2 class="gf-section-title" id="price-title">Você entende a estimativa antes de confirmar.</h2>
            <p class="gf-section-description">A cotação considera o tipo de atendimento, sua localização, o destino quando necessário, a rota e a disponibilidade da rede. O valor é apresentado antes da confirmação.</p>
            <div class="gf-feature-grid" style="margin-top:32px">
                <article class="gf-feature-item"><div class="gf-icon-tile"><i class="fas fa-location-dot"></i></div><h3>Localização</h3><p>Encontramos a região de atendimento e os parceiros elegíveis.</p></article>
                <article class="gf-feature-item"><div class="gf-icon-tile"><i class="fas fa-route"></i></div><h3>Rota e destino</h3><p>Quando houver reboque, o destino entra no cálculo da rota e da estimativa.</p></article>
                <article class="gf-feature-item"><div class="gf-icon-tile"><i class="fas fa-scale-balanced"></i></div><h3>Disponibilidade real</h3><p>A busca considera cobertura e alternativas, sem prometer atendimento inexistente.</p></article>
            </div>
        </div>
    </section>

    <section class="gf-section gf-section--alt" aria-labelledby="cities-title">
        <div class="gf-container">
            <div class="gf-eyebrow">CIDADES ATENDIDAS</div>
            <h2 class="gf-section-title" id="cities-title">Começamos pela sua região.</h2>
            <p class="gf-section-description">Consulte a cobertura local e inicie uma cotação para verificar as opções disponíveis no momento.</p>
            <div class="gf-feature-grid" style="margin-top:28px">
                <a class="gf-feature-item" href="<?php echo $e($bp); ?>/guincho/niteroi-rj"><h3>Niterói - RJ</h3><p>Veja a página local e peça uma cotação.</p></a>
                <a class="gf-feature-item" href="<?php echo $e($bp); ?>/mapa-do-site"><h3>Mapa do site</h3><p>Explore serviços, cidades e páginas da rede.</p></a>
                <a class="gf-feature-item" href="<?php echo $e($bp); ?>/pre-cotacao"><h3>Consultar agora</h3><p>Informe o local para verificar as opções disponíveis.</p></a>
            </div>
        </div>
    </section>

    <section class="gf-section gf-section--alt" aria-labelledby="network-title">
        <div class="gf-container">
            <div class="gf-eyebrow">PARA ALEM DO GUINCHO</div>
            <h2 class="gf-section-title" id="network-title">A GuinchaFacil conecta quem precisa a quem pode resolver.</h2>
            <p class="gf-section-description">Guinchos e oficinas cuidam do atendimento. Empresas de seguro, frotas, estacionamentos e postos ajudam motoristas a encontrar a rede no momento certo.</p>
            <div class="gf-feature-grid" style="margin-top:32px">
                <article class="gf-feature-item"><div class="gf-icon-tile"><i class="fas fa-people-group" aria-hidden="true"></i></div><h3>Para guinchos e oficinas</h3><p>Receba oportunidades compativeis com sua regiao, capacidade e especialidade.</p><a href="<?php echo $e($bp); ?>/parceiros" data-marketing-event="partner_interest" data-marketing-label="operational_partner">Conhecer a rede &rarr;</a></article>
                <article class="gf-feature-item"><div class="gf-icon-tile"><i class="fas fa-building" aria-hidden="true"></i></div><h3>Para empresas parceiras</h3><p>Ofereca uma rota de assistencia aos seus clientes, colaboradores ou motoristas.</p><a href="<?php echo $e($bp); ?>/parceiros" data-marketing-event="partner_interest" data-marketing-label="distribution_partner">Falar sobre parceria &rarr;</a></article>
            </div>
        </div>
    </section>

    <section class="gf-partner-band">
        <div class="gf-container">
            <div>
                <div class="gf-eyebrow" style="color:var(--gf-verde)">VAMOS MAIS LONGE, JUNTOS</div>
                <h2>Seu trabalho move a nossa rede.</h2>
                <p>Tem um guincho, uma oficina ou uma empresa que atende motoristas? Conecte seu negocio a novas oportunidades com a GuinchaFacil.</p>
            </div>
            <a class="gf-btn gf-btn--primary gf-btn--lg" href="<?php echo $e($bp); ?>/parceiros" data-marketing-event="partner_interest" data-marketing-label="home_partner_band">
                <i class="fas fa-wrench"></i> Quero ser parceiro &nearr;
            </a>
        </div>
    </section>

    <section class="gf-section" aria-labelledby="faq-title">
        <div class="gf-container">
            <div class="gf-eyebrow">DUVIDAS FREQUENTES</div>
            <h2 class="gf-section-title" id="faq-title">Antes de pedir ajuda.</h2>
            <div class="gf-feature-grid" style="margin-top:28px">
                <?php foreach ($faq as $item): ?>
                    <article class="gf-feature-item">
                        <h3><?= $e($item['q']) ?></h3>
                        <p><?= $e($item['a']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="gf-partner-band gf-home-final-cta" aria-label="Começar atendimento">
        <div class="gf-container"><div><div class="gf-eyebrow" style="color:var(--gf-verde)">PRONTO PARA COMEÇAR?</div><h2>Seu carro parou? Calcule o atendimento.</h2><p>Informe o local, veja as opções e acompanhe o caminho até a solução.</p></div><a class="gf-btn gf-btn--primary gf-btn--lg" href="<?php echo $e($bp); ?>/pre-cotacao" data-marketing-event="begin_quote" data-marketing-label="home_final_cta"><i class="fas fa-truck"></i> Calcular atendimento &nearr;</a></div>
    </section>
</main>

<script src="<?php echo $e($bp); ?>/public/assets/js/first-party-marketing.js?v=20261103-1" defer></script>
<?php require __DIR__ . '/_footer-public.php'; ?>
