<?php
// Ficheiro: api/api_enviar_notificacao_massa.php (VERSÃO COM VERIFICAÇÃO DE AMBIENTE)
require_once __DIR__ . '/init.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

try {
    // VERIFICAÇÃO DE AMBIENTE: Checa se o OpenSSL está configurado corretamente.
    if (!extension_loaded('openssl')) {
        throw new Exception('A extensão OpenSSL do PHP não está ativada. Ela é necessária para enviar notificações push. Verifique seu ficheiro php.ini.', 500);
    }
    // A biblioteca precisa de criptografia de curva elíptica (ECC), especificamente 'prime256v1'.
    if (!function_exists('openssl_get_curve_names') || !in_array('prime256v1', openssl_get_curve_names())) {
        throw new Exception('A sua configuração OpenSSL do PHP não suporta criptografia de curva elíptica (ECC). Isto geralmente ocorre no XAMPP quando o ficheiro "openssl.cnf" não é encontrado. Verifique a configuração "openssl.cafile" no seu php.ini.', 500);
    }

    require __DIR__ . '/config.php';

    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) { $headers = trim($_SERVER["Authorization"]); }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { $headers = trim($_SERVER["HTTP_AUTHORIZATION"]); } 
        elseif (function_exists('getallheaders')) {
            $requestHeaders = getallheaders();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) { $headers = trim($requestHeaders['Authorization']); }
        }
        return $headers;
    }

    $authHeader = getAuthorizationHeader();

    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Token de autenticação não fornecido ou mal formatado.', 401);
    }

    $token = substr($authHeader, 7);

    if (empty($token)) {
        throw new Exception('Token de administrador inválido.', 401);
    }

    $endpoint_admin_check = $supabase_url . '/rest/v1/administradores?select=nome&nome=eq.' . urlencode($token);
    $headers_check = [ 'apikey: ' . $supabase_secret_key, 'Authorization: Bearer ' . $supabase_secret_key ];
    $ch_check = curl_init($endpoint_admin_check);
    curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_check, CURLOPT_HTTPHEADER, $headers_check);
    $response_check = curl_exec($ch_check);
    $httpcode_check = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
    curl_close($ch_check);

    if ($httpcode_check !== 200 || empty(json_decode($response_check, true))) {
        throw new Exception('Acesso não autorizado. Administrador não encontrado.', 403);
    }

    $data = json_decode(file_get_contents("php://input"));
    if (!isset($data->title) || !isset($data->body)) {
        throw new Exception('Título e corpo da mensagem são obrigatórios.', 400);
    }

    $endpoint_get = $supabase_url . '/rest/v1/notificacoes_push?select=*';
    $headers_get = [ 'apikey: ' . $supabase_secret_key, 'Authorization: Bearer ' . $supabase_secret_key ];
    $ch_get = curl_init($endpoint_get);
    curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_get, CURLOPT_HTTPHEADER, $headers_get);
    $response_get = curl_exec($ch_get);
    $httpcode_get = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
    curl_close($ch_get);

    if ($httpcode_get !== 200) {
        throw new Exception('Falha ao buscar as inscrições dos usuários. Código: ' . $httpcode_get, 500);
    }
    $subscriptions_data = json_decode($response_get, true);

    if (empty($subscriptions_data)) {
        http_response_code(200);
        echo json_encode(['message' => 'Nenhum usuário inscrito para receber notificações.']);
        exit();
    }

    $auth = [
        'VAPID' => [
            'subject' => 'https://bisque-salamander-506742.hostingersite.com/', // ALTERADO AQUI
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);
    $payload = json_encode(['title' => $data->title, 'body' => $data->body]);

    foreach ($subscriptions_data as $sub_data) {
        if (!isset($sub_data['endpoint'], $sub_data['p256dh'], $sub_data['auth'])) {
            error_log("Inscrição malformada encontrada e ignorada: " . json_encode($sub_data));
            continue;
        }

        try {
            $subscription = Subscription::create([
                'endpoint' => $sub_data['endpoint'], 'publicKey' => $sub_data['p256dh'], 'authToken' => $sub_data['auth'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        } catch (\Exception $e) {
            error_log("Inscrição inválida encontrada e ignorada: " . $e->getMessage());
        }
    }

    $successCount = 0; $errorCount = 0;
    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $successCount++;
        } else {
            $errorCount++;
            error_log("Falha ao enviar notificação para {$report->getEndpoint()}: {$report->getReason()}");
        }
    }

    http_response_code(200);
    echo json_encode([
        'message' => "Envio concluído. Sucesso: {$successCount}. Falhas: {$errorCount}.",
        'success_count' => $successCount, 'error_count' => $errorCount
    ]);

} catch (\Throwable $e) {
    $httpCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($httpCode);
    echo json_encode([
        'message' => 'Ocorreu um erro interno no servidor.',
        'error_details' => [
            'type' => get_class($e), 'error_message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()
        ]
    ]);
} finally {
    restore_error_handler();
}
?>

