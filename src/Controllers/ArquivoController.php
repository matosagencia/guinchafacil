<?php
// File: guinchafacil/src/Controllers/ArquivoController.php
// §SEC-UPL-02: download autenticado de documentos — nunca expõe caminho real de uploads

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Guincho.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/PedidoEvidencia.php';
require_once __DIR__ . '/../Services/Evidence/EvidenceService.php';

class ArquivoController extends BaseController
{
    public function __construct() { parent::__construct(); }

    /**
     * GET /arquivo/{id}
     * Serve documento de guincho com verificação de permissão.
     * Admin acessa qualquer documento; guincho só os seus.
     */
    public function servir(int $guinchoId): void
    {
        $usuario = AuthService::requireAuth(null); // qualquer perfil autenticado

        $tipo = $_GET['tipo'] ?? '';
        $tiposPermitidos = ['doc_cnh_frente', 'doc_cnh_verso', 'foto_veiculo'];
        if (!in_array($tipo, $tiposPermitidos, true)) {
            http_response_code(400);
            echo '<h1>400 — Tipo de documento inválido</h1>';
            exit;
        }

        $guincho = Guincho::buscarPorId($guinchoId);
        if (!$guincho) {
            http_response_code(404);
            echo '<h1>404 — Guincho não encontrado</h1>';
            exit;
        }

        // Guincho só acessa os seus próprios documentos
        if ($usuario['tipo'] !== 'admin' && (int)$guincho['usuario_id'] !== (int)$usuario['id']) {
            http_response_code(403);
            echo '<h1>403 — Acesso negado</h1>';
            exit;
        }

        $campo    = $guincho[$tipo] ?? '';
        if (empty($campo)) {
            http_response_code(404);
            echo '<h1>404 — Documento não cadastrado</h1>';
            exit;
        }

        // Impede path traversal: aceita apenas nome de arquivo simples
        $nomeArquivo = basename($campo);
        if ($nomeArquivo !== $campo || strpos($nomeArquivo, '..') !== false) {
            http_response_code(400);
            echo '<h1>400 — Nome de arquivo inválido</h1>';
            exit;
        }

        // §SEC-UPL-02: documentos de identidade (CNH/foto do veículo) ficam
        // em UPLOAD_PATH_DOCS, fora do webroot — não em UPLOAD_PATH (que é
        // público, usado só para imagens de exibição livre como
        // foto_caminhao/comunicados). Fallback pro caminho antigo cobre
        // arquivos enviados antes desta correção.
        $baseDoc = defined('UPLOAD_PATH_DOCS') ? UPLOAD_PATH_DOCS : UPLOAD_PATH;
        $caminhoAbsoluto = rtrim($baseDoc, '/\\') . DIRECTORY_SEPARATOR . $nomeArquivo;
        if (!is_file($caminhoAbsoluto)) {
            $caminhoAbsoluto = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . $nomeArquivo;
        }

        if (!is_file($caminhoAbsoluto)) {
            http_response_code(404);
            echo '<h1>404 — Arquivo não encontrado</h1>';
            exit;
        }

        // Valida MIME real (§5.3)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($caminhoAbsoluto);
        $mimesOk  = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $mimesOk, true)) {
            http_response_code(415);
            echo '<h1>415 — Tipo de arquivo não suportado</h1>';
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($nomeArquivo) . '"');
        header('Content-Length: ' . filesize($caminhoAbsoluto));
        header('X-Content-Type-Options: nosniff');
        readfile($caminhoAbsoluto);
        exit;
    }

    /**
     * GET /evidencia/{id}
     * Pacote L1.5 — serve foto de evidência (coleta/entrega) SOMENTE para:
     * - o guincho dono da evidência;
     * - o cliente dono do pedido correspondente;
     * - admin.
     * Arquivo mora fora do webroot (storage/private/evidencias), então este
     * é o único caminho de acesso possível.
     */
    public function servirEvidencia(int $evidenciaId): void
    {
        $usuario = AuthService::requireAuth(null);

        $evidencia = PedidoEvidencia::buscarPorId($evidenciaId);
        if (!$evidencia) {
            http_response_code(404);
            echo '<h1>404 — Evidência não encontrada</h1>';
            exit;
        }

        $pedido = Pedido::buscarPorId((int)$evidencia['pedido_id']);
        if (!$pedido) {
            http_response_code(404);
            echo '<h1>404 — Pedido não encontrado</h1>';
            exit;
        }

        $ehAdmin = $usuario['tipo'] === 'admin';
        $ehClienteDono = $usuario['tipo'] === 'cliente' && (int)($pedido['cliente_id'] ?? 0) === (int)$usuario['id'];

        // guinchos.id normalmente difere de usuarios.id — revalida contra a
        // tabela guinchos em vez de confiar em qualquer id solto de sessão.
        $ehGuinchoDono = false;
        if ($usuario['tipo'] === 'guincho') {
            $guinchoSessao = Guincho::buscarPorUsuario((int)$usuario['id']);
            $ehGuinchoDono = $guinchoSessao && (int)($pedido['guincho_id'] ?? 0) === (int)$guinchoSessao['id'];
        }

        if (!$ehAdmin && !$ehGuinchoDono && !$ehClienteDono) {
            http_response_code(403);
            echo '<h1>403 — Acesso negado</h1>';
            exit;
        }

        $nomeArquivo = basename((string)$evidencia['stored_name']);
        if ($nomeArquivo !== $evidencia['stored_name'] || strpos($nomeArquivo, '..') !== false) {
            http_response_code(400);
            echo '<h1>400 — Nome de arquivo inválido</h1>';
            exit;
        }

        $caminhoAbsoluto = rtrim(EvidenceService::privateStorageDir(), '/\\') . DIRECTORY_SEPARATOR . $nomeArquivo;
        if (!is_file($caminhoAbsoluto)) {
            http_response_code(404);
            echo '<h1>404 — Arquivo não encontrado</h1>';
            exit;
        }

        header('Content-Type: ' . (string)$evidencia['mime_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode($nomeArquivo) . '"');
        header('Content-Length: ' . filesize($caminhoAbsoluto));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($caminhoAbsoluto);
        exit;
    }

    public function servirEvidenciaEspecialista(int $eventoId): void
    {
        $usuario = AuthService::requireAuth(null);
        $st = getPDO()->prepare("SELECT ev.*, i.cliente_id, e.especialista_id, e.usuario_id FROM atendimento_eventos ev JOIN incidentes i ON i.id=ev.incidente_id LEFT JOIN atendimentos_especialista a ON a.id=ev.atendimento_id LEFT JOIN especialistas e ON e.id=a.especialista_id WHERE ev.id=? AND ev.atendimento_tipo='especialista'");
        $st->execute([$eventoId]); $ev = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ev) { http_response_code(404); exit('Evidência não encontrada'); }
        $ok = $usuario['tipo']==='admin' || ($usuario['tipo']==='cliente' && (int)$usuario['id']===(int)$ev['cliente_id']) || ($usuario['tipo']==='especialista' && (int)$usuario['id']===(int)$ev['usuario_id']);
        if (!$ok) { http_response_code(403); exit('Acesso negado'); }
        $meta = json_decode((string)$ev['metadata_json'], true) ?: []; $name = basename((string)($meta['arquivo'] ?? ''));
        $path = (defined('UPLOAD_PATH_DOCS') ? UPLOAD_PATH_DOCS : dirname(PUBLIC_PATH).'/storage/private/uploads') . DIRECTORY_SEPARATOR . 'especialistas' . DIRECTORY_SEPARATOR . $name;
        if (!$name || !is_file($path)) { http_response_code(404); exit('Arquivo não encontrado'); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path); if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) { http_response_code(415); exit; }
        header('Content-Type: '.$mime); header('Content-Disposition: inline; filename="'.rawurlencode($name).'"'); header('Cache-Control: private, no-store'); readfile($path); exit;
    }

    public function servirDocumentoEspecialista(int $documentoId): void
    {
        $usuario = AuthService::requireAuth(null);
        if (($usuario['tipo'] ?? '') !== 'admin') { http_response_code(403); exit('Acesso negado'); }
        $st = getPDO()->prepare('SELECT arquivo FROM especialista_documentos WHERE id=?');
        $st->execute([$documentoId]); $nome = basename((string)$st->fetchColumn());
        $path = (defined('UPLOAD_PATH_DOCS') ? UPLOAD_PATH_DOCS : dirname(PUBLIC_PATH).'/storage/private/uploads') . DIRECTORY_SEPARATOR . 'especialistas' . DIRECTORY_SEPARATOR . $nome;
        if (!$nome || !is_file($path)) { http_response_code(404); exit('Arquivo não encontrado'); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { http_response_code(415); exit; }
        header('Content-Type: '.$mime); header('Content-Disposition: inline; filename="'.rawurlencode($nome).'"'); header('Cache-Control: private, no-store'); readfile($path); exit;
    }
}
