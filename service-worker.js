// Ficheiro: veronesi-site/service-worker.js (VERSÃO APRIMORADA)

// Evento 'install' - Disparado quando o service worker é instalado
self.addEventListener('install', event => {
  console.log('Service Worker: Instalado');
  // Força o novo service worker a se tornar ativo imediatamente
  self.skipWaiting();
});

// Evento 'activate' - Disparado quando o service worker é ativado
self.addEventListener('activate', event => {
  console.log('Service Worker: Ativado');
  // Garante que o service worker tome controle de todas as abas abertas imediatamente
  event.waitUntil(clients.claim());
});

// Evento 'push' - Disparado quando uma notificação é recebida do servidor
self.addEventListener('push', function (event) {
    console.log('Service Worker: Notificação Push recebida');
    const data = event.data.json(); // Recebe os dados da notificação (título, corpo, etc.)

    const title = data.title || 'Supermercado Veronesi';
    const options = {
        body: data.body,
        icon: './admin/logo.png', // Caminho para um ícone (opcional)
        badge: './admin/logo.png' // Ícone para Android
    };

    // Pede ao navegador para exibir a notificação
    event.waitUntil(self.registration.showNotification(title, options));
});