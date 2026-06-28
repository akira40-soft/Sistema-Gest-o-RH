P# Documentação Técnica: Sistema de Gestão de RH - Farmácia Valódia

## 1. Visão Geral
Esta documentação descreve a implementação da página de login e do Dashboard principal do Sistema de Gestão de Recursos Humanos. O foco foi criar uma interface moderna, "uau", com efeitos de transparência (Glassmorphism) e paleta de cores verde neon/cinza, mantendo um código limpo e escalável utilizando PHP Orientado a Objetos (POO).

## 2. Tecnologias Utilizadas
- **HTML5**: Estrutura semântica da página.
- **CSS3 (Vanilla)**: Design system, efeitos de desfoque (blur), gradientes neon e animações.
- **JavaScript (ES6+)**: Interatividade, feedbacks de carregamento, data dinâmica e efeitos de mouse.
- **PHP 8.x**: Lógica de backend e abstração de banco de dados (PDO).
- **Bootstrap 5.3**: Utilizado via CDN para utilitários de layout e ícones (Bootstrap Icons).

## 3. Arquitetura do Projeto (POO)
O sistema foi organizado para ser escalável, separando responsabilidades:

- `/src/Database/Database.php`: Implementa o padrão **Singleton** para gerenciar a conexão com o MySQL.
- `/src/Auth/Auth.php`: Contém a lógica de autenticação e permissões.
- `/public/`: Contém os arquivos acessíveis ao navegador.
    - `login.php`: Interface de entrada do sistema.
    - `dashboard.php`: Painel principal de gestão pós-login.
    - `css/style.css`: Estilos globais e componentes visuais.
    - `js/script.js`: Comportamentos dinâmicos de front-end.
    - `assets/img/`: Recursos de mídia (wallpaper).

## 4. Fluxo Lógico e Técnico

### 4.1 Carregamento e Glassmorphism
- O sistema utiliza `backdrop-filter: blur()` extensivamente para criar a estética de vidro.
- O efeito de brilho neon é controlado por variáveis CSS (`--neon-green`), permitindo mudanças rápidas em toda a paleta.

### 4.2 Lógica de Transição
- Ao realizar o login bem-sucedido (simulado no JS por enquanto), o sistema aplica um pequeno delay para feedback visual e redireciona o usuário para o `dashboard.php`.

### 4.3 Dashboard Principal (Interface de Gestão)
1. **Sidebar Navegável**: Implementada com fundo escuro semi-transparente. Links ativos ganham destaque neon.
2. **Cards de Estatísticas (KPIs)**: Mostram dados rápidos como total de funcionários e assiduidade diária.
3. **Tabela de Dados**: Listagem limpa com ícones de ação e badges de status coloridos.
4. **Header Dinâmico**: O JavaScript detecta e exibe a data atual formatada (Ex: "12 de Fevereiro, 2026").

## 5. Variáveis Chave e Design Tokens
- `--neon-green (#39FF14)`: Principal cor de destaque.
- `--glass-bg`: Fundo com baixa opacidade para transparência.
- `--text-white`: Cor principal para textos sobre fundos escuros.

## 6. Próximos Passos (Escalabilidade)
- **Integração MySQL**: Substituir os mocks na classe `Auth` por consultas reais ao banco de dados.
- **Formulários de Cadastro**: Implementar as telas de "Novo Funcionário" dentro do container de conteúdo principal.

---
*Documento atualizado em: 12/02/2026*
*Desenvolvido por: Antigravity AI*
