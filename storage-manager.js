// ARQUIVO: storage-manager.js
// Gerenciador centralizado de armazenamento

const StorageManager = {
    // Dados do cliente (persiste entre sessões)
    setCliente(clienteData) {
        localStorage.setItem('cliente', JSON.stringify(clienteData));
    },
    
    getCliente() {
        const data = localStorage.getItem('cliente');
        return data ? JSON.parse(data) : null;
    },
    
    removeCliente() {
        localStorage.removeItem('cliente');
    },
    
    // Carrinho (persiste entre sessões)
    setCarrinho(carrinhoData) {
        localStorage.setItem('cart', JSON.stringify(carrinhoData));
    },
    
    getCarrinho() {
        const data = localStorage.getItem('cart');
        return data ? JSON.parse(data) : [];
    },
    
    removeCarrinho() {
        localStorage.removeItem('cart');
    },
    
    // Dados temporários (apenas na sessão atual)
    setTemporary(key, value) {
        sessionStorage.setItem(key, JSON.stringify(value));
    },
    
    getTemporary(key) {
        const data = sessionStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    },
    
    removeTemporary(key) {
        sessionStorage.removeItem(key);
    },
    
    // Limpar tudo
    clearAll() {
        localStorage.clear();
        sessionStorage.clear();
    },
    
    // Verificar se cliente está logado
    isLoggedIn() {
        const cliente = this.getCliente();
        return cliente !== null && cliente.id_cliente !== undefined;
    }
};

// Exporta para uso global
window.StorageManager = StorageManager;