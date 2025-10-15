<?php
// ARQUIVO: api/config.php (VERSÃO SEGURA)
// Carrega as variáveis de ambiente do arquivo .env na raiz do projeto.
require_once __DIR__ . '/../vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
    $dotenv->required([
        'SUPABASE_URL', 'SUPABASE_PUBLISHABLE_KEY', 'SUPABASE_SECRET_KEY', 
        'SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_PORT', 'SMTP_SECURE',
        'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY'
    ])->notEmpty();
} catch (Exception $e) {
    // Em caso de erro ao carregar as variáveis, retorna um erro claro.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro crítico de configuração do servidor: ' . $e->getMessage()]);
    exit();
}

// URL do seu projeto Supabase (lido do .env)
$supabase_url = $_ENV['SUPABASE_URL'];
$supabase_publishable_key = $_ENV['SUPABASE_PUBLISHABLE_KEY'];
$supabase_secret_key = $_ENV['SUPABASE_SECRET_KEY'];

// --- CONFIGURAÇÃO DE E-MAIL (PHPMailer) ---
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME']);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD']);
define('SMTP_PORT', (int)$_ENV['SMTP_PORT']);
define('SMTP_SECURE', $_ENV['SMTP_SECURE']);

// --- CHAVES VAPID (Web Push) ---
define('VAPID_PUBLIC_KEY', $_ENV['VAPID_PUBLIC_KEY']);
define('VAPID_PRIVATE_KEY', $_ENV['VAPID_PRIVATE_KEY']);
?>
