<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

if (!$auth->isHRStaff()) {
    header('Location: acesso_negado.php');
    exit;
}

$user = [
    'username' => $auth->getUsername(),
    'role'     => $auth->getUserRole(),
    'id'       => $auth->getUserId()
];

$db = Database::getInstance()->getConnection();
$msg = '';
$msgType = '';
$generatedCredentials = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $required = ['nome_completo', 'bi', 'data_nascimento', 'sexo', 'departamento_id', 'cargo_id', 'data_admissao', 'salario_inicial'];
        foreach ($required as $r) {
            if (empty($_POST[$r])) {
                throw new Exception("Campo obrigatório: " . $r);
            }
        }

        $sexoValidos = ['M', 'F', 'Outro'];
        if (!in_array($_POST['sexo'], $sexoValidos)) {
            throw new Exception("Sexo inválido.");
        }

        $tipoContratoValidos = ['Tempo_Indeterminado', 'Tempo_Determinado', 'Estagio', 'Temporario'];
        $tipoContrato = in_array($_POST['tipo_contrato'] ?? '', $tipoContratoValidos) ? $_POST['tipo_contrato'] : 'Tempo_Indeterminado';

        $sql = "INSERT INTO funcionarios (
            nome_completo, bi, data_nascimento, sexo,
            telefone, email, endereco,
            departamento_id, cargo_id, data_admissao, tipo_contrato, status,
            salario_atual, banco, agencia, conta, iban,
            nif_angolano
        ) VALUES (
            :nome_completo, :bi, :data_nascimento, :sexo,
            :telefone, :email, :endereco,
            :departamento_id, :cargo_id, :data_admissao, :tipo_contrato, 'ativo',
            :salario_atual, :banco, :agencia, :conta, :iban,
            :nif_angolano
        )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nome_completo'              => trim($_POST['nome_completo']),
            ':bi'                         => trim($_POST['bi']),
            ':data_nascimento'            => $_POST['data_nascimento'],
            ':sexo'                       => $_POST['sexo'],
            ':telefone'                   => trim($_POST['telefone'] ?? '') ?: null,
            ':email'                      => trim($_POST['email'] ?? '') ?: null,
            ':endereco'                   => trim($_POST['endereco'] ?? '') ?: null,
            ':departamento_id'            => (int)$_POST['departamento_id'],
            ':cargo_id'                   => (int)$_POST['cargo_id'],
            ':data_admissao'              => $_POST['data_admissao'],
            ':tipo_contrato'              => $tipoContrato,
            ':salario_atual'              => (float)str_replace(',', '.', $_POST['salario_inicial']),
            ':banco'                      => trim($_POST['banco'] ?? '') ?: null,
            ':agencia'                    => trim($_POST['agencia'] ?? '') ?: null,
            ':conta'                      => trim($_POST['conta'] ?? '') ?: null,
            ':iban'                       => trim($_POST['iban'] ?? '') ?: null,
            ':nif_angolano'               => trim($_POST['nif_angolano'] ?? '') ?: null
        ]);

        $funcionarioId = $db->lastInsertId();

        $partes = explode(' ', strtolower(trim($_POST['nome_completo'])));
        $first = preg_replace('/[^a-z0-9]/', '', $partes[0] ?? 'user');
        $last  = preg_replace('/[^a-z0-9]/', '', end($partes) ?: '');
        $baseUsername = $first . ($last ? '.' . $last : '');
        $username = $baseUsername;
        $i = 1;
        $check = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :u");
        while (true) {
            $check->execute([':u' => $username]);
            if ($check->fetchColumn() == 0) break;
            $username = $baseUsername . $i++;
            if ($i > 99) {
                $username = $baseUsername . substr(str_shuffle('0123456789'), 0, 4);
                break;
            }
        }

        $senhaTemporaria = 'Tmp@' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
        $passwordHash = password_hash($senhaTemporaria, PASSWORD_BCRYPT);

        $stmtUser = $db->prepare("
            INSERT INTO usuarios (username, password_hash, tipo_acesso, ativo)
            VALUES (:u, :p, :role, 1)
        ");
        $stmtUser->execute([
            ':u'    => $username,
            ':p'    => $passwordHash,
            ':role' => 'funcionario'
        ]);
        $usuarioId = $db->lastInsertId();

        $db->prepare("UPDATE funcionarios SET usuario_id = :uid WHERE id = :fid")
           ->execute([':uid' => $usuarioId, ':fid' => $funcionarioId]);

        $docMap = [
            'doc_bi'                 => ['bi', null],
            'doc_certificado'        => ['certificado_habilitacoes', null],
            'doc_carteira'           => ['outro', 'Carteira Profissional'],
            'doc_atestado'           => ['atestado_medico', null],
            'doc_registo_criminal'   => ['registo_criminal', null],
            'doc_comprovativo'       => ['comprovativo_residencia', null],
            'doc_cv'                 => ['cv', null],
        ];
        $stmtDoc = $db->prepare("
            INSERT INTO documentos_funcionarios
              (funcionario_id, tipo_documento, nome_original, nome_arquivo, caminho_arquivo, data_validade, uploaded_por, observacoes)
            VALUES
              (:fid, :tipo, :orig, :arq, :path, :validade, :uid, :obs)
        ");
        foreach ($docMap as $postKey => $mapInfo) {
            if (!empty($_POST[$postKey])) {
                $tipoDoc = $mapInfo[0];
                $labelDoc = $mapInfo[1] ?? ucfirst(str_replace('_', ' ', $tipoDoc));
                $nomeOriginal = $labelDoc;
                $nomeUnico = $tipoDoc . '_' . $funcionarioId . '_' . substr(str_shuffle('0123456789abcdef'), 0, 8);
                $stmtDoc->execute([
                    ':fid'      => $funcionarioId,
                    ':tipo'     => $tipoDoc,
                    ':orig'     => $nomeOriginal,
                    ':arq'      => $nomeUnico,
                    ':path'     => 'documentos/pendente_' . $nomeUnico,
                    ':validade' => $_POST[$postKey],
                    ':uid'      => $user['id'],
                    ':obs'      => 'Validade registada na admissão'
                ]);
            }
        }

        if ($requerCert && empty($_POST['validade_certificacao'])) {
            $msg = '<div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div class="alert-content">
                    <strong>Atenção:</strong> O cargo selecionado requer certificação farmacêutica, mas a validade não foi informada.
                    Adicione a certidão na seção de documentos do funcionário.
                </div>
            </div>';
        }

        $db->commit();

        $generatedCredentials = [
            'username' => $username,
            'password' => $senhaTemporaria
        ];

        $msg = '<div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <div class="alert-content">
                <strong>Admissão realizada com sucesso!</strong><br>
                Funcionário: <strong>' . htmlspecialchars($_POST['nome_completo']) . '</strong><br>
                BI: <code>' . htmlspecialchars($_POST['bi']) . '</code> · ID: #' . $funcionarioId . '
            </div>
        </div>';

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $msg = '<div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div class="alert-content">
                <strong>Erro na admissão</strong><br>' . htmlspecialchars($e->getMessage()) . '
            </div>
        </div>';
    }
}

$departamentos = $db->query("SELECT id, nome FROM departamentos WHERE ativo = 1 ORDER BY nome")->fetchAll();
$cargos        = $db->query("SELECT id, nome, requer_certificacao, nivel_hierarquico FROM cargos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$pageTitle    = 'Nova Admissão';
$pageSubtitle = 'Cadastro de novo colaborador no sistema';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Admissão | SG Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
</head>
<body class="dashboard-body">
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>

            <div class="content-body">

                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item"><a href="funcionarios.php">Funcionários</a></li>
                        <li class="breadcrumb-item active">Nova Admissão</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($generatedCredentials): ?>
                <div class="card mb-3" style="border-left: 4px solid var(--success-500);">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-key"></i> Credenciais de Acesso Geradas</h3>
                    </div>
                    <div class="card-body">
                        <p style="margin-top:0;color:var(--text-muted);">Anote e entregue ao colaborador. Por segurança, esta senha não será exibida novamente.</p>
                        <div class="grid-2" style="gap:1rem;">
                            <div class="form-group">
                                <label class="form-label">Usuário</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($generatedCredentials['username']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Senha Temporária</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($generatedCredentials['password']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" id="admissaoForm">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-person-vcard"></i> 1. Dados Pessoais</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid-2" style="gap:1rem;">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label" for="nome_completo">Nome Completo <span class="required">*</span></label>
                                    <input type="text" id="nome_completo" name="nome_completo" class="form-control" required placeholder="Nome completo do colaborador" value="<?php echo htmlspecialchars($_POST['nome_completo'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="bi">BI (Bilhete de Identidade) <span class="required">*</span></label>
                                    <input type="text" id="bi" name="bi" class="form-control" required placeholder="000000000LA000" value="<?php echo htmlspecialchars($_POST['bi'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="nif_angolano">NIF Angolano</label>
                                    <input type="text" id="nif_angolano" name="nif_angolano" class="form-control" placeholder="5XXXXXXXX" value="<?php echo htmlspecialchars($_POST['nif_angolano'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="data_nascimento">Data de Nascimento <span class="required">*</span></label>
                                    <input type="date" id="data_nascimento" name="data_nascimento" class="form-control" required value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="sexo">Gênero <span class="required">*</span></label>
                                    <select id="sexo" name="sexo" class="form-select" required>
                                        <option value="M" <?php echo ($_POST['sexo'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo ($_POST['sexo'] ?? '') === 'F' ? 'selected' : ''; ?>>Feminino</option>
                                        <option value="Outro" <?php echo ($_POST['sexo'] ?? '') === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="estado_civil">Estado Civil</label>
                                    <select id="estado_civil" name="estado_civil" class="form-select">
                                        <option value="">—</option>
                                        <option value="solteiro" <?php echo ($_POST['estado_civil'] ?? '') === 'solteiro' ? 'selected' : ''; ?>>Solteiro(a)</option>
                                        <option value="casado" <?php echo ($_POST['estado_civil'] ?? '') === 'casado' ? 'selected' : ''; ?>>Casado(a)</option>
                                        <option value="divorciado" <?php echo ($_POST['estado_civil'] ?? '') === 'divorciado' ? 'selected' : ''; ?>>Divorciado(a)</option>
                                        <option value="viuvo" <?php echo ($_POST['estado_civil'] ?? '') === 'viuvo' ? 'selected' : ''; ?>>Viúvo(a)</option>
                                        <option value="uniao_facto" <?php echo ($_POST['estado_civil'] ?? '') === 'uniao_facto' ? 'selected' : ''; ?>>União de Facto</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="nacionalidade">Nacionalidade</label>
                                    <input type="text" id="nacionalidade" name="nacionalidade" class="form-control" value="<?php echo htmlspecialchars($_POST['nacionalidade'] ?? 'Angolana'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="email">E-mail</label>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="email@farmacia.ao" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="telefone">Telefone</label>
                                    <input type="text" id="telefone" name="telefone" class="form-control" placeholder="+244 9XX XXX XXX" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="telefone_emergencia">Telefone de Emergência</label>
                                    <input type="text" id="telefone_emergencia" name="telefone_emergencia" class="form-control" placeholder="Contacto de familiar" value="<?php echo htmlspecialchars($_POST['telefone_emergencia'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="provincia">Província</label>
                                    <input type="text" id="provincia" name="provincia" class="form-control" placeholder="Ex: Luanda" value="<?php echo htmlspecialchars($_POST['provincia'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="municipio">Município</label>
                                    <input type="text" id="municipio" name="municipio" class="form-control" placeholder="Ex: Cazenga" value="<?php echo htmlspecialchars($_POST['municipio'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label" for="endereco">Endereço Completo</label>
                                    <input type="text" id="endereco" name="endereco" class="form-control" placeholder="Rua, Bairro, Nº, Prédio/Apartamento" value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-briefcase"></i> 2. Dados Contratuais e Remuneração</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid-2" style="gap:1rem;">
                                <div class="form-group">
                                    <label class="form-label" for="departamento_id">Departamento <span class="required">*</span></label>
                                    <select id="departamento_id" name="departamento_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($departamentos as $d): ?>
                                            <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars($d['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($departamentos)): ?>
                                        <small style="color:var(--warning-600);"><i class="bi bi-exclamation-triangle"></i> Nenhum departamento cadastrado. <a href="departamentos.php">Criar agora</a>.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="cargo_id">Cargo <span class="required">*</span></label>
                                    <select id="cargo_id" name="cargo_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($cargos as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>" data-cert="<?php echo (int)$c['requer_certificacao']; ?>">
                                                <?php echo htmlspecialchars($c['nome']); ?>
                                                <?php echo $c['requer_certificacao'] ? ' 💊' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($cargos)): ?>
                                        <small style="color:var(--warning-600);"><i class="bi bi-exclamation-triangle"></i> Nenhum cargo cadastrado. <a href="cargos.php">Criar agora</a>.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="tipo_contrato">Tipo de Contrato</label>
                                    <select id="tipo_contrato" name="tipo_contrato" class="form-select">
                                        <option value="CLT">Efetivo (CLT)</option>
                                        <option value="prazo_determinado">Prazo Determinado</option>
                                        <option value="estagio">Estágio</option>
                                        <option value="temporario">Temporário</option>
                                        <option value="prestacao_servicos">Prestação de Serviços</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="data_admissao">Data de Admissão <span class="required">*</span></label>
                                    <input type="date" id="data_admissao" name="data_admissao" class="form-control" required value="<?php echo htmlspecialchars($_POST['data_admissao'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="salario_inicial">Salário Inicial (Kz) <span class="required">*</span></label>
                                    <input type="number" id="salario_inicial" name="salario_inicial" class="form-control" required step="0.01" min="0" placeholder="0.00" value="<?php echo htmlspecialchars($_POST['salario_inicial'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="tipo_presenca">Modalidade de Trabalho</label>
                                    <select id="tipo_presenca" name="tipo_presenca" class="form-select">
                                        <option value="escritorio">Escritório / Balcão</option>
                                        <option value="campo">Campo / Visitas</option>
                                        <option value="teletrabalho">Teletrabalho</option>
                                    </select>
                                </div>
                            </div>

                            <h4 style="margin-top:1.5rem;margin-bottom:0.75rem;font-size:0.9rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Dados Bancários</h4>
                            <div class="grid-3" style="gap:1rem;">
                                <div class="form-group">
                                    <label class="form-label" for="banco">Banco</label>
                                    <input type="text" id="banco" name="banco" class="form-control" placeholder="Ex: BAI, BFA" value="<?php echo htmlspecialchars($_POST['banco'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="agencia">Agência</label>
                                    <input type="text" id="agencia" name="agencia" class="form-control" value="<?php echo htmlspecialchars($_POST['agencia'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="conta">Nº Conta</label>
                                    <input type="text" id="conta" name="conta" class="form-control" value="<?php echo htmlspecialchars($_POST['conta'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label" for="iban">IBAN</label>
                                    <input type="text" id="iban" name="iban" class="form-control" placeholder="AO06 XXXX XXXX XXXX XXXX XXXX X" value="<?php echo htmlspecialchars($_POST['iban'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3" id="farmaceuticoSection" style="display:none;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-capsule"></i> 3. Registo Farmacêutico</h3>
                            <span class="badge badge-info">Requerido pelo cargo</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <div class="alert-content">
                                    O cargo selecionado requer certificação profissional. Informe os dados da Ordem dos Farmacêuticos de Angola.
                                </div>
                            </div>
                            <div class="grid-2" style="gap:1rem;">
                                <div class="form-group">
                                    <label class="form-label" for="numero_ordem_farmaceuticos">Nº de Ordem dos Farmacêuticos</label>
                                    <input type="text" id="numero_ordem_farmaceuticos" name="numero_ordem_farmaceuticos" class="form-control" placeholder="Ex: 1234" value="<?php echo htmlspecialchars($_POST['numero_ordem_farmaceuticos'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="validade_certificacao">Validade da Cédula</label>
                                    <input type="date" id="validade_certificacao" name="validade_certificacao" class="form-control" value="<?php echo htmlspecialchars($_POST['validade_certificacao'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="carteira_profissional">Carteira Profissional</label>
                                    <input type="text" id="carteira_profissional" name="carteira_profissional" class="form-control" placeholder="Série/Nº" value="<?php echo htmlspecialchars($_POST['carteira_profissional'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3" id="presencaSection" style="display:none;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-geo-alt"></i> 4. Localização de Presença</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <div class="alert-content">
                                    Para registro de ponto por GPS, defina as coordenadas do local principal de trabalho e o raio de tolerância.
                                </div>
                            </div>
                            <div class="grid-3" style="gap:1rem;">
                                <div class="form-group">
                                    <label class="form-label" for="latitude_escritorio">Latitude</label>
                                    <input type="number" step="0.00000001" id="latitude_escritorio" name="latitude_escritorio" class="form-control" placeholder="-8.838333" value="<?php echo htmlspecialchars($_POST['latitude_escritorio'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="longitude_escritorio">Longitude</label>
                                    <input type="number" step="0.00000001" id="longitude_escritorio" name="longitude_escritorio" class="form-control" placeholder="13.234444" value="<?php echo htmlspecialchars($_POST['longitude_escritorio'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="raio_permitido">Raio Permitido (m)</label>
                                    <input type="number" id="raio_permitido" name="raio_permitido" class="form-control" placeholder="100" min="10" value="<?php echo htmlspecialchars($_POST['raio_permitido'] ?? '100'); ?>">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary mt-2" onclick="getCurrentLocation()">
                                <i class="bi bi-crosshair"></i> Usar Localização Atual
                            </button>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-file-earmark-text"></i> 5. Validade de Documentos</h3>
                            <span class="badge badge-neutral">Opcional</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <div class="alert-content">
                                    Informe as datas de validade dos documentos entregues. O sistema alertará quando estiverem próximos do vencimento.
                                </div>
                            </div>
                            <div class="grid-2" style="gap:1rem;">
                                <div class="form-group">
                                    <label class="form-label" for="doc_bi">Validade do BI</label>
                                    <input type="date" id="doc_bi" name="doc_bi" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="doc_certificado">Validade do Certificado de Habilitações</label>
                                    <input type="date" id="doc_certificado" name="doc_certificado" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="doc_carteira">Validade da Carteira Profissional</label>
                                    <input type="date" id="doc_carteira" name="doc_carteira" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="doc_atestado">Validade do Atestado Médico</label>
                                    <input type="date" id="doc_atestado" name="doc_atestado" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="doc_registo_criminal">Validade do Registo Criminal</label>
                                    <input type="date" id="doc_registo_criminal" name="doc_registo_criminal" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="doc_comprovativo">Validade do Comprovativo de Residência</label>
                                    <input type="date" id="doc_comprovativo" name="doc_comprovativo" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-card-text"></i> 6. Observações</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label" for="observacoes">Notas internas</label>
                                <textarea id="observacoes" name="observacoes" class="form-control" rows="3" placeholder="Informações adicionais relevantes..."><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mb-4">
                        <a href="funcionarios.php" class="btn btn-ghost">Cancelar</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-check-circle"></i> Confirmar Admissão
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="js/app-2026.js"></script>
    <script>
    (function() {
        const cargoSelect = document.getElementById('cargo_id');
        const farmSection = document.getElementById('farmaceuticoSection');
        const presencaSelect = document.getElementById('tipo_presenca');
        const presencaSection = document.getElementById('presencaSection');

        function toggleFarmaceutico() {
            const opt = cargoSelect.options[cargoSelect.selectedIndex];
            const reqCert = opt && opt.dataset.cert === '1';
            farmSection.style.display = reqCert ? '' : 'none';
        }
        cargoSelect.addEventListener('change', toggleFarmaceutico);
        toggleFarmaceutico();

        function togglePresenca() {
            presencaSection.style.display = presencaSelect.value ? '' : 'none';
        }
        presencaSelect.addEventListener('change', togglePresenca);
        togglePresenca();

        window.getCurrentLocation = function() {
            if (!navigator.geolocation) {
                App.toast('Geolocalização não suportada pelo navegador.', 'error');
                return;
            }
            navigator.geolocation.getCurrentPosition(function(pos) {
                document.getElementById('latitude_escritorio').value = pos.coords.latitude.toFixed(8);
                document.getElementById('longitude_escritorio').value = pos.coords.longitude.toFixed(8);
                App.toast('Localização capturada com sucesso.', 'success');
            }, function(err) {
                App.toast('Erro ao obter localização: ' + err.message, 'error');
            });
        };

        document.getElementById('admissaoForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;"></span> Processando...';
        });
    })();
    </script>
</body>
</html>
