<?php
// ARQUIVO: api/api_admin_cache.php
// API para gerenciamento de cache pelo admin

require 'config.php';
require 'cache-manager.php';

set_cors_headers();

// Verifica autenticação básica
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit();
}

$token = substr($auth_header, 7);

// Verifica se o token é válido (nome do admin)
$endpoint = $supabase_url . '/rest/v1/administradores?select=nome&nome=eq.' . urlencode($token) . '&limit=1';
$context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
$response = @file_get_contents($endpoint, false, $context);

if (!$response || empty(json_decode($response, true))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit();
}

$cache = new CacheManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Retorna estatísticas do cache
            $stats = $cache->getStats();
            echo json_encode([
                'status' => 'success',
                'stats' => $stats
            ]);
            break;
            
        case 'DELETE':
            // Limpa todo o cache ou apenas uma chave específica
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (isset($data['key'])) {
                // Limpa cache específico
                $cache->delete($data['key']);
                $message = "Cache '{$data['key']}' removido com sucesso";
            } else {
                // Limpa todo o cache
                $deleted = $cache->clear();
                $message = "$deleted arquivo(s) de cache removido(s)";
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => $message
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao gerenciar cache',
        'error_details' => $e->getMessage()
    ]);
}
?>