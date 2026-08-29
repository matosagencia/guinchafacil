<?php
require_once __DIR__ . '/../../config.php';

class MediaUploadService
{
    public static function storeCommunicationImage(array $file, string $prefix = 'comunicado'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload.');
        }
        if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new RuntimeException('Arquivo acima do limite.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Formato inválido.');
        }
        $img = @getimagesize($tmp);
        if (!$img) {
            throw new RuntimeException('Imagem inválida.');
        }
        $dir = __DIR__ . '/../../public/uploads/comunicados/' . date('Y/m');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Não foi possível mover a imagem.');
        }
        return '/public/uploads/comunicados/' . date('Y/m') . '/' . $name;
    }

    /**
     * §CATALOGO-VISUAL-01: logo de marca de veículo — imagem PÚBLICA (mesmo
     * espírito de `foto_caminhao`, não documento privado), servida direto
     * por URL sem controller de download. Mesma validação rigorosa (mime
     * real + getimagesize) já usada em storeCommunicationImage().
     */
    public static function storeVehicleBrandLogo(array $file, string $prefix = 'marca'): string
    {
        return self::storeVehicleImage($file, 'marcas', $prefix, 1 * 1024 * 1024);
    }

    /** §CATALOGO-VISUAL-01: imagem de modelo de veículo (foto ilustrativa). */
    public static function storeVehicleModelImage(array $file, string $prefix = 'modelo'): string
    {
        return self::storeVehicleImage($file, 'modelos', $prefix, 3 * 1024 * 1024);
    }

    private static function storeVehicleImage(array $file, string $subpasta, string $prefix, int $tamanhoMaximo): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload.');
        }
        if (($file['size'] ?? 0) > $tamanhoMaximo) {
            throw new RuntimeException('Arquivo acima do limite.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Formato inválido.');
        }
        $img = @getimagesize($tmp);
        if (!$img) {
            throw new RuntimeException('Imagem inválida.');
        }
        $dir = __DIR__ . '/../../public/uploads/veiculos/' . $subpasta;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Não foi possível mover a imagem.');
        }
        return '/public/uploads/veiculos/' . $subpasta . '/' . $name;
    }
}
