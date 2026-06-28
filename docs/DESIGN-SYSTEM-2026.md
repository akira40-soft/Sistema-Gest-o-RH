# Design System 2026 — Guia de Migração

Este guia mostra como converter uma página legada para usar o novo
design system (sidebar, topbar, CSS 2026, app.js 2026).

## 1. Substituir o `<head>`

**Remover:**
```html
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/style-new.css">
```

**Adicionar:**
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="css/style-2026.css">
```

## 2. Substituir o `<body>`

**Remover** todos os `<div class="sidebar">` / `<nav>` antigos.

**Adicionar:**
```html
<body class="dashboard-body">
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>

            <div class="content-body">
                <!-- seu conteúdo aqui -->
            </div>
        </div>
    </div>

    <script src="js/app-2026.js"></script>
</body>
```

## 3. Adicionar breadcrumb (opcional)

```php
$pageTitle = 'Nome da Página';
$pageSubtitle = 'Descrição curta';
```

O `topbar.php` lê estas variáveis automaticamente.

```html
<nav>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Início</a></li>
        <li class="breadcrumb-item active">Nome da Página</li>
    </ol>
</nav>
```

## 4. Substituir classes legadas

| Legado               | Novo (2026)                |
|----------------------|----------------------------|
| `.btn-primary` Bootstrap | `.btn .btn-primary`         |
| `.card` Bootstrap    | `.card` (mantido)          |
| `.table` Bootstrap   | `.data-table` (custom)     |
| `.form-control`      | `.form-control` (mantido)  |
| `.alert-success`     | `.alert .alert-success`    |
| `.badge-primary`     | `.badge .badge-primary`    |
| `.bg-dark`           | usar variáveis CSS         |

## 5. Componentes disponíveis em `style-2026.css`

- **Botões:** `btn`, `btn-primary`, `btn-secondary`, `btn-success`, `btn-warning`, `btn-danger`, `btn-ghost`, `btn-icon`, `btn-block`
- **Cards:** `card`, `card-header`, `card-title`, `card-body`, `card-footer`
- **Forms:** `form-group`, `form-label`, `form-control`, `form-select`, `form-check`
- **Alerts:** `alert alert-{success|warning|danger|info}`, `alert-content`
- **Badges:** `badge badge-{primary|success|warning|danger|info|neutral}`
- **Stats:** `stats-grid`, `stat-card`
- **Tables:** `data-table`, `table-responsive`
- **Layout:** `grid-2`, `grid-3`, `grid-4`, `d-flex`, `gap-{1|2|3}`
- **Utilitários:** `mb-{1|2|3|4}`, `mt-{1|2|3|4}`, `p-{1|2|3|4}`

## 6. Funções JS disponíveis em `app-2026.js`

- `App.toast(mensagem, tipo)` — exibe notificação
- `App.confirm(mensagem, callback)` — diálogo de confirmação
- `App.applyTheme(tema)` — alterna entre 'dark' e 'light'
- `App.search(query)` — busca global

## 7. Páginas que ainda precisam migrar

Estas páginas mantêm o design antigo. Quando migradas, basta seguir
os passos acima:

- [ ] `departamentos.php`
- [ ] `cargos.php`
- [ ] `escalas.php`
- [ ] `folha.php`
- [ ] `pontos.php`
- [ ] `ferias.php`
- [ ] `licencas.php`
- [ ] `avaliacoes.php`
- [ ] `treinamentos.php`
- [ ] `vagas.php`
- [ ] `candidatos.php`
- [ ] `comunicados.php`
- [ ] `documentos.php`
- [ ] `beneficios.php`
- [ ] `advertencias.php`
- [ ] `uniformes.php`
- [ ] `relatorios.php`
- [ ] `usuarios.php` (admin)
- [ ] `admin-*.php`

## 8. Bugs conhecidos nas páginas legadas

Estes arquivos têm queries SQL que referenciam colunas/tabelas que
não existem no schema real. Precisam ser reescritos antes de
funcionar:

- ❌ `ferias.php` — usa tabela `ferias` (não existe; usar `licencas` com `tipo='ferias'`)
- ❌ `avaliacoes.php` — usa tabela `avaliacoes_desempenho` e colunas erradas (existe `avaliacoes`)
- ❌ `advertencias.php` — usa tabela `advertencias` (não existe; usar `comunicados` com `tipo='advertencia'`)
- ❌ `uniformes.php` — usa tabela `entregas_uniformes` (não existe)
- ⚠️ `pontos.php` — usa coluna `data_registro` (correto é `data`)
- ⚠️ `usuarios.php` — formulário tem `funcionario_id` (não existe em `usuarios`)

## 9. Tema dark/light

```js
// Aplicar
App.applyTheme('dark');
App.applyTheme('light');

// Ler do localStorage
const tema = localStorage.getItem('rg_theme') || 'dark';
```

## 10. Auth helpers

```php
$auth = new Auth();
$auth->requireAuth();              // exige login
$auth->requireAuth(true);          // exige admin
$auth->isAdmin();                  // true para super_admin/gestor_rh
$auth->getUserRole();              // 'funcionario', 'gestor_rh', etc.
$auth->getUserId();
$auth->getUsername();
```
