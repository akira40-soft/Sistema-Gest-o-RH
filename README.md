# Farmácia Gingongo RG — Sistema de Gestão de RH

Sistema profissional de gestão de RH e operações para farmácia, desenvolvido em Angola (Luanda).

## Licença

GNU General Public License v3.0 — Copyright (C) 2026 Isaac Quarenta.

## Funcionalidades

- Autenticação segura (bcrypt) com 6 roles de acesso
- Gestão de funcionários (CRUD completo)
- Dashboard administrativo com gráficos
- Portal do funcionário (self-service)
- Registo de ponto com geolocalização
- Gestão de turnos e escalas
- Licenças, férias e advertências
- Folha de pagamento (INSS 3%, IRT Lei 15/23)
- Geração de PDF (TCPDF)
- Carteira profissional angolana
- Treinamentos, avaliações 360° e recrutamento
- API REST

## Requisitos

- PHP >= 8.0
- MySQL/MariaDB ou SQLite (fallback automático)
- Composer
- XAMPP (recomendado no Windows)

## Instalação & Execução

```bash
git clone https://github.com/akira40-soft/Sistema-Gest-o-RH.git
cd Sistema-Gest-o-RH
composer install
php -S localhost:8080 -t public
```

Acesse: `http://localhost:8080`

## Estrutura

```
src/
  Auth/          Autenticação e middleware
  Database/      Conexão MySQL/SQLite (singleton)
  Models/        Modelos de dados
  Utils/         PDF, Upload, Audit, Notification, etc.
  bootstrap.php  Autoloader, sessão, helpers

public/
  index.php      Router (redireciona por role)
  login.php      Tela de login
  dashboard.php  Painel administrativo
  portal.php     Portal do funcionário
  api/           Endpoints REST
  includes/      Sidebar, topbar, layout reutilizáveis
  css/           style-2026.css (design system)
  js/            app-2026.js

database/
  schema.sql         Schema completo (MySQL)
  schema_sqlite.sql  Schema SQLite (fallback)

docs/              Documentação e referências do TCC
```

## Credenciais de Teste

| Usuário | Senha | Role |
|---------|-------|------|
| admin | senha123 | Super Admin |
| josemar_quarenta | senha123 | Super Admin |
| livenia | senha123 | Gestor RH |
| ilda | senha123 | Líder Farmacêutico |
| jardel | senha123 | Funcionário |

## Tecnologias

- **Backend:** PHP 8.0+, PSR-4 autoloading
- **Banco:** MySQL (MariaDB) / SQLite
- **PDF:** TCPDF
- **Frontend:** Inter font, Bootstrap Icons, CSS custom properties, Chart.js

---

**Autor:** Isaac Quarenta
