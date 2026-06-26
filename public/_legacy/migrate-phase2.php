<?php
/**
 * Página de Migração Phase 2
 * Acesse via: http://localhost:8080/migrate-phase2.php
 * 
 * ⚠️ IMPORTANTE: Remover este arquivo após executar a migração
 */

// Segurança básica - apenas localhost ou IP local
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Simplificar para teste local
if (php_sapi_name() !== 'cli' && !in_array($remote_ip, $allowed_ips)) {
    http_response_code(403);
    die('❌ Acesso negado! Esta página só pode ser acessada localmente.');
}

// Requiresbootstrap
require_once __DIR__ . '/src/bootstrap.php';

use App\Database\Database;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração Phase 2 - RG Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .status {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .status-item {
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .success {
            color: #0e7490;
        }

        .error {
            color: #dc2626;
        }

        .warning {
            color: #f59e0b;
        }

        .info {
            color: #2563eb;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .button:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .progress {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 20px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
            width: 0%;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #666;
            font-size: 12px;
        }

        .warning-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            color: #92400e;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Migração Phase 2</h1>
        <p class="subtitle">Aplicando mudanças do banco de dados do sistema</p>

        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Faça um backup do seu banco de dados antes de continuar!
        </div>

        <div class="status" id="status">
            <div class="status-item info">⏳ Pronto para executar migração...</div>
        </div>

        <button class="button" id="btnMigrate" onclick="runMigration()">
            ▶️ Executar Migração
        </button>

        <button class="button" id="btnVerify" onclick="verifyMigration()" style="display:none; background: #0e7490;">
            ✓ Verificar Instalação
        </button>

        <button class="button" id="btnCleanup" onclick="cleanupFile()" style="display:none; background: #f59e0b;">
            🗑️ Remover Script
        </button>

        <div class="progress">
            <div class="progress-bar" id="progressBar"></div>
        </div>

        <div class="footer">
            <p>Phase 2 - Sistema de Gestão RG | Farmácia Gingongo</p>
        </div>
    </div>

    <script>
        function addLog(message, type = 'info') {
            const status = document.getElementById('status');
            const item = document.createElement('div');
            item.className = 'status-item ' + type;
            item.textContent = message;
            status.appendChild(item);
            status.scrollTop = status.scrollHeight;
        }

        function updateProgress(percent) {
            document.getElementById('progressBar').style.width = percent + '%';
        }

        function runMigration() {
            addLog('🔄 Iniciando migração Phase 2...');
            updateProgress(10);

            fetch('/api/migrate-phase2.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLog('✅ Migração executada com sucesso!', 'success');
                    addLog('📊 ' + data.message, 'success');
                    updateProgress(100);
                    
                    document.getElementById('btnMigrate').style.display = 'none';
                    document.getElementById('btnVerify').style.display = 'inline-block';
                } else {
                    addLog('❌ Erro na migração: ' + data.message, 'error');
                    updateProgress(0);
                }
            })
            .catch(error => {
                addLog('❌ Erro: ' + error.message, 'error');
                updateProgress(0);
            });
        }

        function verifyMigration() {
            addLog('🔍 Verificando migração...');
            updateProgress(50);

            fetch('/api/verify-phase2.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLog('✅ Todas as tabelas foram criadas!', 'success');
                    data.tables.forEach(t => {
                        addLog('  ✓ Tabela: ' + t, 'success');
                    });
                    updateProgress(100);
                    
                    document.getElementById('btnVerify').style.display = 'none';
                    document.getElementById('btnCleanup').style.display = 'inline-block';
                    addLog('', 'info');
                    addLog('✅ Migração Phase 2 concluída com sucesso!', 'success');
                } else {
                    addLog('⚠️ ' + data.message, 'warning');
                    updateProgress(50);
                }
            })
            .catch(error => {
                addLog('❌ Erro na verificação: ' + error.message, 'error');
            });
        }

        function cleanupFile() {
            fetch('/api/cleanup-migration.php', {method: 'POST'})
            .then(() => {
                addLog('🗑️ Script de migração removido!', 'success');
                addLog('', 'info');
                addLog('ℹ️ Você pode fechar esta página agora.', 'info');
                document.getElementById('btnCleanup').disabled = true;
            })
            .catch(error => {
                addLog('⚠️ Erro ao remover script: ' + error.message, 'warning');
            });
        }

        // Auto-run if parameter is set
        if (new URLSearchParams(window.location.search).has('auto')) {
            setTimeout(runMigration, 500);
        }
    </script>
</body>
</html>
