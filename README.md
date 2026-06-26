# Farmácia Gingongo RG — Sistema de Gestão de RH

Sistema profissional de gestão de RH e operações para farmácia, desenvolvido em Angola (Luanda).

## Licença

Este projeto está licenciado sob a **GNU General Public License v3.0** — veja o arquivo [LICENSE](LICENSE) para detalhes.

Copyright (C) 2026 Isaac Quarenta. Todos os direitos reservados.

## Funcionalidades

- Autenticação segura (bcrypt)
- Gestão de funcionários (CRUD completo)
- Controle de acesso por roles (super_admin, admin, gestor_rh, lider_farmaceutico, funcionario_rh, funcionario)
- Dashboard administrativo
- Portal do funcionário
- Registo de ponto (incluindo geolocalização)
- Gestão de turnos e escalas
- Licenças e benefícios
- Folha de pagamento com cálculos Angola (INSS 3%, IRT Lei 15/23)
- Geração de PDF (TCPDF) — recibos, mapas INSS/IRT, folha de pagamento
- Carteira profissional angolana
- Treinamentos
- API REST

## Requisitos

- PHP >= 8.0
- MySQL/MariaDB ou SQLite
- Composer
- XAMPP (recomendado no Windows)

## Instalação

```bash
# Clonar o repositório
git clone https://github.com/akira40-soft/Sistema-Gest-o-RH.git
cd Sistema-Gest-o-RH

# Instalar dependências
composer install
```

## Execução

**Windows (XAMPP):**
```bash
I:\xampp\php\php.exe -S localhost:8080 -t public
```

**Linux/Mac:**
```bash
php -S localhost:8080 -t public
```

Acesse: `http://localhost:8080`

## Credenciais de Teste

| Usuário | Senha | Role |
|---------|-------|------|
| augusto | Augusto@Gingongo2026 | Admin |
| josemar_quarenta | — | Super Admin |

Para criar mais usuários: login como admin → Gerenciar Usuários.

## Estrutura

```
src/
  Auth/          Autenticação e middleware
  Database/      Conexão com banco (MySQL/SQLite)
  Models/        Modelos de dados
  Utils/         Utilidades (PDF, Upload, Audit, etc.)
  bootstrap.php  Inicialização

public/
  login.php      Login
  dashboard.php  Dashboard admin
  portal_new.php Portal funcionário
  css/           Estilos
  js/            Scripts
  api/           API REST

database/
  schema_init.sql  Schema do banco

Exemplos/        Exemplos de uso
```

## Tecnologias

- **Backend:** PHP 8.0+
- **Banco:** MySQL (MariaDB) / SQLite
- **PDF:** TCPDF
- **Frontend:** HTML, CSS, JavaScript, Bootstrap
- **Composer:** PSR-4 autoloading

## Autor

**Isaac Quarenta** — Desenvolvedor

---

*Distribuído sob a GNU GPL v3.0.*
