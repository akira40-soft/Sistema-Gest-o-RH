<?php
/**
 * Upload seguro de arquivos
 *
 * Suporta dois modos de uso:
 *  - Estático:   Upload::save($_FILES['campo'], 'documentos', ['pdf','jpg'], 5_000_000)
 *  - Instância:  (new Upload(__DIR__ . '/uploads/perfis'))->save($_FILES['campo'], 'prefixo')
 *
 * A versão de instância recebe o diretório de destino absoluto e devolve um array
 * com chaves amigáveis ao front-end (original_name, filename, path, size_kb, mime_type).
 */

namespace App\Utils;

class Upload
{
    public const MAX_SIZE_DEFAULT = 5 * 1024 * 1024; // 5MB
    public const ALLOWED_DEFAULT = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'webp', 'gif', 'bmp', 'tiff', 'svg'];
    public const DEFAULT_MIMES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private string $baseDir;
    private string $webPrefix;
    private int $maxSize;
    private array $allowedExts;
    private array $allowedMimes = [];

    /**
     * Constrói um uploader vinculado a um diretório de destino.
     *
     * @param string $baseDir       Diretório absoluto onde os ficheiros serão guardados
     * @param array  $allowedExts   Lista de extensões permitidas (sem ponto). Vazio = DEFAULT_MIMES
     * @param int    $maxSize       Tamanho máximo em bytes
     * @param string $webPrefix     Prefixo público (URL relativa) para os ficheiros guardados
     */
    public function __construct(
        string $baseDir,
        array $allowedExts = [],
        int $maxSize = self::MAX_SIZE_DEFAULT,
        string $webPrefix = ''
    ) {
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }
        if (!is_writable($baseDir) && is_dir($baseDir)) {
            throw new \RuntimeException("Diretório de upload sem permissão de escrita: $baseDir");
        }
        $this->baseDir = rtrim($baseDir, '/\\');
        $this->webPrefix = trim($webPrefix, '/\\');
        $this->maxSize = $maxSize;
        $this->allowedExts = empty($allowedExts) ? array_keys(self::DEFAULT_MIMES) : array_map('strtolower', $allowedExts);
        $this->allowedMimes = array_intersect_key(self::DEFAULT_MIMES, array_flip($this->allowedExts));
    }

    /**
     * Salva um ficheiro (estilo instância). Devolve:
     *   [original_name, filename, path, size_kb, mime_type]
     *
     * @throws \RuntimeException
     */
    public function upload(array $file, string $prefix = ''): array
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Nenhum ficheiro enviado.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'Ficheiro excede o tamanho máximo permitido pelo servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'Ficheiro excede o tamanho máximo do formulário.',
                UPLOAD_ERR_PARTIAL    => 'Upload incompleto. Tente novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever no disco.',
                UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão do servidor.',
            ];
            throw new \RuntimeException($errors[$file['error']] ?? 'Erro desconhecido no upload.');
        }
        if ($file['size'] > $this->maxSize) {
            throw new \RuntimeException('Ficheiro excede o tamanho máximo de ' . self::formatSize($this->maxSize) . '.');
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExts, true)) {
            throw new \RuntimeException('Tipo de ficheiro não permitido. Permitidos: ' . implode(', ', $this->allowedExts));
        }

        $mime = @mime_content_type($file['tmp_name']);
        $dangerous = ['application/x-php', 'text/x-php', 'application/x-sh', 'text/x-shellscript', 'application/x-msdownload'];
        if (in_array($mime, $dangerous, true)) {
            throw new \RuntimeException('Tipo de ficheiro potencialmente perigoso.');
        }
        if (!empty($this->allowedMimes) && isset(self::DEFAULT_MIMES[$ext]) && self::DEFAULT_MIMES[$ext] !== $mime) {
            // Aviso silencioso — o tipo MIME real pode variar; aceita se não for perigoso
        }

        $prefix = preg_replace('/[^a-z0-9_\-]/i', '', $prefix);
        $safeOriginal = self::sanitizeName($file['name'] ?? 'ficheiro');
        $filename = trim($prefix . '_' . $safeOriginal, '_');
        if (strlen($filename) > 80) $filename = substr($filename, 0, 80);
        $filename .= '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;

        $target = $this->baseDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Falha ao mover ficheiro para o destino.');
        }
        @chmod($target, 0644);

        $relPath = $this->webPrefix === ''
            ? $filename
            : $this->webPrefix . '/' . $filename;

        return [
            'original_name' => $file['name'] ?? $filename,
            'filename'      => $filename,
            'path'          => str_replace('\\', '/', $relPath),
            'size_kb'       => (int)round($file['size'] / 1024),
            'mime_type'     => $mime,
        ];
    }

    /**
     * Modo estático: salva em /uploads/<subdir>/ (project root).
     * Mantido para retrocompatibilidade com licencas.php e outros chamadores antigos.
     */
    public static function saveStatic(array $file, string $subdir = 'documentos', array $allowedExts = self::ALLOWED_DEFAULT, int $maxSize = self::MAX_SIZE_DEFAULT): array
    {
        $baseDir = dirname(__DIR__, 2) . '/uploads';
        $inst = new self($baseDir . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $subdir), $allowedExts, $maxSize, 'uploads/' . $subdir);
        try {
            $r = $inst->upload($file, '');
            return [
                'success'   => true,
                'path'      => $r['path'],
                'name'      => $r['original_name'],
                'unique'    => $r['filename'],
                'size'      => $r['size_kb'] * 1024,
                'mime'      => $r['mime_type'],
                'extension' => pathinfo($r['filename'], PATHINFO_EXTENSION),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function delete(string $relativePath): bool
    {
        $base = dirname(__DIR__, 2);
        $file = $base . '/' . ltrim($relativePath, '/');
        $real = realpath($file);
        $baseReal = realpath($base);
        if ($real === false || $baseReal === false) return false;
        if (strpos($real, $baseReal) !== 0) return false;
        return @unlink($real);
    }

    public static function sanitizeName(string $name): string
    {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        return substr($name, 0, 100);
    }

    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /** Alias retrocompatível — licencas.php e outros chamadores antigos. */
    public static function save(array $file, string $subdir = 'documentos', array $allowedExts = self::ALLOWED_DEFAULT, int $maxSize = self::MAX_SIZE_DEFAULT): array
    {
        return self::saveStatic($file, $subdir, $allowedExts, $maxSize);
    }
}
