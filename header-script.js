// ARQUIVO: header-script.js
// Script centralizado para gerenciar o cabeçalho de navegação

(function() {
    function atualizarHeader() {
        const cliente = StorageManager.getCliente();
        const navHTML = cliente
            ? `<a href="minha-conta.html" class="nav-link"><i class="fa-solid fa-user"></i> Minha Conta</a>
               <a href="#" id="logoutBtn" class="nav-link"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>`
            : `<a href="login.html" class="nav-link"><i class="fa-solid fa-right-to-bracket"></i> Entrar</a>`;
        
        document.addEventListener('DOMContentLoaded', () => {
            const navContainer = document.querySelector('.main-nav');
            if (navContainer) {
                navContainer.innerHTML = `
                    ${navHTML}
                    <a href="encomenda.html" class="nav-link cart-link">
                        <i class="fa-solid fa-cart-shopping"></i> Encomenda
                        <span id="cart-counter" class="cart-counter">0</span>
                    </a>
                `;
                
                if (cliente) {
                    const logoutBtn = document.getElementById('logoutBtn');
                    if (logoutBtn) {
                        logoutBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            StorageManager.clearAll();
                            window.location.href = 'index.html';
                        });
                    }
                }
            }
        });
    }
    
    atualizarHeader();
})();