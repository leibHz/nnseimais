// ARQUIVO: auth_cliente.js (ATUALIZADO COM STORAGEMANAGER)
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const cadastroForm = document.getElementById('cadastroForm');

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorMessageEl = document.getElementById('errorMessage');
            errorMessageEl.textContent = '';

            try {
                const response = await fetch('api/api_cliente_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.fromEntries(new FormData(loginForm).entries()))
                });
                const result = await response.json();
                if (!response.ok) throw result;

                // Usa StorageManager para salvar dados do cliente
                StorageManager.setCliente(result.cliente);
                
                // Lógica de redirecionamento inteligente
                const params = new URLSearchParams(window.location.search);
                const redirectUrl = params.get('redirect');
                
                window.location.href = redirectUrl || 'index.html';

            } catch (error) {
                errorMessageEl.textContent = error.message || 'Erro de conexão. Tente novamente.';
            }
        });
    }

    if (cadastroForm) {
        cadastroForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorMessageEl = document.getElementById('errorMessage');
            const submitButton = cadastroForm.querySelector('.auth-btn');
            errorMessageEl.textContent = '';
            
            const formData = new FormData(cadastroForm);
            const data = Object.fromEntries(formData.entries());

            if (data.senha !== data.confirmar_senha) {
                errorMessageEl.textContent = 'As senhas não coincidem.';
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Cadastrando...';

            try {
                const response = await fetch('api/api_cliente_cadastro.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const responseText = await response.text();
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    throw new Error("O servidor respondeu de forma inesperada. Detalhe: " + responseText.substring(0, 200));
                }

                if (!response.ok) {
                    throw result;
                }
                
                // Usa StorageManager para dados temporários
                StorageManager.setTemporary('email_para_verificacao', data.email);
                window.location.href = 'verificacao.html';

            } catch (error) {
                let displayMessage = 'Ocorreu um erro. Tente novamente.';
                if (error && (error.message || error.error_details)) {
                    displayMessage = error.message;
                    if(error.error_details) displayMessage += ` (${error.error_details})`;
                }
                errorMessageEl.textContent = displayMessage;
                
                submitButton.disabled = false;
                submitButton.textContent = 'Cadastrar';
            }
        });
    }
});