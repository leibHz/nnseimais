// ARQUIVO: verificacao.js (ATUALIZADO COM STORAGEMANAGER)
document.addEventListener('DOMContentLoaded', () => {
    const verificationForm = document.getElementById('verificationForm');
    const userEmailEl = document.getElementById('userEmail');
    const errorMessageEl = document.getElementById('errorMessage');

    const email = StorageManager.getTemporary('email_para_verificacao');

    if (!email) {
        window.location.href = 'cadastro.html';
        return;
    }
    userEmailEl.textContent = email;

    verificationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorMessageEl.textContent = '';

        const codigo = document.getElementById('codigo').value;

        if (!codigo || codigo.length !== 6) {
            errorMessageEl.textContent = 'Por favor, insira um código válido de 6 dígitos.';
            return;
        }

        try {
            const response = await fetch('api/api_cliente_verificar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, codigo: codigo })
            });

            const result = await response.json();

            if (response.ok) {
                alert('Conta verificada com sucesso! Você será redirecionado para a página de login.');
                StorageManager.removeTemporary('email_para_verificacao');
                window.location.href = 'login.html';
            } else {
                errorMessageEl.textContent = result.message;
            }

        } catch (error) {
            errorMessageEl.textContent = 'Erro de conexão. Tente novamente mais tarde.';
        }
    });
});