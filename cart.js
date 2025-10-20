// ARQUIVO: cart.js (CORRIGIDO - BUG 4)
document.addEventListener('DOMContentLoaded', () => {
    const cartContainer = document.getElementById('cart-container');
    const cartSummary = document.getElementById('cart-summary');
    const totalPriceEl = document.getElementById('total-price');
    const checkoutBtn = document.getElementById('checkout-btn');

    const API_CRIAR_ENCOMENDA = 'api/api_encomenda_criar.php';
    const SITE_INFO_API_URL = 'api/api_site_info.php';

    const renderCart = () => {
        const cart = StorageManager.getCarrinho();
        cartContainer.innerHTML = '';

        if (cart.length === 0) {
            cartContainer.innerHTML = '<p class="empty-cart-message">Sua encomenda está vazia.</p>';
            cartSummary.style.display = 'none';
            return;
        }

        cartSummary.style.display = 'block';
        let total = 0;

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;

            const cartItem = document.createElement('div');
            cartItem.className = 'cart-item';
            cartItem.innerHTML = `
                <img src="${item.image || 'https://placehold.co/80x80/1e1e1e/ffffff?text=Produto'}" alt="${item.name}" class="cart-item-img">
                <div class="cart-item-info">
                    <h4>${item.name}</h4>
                    <p class="cart-item-price">${formatCurrency(item.price)} / ${item.unit}</p>
                </div>
                <div class="cart-item-actions">
                    <input type="number" class="quantity-input" value="${item.quantity}" min="${item.unit === 'kg' ? '0.1' : '1'}" step="${item.unit === 'kg' ? '0.1' : '1'}" data-id="${item.id}">
                    <button class="remove-btn" data-id="${item.id}"><i class="fa-solid fa-trash-can"></i></button>
                </div>
            `;
            cartContainer.appendChild(cartItem);
        });

        totalPriceEl.textContent = formatCurrency(total);
    };

    const formatCurrency = (value) => {
        return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    cartContainer.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.remove-btn');
        if (removeBtn) {
            removeFromCart(removeBtn.dataset.id);
        }
    });

    cartContainer.addEventListener('change', (e) => {
        const quantityInput = e.target.closest('.quantity-input');
        if (quantityInput) {
            updateQuantity(quantityInput.dataset.id, parseFloat(quantityInput.value));
        }
    });

    // ✅ CORREÇÃO BUG 4: Validação completa de quantidade
    const updateQuantity = (id, quantity) => {
        let cart = StorageManager.getCarrinho();
        const itemIndex = cart.findIndex(item => item.id == id);
        
        if (itemIndex === -1) return; // Item não encontrado
        
        const item = cart[itemIndex];
        const isKg = item.unit === 'kg';
        
        // ✅ VALIDAÇÃO: Verifica limites mínimos e máximos
        const minQty = isKg ? 0.1 : 1;
        const maxQty = isKg ? 50 : 999; // Limites razoáveis
        
        if (quantity < minQty) {
            alert(`Quantidade mínima: ${minQty} ${item.unit}`);
            renderCart(); // Restaura valor anterior
            return;
        }
        
        if (quantity > maxQty) {
            alert(`Quantidade máxima: ${maxQty} ${item.unit}`);
            renderCart();
            return;
        }
        
        // ✅ VALIDAÇÃO: Para kg, arredonda para 1 casa decimal
        if (isKg) {
            quantity = Math.round(quantity * 10) / 10;
        } else {
            quantity = Math.floor(quantity); // Para unidades, garante inteiro
        }
        
        // ✅ VALIDAÇÃO: Verifica se a quantidade é válida após processamento
        if (isNaN(quantity) || quantity <= 0) {
            alert('Quantidade inválida');
            renderCart();
            return;
        }
        
        cart[itemIndex].quantity = quantity;
        StorageManager.setCarrinho(cart);
        renderCart();
        updateCartCounter();
    };

    const removeFromCart = (id) => {
        let cart = StorageManager.getCarrinho();
        cart = cart.filter(item => item.id != id);
        StorageManager.setCarrinho(cart);
        renderCart();
        updateCartCounter();
    };

    checkoutBtn.addEventListener('click', async () => {
        const cliente = StorageManager.getCliente();
        const cart = StorageManager.getCarrinho();

        if (!cliente) {
            alert('Você precisa estar logado para finalizar a encomenda.');
            window.location.href = 'login.html?redirect=encomenda.html';
            return;
        }

        if (cart.length === 0) {
            alert('Sua encomenda está vazia.');
            return;
        }

        try {
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Verificando...';

            const statusResponse = await fetch(`${SITE_INFO_API_URL}?_=${new Date().getTime()}`);
            const siteConfig = await statusResponse.json();

            if (!siteConfig || !siteConfig.pode_encomendar) {
                throw new Error(siteConfig.mensagem_loja_real || 'A loja está fechada para encomendas no momento.');
            }

            checkoutBtn.textContent = 'Processando...';
            const orderData = {
                id_cliente: cliente.id_cliente,
                itens: cart.map(item => ({ id: item.id, quantity: item.quantity, price: item.price }))
            };

            const response = await fetch(API_CRIAR_ENCOMENDA, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            });

            const result = await response.json();
            if (response.ok) {
                alert('Encomenda realizada com sucesso!');
                StorageManager.removeCarrinho();
                window.location.href = 'minha-conta.html';
            } else {
                throw new Error(result.message || 'Ocorreu um erro desconhecido.');
            }
        } catch (error) {
            alert(`Erro ao finalizar a encomenda: ${error.message}`);
            checkoutBtn.disabled = false;
            checkoutBtn.textContent = 'Finalizar Encomenda';
        }
    });

    renderCart();
});