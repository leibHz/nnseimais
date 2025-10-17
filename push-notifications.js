// Ficheiro: push-notifications.js (VERSÃO CORRIGIDA - APENAS USUÁRIOS LOGADOS)
// BE9uBAyJB7pNQWsh7U7GbvxphNlFslYQaQguc6aYxuBXbWUd_7aZy0Kq6G7wcRUTHtIw6o27vQlhCEaM7hhcfGY

// Ficheiro: push-notifications.js (VERSÃO FINAL - TODOS OS BUGS CORRIGIDOS)

const VAPID_PUBLIC_KEY = 'BE9uBAyJB7pNQWsh7U7GbvxphNlFslYQaQguc6aYxuBXbWUd_7aZy0Kq6G7wcRUTHtIw6o27vQlhCEaM7hhcfGY';

console.log('🔔 push-notifications.js carregado');

/**
 * Converte uma string base64 (URL safe) para um Uint8Array.
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Registra o Service Worker e inicializa a lógica do botão de notificação.
 */
async function initPushNotifications() {
    console.log('🚀 Inicializando sistema de notificações...');
    
    // ✅ CORREÇÃO 1: Verifica se o usuário está logado ANTES de continuar
    const cliente = JSON.parse(localStorage.getItem('cliente'));
    if (!cliente || !cliente.id_cliente) {
        console.log('❌ Usuário não está logado. Notificações desabilitadas.');
        const btn = document.getElementById('notification-prompt');
        if (btn) btn.remove();
        return;
    }
    
    console.log('✅ Usuário logado:', cliente.nome_completo);
    
    // Verifica se o navegador suporta as tecnologias necessárias
    if (!('serviceWorker' in navigator && 'PushManager' in window)) {
        console.warn('❌ Seu navegador não suporta notificações push.');
        return;
    }

    try {
        // Registra o Service Worker
        console.log('📝 Registrando Service Worker...');
        const registration = await navigator.serviceWorker.register('./service-worker.js');
        await navigator.serviceWorker.ready;
        console.log('✅ Service Worker pronto');

        // Configura o botão
        await setupNotificationButton(registration);

    } catch (error) {
        console.error('❌ Erro na inicialização do Service Worker:', error);
    }
}

/**
 * Verifica a permissão e o status da inscrição para decidir se mostra o botão.
 */
async function setupNotificationButton(registration) {
    const btn = document.getElementById('notification-prompt');
    if (!btn) {
        console.warn('⚠️ Botão #notification-prompt não encontrado no DOM.');
        return;
    }

    // ✅ CORREÇÃO BUG 1: Esconde botão se permissão foi negada
    if (Notification.permission === 'denied') {
        console.log('❌ Permissão negada permanentemente pelo usuário.');
        btn.remove();
        return;
    }

    // ✅ CORREÇÃO BUG 1: Verifica se já existe uma inscrição ativa
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
        console.log('✅ Usuário já inscrito. Botão não será exibido.');
        btn.remove(); // Remove o botão permanentemente
        return;
    }

    // ✅ CORREÇÃO BUG 1: Esconde se já concedeu permissão mas perdeu inscrição
    if (Notification.permission === 'granted') {
        console.log('🔔 Permissão concedida mas sem inscrição. Tentando reinscrever automaticamente...');
        const success = await subscribeUser(registration);
        if (success) {
            btn.remove();
            return;
        }
    }

    // Se chegou até aqui, o usuário pode se inscrever. Mostra o botão.
    console.log('🔔 Usuário não inscrito. Mostrando botão...');
    btn.classList.remove('hidden');
    btn.classList.add('show');
    
    // ✅ CORREÇÃO BUG 3: Melhora compatibilidade mobile
    // Adiciona evento de clique E de touch
    const handleClick = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('🖱️ Botão clicado');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Aguarde...</span>';
        
        const success = await subscribeUser(registration);
        
        if (success) {
            btn.remove(); // ✅ CORREÇÃO BUG 1: Remove após sucesso
        } else {
            // Se falhar, restaura o botão após 3 segundos
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bell"></i> <span>Receber Notificações</span>';
            }, 3000);
        }
    };
    
    // ✅ CORREÇÃO BUG 3: Adiciona listeners para desktop e mobile
    btn.addEventListener('click', handleClick, { once: true });
    btn.addEventListener('touchend', handleClick, { once: true });
}

/**
 * Executa o processo de solicitação de permissão e inscrição do usuário.
 */
async function subscribeUser(registration) {
    try {
        // Verifica novamente se está logado antes de inscrever
        const cliente = JSON.parse(localStorage.getItem('cliente'));
        if (!cliente || !cliente.id_cliente) {
            throw new Error('Você precisa estar logado para ativar as notificações.');
        }

        // 1. Solicita permissão ao usuário
        console.log('🔔 Solicitando permissão...');
        
        // ✅ CORREÇÃO BUG 3: Aguarda um pouco no mobile antes de solicitar
        if (/iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
            await new Promise(resolve => setTimeout(resolve, 300));
        }
        
        const permission = await Notification.requestPermission();
        
        if (permission !== 'granted') {
            throw new Error('Permissão de notificação não concedida.');
        }
        console.log('✅ Permissão concedida');

        // 2. Cria a inscrição (subscription)
        console.log('📝 Criando inscrição...');
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });
        console.log('✅ Inscrição criada');

        // 3. Envia a inscrição para o servidor
        await saveSubscriptionToServer(subscription);
        console.log('✅ Inscrição salva no servidor!');
        
        // 4. Mostra feedback de sucesso
        showFeedback('success', 'Notificações Ativadas!', `Olá ${cliente.nome_completo}! Você receberá ofertas e novidades.`);
        return true;

    } catch (error) {
        console.error('❌ Erro durante o processo de inscrição:', error);
        
        if (Notification.permission === 'denied') {
             showFeedback('error', 'Notificações Bloqueadas', 'Altere a permissão nas configurações do site (ícone de cadeado 🔒 na barra de endereços).');
        } else {
            showFeedback('error', 'Ocorreu um Erro', error.message);
        }
        return false;
    }
}

/**
 * Envia o objeto de inscrição para o backend.
 */
async function saveSubscriptionToServer(subscription) {
    const cliente = JSON.parse(localStorage.getItem('cliente'));
    const response = await fetch('api/api_salvar_inscricao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            subscription: subscription,
            id_cliente: cliente ? cliente.id_cliente : null
        }),
    });

    if (!response.ok) {
        const result = await response.json();
        throw new Error(result.message || 'Falha ao comunicar com o servidor.');
    }
    return response.json();
}

/**
 * Mostra uma mensagem de feedback visual na tela.
 */
function showFeedback(type, title, message) {
    const existingFeedback = document.getElementById('feedback-notification');
    if (existingFeedback) existingFeedback.remove();

    const feedback = document.createElement('div');
    feedback.id = 'feedback-notification';
    
    const isSuccess = type === 'success';
    const backgroundColor = isSuccess 
        ? 'linear-gradient(135deg, #00c853 0%, #00a040 100%)' 
        : 'linear-gradient(135deg, #d32f2f 0%, #c62828 100%)';
    const iconClass = isSuccess ? 'fa-circle-check' : 'fa-triangle-exclamation';

    feedback.style.cssText = `
        position: fixed; top: 80px; right: 20px;
        background: ${backgroundColor};
        color: white; padding: 20px 30px; border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        z-index: 10000; font-family: sans-serif;
        animation: slideInRight 0.5s ease;
        max-width: 90%;
    `;

    feedback.innerHTML = `
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fa-solid ${iconClass}" style="font-size: 2rem;"></i>
            <div>
                <div style="font-size: 1.1rem; font-weight: 500; margin-bottom: 5px;">${title}</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">${message}</div>
            </div>
        </div>
    `;
    
    document.body.appendChild(feedback);

    setTimeout(() => {
        feedback.style.animation = 'slideOutRight 0.5s ease forwards';
        setTimeout(() => feedback.remove(), 500);
    }, 5000);
}

// Adiciona as animações CSS ao head
function injectAnimationStyles() {
    if (document.getElementById('notification-animations')) return;
    const style = document.createElement('style');
    style.id = 'notification-animations';
    style.textContent = `
        .hidden { display: none !important; }
        @keyframes slideInRight {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(120%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

// Ponto de entrada
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        injectAnimationStyles();
        initPushNotifications();
    });
} else {
    injectAnimationStyles();
    initPushNotifications();
}

window.subscribeUser = subscribeUser;

console.log('✅ Script de notificações pronto!');