// ARQUIVO: admin/auth.js
// -----------------------------------------------------------------
// Gerencia a autenticação e proteção de rotas.

// URL da API de autenticação
const AUTH_API_URL = '../api/api_admin_auth.php';

// Verifica se estamos na página de login ou em outra página do painel
const isLoginPage = window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('admin/');

// --- PROTEÇÃO DE ROTA ---
// Se não estiver logado e não estiver na página de login, redireciona para o login.
if (!sessionStorage.getItem('admin_nome') && !isLoginPage) {
    window.location.href = 'index.html';
}

// Se estiver logado e tentar acessar a página de login, redireciona para o dashboard.
if (sessionStorage.getItem('admin_nome') && isLoginPage) {
    window.location.href = 'dashboard.html';
}


document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');

    // Se o formulário de login existir na página, adiciona o listener.
    if (loginForm) {
        const errorMessage = document.getElementById('errorMessage');
        const btnText = document.getElementById('btn-text');
        const btnLoader = document.getElementById('btn-loader');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // Impede o recarregamento da página

            // Mostra o loader e desabilita o botão
            btnText.classList.add('hidden');
            btnLoader.classList.remove('hidden');
            loginForm.querySelector('button').disabled = true;
            errorMessage.textContent = '';

            const formData = new FormData(loginForm);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(AUTH_API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email: data.email, senha: data.senha })
                });

                const result = await response.json();

                if (response.ok) {
                    // Armazena o nome do admin no sessionStorage para manter o estado de login
                    sessionStorage.setItem('admin_nome', result.admin_nome);
                    window.location.href = 'dashboard.html'; // Redireciona para o painel
                } else {
                    errorMessage.textContent = result.message || 'Ocorreu um erro.';
                }

            } catch (error) {
                console.error('Erro de rede:', error);
                errorMessage.textContent = 'Erro de conexão. Tente novamente.';
            } finally {
                // Esconde o loader e reabilita o botão
                btnText.classList.remove('hidden');
                btnLoader.classList.add('hidden');
                loginForm.querySelector('button').disabled = false;
            }
        });
    }
});