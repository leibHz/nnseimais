// ARQUIVO: admin/dashboard.js (COM ATUALIZAÇÃO EM TEMPO REAL)
document.addEventListener('DOMContentLoaded', () => {
    // Só executa se não estiver na página de login
    if (window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('admin/')) {
        return;
    }

    const adminNameEl = document.getElementById('adminName');
    const logoutBtn = document.getElementById('logout-button'); // ID corrigido para corresponder ao HTML
    
    const adminName = sessionStorage.getItem('admin_nome');
    if (adminName) {
        // Esta parte pode ser removida se o nome não for exibido, mas mantida por segurança
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

            document.getElementById('totalProdutos').textContent = stats.total_produtos;
            document.getElementById('totalClientes').textContent = stats.total_clientes;
            document.getElementById('totalEncomendas').textContent = stats.total_encomendas;

        } catch (error) {
            console.error('Erro ao buscar estatísticas:', error);
        }
    };

    // Chama a função para carregar os dados imediatamente
    fetchDashboardStats();
    
    // Bug 5: Atualiza as estatísticas a cada 30 segundos
    setInterval(fetchDashboardStats, 30000); 
});

