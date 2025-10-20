<?php
// ARQUIVO: api/api_count_notificacoes_cliente.php
// Conta quantos dispositivos um cliente tem registrados para notificações
require_once __DIR__ . '/init.php';
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_GET['id_cliente'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID do cliente é obrigatório.']);
    exit();
}

$id_cliente = intval($_GET['id_cliente']);

try {
    // Busca a contagem de inscrições do cliente
    $endpoint = $supabase_url . '/rest/v1/notificacoes_push?select=id_inscricao&id_cliente=eq.' . $id_cliente;
    
    $ch = curl_init($endpoint);
    $headers = [
        'apikey: ' . $supabase_secret_key,
        'Authorization: Bearer ' . $supabase_secret_key
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $subscriptions = json_decode($response, true);
    $count = is_array($subscriptions) ? count($subscriptions) : 0;
    
    echo json_encode([
        'status' => 'success',
        'count' => $count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar notificações do cliente.',
        'error_details' => $e->getMessage()
    ]);
}
?>