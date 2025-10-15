// Ficheiro: push-notifications.js (VERSÃO OTIMIZADA E CORRIGIDA)

// ATENÇÃO: Substitua esta chave pela sua chave pública VAPID.
const VAPID_PUBLIC_KEY = 'BL-VAB4fZOhyco0eMUvU1uUevvs0ctR5mSI-kRHrMLmyIS2BoUb4iGwZ_l2bCct8JdxwI5XMKqPoG2a_eA2UjBY';

console.log('🔔 push-notifications.js carregado');

/**
 * Converte uma string base64 (URL safe) para um Uint8Array.
 * @param {string} base64String A chave pública VAPID.
 * @returns {Uint8Array}
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
    
    // 1. Verifica se o navegador suporta as tecnologias necessárias
    if (!('serviceWorker' in navigator && 'PushManager' in window)) {
        console.warn('❌ Seu navegador não suporta notificações push.');
        return;
    }

    try {
        // 2. Registra o Service Worker (melhor prática: fazer isso apenas uma vez)
        console.log('📝 Registrando Service Worker...');
        const registration = await navigator.serviceWorker.register('./service-worker.js');
        await navigator.serviceWorker.ready;
        console.log('✅ Service Worker pronto');

        // 3. Após o SW estar pronto, configura o botão
        await setupNotificationButton(registration);

    } catch (error) {
        console.error('❌ Erro na inicialização do Service Worker:', error);
    }
}

/**
 * Verifica a permissão e o status da inscrição para decidir se mostra o botão.
 * @param {ServiceWorkerRegistration} registration O registro do Service Worker.
 */
async function setupNotificationButton(registration) {
    const btn = document.getElementById('notification-prompt');
    if (!btn) {
        console.warn('⚠️ Botão #notification-prompt não encontrado no DOM.');
        return;
    }

    // Não mostra o botão se a permissão foi explicitamente negada
    if (Notification.permission === 'denied') {
        console.log('❌ Permissão negada permanentemente pelo usuário.');
        return;
    }

    // Verifica se já existe uma inscrição ativa
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
        console.log('✅ Usuário já inscrito.');
        return;
    }

    // Se chegou até aqui, o usuário pode se inscrever. Mostra o botão.
    console.log('🔔 Usuário não inscrito. Mostrando botão...');
    btn.classList.remove('hidden'); // Usa classe para controlar visibilidade
    btn.classList.add('show', 'pulse');

    // Adiciona o evento de clique uma única vez
    btn.addEventListener('click', async () => {
        console.log('🖱️ Botão clicado');
        btn.classList.add('hidden'); // Esconde o botão imediatamente
        
        const success = await subscribeUser(registration);
        
        if (!success) {
            // Se a inscrição falhar, mostra o botão novamente após um tempo
            setTimeout(() => {
                console.log('🤔 Falha na inscrição. Mostrando botão novamente.');
                btn.classList.remove('hidden');
            }, 5000);
        }
    });
}

/**
 * Executa o processo de solicitação de permissão e inscrição do usuário.
 * @param {ServiceWorkerRegistration} registration O registro do Service Worker.
 * @returns {Promise<boolean>} Retorna true se a inscrição for bem-sucedida.
 */
async function subscribeUser(registration) {
    try {
        // 1. Solicita permissão ao usuário
        console.log('🔔 Solicitando permissão...');
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('Permissão de notificação não concedida.');
        }
        console.log('✅ Permissão concedida');

        // 2. Cria a inscrição (subscription)
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });
        console.log('✅ Inscrição criada');

        // 3. Envia a inscrição para o servidor
        await saveSubscriptionToServer(subscription);
        console.log('✅ Inscrição salva no servidor!');
        
        // 4. Mostra feedback de sucesso
        showFeedback('success', 'Notificações Ativadas!', 'Você receberá ofertas e novidades.');
        return true;

    } catch (error) {
        console.error('❌ Erro durante o processo de inscrição:', error);
        
        if (Notification.permission === 'denied') {
             showFeedback('error', 'Notificações Bloqueadas', 'Altere a permissão nas configurações do site (no cadeado 🔒).');
        } else {
            showFeedback('error', 'Ocorreu um Erro', error.message);
        }
        return false;
    }
}

/**
 * Envia o objeto de inscrição para o backend.
 * @param {PushSubscription} subscription
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
        // Lança um erro que será capturado pelo bloco catch em `subscribeUser`
        throw new Error(result.message || 'Falha ao comunicar com o servidor.');
    }
    return response.json();
}

/**
 * Mostra uma mensagem de feedback visual na tela (para sucesso ou erro).
 * @param {'success'|'error'} type O tipo de feedback.
 * @param {string} title O título da mensagem.
 * @param {string} message O corpo da mensagem.
 */
function showFeedback(type, title, message) {
    // Remove qualquer feedback anterior
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

    // Animação de saída e remoção do elemento
    setTimeout(() => {
        feedback.style.animation = 'slideOutRight 0.5s ease forwards';
        setTimeout(() => feedback.remove(), 500);
    }, 5000);
}

// Adiciona as animações CSS ao head se não existirem
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

// Ponto de entrada: inicia o processo quando o DOM estiver pronto.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        injectAnimationStyles();
        initPushNotifications();
    });
} else {
    injectAnimationStyles();
    initPushNotifications();
}

// Exporta a função principal para o escopo global, caso seja necessário chamá-la de outro lugar.
window.subscribeUser = subscribeUser;

console.log('✅ Script de notificações pronto!');
