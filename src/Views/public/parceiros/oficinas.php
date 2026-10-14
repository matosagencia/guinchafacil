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

require __DIR__ . '/../_header-public.php';
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

<?php require __DIR__ . '/../_footer-public.php'; ?>