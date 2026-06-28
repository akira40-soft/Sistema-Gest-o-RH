<p align="center">
  <img src="https://raw.githubusercontent.com/akira40-soft/Sistema-Gest-o-RH/main/public/assets/img/banner.png" alt="Sistema de Gestão de RH - Farmácia Gingongo RG" width="100%">
</p>

<h1 align="center">Farmácia Gingongo RG — Sistema de Gestão de RH</h1>

<p align="center">
  Sistema profissional de gestão de RH e operações para farmácia, desenvolvido em Angola (Luanda).
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/License-GPL%20v3-333?style=for-the-badge" alt="License">
  <img src="https://img.shields.io/badge/Platform-Windows%20%7C%20Linux-0078D4?style=for-the-badge&logo=windows&logoColor=white" alt="Platform">
</p>

---

## Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Autenticação** | Login seguro com bcrypt, 6 roles de acesso granulares |
| **Dashboard** | Painel administrativo com gráficos e KPIs em tempo real |
| **Gestão de Funcionários** | CRUD completo com admissão, edição e perfil |
| **Portal do Funcionário** | Self-service para consultas, ponto e documentos |
| **Registo de Ponto** | Batida de ponto com geolocalização (GPS) |
| **Escalas & Turnos** | Gestão de escalas de trabalho e turnos |
| **Licenças & Férias** | Controle de licenças médicas, férias e advertências |
| **Folha de Pagamento** | Cálculo automático INSS 3%, IRT (Lei 15/23) |
| **Geração de PDF** | Recibos de salário, carteira profissional, relatórios |
| **Treinamentos** | Gestão de treinamentos e avaliações 360° |
| **Recrutamento** | Publicação de vagas e gestão de candidatos |
| **API REST** | Endpoints para integrações externas |

## Requisitos

- **PHP** >= 8.0
- **MySQL/MariaDB** ou SQLite (fallback automático)
- **Composer**
- **XAMPP** (recomendado no Windows)

## Instalação & Execução

```bash
# Clonar o repositório
git clone https://github.com/akira40-soft/Sistema-Gest-o-RH.git
cd Sistema-Gest-o-RH

# Instalar dependências
composer install

# Iniciar servidor de desenvolvimento
php -S localhost:8080 -t public
```

Acesse: **http://localhost:8080**

## Estrutura do Projeto

```
Sistema-Gest-o-RH/
├── src/                          # Código-fonte PHP
│   ├── Auth/                     # Autenticação e middleware
│   ├── Database/                 # Conexão MySQL/SQLite (singleton)
│   ├── Models/                   # Modelos de dados
│   ├── Utils/                    # PDF, Upload, Audit, Notification
│   └── bootstrap.php             # Autoloader, sessão, helpers
│
├── public/                       # Document-root do servidor
│   ├── index.php                 # Router principal
│   ├── login.php                 # Tela de login
│   ├── dashboard.php             # Painel administrativo
│   ├── portal.php                # Portal do funcionário
│   ├── perfil.php                # Perfil do utilizador
│   ├── perfil_colaborador.php    # Perfil público de colegas
│   ├── api/                      # Endpoints REST
│   ├── includes/                 # Sidebar, topbar, layout
│   ├── css/style-2026.css        # Design system
│   ├── js/app-2026.js            # Interatividade
│   └── assets/img/               # Imagens e banners
│
├── database/
│   ├── schema.sql                # Schema completo (MySQL)
│   ├── schema_sqlite.sql         # Schema SQLite (fallback)
│   └── seed.sql                  # Dados de teste
│
├── docs/                         # Documentação e referências do TCC
├── composer.json
└── README.md
```

## Credenciais de Teste

| Usuário | Senha | Role |
|---------|-------|------|
| `admin` | `senha123` | Super Admin |
| `josemar_quarenta` | `senha123` | Super Admin |
| `livenia` | `senha123` | Gestor RH |
| `ilda` | `senha123` | Líder Farmacêutico |
| `jardel` | `senha123` | Funcionário |

## Roles de Acesso

| Role | Descrição |
|------|-----------|
| `super_admin` | Acesso total ao sistema |
| `admin` | Acesso administrativo completo |
| `gestor_rh` | Gestão de pessoas e processos de RH |
| `funcionario_rh` | Operações de RH (limitado) |
| `lider_farmaceutico` | Supervisão de equipe farmacêutica |
| `funcionario` | Portal do funcionário (self-service) |

## Stack Tecnológica

| Camada | Tecnologias |
|--------|-------------|
| **Backend** | PHP 8.0+, PSR-4 Autoloading |
| **Banco de Dados** | MySQL (MariaDB) / SQLite |
| **PDF** | TCPDF |
| **Frontend** | CSS Custom Properties, Inter Font |
| **Ícones** | Bootstrap Icons |
| **Gráficos** | Chart.js |

## Licença

Este projeto está sob a licença **GNU General Public License v3.0** — Copyright (C) 2026 Isaac Quarenta.

---

<p align="center">
  Desenvolvido com dedication por <a href="https://github.com/akira40-soft">Isaac Quarenta</a> — Luanda, Angola
</p>
