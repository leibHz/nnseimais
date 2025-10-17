// ARQUIVO: admin/dashboard.js (CORRIGIDO - VERSÃO FINAL)
document.addEventListener('DOMContentLoaded', () => {
    // Só executa se não estiver na página de login
    if (window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('admin/')) {
        return;
    }

    // CORREÇÃO 1: Buscar o elemento correto que existe no HTML
    const adminNameEl = document.getElementById('adminName');
    
    // CORREÇÃO 2: ID correto do botão de logout
    const logoutBtn = document.getElementById('logoutBtn');
    
    const adminName = sessionStorage.getItem('admin_nome');
    
    // CORREÇÃO 3: Exibir o nome do administrador se o elemento existir
    if (adminName && adminNameEl) {
        adminNameEl.textContent = adminName;
    }

    // --- LOGOUT ---
    if(logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            sessionStorage.removeItem('admin_nome');
            window.location.href = 'index.html';
        });
    }

    // --- CARREGAR ESTATÍSTICAS ---
    const fetchDashboardStats = async () => {
        const statsUrl = '../api/api_admin_dashboard.php';
        try {
            const response = await fetch(statsUrl);
            const stats = await response.json();

            // Atualiza os elementos se existirem
            const totalProdutosEl = document.getElementById('totalProdutos');
            const totalClientesEl = document.getElementById('totalClientes');
            const totalEncomendasEl = document.getElementById('totalEncomendas');

            if (totalProdutosEl) totalProdutosEl.textContent = stats.total_produtos;
            if (totalClientesEl) totalClientesEl.textContent = stats.total_clientes;
            if (totalEncomendasEl) totalEncomendasEl.textContent = stats.total_encomendas;

        } catch (error) {
            console.error('Erro ao buscar estatísticas:', error);
        }
    };

    // Chama a função para carregar os dados imediatamente
    fetchDashboardStats();
    
    // Atualiza as estatísticas a cada 30 segundos
    setInterval(fetchDashboardStats, 30000); 
});