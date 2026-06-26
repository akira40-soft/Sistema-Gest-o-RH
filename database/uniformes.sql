CREATE TABLE IF NOT EXISTS `uniformes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL,
    `descricao` TEXT,
    `tamanho` ENUM(
        'PP',
        'P',
        'M',
        'G',
        'GG',
        'XG',
        'Unico'
    ) DEFAULT 'Unico',
    `estoque_atual` INT DEFAULT 0,
    `estoque_minimo` INT DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entregas_uniformes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `funcionario_id` INT NOT NULL,
    `uniforme_id` INT NOT NULL,
    `quantidade` INT NOT NULL,
    `data_entrega` DATE NOT NULL,
    `data_devolucao` DATE DEFAULT NULL,
    `estado_devolucao` ENUM('bom', 'danificado', 'perda') DEFAULT NULL,
    `observacoes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uniforme_id`) REFERENCES `uniformes` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Dados iniciais
INSERT INTO
    `uniformes` (
        `nome`,
        `descricao`,
        `tamanho`,
        `estoque_atual`
    )
VALUES (
        'Jaleco Farmacêutico',
        'Jaleco branco padrão com logo',
        'M',
        20
    ),
    (
        'Jaleco Farmacêutico',
        'Jaleco branco padrão com logo',
        'G',
        15
    ),
    (
        'Camisa Polo Balconista',
        'Uniforme de atendimento azul',
        'M',
        30
    ),
    (
        'Camisa Polo Balconista',
        'Uniforme de atendimento azul',
        'G',
        25
    ),
    (
        'Crachá Identificação',
        'Porta crachá e cordão',
        'Unico',
        100
    );