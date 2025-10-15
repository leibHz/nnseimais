// Ficheiro: veronesi-site/admin/notificacoes.js (COM DIAGNÓSTICO AVANÇADO)

document.addEventListener('DOMContentLoaded', function() {
    console.log('Script de notificações carregado.');

    const form = document.getElementById('notification-form');
    const sendButton = document.getElementById('send-button');
    const statusMessage = document.getElementById('status-message');

    if (!form || !sendButton || !statusMessage) {
        console.error('ERRO: Elementos do formulário não encontrados.');
        return;
    }

    sendButton.addEventListener('click', async function(event) {
        event.preventDefault();
        console.log('Botão de envio clicado.');

        const title = document.getElementById('title').value;
        const message = document.getElementById('message').value;

        if (!title || !message) {
            alert('Por favor, preencha o título e a mensagem.');
            return;
        }

        sendButton.disabled = true;
        sendButton.textContent = 'Enviando...';
        statusMessage.className = '';
        statusMessage.textContent = '';
        statusMessage.style.display = 'none';

        const adminToken = sessionStorage.getItem('admin_nome');
        if (!adminToken) {
            alert('Sessão de administrador inválida. Por favor, faça login novamente.');
            window.location.href = 'index.html';
            return;
        }

        try {
            const response = await fetch('../api/api_enviar_notificacao_massa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + adminToken
                },
                body: JSON.stringify({ title: title, body: message }),
            });
            
            // PASSO 1: Obter a resposta como TEXTO para inspeção
            const responseText = await response.text();
            console.log('Resposta bruta do servidor:', responseText);

            let result;
            try {
                // PASSO 2: Tentar analisar o texto como JSON
                result = JSON.parse(responseText);
            } catch (e) {
                // PASSO 3: Se a análise falhar, é porque recebemos HTML/texto de erro
                // Lançamos um erro com o conteúdo da resposta para que ele seja exibido
                throw new Error("O servidor retornou uma resposta inválida. Detalhe: " + responseText.substring(0, 300));
            }

            if (response.ok) {
                statusMessage.className = 'success';
                statusMessage.textContent = result.message;
                form.reset();
            } else {
                // Se for um JSON de erro válido, usamos a mensagem dele
                throw new Error(result.message || `Erro do servidor: ${response.status}`);
            }

        } catch (error) {
            console.error('Falha na operação de fetch:', error);
            statusMessage.className = 'error';
            // Exibe a mensagem de erro detalhada que capturamos
            statusMessage.textContent = 'Falha ao enviar notificação: ' + error.message;
        } finally {
            sendButton.disabled = false;
            sendButton.textContent = 'Enviar para Todos';
            statusMessage.style.display = 'block';
        }
    });
});

