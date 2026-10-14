<?php
/**
 * Pre-cotacao publica - Onda 5.3 (funil V2 + Leaflet + flow JS).
 * Coluna unica, mapa full-width, painel flutuante, trust bar horizontal, WhatsApp float.
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e  = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = (string)$_SESSION['_csrf_token'];

$meta_title = 'Pre-cotacao de guincho';
$meta_description = 'Selecione sua localizacao, informe o imprevisto e veja a estimativa antes de criar sua conta.';
$meta_canonical = 'https://guinchafacil.com.br/pre-cotacao';
$body_class = 'gf-landing gf-precotacao-page';
$nav_atual = 'pre-cotacao';

$extra_css = [
    '/public/assets/vendor/leaflet/leaflet.css',
    '/public/assets/css/pages/precotacao-v2.css',
];

$meta_json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $meta_title,
    'url' => $meta_canonical,
    'description' => $meta_description,
];

require __DIR__ . '/_header-public.php';
?>

<main class="gf-precotacao">
    <section class="gf-section">
        <div class="gf-container">

            <div class="gf-precotacao__intro">
                <div class="gf-eyebrow">PRE-COTACAO</div>
                <h1 class="gf-section-title">Vamos colocar voce de volta no caminho.</h1>
                <p class="gf-section-description">Conte o que aconteceu. Em poucos passos, voce ve uma estimativa para decidir com tranquilidade.</p>
            </div>

            <?php require __DIR__ . '/partials/_precotacao_funil.php'; ?>

            <div class="preco-trust-bar" aria-label="Compromissos do atendimento">
                <span class="preco-trust-item">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    <strong>Nota media dos mecanicos:</strong> 4,8 de 5
                </span>
                <span class="preco-trust-item">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <strong>Tempo medio de resposta:</strong> 12 min
                </span>
                <span class="preco-trust-item">
                    <i class="fas fa-users" aria-hidden="true"></i>
                    <strong>Mais de 300</strong> atendimentos realizados
                </span>
            </div>

        </div>
    </section>
</main>

<a class="pc-whats-float" href="https://wa.me/5521999999999?text=Ola%2C%20preciso%20de%20ajuda%20com%20a%20pre-cotacao" target="_blank" rel="noopener">
    <i class="fab fa-whatsapp" aria-hidden="true"></i>
    <span class="pc-whats-label">
        <span class="pc-whats-eyebrow">Precisa de ajuda urgente?</span>
        Falar com alguem agora
    </span>
</a>

<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/public-pre-cotacao-flow.js"></script>

<?php require __DIR__ . '/_footer-public.php'; ?>