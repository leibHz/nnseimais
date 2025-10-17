// ARQUIVO: admin/clientes.js (CORRIGIDO COM BOTÃO DE NOTIFICAÇÕES)
document.addEventListener('DOMContentLoaded', () => {
    const API_URL = '../api/api_admin_clientes.php';
    const API_NOTIF_URL = '../api/api_enviar_notificacao_individual.php';
    const API_COUNT_NOTIF_URL = '../api/api_count_notificacoes_cliente.php';
    
    const tableBody = document.getElementById('clientsTableBody');
    
    // Modal de exclusão
    const deleteModal = document.getElementById('deleteModal');
    const deleteCloseBtns = deleteModal.querySelectorAll('.close-btn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const clientNameToDeleteSpan = document.getElementById('clientNameToDelete');
    
    // Modal de notificação
    const notificationModal = document.getElementById('notificationModal');
    const notifCloseBtns = notificationModal.querySelectorAll('.close-btn');
    const sendNotifBtn = document.getElementById('sendNotifBtn');
    const clientNameNotif = document.getElementById('clientNameNotif');
    const clientIdNotif = document.getElementById('clientIdNotif');
    const notifTitle = document.getElementById('notifTitle');
    const notifBody = document.getElementById('notifBody');

    let clientIdToDelete = null;

    // Função para buscar e renderizar os clientes
    const fetchClients = async () => {
        try {
            const response = await fetch(API_URL);
            if (!response.ok) throw new Error('Falha ao carregar clientes.');
            
            const clients = await response.json();
            await renderClients(clients);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5">${error.message}</td></tr>`;
        }
    };

    // Função para contar notificações de um cliente
    const countNotifications = async (clientId) => {
        try {
            const response = await fetch(`${API_COUNT_NOTIF_URL}?id_cliente=${clientId}`);
            const data = await response.json();
            return data.count || 0;
        } catch (error) {
            console.error('Erro ao contar notificações:', error);
            return 0;
        }
    };

    // Função para exibir os clientes na tabela
    const renderClients = async (clients) => {
        tableBody.innerHTML = '';
        if (!clients || clients.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5">Nenhum cliente cadastrado.</td></tr>';
            return;
        }

        for (const client of clients) {
            const row = document.createElement('tr');
            const formattedDate = new Date(client.data_cadastro).toLocaleDateString('pt-BR');
            
            // Busca a contagem de notificações
            const notifCount = await countNotifications(client.id_cliente);
            const notifBadge = notifCount > 0 
                ? `<span class="notif-badge">${notifCount} dispositivo${notifCount > 1 ? 's' : ''}</span>`
                : '<span class="notif-badge notif-none">Nenhum</span>';
            
            row.innerHTML = `
                <td>${client.nome_completo}</td>
                <td>${client.email}</td>
                <td>${formattedDate}</td>
                <td>${notifBadge}</td>
                <td class="actions-cell">
                    <button class="notify-btn" data-id="${client.id_cliente}" data-name="${client.nome_completo}" ${notifCount === 0 ? 'disabled' : ''} title="${notifCount === 0 ? 'Cliente não habilitou notificações' : 'Enviar notificação'}">
                        <i class="fa-solid fa-bell"></i>
                    </button>
                    <button class="delete-btn" data-id="${client.id_cliente}" data-name="${client.nome_completo}">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        }
    };

    // Funções do modal de exclusão
    const openDeleteModal = (id, name) => {
        clientIdToDelete = id;
        clientNameToDeleteSpan.textContent = name;
        deleteModal.style.display = 'block';
    };

    const closeDeleteModal = () => {
        clientIdToDelete = null;
        deleteModal.style.display = 'none';
    };

    // Funções do modal de notificação
    const openNotificationModal = (id, name) => {
        clientIdNotif.value = id;
        clientNameNotif.textContent = name;
        notifTitle.value = '';
        notifBody.value = '';
        notificationModal.style.display = 'block';
    };

    const closeNotificationModal = () => {
        notificationModal.style.display = 'none';
    };

    // Função para enviar notificação
    const sendNotification = async () => {
        const clientId = clientIdNotif.value;
        const title = notifTitle.value.trim();
        const body = notifBody.value.trim();

        if (!title || !body) {
            alert('Por favor, preencha o título e a mensagem.');
            return;
        }

        try {
            sendNotifBtn.disabled = true;
            sendNotifBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

            const adminToken = sessionStorage.getItem('admin_nome');
            const response = await fetch(API_NOTIF_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + adminToken
                },
                body: JSON.stringify({
                    id_cliente: parseInt(clientId),
                    title: title,
                    body: body
                })
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                alert(`✅ ${result.message}\n\n✅ Enviado para ${result.total_devices} dispositivo(s)\n❌ Falhas: ${result.error_count}`);
                closeNotificationModal();
            } else {
                throw new Error(result.message || 'Erro ao enviar notificação');
            }

        } catch (error) {
            alert('❌ Erro ao enviar notificação: ' + error.message);
        } finally {
            sendNotifBtn.disabled = false;
            sendNotifBtn.innerHTML = '<i class="fa-solid fa-bell"></i> Enviar Notificação';
        }
    };

    // Função para deletar o cliente
    const deleteClient = async () => {
        if (!clientIdToDelete) return;

        try {
            const response = await fetch(API_URL, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_cliente: clientIdToDelete })
            });

            const result = await response.json();
            if (result.status === 'success') {
                closeDeleteModal();
                fetchClients(); // Atualiza a lista
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            alert('Erro ao excluir cliente: ' + error.message);
        }
    };

    // Event Listeners
    tableBody.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.delete-btn');
        const notifyBtn = e.target.closest('.notify-btn');
        
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const name = deleteBtn.dataset.name;
            openDeleteModal(id, name);
        }
        
        if (notifyBtn && !notifyBtn.disabled) {
            const id = notifyBtn.dataset.id;
            const name = notifyBtn.dataset.name;
            openNotificationModal(id, name);
        }
    });

    // Event listeners dos modais
    deleteCloseBtns.forEach(btn => btn.addEventListener('click', closeDeleteModal));
    notifCloseBtns.forEach(btn => btn.addEventListener('click', closeNotificationModal));
    confirmDeleteBtn.addEventListener('click', deleteClient);
    sendNotifBtn.addEventListener('click', sendNotification);

    window.addEventListener('click', (e) => {
        if (e.target == deleteModal) closeDeleteModal();
        if (e.target == notificationModal) closeNotificationModal();
    });

    // Inicialização
    fetchClients();
});