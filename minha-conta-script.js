// ARQUIVO: minha-conta-script.js (ATUALIZADO COM STORAGEMANAGER)
// Script para a página minha-conta.html

document.addEventListener('DOMContentLoaded', () => {
    const cliente = StorageManager.getCliente();
    const userNameEl = document.getElementById('userName');
    const orderListEl = document.getElementById('order-list');
    
    // Função para verificar se a sessão do cliente ainda é válida
    async function checkSession() {
        if (!cliente || !cliente.id_cliente) {
            logout();
            return;
        }
        try {
            const response = await fetch(`api/api_cliente_check_session.php?id_cliente=${cliente.id_cliente}`);
            const result = await response.json();
            if (!result.valid) {
                alert("Sua sessão expirou ou a conta foi removida. Por favor, faça login novamente.");
                logout();
            }
        } catch (error) {
            console.error("Erro ao verificar sessão, desconectando por segurança.");
            logout();
        }
    }

    function logout() {
        StorageManager.clearAll();
        window.location.href = 'login.html';
    }

    if (cliente) {
        userNameEl.textContent = cliente.nome_completo;
        
        // Verifica a sessão ao carregar e depois a cada 20 segundos
        checkSession();
        fetchOrderHistory(cliente.id_cliente);

        setInterval(() => {
            checkSession();
            fetchOrderHistory(cliente.id_cliente);
        }, 20000);
    }

    async function fetchOrderHistory(clienteId) {
        if (!orderListEl.innerHTML.trim() && !orderListEl.querySelector('.loading-spinner')) {
            orderListEl.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';
        }
        
        try {
            const response = await fetch(`api/api_encomenda_historico.php?id_cliente=${clienteId}&_=${new Date().getTime()}`);
            if (!response.ok) throw new Error('Falha ao buscar o histórico.');
            
            const orders = await response.json();
            const spinner = orderListEl.querySelector('.loading-spinner');
            if (spinner) spinner.remove();

            renderOrderHistory(orders);
        } catch (error) {
            orderListEl.innerHTML = `<p class="error-message">Não foi possível carregar seu histórico.</p>`;
        }
    }

    function renderOrderHistory(orders) {
        if (!orders || orders.length === 0) {
            orderListEl.innerHTML = '<p class="empty-message">Você ainda não fez nenhuma encomenda.</p>';
            return;
        }

        const processedOrderIds = new Set();

        orders.forEach(order => {
            processedOrderIds.add(String(order.id_encomenda));
            let orderCard = orderListEl.querySelector(`.order-card[data-order-id='${order.id_encomenda}']`);

            if (!orderCard) {
                orderCard = document.createElement('div');
                orderCard.className = 'order-card';
                orderCard.dataset.orderId = order.id_encomenda;
                orderListEl.insertBefore(orderCard, orderListEl.firstChild);
                
                const formattedDate = new Date(order.data_encomenda).toLocaleString('pt-BR', { 
                    day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' 
                });
                const formattedTotal = parseFloat(order.valor_total).toLocaleString('pt-BR', { 
                    style: 'currency', currency: 'BRL' 
                });
                const statusText = order.status.replace('_', ' ');

                let itemsHtml = '<ul class="order-item-list">';
                order.encomenda_itens.forEach(item => {
                    const productName = item.produtos ? item.produtos.nome : 'Produto indisponível';
                    const imageUrl = item.produtos ? item.produtos.imagem_url : 'https://placehold.co/50x50';
                    itemsHtml += `
                        <li class="order-item">
                            <img src="${imageUrl}" alt="${productName}" onerror="this.src='https://placehold.co/50x50/e53935/ffffff?text=X'">
                            <div class="order-item-details">
                                <span>${item.quantidade}x ${productName}</span>
                            </div>
                        </li>
                    `;
                });
                itemsHtml += '</ul>';
                
                let justificationHtml = '';
                if (order.status === 'cancelada' && order.justificativa_cancelamento) {
                    justificationHtml = `<div class="order-justification"><p><strong>Motivo do Cancelamento:</strong> ${order.justificativa_cancelamento}</p></div>`;
                }

                orderCard.innerHTML = `
                    <div class="order-header">
                        <div class="order-header-info"><span>Encomenda</span><strong>#${order.id_encomenda}</strong></div>
                        <div class="order-header-info"><span>Data</span><strong>${formattedDate}</strong></div>
                        <div class="order-header-info"><span>Total</span><strong>${formattedTotal}</strong></div>
                        <div class="order-status status-${order.status}">${statusText}</div>
                    </div>
                    <div class="order-body">
                        ${itemsHtml}
                        ${justificationHtml}
                    </div>
                `;
            } else {
                // Atualiza apenas o status se mudou
                const statusEl = orderCard.querySelector('.order-status');
                const newStatusClass = `status-${order.status}`;
                const newStatusText = order.status.replace('_', ' ');

                if (statusEl && !statusEl.classList.contains(newStatusClass)) {
                    statusEl.className = `order-status ${newStatusClass}`;
                    statusEl.textContent = newStatusText;

                    let justificationEl = orderCard.querySelector('.order-justification');
                    if (order.status === 'cancelada' && order.justificativa_cancelamento && !justificationEl) {
                        const justificationHtml = `<div class="order-justification"><p><strong>Motivo do Cancelamento:</strong> ${order.justificativa_cancelamento}</p></div>`;
                        orderCard.querySelector('.order-body').insertAdjacentHTML('beforeend', justificationHtml);
                    }
                }
            }
        });
        
        // Remove cards que não existem mais
        orderListEl.querySelectorAll('.order-card').forEach(card => {
            if (!processedOrderIds.has(card.dataset.orderId)) {
                card.remove();
            }
        });
    }
});