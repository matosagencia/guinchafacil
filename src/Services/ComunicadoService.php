<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/Comunicado.php';

class ComunicadoService
{
    public const PLACEMENT_CLIENT_DASHBOARD_TOP = 'cliente_dashboard_top';
    public const PLACEMENT_TOW_DASHBOARD_AFTER_STATS = 'guincho_dashboard_after_stats';

    public static function validatePayload(array $input): array
    {
        $titulo = trim((string)($input['titulo'] ?? ''));
        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
            throw new RuntimeException('Título inválido.');
        }

        $publico = (string)($input['publico'] ?? 'ambos');
        if (!in_array($publico, ['cliente', 'guincho', 'ambos'], true)) {
            throw new RuntimeException('Público inválido.');
        }

        $placement = (string)($input['placement'] ?? '');
        if (!in_array($placement, [self::PLACEMENT_CLIENT_DASHBOARD_TOP, self::PLACEMENT_TOW_DASHBOARD_AFTER_STATS], true)) {
            throw new RuntimeException('Placement inválido.');
        }

        $inicio = trim((string)($input['inicio_em'] ?? ''));
        $fim = trim((string)($input['fim_em'] ?? ''));
        if ($inicio !== '' && $fim !== '' && strtotime($fim) !== false && strtotime($inicio) !== false && strtotime($fim) <= strtotime($inicio)) {
            throw new RuntimeException('Fim deve ser posterior ao início.');
        }

        $imagemDesktop = trim((string)($input['imagem_desktop'] ?? ''));
        if ($imagemDesktop === '') {
            throw new RuntimeException('Imagem desktop é obrigatória.');
        }

        return [
            'id' => (int)($input['id'] ?? 0),
            'titulo' => $titulo,
            'subtitulo' => trim((string)($input['subtitulo'] ?? '')) ?: null,
            'etiqueta' => trim((string)($input['etiqueta'] ?? '')) ?: null,
            'publico' => $publico,
            'placement' => $placement,
            'formato' => in_array(($input['formato'] ?? 'wide'), ['wide', 'card'], true) ? $input['formato'] : 'wide',
            'tema' => in_array(($input['tema'] ?? 'auto'), ['auto', 'light', 'dark', 'success', 'warning', 'info'], true) ? $input['tema'] : 'auto',
            'imagem_desktop' => $imagemDesktop,
            'imagem_mobile' => trim((string)($input['imagem_mobile'] ?? '')) ?: null,
            'imagem_alt' => trim((string)($input['imagem_alt'] ?? '')) ?: '',
            'object_position_x' => max(0, min(100, (int)($input['object_position_x'] ?? 50))),
            'object_position_y' => max(0, min(100, (int)($input['object_position_y'] ?? 50))),
            'cta_label' => trim((string)($input['cta_label'] ?? '')) ?: null,
            'cta_url' => self::sanitizeTargetUrl((string)($input['cta_url'] ?? '')) ?: null,
            'cta_target' => in_array(($input['cta_target'] ?? 'self'), ['self', 'blank'], true) ? $input['cta_target'] : 'self',
            'status' => in_array(($input['status'] ?? 'rascunho'), ['rascunho', 'publicado', 'pausado', 'arquivado'], true) ? $input['status'] : 'rascunho',
            'prioridade' => (int)($input['prioridade'] ?? 100),
            'inicio_em' => trim((string)($input['inicio_em'] ?? '')) ?: null,
            'fim_em' => trim((string)($input['fim_em'] ?? '')) ?: null,
            'duracao_slide_seg' => max(5, min(20, (int)($input['duracao_slide_seg'] ?? 8))),
            'frequencia' => in_array(($input['frequencia'] ?? 'sempre'), ['sempre', 'sessao', 'dia'], true) ? $input['frequencia'] : 'sempre',
            'dismissivel' => !empty($input['dismissivel']) ? 1 : 0,
            'dismiss_ttl_horas' => max(1, (int)($input['dismiss_ttl_horas'] ?? 24)),
        ];
    }

    public static function sanitizeTargetUrl(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        if (preg_match('~^https://~i', $url)) {
            return $url;
        }
        return '';
    }

    public static function resolveActiveForProfile(string $profile, string $placement, int $limit = 5): array
    {
        return array_map([self::class, 'toViewModel'], Comunicado::listActive($profile, $placement, $limit));
    }

    public static function toViewModel(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'titulo' => (string)($row['titulo'] ?? ''),
            'subtitulo' => (string)($row['subtitulo'] ?? ''),
            'etiqueta' => (string)($row['etiqueta'] ?? ''),
            'formato' => (string)($row['formato'] ?? 'wide'),
            'tema' => (string)($row['tema'] ?? 'auto'),
            'imagem_desktop' => (string)($row['imagem_desktop'] ?? ''),
            'imagem_mobile' => (string)($row['imagem_mobile'] ?? ''),
            'imagem_alt' => (string)($row['imagem_alt'] ?? ''),
            'cta_label' => (string)($row['cta_label'] ?? ''),
            'cta_url' => (string)($row['cta_url'] ?? ''),
            'cta_target' => (string)($row['cta_target'] ?? 'self'),
            'object_position_x' => (int)($row['object_position_x'] ?? 50),
            'object_position_y' => (int)($row['object_position_y'] ?? 50),
            'duracao_slide_seg' => (int)($row['duracao_slide_seg'] ?? 8),
        ];
    }
}
