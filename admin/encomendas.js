document.addEventListener('DOMContentLoaded', () => {
    const API_URL = '../api/api_admin_encomendas.php';
    const tableBody = document.getElementById('ordersTableBody');
    const modal = document.getElementById('orderModal');
    const closeBtns = document.querySelectorAll('.close-btn');
    const orderIdSpan = document.getElementById('orderId');
    const orderDetailsContainer = document.getElementById('orderDetails');
    const statusSelect = document.getElementById('statusSelect');
    const saveStatusBtn = document.getElementById('saveStatusBtn');
    
    // Elementos da justificativa
    const justificativaContainer = document.getElementById('justificativaContainer');
    const justificativaInput = document.getElementById('justificativaInput');

    let currentOrderId = null;

    const fetchOrders = async () => {
        try {
            const response = await fetch(API_URL);
            const orders = await response.json();
            renderOrders(orders);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6">Erro ao carregar encomendas.</td></tr>`;
        }
    };

    const renderOrders = (orders) => {
        tableBody.innerHTML = '';
        if (!orders || orders.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6">Nenhuma encomenda encontrada.</td></tr>';
            return;
        }
        orders.forEach(order => {
            const row = `
                <tr>
                    <td>#${order.id_encomenda}</td>
                    <td>${order.clientes?.nome_completo || 'Cliente Removido'}</td>
                    <td>${new Date(order.data_encomenda).toLocaleDateString('pt-BR')}</td>
                    <td>R$ ${parseFloat(order.valor_total).toFixed(2)}</td>
                    <td><span class="status status-${order.status}">${order.status.replace('_', ' ')}</span></td>
                    <td class="actions-cell">
                        <button class="view-btn" data-id="${order.id_encomenda}"><i class="fa-solid fa-pencil"></i></button>
                    </td>
                </tr>
            `;
            tableBody.insertAdjacentHTML('beforeend', row);
        });
    };

    const openModal = async (orderId) => {
        currentOrderId = orderId;
        try {
            const response = await fetch(`${API_URL}?id=${orderId}`);
            const order = await response.json();
            
            if (!order) {
                alert('Encomenda não encontrada.');
                return;
            }

            orderIdSpan.textContent = order.id_encomenda;
            statusSelect.value = order.status;
            justificativaInput.value = order.justificativa_cancelamento || ''; // Limpa ou preenche a justificativa
            
            // Mostra ou esconde o campo de justificativa baseado no status inicial
            justificativaContainer.style.display = order.status === 'cancelada' ? 'block' : 'none';

            let detailsHtml = `
                <p><strong>Cliente:</strong> ${order.clientes?.nome_completo || 'N/A'}</p>
                <p><strong>Data:</strong> ${new Date(order.data_encomenda).toLocaleString('pt-BR')}</p>
                <h4>Itens da Encomenda:</h4>
                <ul>
            `;
            order.encomenda_itens.forEach(item => {
                detailsHtml += `<li>${item.quantidade}x ${item.produtos?.nome || 'Produto Removido'} - R$ ${parseFloat(item.preco_unitario).toFixed(2)}</li>`;
            });
            detailsHtml += '</ul>';
            
            orderDetailsContainer.innerHTML = detailsHtml;
            modal.style.display = 'block';

        } catch (error) {
            alert('Erro ao buscar detalhes da encomenda.');
        }
    };

    const closeModal = () => {
        modal.style.display = 'none';
        currentOrderId = null;
    };

    const saveStatus = async () => {
        if (!currentOrderId) return;
        
        const newStatus = statusSelect.value;
        const justificativa = justificativaInput.value;

        if (newStatus === 'cancelada' && !justificativa.trim()) {
            alert('Por favor, forneça uma justificativa para o cancelamento.');
            justificativaInput.focus();
            return;
        }

        const payload = {
            id_encomenda: currentOrderId,
            status: newStatus
        };

        if (newStatus === 'cancelada') {
            payload.justificativa_cancelamento = justificativa;
        }

        try {
            const response = await fetch(API_URL, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.status === 'success') {
                closeModal();
                fetchOrders(); 
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            alert('Erro ao atualizar status: ' + error.message);
        }
    };

    // Listener para mostrar/esconder campo de justificativa
    statusSelect.addEventListener('change', () => {
        if (statusSelect.value === 'cancelada') {
            justificativaContainer.style.display = 'block';
        } else {
            justificativaContainer.style.display = 'none';
        }
    });

    tableBody.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.view-btn');
        if (viewBtn) {
            const id = viewBtn.dataset.id;
            openModal(id);
        }
    });

    closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
    saveStatusBtn.addEventListener('click', saveStatus);
    window.addEventListener('click', e => e.target == modal && closeModal());

    fetchOrders();
    
    // ADICIONADO: Atualiza a lista de encomendas automaticamente a cada 20 segundos
    setInterval(fetchOrders, 20000); 
});
