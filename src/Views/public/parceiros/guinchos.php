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

require __DIR__ . '/../_header-public.php';
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

<?php require __DIR__ . '/../_footer-public.php'; ?>