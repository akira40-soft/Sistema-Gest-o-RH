# Guia de Execução: Sistema de Gestão de RH - Farmácia Valódia

Este documento fornece as instruções exatadas e a explicação técnica de como rodar o projeto localmente, garantindo que o ambiente de desenvolvimento esteja configurado corretamente.

## 2. Erros Comuns e Soluções

### 2.1 "Directory public does not exist"
Este erro ocorre quando você executa o comando `-t public` estando **já dentro** da pasta `public`. 
- **Onde rodar**: Sempre na raiz do projeto (`Sistema-de-gestão-RG`).
- **Se estiver em public**: Digite `cd ..` antes de rodar o comando.

### 2.2 Erro 404 ao carregar a página
O servidor PHP, por padrão, procura um arquivo chamado `index.php`. Se ele não existir, você verá "No such file or directory".
- **Solução Aplicada**: Criamos o arquivo `public/index.php` que redireciona automaticamente para `login.php`.

## 3. Comando Correto para Rodar
Para iniciar o servidor, certifique-se de estar na raiz do projeto e execute:

```powershell
php -S localhost:8080 -t public
```

### Explicação do Comando:
- `php`: Chama o executável do PHP.
- `-S localhost:8080`: Inicia o servidor embutido escutando no endereço local (`localhost`) e na porta `8080`.
- `-t public`: Define o diretório `public` como a "raiz do servidor" (document root). Isso é crucial porque nossos arquivos principais (`login.php`, `dashboard.php`) e pastas de assets estão dentro de `public`.

## 3. Fluxo Técnico e Lógico
### Como o PHP processa a requisição:
1. **Entrada**: Quando você acessa `http://localhost:8080`, o PHP procura por um arquivo `index.php` ou `index.html` na pasta `public`. Como não temos um `index.php`, você deve acessar diretamente:
   - `http://localhost:8080/login.php`
2. **Assets**: As tags HTML `<link href="css/style.css">` funcionarão corretamente porque o servidor entende que a pasta `public` é o ponto de partida.
3. **Escalabilidade**: Ao usar a flag `-t public`, protegemos os arquivos de lógica (`/src/`) de serem acessados diretamente pelo navegador, aumentando a segurança.

## 4. Ferramentas e Variáveis
- **Ferramenta**: PHP Built-in Server (útil para desenvolvimento rápido sem necessidade de configurar Apache/Nginx).
- **Variáveis de Ambiente**: O PHP utiliza a porta configurada no comando. Certifique-se de que a porta `8080` não esteja ocupada por outro serviço.

## 5. Resumo de Diretórios
- `/public`: Contém os pontos de entrada do usuário.
- `/src`: Contém a "inteligência" do sistema (Classes PHP, Conexão com Banco).
- `/assets`: Imagens e arquivos estáticos.

---
*Este guia foi gerado para facilitar o estudo e garantir que o fluxo de execução seja compreendido "tintim por tintim".*
