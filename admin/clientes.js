// ARQUIVO NOVO: admin/clientes.js
document.addEventListener('DOMContentLoaded', () => {
    const API_URL = '../api/api_admin_clientes.php';
    const tableBody = document.getElementById('clientsTableBody');
    const deleteModal = document.getElementById('deleteModal');
    const closeBtns = deleteModal.querySelectorAll('.close-btn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const clientNameToDeleteSpan = document.getElementById('clientNameToDelete');

    let clientIdToDelete = null;

    // Função para buscar e renderizar os clientes
    const fetchClients = async () => {
        try {
            const response = await fetch(API_URL);
            if (!response.ok) throw new Error('Falha ao carregar clientes.');
            
            const clients = await response.json();
            renderClients(clients);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="4">${error.message}</td></tr>`;
        }
    };

    // Função para exibir os clientes na tabela
    const renderClients = (clients) => {
        tableBody.innerHTML = '';
        if (!clients || clients.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4">Nenhum cliente cadastrado.</td></tr>';
            return;
        }

        clients.forEach(client => {
            const row = document.createElement('tr');
            const formattedDate = new Date(client.data_cadastro).toLocaleDateString('pt-BR');
            row.innerHTML = `
                <td>${client.nome_completo}</td>
                <td>${client.email}</td>
                <td>${formattedDate}</td>
                <td class="actions-cell">
                    <button class="delete-btn" data-id="${client.id_cliente}" data-name="${client.nome_completo}">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
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
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const name = deleteBtn.dataset.name;
            openDeleteModal(id, name);
        }
    });

    closeBtns.forEach(btn => btn.addEventListener('click', closeDeleteModal));
    confirmDeleteBtn.addEventListener('click', deleteClient);
    window.addEventListener('click', (e) => {
        if (e.target == deleteModal) {
            closeDeleteModal();
        }
    });

    // Inicialização
    fetchClients();
});
