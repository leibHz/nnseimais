<?php
// ARQUIVO NOVO: api/api_cliente_check_session.php (SOLUÇÃO PARA BUG 1)
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_GET['id_cliente'])) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'ID do cliente não fornecido.']);
    exit();
}

$id_cliente = intval($_GET['id_cliente']);

// Verifica se um cliente com o ID fornecido ainda existe no banco
$endpoint = $supabase_url . '/rest/v1/clientes?select=id_cliente&id_cliente=eq.' . $id_cliente . '&limit=1';

$context = stream_context_create([
    'http' => [
        'header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"
    ]
]);

$response = @file_get_contents($endpoint, false, $context);
$client_data = json_decode($response, true);

// Se o array retornado estiver vazio, o cliente foi excluído.
if (empty($client_data)) {
    echo json_encode(['valid' => false]);
} else {
    echo json_encode(['valid' => true]);
}
?>
