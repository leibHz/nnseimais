<?php
// ARQUIVO: api/api_enviar_notificacao_individual.php
// Envia notificação para um cliente específico
require_once __DIR__ . '/init.php';

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Verifica autenticação do admin
function getAuthorizationHeader(){
    $headers = null;
    if (isset($_SERVER['Authorization'])) { 
        $headers = trim($_SERVER["Authorization"]); 
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { 
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]); 
    } elseif (function_exists('getallheaders')) {
        $requestHeaders = getallheaders();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) { 
            $headers = trim($requestHeaders['Authorization']); 
        }
    }
    return $headers;
}

$authHeader = getAuthorizationHeader();

if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token de autenticação não fornecido.']);
    exit();
}

$token = substr($authHeader, 7);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token inválido.']);
    exit();
}

// Verifica se o admin existe
$endpoint_admin_check = $supabase_url . '/rest/v1/administradores?select=nome&nome=eq.' . urlencode($token);
$headers_check = ['apikey: ' . $supabase_secret_key, 'Authorization: Bearer ' . $supabase_secret_key];
$ch_check = curl_init($endpoint_admin_check);
curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_check, CURLOPT_HTTPHEADER, $headers_check);
$response_check = curl_exec($ch_check);
$httpcode_check = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
curl_close($ch_check);

if ($httpcode_check !== 200 || empty(json_decode($response_check, true))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado.']);
    exit();
}

// Recebe os dados
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->title) || !isset($data->body) || !isset($data->id_cliente)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Título, mensagem e ID do cliente são obrigatórios.']);
    exit();
}

$id_cliente = (int)$data->id_cliente;
$title = $data->title;
$body = $data->body;

try {
    // Busca as inscrições do cliente específico
    $endpoint_get = $supabase_url . '/rest/v1/notificacoes_push?select=*&id_cliente=eq.' . $id_cliente;
    $headers_get = ['apikey: ' . $supabase_secret_key, 'Authorization: Bearer ' . $supabase_secret_key];
    $ch_get = curl_init($endpoint_get);
    curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_get, CURLOPT_HTTPHEADER, $headers_get);
    $response_get = curl_exec($ch_get);
    $httpcode_get = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
    curl_close($ch_get);

    if ($httpcode_get !== 200) {
        throw new Exception('Falha ao buscar inscrições do cliente.');
    }

    $subscriptions_data = json_decode($response_get, true);

    if (empty($subscriptions_data)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cliente não está inscrito para receber notificações.',
            'id_cliente' => $id_cliente
        ]);
        exit();
    }

    // Configura Web Push
    $auth = [
        'VAPID' => [
            'subject' => 'https://bisque-salamander-506742.hostingersite.com/',
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);
    $payload = json_encode(['title' => $title, 'body' => $body]);

    $successCount = 0;
    $errorCount = 0;

    foreach ($subscriptions_data as $sub_data) {
        if (!isset($sub_data['endpoint'], $sub_data['p256dh'], $sub_data['auth'])) {
            $errorCount++;
            continue;
        }

        try {
            $subscription = Subscription::create([
                'endpoint' => $sub_data['endpoint'],
                'publicKey' => $sub_data['p256dh'],
                'authToken' => $sub_data['auth'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        } catch (\Exception $e) {
            error_log("Inscrição inválida: " . $e->getMessage());
            $errorCount++;
        }
    }

    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $successCount++;
        } else {
            $errorCount++;
            error_log("Falha ao enviar: {$report->getReason()}");
        }
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Notificação enviada para o cliente #$id_cliente",
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'total_devices' => count($subscriptions_data)
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao enviar notificação.',
        'error_details' => $e->getMessage()
    ]);
}
?>