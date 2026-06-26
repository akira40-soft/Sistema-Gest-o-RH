<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

if (!isset($_GET['file'])) {
    die("Arquivo não especificado.");
}

$caminhoRelativo = $_GET['file'];

// Segurança: Previne Path Traversal (evitar subir diretórios com ../)
// O caminho deve começar com 'uploads/' e não conter '..'
if (strpos($caminhoRelativo, 'uploads/') !== 0 || strpos($caminhoRelativo, '..') !== false) {
    die("Acesso inválido.");
}

$caminhoAbsoluto = __DIR__ . '/../' . $caminhoRelativo;

if (!file_exists($caminhoAbsoluto)) {
    die("Arquivo não encontrado.");
}

// Opcional: Verificar permissões no banco.
// Por enquanto, se está logado pode ver.
// Futuro: Verificar se user é dono ou admin/gestor.

// Detectar MIME type
$mime = mime_content_type($caminhoAbsoluto);

// Configurar headers para download/visualização
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($caminhoAbsoluto) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($caminhoAbsoluto));

// Limpar buffer de saída e enviar arquivo
ob_clean();
flush();
readfile($caminhoAbsoluto);
exit;
