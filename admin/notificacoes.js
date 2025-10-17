// Ficheiro: admin/notificacoes.js (VERSÃO CORRIGIDA)

document.addEventListener('DOMContentLoaded', function() {
    console.log('Script de notificações carregado.');

    const form = document.getElementById('notification-form');
    const sendButton = document.getElementById('send-button');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message-box');
    const successText = document.getElementById('success-text');
    const errorText = document.getElementById('error-text');

    if (!form || !sendButton) {
        console.error('ERRO: Elementos do formulário não encontrados.');
        return;
    }

    sendButton.addEventListener('click', async function(event) {
        event.preventDefault();
        console.log('Botão de envio clicado.');

        const title = document.getElementById('title').value.trim();
        const message = document.getElementById('message').value.trim();

        if (!title || !message) {
            showError('Por favor, preencha o título e a mensagem.');
            return;
        }

        // Desabilita botão e mostra loading
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';
        hideMessages();

        const adminToken = sessionStorage.getItem('admin_nome');
        if (!adminToken) {
            showError('Sessão de administrador inválida. Por favor, faça login novamente.');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
            return;
        }

        try {
            console.log('Enviando requisição para:', '../api/api_enviar_notificacao_massa.php');
            console.log('Payload:', { title, body: message });

            const response = await fetch('../api/api_enviar_notificacao_massa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + adminToken
                },
                body: JSON.stringify({ 
                    title: title, 
                    body: message 
                }),
            });
            
            console.log('Status da resposta:', response.status);
            
            // Obter a resposta como texto primeiro
            const responseText = await response.text();
            console.log('Resposta bruta do servidor:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('Erro ao parsear JSON:', e);
                throw new Error("O servidor retornou uma resposta inválida. Verifique os logs do PHP. Resposta: " + responseText.substring(0, 200));
            }

            if (response.ok) {
                showSuccess(result.message || 'Notificações enviadas com sucesso!');
                form.reset();
                
                // Atualiza os contadores se existirem
                if (result.success_count !== undefined) {
                    showSuccess(`✅ Enviado com sucesso: ${result.success_count} notificações\n❌ Falhas: ${result.error_count || 0}`);
                }
            } else {
                throw new Error(result.message || `Erro do servidor: ${response.status}`);
            }

        } catch (error) {
            console.error('Falha na operação de fetch:', error);
            showError('Falha ao enviar notificação: ' + error.message);
        } finally {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar para Todos';
        }
    });

    function showSuccess(message) {
        hideMessages();
        successText.textContent = message;
        successMessage.style.display = 'block';
        
        // Auto-esconde após 5 segundos
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }

    function showError(message) {
        hideMessages();
        errorText.textContent = message;
        errorMessage.style.display = 'block';
        
        // Auto-esconde após 8 segundos (erros ficam mais tempo)
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 8000);
    }

    function hideMessages() {
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';
    }
});