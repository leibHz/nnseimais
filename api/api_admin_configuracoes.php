<?php
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $endpoint = $supabase_url . '/rest/v1/configuracoes_site?id_config=eq.1&select=*';
    $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
    $response = file_get_contents($endpoint, false, $context);
    $config = json_decode($response);
    echo json_encode($config[0] ?? null);
    exit();
}

if ($method === 'PATCH') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos ou corpo da requisição vazio.']);
        exit();
    }

    // CORREÇÃO: O payload agora corresponde exatamente às colunas do seu banco de dados
    $payload = [
        'horario_seg_sex' => $data->horario_seg_sex,
        'horario_sab' => $data->horario_sab,
        'status_manual' => $data->status_manual, // Nome da coluna corrigido
        'encomendas_ativas' => $data->encomendas_ativas,
        'mensagem_status' => $data->mensagem_status // Nome da coluna corrigido
    ];

    $endpoint = $supabase_url . '/rest/v1/configuracoes_site?id_config=eq.1';

    $ch = curl_init($endpoint);
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $supabase_secret_key,
        'Authorization: Bearer ' . $supabase_secret_key,
        'Prefer: return=minimal'
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response_body = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode >= 200 && $httpcode < 300) {
        echo json_encode(['status' => 'success', 'message' => 'Configurações salvas com sucesso.']);
    } else {
        http_response_code($httpcode);
        echo json_encode(['status' => 'error', 'message' => 'Falha ao salvar no banco de dados.', 'debug_code' => $httpcode, 'response_body' => $response_body]);
    }
    exit();
}
?>

