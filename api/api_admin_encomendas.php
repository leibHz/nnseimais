<?php
// ARQUIVO: api/api_admin_encomendas.php (ATUALIZADO)
// Adicionada a lógica para buscar detalhes de uma encomenda e para atualizar seu status.
require_once __DIR__ . '/init.php';
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

// Rota para buscar detalhes de UMA encomenda específica
if ($method === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Busca a encomenda e os itens relacionados, incluindo o nome do produto
    $endpoint = $supabase_url . '/rest/v1/encomendas?id_encomenda=eq.' . $id . '&select=*,clientes(nome_completo),encomenda_itens(*,produtos(nome))';
    
    $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
    $response = file_get_contents($endpoint, false, $context);
    $order = json_decode($response);
    
    echo json_encode($order[0] ?? null);
    exit();
}

// Rota para buscar TODAS as encomendas
if ($method === 'GET') {
    $endpoint = $supabase_url . '/rest/v1/encomendas?select=*,clientes(nome_completo)&order=data_encomenda.desc';
    $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
    $response = file_get_contents($endpoint, false, $context);
    echo $response;
    exit();
}

// Rota para ATUALIZAR o status de uma encomenda
if ($method === 'PATCH') {
    $data = json_decode(file_get_contents("php://input"));
    if (!isset($data->id_encomenda) || !isset($data->status)) {
        http_response_code(400);
        echo json_encode(['message' => 'ID da encomenda e novo status são obrigatórios.']);
        exit();
    }

    $id = $data->id_encomenda;
    $payload = ['status' => $data->status];

    // Adiciona a justificativa ao payload se ela existir
    if (isset($data->justificativa_cancelamento)) {
        $payload['justificativa_cancelamento'] = $data->justificativa_cancelamento;
    }
    
    $endpoint = $supabase_url . '/rest/v1/encomendas?id_encomenda=eq.' . $id;
    $opts = ['http' => [
        'method' => 'PATCH',
        'header' => "Content-Type: application/json\r\napikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\nPrefer: return=minimal\r\n",
        'content' => json_encode($payload)
    ]];
    $context = stream_context_create($opts);
    file_get_contents($endpoint, false, $context);

    echo json_encode(['status' => 'success', 'message' => 'Status da encomenda atualizado.']);
    exit();
}
?>
