-- ============================================================================
-- DADOS SEED - Sistema RG Farmácia Valódia
-- Dados iniciais para testes e desenvolvimento
-- ============================================================================

USE farmacia_valodia_rg;

-- ============================================================================
-- 1. DEPARTAMENTOS
-- ============================================================================
INSERT INTO
    departamentos (nome, descricao, ativo)
VALUES (
        'Farmácia',
        'Atendimento farmacêutico e manipulação de medicamentos',
        TRUE
    ),
    (
        'Administração',
        'Gestão administrativa e recursos humanos',
        TRUE
    ),
    (
        'Vendas',
        'Atendimento ao cliente e vendas de produtos',
        TRUE
    ),
    (
        'Estoque',
        'Controle de inventário e logística',
        TRUE
    ),
    (
        'Suporte TI',
        'Tecnologia da informação e suporte técnico',
        TRUE
    );

-- ============================================================================
-- 2. CARGOS
-- ============================================================================
INSERT INTO
    cargos (
        nome,
        descricao,
        salario_base,
        nivel_hierarquico,
        requer_certificacao,
        ativo
    )
VALUES (
        'Farmacêutico Chefe',
        'Responsável técnico pela farmácia',
        250000.00,
        'gerencial',
        TRUE,
        TRUE
    ),
    (
        'Farmacêutico',
        'Atendimento farmacêutico e manipulação',
        150000.00,
        'tecnico',
        TRUE,
        TRUE
    ),
    (
        'Técnico de Farmácia',
        'Auxiliar farmacêutico',
        85000.00,
        'tecnico',
        TRUE,
        TRUE
    ),
    (
        'Atendente',
        'Atendimento ao cliente e vendas',
        65000.00,
        'operacional',
        FALSE,
        TRUE
    ),
    (
        'Gestor de RH',
        'Coordenação de recursos humanos',
        180000.00,
        'gerencial',
        FALSE,
        TRUE
    ),
    (
        'Auxiliar Administrativo',
        'Suporte administrativo',
        75000.00,
        'operacional',
        FALSE,
        TRUE
    ),
    (
        'Técnico de TI',
        'Suporte técnico e manutenção de sistemas',
        120000.00,
        'tecnico',
        FALSE,
        TRUE
    ),
    (
        'Gerente de Estoque',
        'Gestão de estoque e logística',
        160000.00,
        'gerencial',
        FALSE,
        TRUE
    ),
    (
        'Diretor Geral',
        'Gestão estratégica da farmácia',
        400000.00,
        'diretivo',
        FALSE,
        TRUE
    );

-- ============================================================================
-- 3. USUÁRIOS (senha padrão: senha123)
-- Hash bcrypt: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================================
INSERT INTO
    usuarios (
        username,
        password_hash,
        tipo_acesso,
        ativo
    )
VALUES (
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'super_admin',
        TRUE
    ),
    (
        'isaac.quarenta',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'gestor_rh',
        TRUE
    ),
    (
        'ilda.livenia',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'lider_farmaceutico',
        TRUE
    ),
    (
        'jardel.banoyo',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'funcionario_rh',
        TRUE
    ),
    (
        'funcionario.teste',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'funcionario',
        TRUE
    );

-- ============================================================================
-- 4. FUNCIONÁRIOS
-- ============================================================================
INSERT INTO
    funcionarios (
        nome_completo,
        cpf,
        bi,
        data_nascimento,
        sexo,
        estado_civil,
        nacionalidade,
        telefone,
        telefone_emergencia,
        email,
        endereco,
        provincia,
        municipio,
        departamento_id,
        cargo_id,
        data_admissao,
        tipo_contrato,
        status,
        salario_atual,
        banco,
        agencia,
        conta,
        iban,
        numero_ordem_farmaceuticos,
        validade_certificacao,
        usuario_id,
        gestor_direto_id
    )
VALUES
    -- 1. Isaac Quarenta (Farmacêutico Chefe)
    (
        'Isaac Nascimento Quarenta',
        '123.456.789-00',
        '005678901LA045',
        '1995-03-15',
        'M',
        'solteiro',
        'Angolana',
        '+244 923 456 789',
        '+244 923 456 788',
        'isaac@farmacia-valodia.ao',
        'Rua Comandante Gika, Luanda',
        'Luanda',
        'Luanda',
        1,
        1,
        '2023-01-10',
        'CLT',
        'ativo',
        250000.00,
        'BFA',
        '0001',
        '123456789',
        'AO06000100012345678901234',
        'OF-12345',
        '2027-12-31',
        2,
        NULL
    ),

-- 2. Ilda Livénia (Gestor RH)
(
    'Ilda Alexandra Livénia',
    '234.567.890-11',
    '006789012LA046',
    '1992-07-22',
    'F',
    'casado',
    'Angolana',
    '+244 924 567 890',
    '+244 924 567 889',
    'ilda@farmacia-valodia.ao',
    'Rua Rainha Ginga, Luanda',
    'Luanda',
    'Luanda',
    2,
    5,
    '2023-02-01',
    'CLT',
    'ativo',
    180000.00,
    'BAI',
    '0002',
    '234567890',
    'AO06000200023456789012345',
    NULL,
    NULL,
    3,
    1
),

-- 3. Jardel Banoyo (Técnico TI)
(
    'Jardel Ilunga P. Banoyo',
    '345.678.901-22',
    '007890123LA047',
    '1998-11-05',
    'M',
    'solteiro',
    'Angolana',
    '+244 925 678 901',
    '+244 925 678 900',
    'jardel@farmacia-valodia.ao',
    'Bairro Maculusso, Luanda',
    'Luanda',
    'Luanda',
    5,
    7,
    '2023-03-15',
    'CLT',
    'ferias',
    120000.00,
    'BFA',
    '0001',
    '345678901',
    'AO06000100034567890123456',
    NULL,
    NULL,
    4,
    2
),

-- 4. Jared Armando (Farmacêutico)
(
    'Jared Armando',
    '456.789.012-33',
    '008901234LA048',
    '1996-05-18',
    'M',
    'solteiro',
    'Angolana',
    '+244 926 789 012',
    '+244 926 789 011',
    'jared@farmacia-valodia.ao',
    'Rua Cirilo da Conceição Silva, Luanda',
    'Luanda',
    'Luanda',
    1,
    2,
    '2023-04-01',
    'CLT',
    'ativo',
    150000.00,
    'BCI',
    '0003',
    '456789012',
    'AO06000300045678901234567',
    'OF-23456',
    '2026-08-15',
    NULL,
    1
),

-- 5. Mauricio Chitula (Atendente)
(
    'Mauricio Manuel F. Chitula',
    '567.890.123-44',
    '009012345LA049',
    '1994-09-30',
    'M',
    'casado',
    'Angolana',
    '+244 927 890 123',
    '+244 927 890 122',
    'mauricio@farmacia-valodia.ao',
    'Bairro Prenda, Luanda',
    'Luanda',
    'Luanda',
    3,
    4,
    '2023-05-10',
    'CLT',
    'ativo',
    65000.00,
    'BFA',
    '0001',
    '567890123',
    'AO06000100056789012345678',
    NULL,
    NULL,
    NULL,
    1
),

-- 6. Francisco Chihamba (Gerente Estoque)
(
    'Francisco da Silva K. Chihamba',
    '678.901.234-55',
    '010123456LA050',
    '1997-12-12',
    'M',
    'solteiro',
    'Angolana',
    '+244 928 901 234',
    '+244 928 901 233',
    'francisco@farmacia-valodia.ao',
    'Bairro Vila Alice, Luanda',
    'Luanda',
    'Luanda',
    4,
    8,
    '2023-06-20',
    'CLT',
    'ativo',
    160000.00,
    'BAI',
    '0002',
    '678901234',
    'AO06000200067890123456789',
    NULL,
    NULL,
    NULL,
    2
),

-- 7. Vasco Alexandre (Atendente)
(
    'Vasco Alexandre',
    '789.012.345-66',
    '011234567LA051',
    '1993-02-28',
    'M',
    'divorciado',
    'Angolana',
    '+244 929 012 345',
    '+244 929 012 344',
    'vasco@farmacia-valodia.ao',
    'Rua Ngola Mbandi, Luanda',
    'Luanda',
    'Luanda',
    3,
    4,
    '2023-07-05',
    'CLT',
    'ativo',
    65000.00,
    'BFA',
    '0001',
    '789012345',
    'AO06000100078901234567890',
    NULL,
    NULL,
    NULL,
    1
);

-- Atualizar responsáveis dos departamentos
UPDATE departamentos SET responsavel_id = 1 WHERE nome = 'Farmácia';

UPDATE departamentos
SET
    responsavel_id = 2
WHERE
    nome = 'Administração';

UPDATE departamentos SET responsavel_id = 5 WHERE nome = 'Vendas';

UPDATE departamentos SET responsavel_id = 6 WHERE nome = 'Estoque';

UPDATE departamentos
SET
    responsavel_id = 3
WHERE
    nome = 'Suporte TI';

-- ============================================================================
-- 5. TURNOS
-- ============================================================================
INSERT INTO
    turnos (
        nome,
        hora_inicio,
        hora_fim,
        tipo,
        duracao_horas,
        intervalo_minutos,
        ativo
    )
VALUES (
        'Manhã',
        '08:00:00',
        '14:00:00',
        'manha',
        6.00,
        60,
        TRUE
    ),
    (
        'Tarde',
        '14:00:00',
        '20:00:00',
        'tarde',
        6.00,
        60,
        TRUE
    ),
    (
        'Noite',
        '20:00:00',
        '08:00:00',
        'noite',
        12.00,
        60,
        TRUE
    ),
    (
        'Integral',
        '08:00:00',
        '17:00:00',
        'integral',
        9.00,
        60,
        TRUE
    ),
    (
        'Flexível',
        '09:00:00',
        '18:00:00',
        'flexivel',
        9.00,
        60,
        TRUE
    );

-- ============================================================================
-- 6. REGISTROS DE PONTO (últimos 7 dias)
-- ============================================================================
INSERT INTO
    registros_ponto (
        funcionario_id,
        data,
        hora_entrada,
        hora_saida,
        horas_trabalhadas,
        horas_extras,
        tipo,
        metodo_registro
    )
VALUES
    -- Dia -6
    (
        1,
        DATE_SUB(CURDATE(), INTERVAL 6 DAY),
        '08:00:00',
        '17:00:00',
        9.00,
        0.00,
        'presenca',
        'biometrico'
    ),
    (
        2,
        DATE_SUB(CURDATE(), INTERVAL 6 DAY),
        '08:15:00',
        '17:10:00',
        8.92,
        0.00,
        'presenca',
        'biometrico'
    ),
    (
        4,
        DATE_SUB(CURDATE(), INTERVAL 6 DAY),
        '08:00:00',
        '18:30:00',
        10.50,
        1.50,
        'presenca',
        'biometrico'
    ),
    (
        5,
        DATE_SUB(CURDATE(), INTERVAL 6 DAY),
        '08:00:00',
        '17:00:00',
        9.00,
        0.00,
        'presenca',
        'manual'
    ),
    -- Dia -5
    (
        1,
        DATE_SUB(CURDATE(), INTERVAL 5 DAY),
        '08:00:00',
        '17:00:00',
        9.00,
        0.00,
        'presenca',
        'biometrico'
    ),
    (
        2,
        DATE_SUB(CURDATE(), INTERVAL 5 DAY),
        '08:10:00',
        '17:05:00',
        8.92,
        0.00,
        'presenca',
        'biometrico'
    ),
    -- Dia -4
    (
        1,
        DATE_SUB(CURDATE(), INTERVAL 4 DAY),
        '08:00:00',
        '17:00:00',
        9.00,
        0.00,
        'presenca',
        'biometrico'
    ),
    (
        2,
        DATE_SUB(CURDATE(), INTERVAL 4 DAY),
        '08:20:00',
        '17:15:00',
        8.92,
        0.00,
        'presenca',
        'biometrico'
    ),
    -- Hoje
    (
        1,
        CURDATE(),
        '08:00:00',
        NULL,
        NULL,
        NULL,
        'presenca',
        'biometrico'
    ),
    (
        2,
        CURDATE(),
        '08:15:00',
        NULL,
        NULL,
        NULL,
        'presenca',
        'biometrico'
    );

-- ============================================================================
-- MENSAGEM FINAL
-- ============================================================================
SELECT
    '✅ Dados seed inseridos com sucesso!' AS status,
    (
        SELECT COUNT(*)
        FROM departamentos
    ) AS departamentos,
    (
        SELECT COUNT(*)
        FROM cargos
    ) AS cargos,
    (
        SELECT COUNT(*)
        FROM usuarios
    ) AS usuarios,
    (
        SELECT COUNT(*)
        FROM funcionarios
    ) AS funcionarios,
    (
        SELECT COUNT(*)
        FROM turnos
    ) AS turnos,
    (
        SELECT COUNT(*)
        FROM registros_ponto
    ) AS registros_ponto;