# Pasta de Arquivos Legados

Esta pasta contém scripts de configuração, migração e debug que foram
utilizados durante o desenvolvimento inicial do sistema.

⚠️ **NÃO COLOCAR EM PRODUÇÃO** — estes arquivos não são necessários
para o funcionamento normal do sistema.

## Arquivos nesta pasta

- `install_rh_modules.php` — instalador inicial
- `setup_database.php` — criação do schema
- `setup_admin.php` — criação do usuário admin inicial
- `update_db_v2.php` — migração v2 do schema
- `migrate-phase2.php` — migração fase 2
- `execute-migration.php` — execução de migrações
- `check-usuarios.php` — debug de usuários
- `finalize.php` — setup final
- `complete-setup.php` — setup completo
- `fix_uniformes_table.php` — correções pontuais
- `create-sessions-dir.php` — criação de diretório de sessões
- `test-conexao.php` — teste de conexão
- `teste-*.php` / `debug-*.php` — scripts de debug
- `dashboard-novo.php` / `index-novo.php` — versões antigas
- `dashboard_admin_advanced.php` / `dashboard_rh_user.php` — dashboards legados
- `portal_new.php` / `acesso_negado_new.php` / `logout_new.php` — versões antigas
- `admin-funcionarios-pendentes.php` — feature não utilizada

## Recomendações

Em produção, esta pasta deve ser excluída completamente do deploy.
Mova para fora de `public/` ou proteja com `.htaccess`:

```apache
Deny from all
```
