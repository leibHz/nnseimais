<?php
// ARQUIVO: api/api_cliente_verificar.php (NOVO)
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->codigo)) {
    http_response_code(400);
    echo json_encode(['message' => 'Email e código são obrigatórios.']);
    exit();
}

$email = $data->email;
$codigo = $data->codigo;

// 1. Buscar o cliente pelo email
$endpoint_get = $supabase_url . '/rest/v1/clientes?select=codigo_verificacao,codigo_expira_em&email=eq.' . urlencode($email) . '&limit=1';
$ch_get = curl_init($endpoint_get);
$headers = ['apikey: ' . $supabase_secret_key, 'Authorization: Bearer ' . $supabase_secret_key];
curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_get, CURLOPT_HTTPHEADER, $headers);
$response_get = curl_exec($ch_get);
curl_close($ch_get);
$clientes = json_decode($response_get, true);

if (count($clientes) === 0) {
    http_response_code(404);
    echo json_encode(['message' => 'Cliente não encontrado.']);
    exit();
}

$cliente = $clientes[0];

// 2. Verificar se o código expirou
$agora = new DateTime();
$data_expiracao = new DateTime($cliente['codigo_expira_em']);
if ($agora > $data_expiracao) {
    http_response_code(410); // Gone
    echo json_encode(['message' => 'Código de verificação expirado.']);
    exit();
}

// 3. Verificar se o código está correto
if ($cliente['codigo_verificacao'] != $codigo) {
    http_response_code(401);
    echo json_encode(['message' => 'Código de verificação inválido.']);
    exit();
}

// 4. Atualizar o status do cliente para verificado
$endpoint_patch = $supabase_url . '/rest/v1/clientes?email=eq.' . urlencode($email);
$patchData = [
    'email_verificado' => true,
    'codigo_verificacao' => null, // Limpa o código
    'codigo_expira_em' => null
];

$ch_patch = curl_init($endpoint_patch);
curl_setopt($ch_patch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_patch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch_patch, CURLOPT_POSTFIELDS, json_encode($patchData));
curl_setopt($ch_patch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
curl_exec($ch_patch);
$httpcode_patch = curl_getinfo($ch_patch, CURLINFO_HTTP_CODE);
curl_close($ch_patch);

if ($httpcode_patch >= 200 && $httpcode_patch < 300) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Conta verificada com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Erro ao atualizar o status da conta.']);
}
?>
