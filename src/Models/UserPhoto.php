<?php

namespace App\Models;

use App\Database\Database;

/**
 * Model: UserPhoto
 * Gerencia fotos de perfil de usuários
 */
class UserPhoto
{
    private $db;
    private $uploadDir;
    private $maxFileSize = 5242880; // 5MB
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->uploadDir = __DIR__ . '/../../uploads/perfil';
        
        // Criar diretório se não existir
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Upload de foto
     */
    public function upload($usuario_id, $file)
    {
        // Validar arquivo
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Arquivo não foi enviado corretamente');
        }

        // Validar tamanho
        if ($file['size'] > $this->maxFileSize) {
            throw new \Exception('Arquivo muito grande. Máximo: 5MB');
        }

        // Validar tipo
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedTypes)) {
            throw new \Exception('Tipo de arquivo não permitido. Permitidos: ' . implode(', ', $this->allowedTypes));
        }

        // Validar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
        ];

        if (!in_array($mime, $allowedMimes)) {
            throw new \Exception('Tipo MIME inválido');
        }

        // Gerar nome único
        $filename = 'usuario_' . $usuario_id . '_' . time() . '.' . $ext;
        $filepath = $this->uploadDir . '/' . $filename;

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        // Obter dimensões da imagem
        $imageinfo = getimagesize($filepath);
        $width = $imageinfo[0] ?? null;
        $height = $imageinfo[1] ?? null;

        // Salvar no banco de dados
        $relativePath = 'uploads/perfil/' . $filename;
        return $this->saveToDatabase($usuario_id, $relativePath, $mime, $file['size'], $width, $height);
    }

    /**
     * Salvar informações no banco
     */
    private function saveToDatabase($usuario_id, $caminho, $mime, $tamanho, $width, $height)
    {
        // Remover foto anterior se existir
        $this->delete($usuario_id);

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO user_photos (usuario_id, caminho_arquivo, tipo_mime, tamanho_bytes, largura, altura, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        if ($stmt->execute([$usuario_id, $caminho, $mime, $tamanho, $width, $height])) {
            // Atualizar campo foto_perfil em usuarios
            $stmtUser = $conn->prepare("
                UPDATE usuarios SET foto_perfil = ? WHERE id = ?
            ");
            $stmtUser->execute([$caminho, $usuario_id]);

            return [
                'success' => true,
                'caminho' => $caminho,
                'tamanho' => $tamanho,
                'width' => $width,
                'height' => $height
            ];
        }

        return false;
    }

    /**
     * Obter foto do usuário
     */
    public function getByUserId($usuario_id)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT * FROM user_photos WHERE usuario_id = ?
        ");
        
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

    /**
     * Deletar foto
     */
    public function delete($usuario_id)
    {
        $photo = $this->getByUserId($usuario_id);

        if (!$photo) {
            return true;
        }

        // Remover arquivo físico
        $filepath = __DIR__ . '/../../' . $photo['caminho_arquivo'];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        // Remover do banco
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            DELETE FROM user_photos WHERE usuario_id = ?
        ");

        if ($stmt->execute([$usuario_id])) {
            // Limpar campo foto_perfil em usuarios
            $stmtUser = $conn->prepare("
                UPDATE usuarios SET foto_perfil = NULL WHERE id = ?
            ");
            $stmtUser->execute([$usuario_id]);

            return true;
        }

        return false;
    }

    /**
     * Obter URL da foto
     */
    public function getPhotoUrl($usuario_id)
    {
        $photo = $this->getByUserId($usuario_id);
        return $photo ? '/' . $photo['caminho_arquivo'] : null;
    }

    /**
     * Verificar se usuário tem foto
     */
    public function hasPhoto($usuario_id)
    {
        return $this->getByUserId($usuario_id) !== false;
    }

    /**
     * Obter foto padrão (gravatar fallback)
     */
    public static function getGravatarUrl($email, $size = 200)
    {
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
    }

    /**
     * Limpar fotos órfãs
     */
    public function cleanupOrphaned()
    {
        $conn = $this->db->getConnection();
        
        // Encontrar fotos cujo usuário não existe mais
        $stmt = $conn->prepare("
            SELECT up.* FROM user_photos up
            LEFT JOIN usuarios u ON up.usuario_id = u.id
            WHERE u.id IS NULL
        ");

        $stmt->execute();
        $orphaned = $stmt->fetchAll();

        foreach ($orphaned as $photo) {
            $filepath = __DIR__ . '/../../' . $photo['caminho_arquivo'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }

            $deleteStmt = $conn->prepare("DELETE FROM user_photos WHERE id = ?");
            $deleteStmt->execute([$photo['id']]);
        }

        return count($orphaned);
    }
}
