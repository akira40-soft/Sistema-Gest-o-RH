<?php
require_once __DIR__ . '/../src/Database/Database.php';
use App\Database\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo "Iniciando atualização do banco de dados (MySQL/MariaDB)...\n";

    // 1. Adicionar colunas faltantes em registros_ponto
    $colunas_ponto = [
        'metodo_registro' => "ENUM('manual', 'biometrico', 'mobile', 'web') DEFAULT 'manual'",
        'ip_registro' => "VARCHAR(45)",
        'gps_latitude' => "DECIMAL(10, 8)",
        'gps_longitude' => "DECIMAL(11, 8)"
    ];

    foreach ($colunas_ponto as $col => $def) {
        try {
            $db->exec("ALTER TABLE registros_ponto ADD COLUMN $col $def");
            echo "✅ Coluna $col adicionada em registros_ponto\n";
        }
        catch (Exception $e) {
            // Se der erro de duplicidade (1060), apenas avisa
            if (strpos($e->getMessage(), '1060') !== false) {
                echo "ℹ️ Coluna $col já existe.\n";
            }
            else {
                echo "⚠️ Erro ao adicionar $col: " . $e->getMessage() . "\n";
            }
        }
    }

    // 2. Criar tabelas faltantes (Sintaxe MySQL)
    $tabelas = [
        "documentos_funcionarios" => "
            CREATE TABLE IF NOT EXISTS documentos_funcionarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                tipo_documento VARCHAR(50) NOT NULL,
                nome_original VARCHAR(255) NOT NULL,
                nome_arquivo VARCHAR(255) UNIQUE NOT NULL,
                caminho_arquivo VARCHAR(500) NOT NULL,
                tamanho_kb INT,
                data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ativo BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

        "turnos" => "
            CREATE TABLE IF NOT EXISTS turnos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(50) NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                tipo VARCHAR(20) DEFAULT 'integral',
                duracao_horas DECIMAL(4,2),
                ativo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

        "escalas" => "
            CREATE TABLE IF NOT EXISTS escalas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                turno_id INT NOT NULL,
                data DATE NOT NULL,
                status ENUM('agendado', 'confirmado', 'substituido', 'cancelado') DEFAULT 'agendado',
                criado_por INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
                FOREIGN KEY (turno_id) REFERENCES turnos(id) ON DELETE RESTRICT,
                FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
                UNIQUE uk_escala_func_data (funcionario_id, data, turno_id)
            ) ENGINE=InnoDB",

        "licencas" => "
            CREATE TABLE IF NOT EXISTS licencas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                tipo VARCHAR(50) NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NOT NULL,
                dias_uteis INT,
                motivo TEXT,
                documento_comprovativo VARCHAR(500),
                status ENUM('pendente', 'aprovada', 'rejeitada', 'cancelada') DEFAULT 'pendente',
                aprovado_por INT NULL,
                data_aprovacao TIMESTAMP NULL,
                observacoes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
                FOREIGN KEY (aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
    ];

    foreach ($tabelas as $nome => $sql) {
        try {
            $db->exec($sql);
            echo "✅ Tabela $nome verificada/criada.\n";
        }
        catch (Exception $e) {
            echo "❌ Erro na tabela $nome: " . $e->getMessage() . "\n";
        }
    }

    echo "🎉 Atualização concluída com sucesso!\n";

}
catch (Exception $e) {
    die("❌ Erro fatal: " . $e->getMessage() . "\n");
}
