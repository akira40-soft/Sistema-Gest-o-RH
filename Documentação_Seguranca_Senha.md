# Ensaio Técnico: Implementação de Registro e Segurança de Senha

Este documento descreve detalhadamente a implementação técnica da nova funcionalidade de registro e o sistema de validação de senha forte do Sistema de Gestão de RH.

## 1. Visão Geral do Fluxo
O objetivo foi transformar a página de login em uma interface dual (Login/Registro) sem recarregar a página, mantendo a estética Glassmorphism e adicionando camadas de segurança proativa no front-end.

## 2. Abordagem Técnica e Ferramentas

### 2.1 Alternância Dinâmica (Toggle Logic)
- **Método**: Manipulação do DOM via JavaScript.
- **Lógica**: Os formulários `#loginForm` e `#registerForm` alternam sua propriedade `display` entre `none` e `block`. 
- **User Experience**: Adicionamos a classe `fadeIn` para garantir que a troca não seja brusca, mantendo a sensação de "fluidez neon".

### 2.2 Algoritmo de Força de Senha (Strength Meter)
Para garantir a "senha forte" solicitada, implementamos um motor de análise em tempo real:
- **Variáveis de Controle**: Uma pontuação de 0 a 4 baseada em:
  1. Comprimento (>= 8 caracteres).
  2. Presença de Letras Maiúsculas.
  3. Presença de Números.
  4. Presença de Caracteres Especiais (`!@#$%^&*` etc).
- **Feedback Visual**: 
  - `0-1`: Vermelho (Fraca).
  - `2`: Amarelo (Média).
  - `3`: Verde (Boa).
  - `4`: Neon Green (Forte).

### 2.3 Animações de Erro e Feedback (Shake Effect)
Caso o usuário tente submeter uma senha com força inferior a 3 (Boa):
- **Logica**: O evento `submit` é interceptado via `e.preventDefault()`.
- **Animação**: Aplicamos a classe `.shake` via JS. Para permitir que a animação rode múltiplas vezes, usamos o truque `void container.offsetWidth` para "forçar" o navegador a renderizar o elemento novamente antes de aplicar a classe.

## 3. Variáveis e Estilos CSS
- `--neon-green`: Utilizada como o ápice da segurança (Senha Forte).
- `.password-strength-container`: Um container de vidro (Glassmorphism) que serve de trilho para a barra de progresso.
- `@keyframes shake`: Define a vibração lateral de 5px em 10 intervalos, criando a sensação visual de "acesso negado".

## 4. Escalabilidade e Debug
- O código JS foi organizado por sessões (Toggle, Validation, Submission) para facilitar futuras integrações com o backend PHP e MySQL.
- Os IDs do DOM são únicos e descritivos (`reg_password`, `strengthMeter`, etc), evitando colisões.

---
*Este ensaio foi desenvolvido para garantir que o fluxo lógico e técnico seja transparente para o estudo do desenvolvedor.*
