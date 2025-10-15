// ARQUIVO: cart.js (CORRIGIDO E PADRONIZADO COM localStorage)
document.addEventListener('DOMContentLoaded', () => {
    const cartContainer = document.getElementById('cart-container');
    const cartSummary = document.getElementById('cart-summary');
    const totalPriceEl = document.getElementById('total-price');
    const checkoutBtn = document.getElementById('checkout-btn');

    const API_CRIAR_ENCOMENDA = 'api/api_encomenda_criar.php';
    const SITE_INFO_API_URL = 'api/api_site_info.php';

    const renderCart = () => {
        // CORREÇÃO DEFINITIVA: Usar localStorage para o carrinho
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
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

    const updateQuantity = (id, quantity) => {
        // CORREÇÃO DEFINITIVA: Usar localStorage para o carrinho
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        const itemIndex = cart.findIndex(item => item.id == id);
        if (itemIndex > -1 && quantity > 0) {
            cart[itemIndex].quantity = quantity;
        } else if (itemIndex > -1) { // Se a quantidade for 0 ou menor, remove o item
            cart.splice(itemIndex, 1);
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        renderCart();
        updateCartCounter();
    };

    const removeFromCart = (id) => {
        // CORREÇÃO DEFINITIVA: Usar localStorage para o carrinho
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        cart = cart.filter(item => item.id != id);
        localStorage.setItem('cart', JSON.stringify(cart));
        renderCart();
        updateCartCounter();
    };

    checkoutBtn.addEventListener('click', async () => {
        // CORREÇÃO DEFINITIVA: Usar localStorage para o cliente e o carrinho
        const cliente = JSON.parse(localStorage.getItem('cliente'));
        const cart = JSON.parse(localStorage.getItem('cart')) || [];

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

            // ADICIONADO PARÂMETRO PARA EVITAR CACHE
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
                // CORREÇÃO: Limpar o carrinho do localStorage
                localStorage.removeItem('cart');
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
