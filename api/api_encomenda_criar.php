<?php
// ARQUIVO: api/api_encomenda_criar.php (VERSÃO REST API - SEM PDO)
require 'config.php';
require 'status_logic.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// --- VERIFICAÇÃO DE STATUS DA LOJA ---
$config_endpoint = $supabase_url . '/rest/v1/configuracoes_site?id_config=eq.1&select=*';
$config_context = stream_context_create(['http' => ['header' => "apikey: $supabase_publishable_key\r\n"]]);
$config_response = @file_get_contents($config_endpoint, false, $config_context);
$config_array = json_decode($config_response);
$config = $config_array[0] ?? null;

if (!$config) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Serviço de encomendas temporariamente indisponível.']);
    exit();
}

$status_calculado = calcularStatusLoja($config);

if (!$config->encomendas_ativas || !$status_calculado['status_real']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Desculpe, a loja está fechada para encomendas no momento.']);
    exit();
}

// --- PROCESSAR ENCOMENDA ---
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->id_cliente) || !isset($data->itens) || empty($data->itens)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados da encomenda inválidos ou incompletos.']);
    exit();
}

$id_cliente = $data->id_cliente;
$itens_carrinho = $data->itens;
$valor_total = 0;

try {
    // Busca os preços dos produtos
    $ids_produtos = array_map(function($item) { return $item->id; }, $itens_carrinho);
    $ids_string = implode(',', $ids_produtos);
    
    $produtos_endpoint = $supabase_url . '/rest/v1/produtos?select=id_produto,preco&id_produto=in.(' . $ids_string . ')';
    $ch_produtos = curl_init($produtos_endpoint);
    $headers = [
        'apikey: ' . $supabase_secret_key,
        'Authorization: Bearer ' . $supabase_secret_key
    ];
    curl_setopt($ch_produtos, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_produtos, CURLOPT_HTTPHEADER, $headers);
    $response_produtos = curl_exec($ch_produtos);
    $httpcode_produtos = curl_getinfo($ch_produtos, CURLINFO_HTTP_CODE);
    curl_close($ch_produtos);

    if ($httpcode_produtos !== 200) {
        throw new Exception('Erro ao verificar produtos.');
    }

    $produtos_db = json_decode($response_produtos, true);
    $produtos_map = [];
    foreach ($produtos_db as $produto) {
        $produtos_map[$produto['id_produto']] = $produto['preco'];
    }

    // Calcula o valor total
    foreach ($itens_carrinho as $item) {
        if (isset($produtos_map[$item->id])) {
            $valor_total += $produtos_map[$item->id] * $item->quantity;
        } else {
            throw new Exception("O produto com ID {$item->id} não foi encontrado.");
        }
    }

    // Cria a encomenda
    $encomenda_data = [
        'id_cliente' => $id_cliente,
        'valor_total' => $valor_total,
        'status' => 'nova'
    ];

    $encomenda_endpoint = $supabase_url . '/rest/v1/encomendas';
    $ch_encomenda = curl_init($encomenda_endpoint);
    curl_setopt($ch_encomenda, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_encomenda, CURLOPT_POST, true);
    curl_setopt($ch_encomenda, CURLOPT_POSTFIELDS, json_encode($encomenda_data));
    curl_setopt($ch_encomenda, CURLOPT_HTTPHEADER, array_merge($headers, [
        'Content-Type: application/json',
        'Prefer: return=representation'
    ]));
    $response_encomenda = curl_exec($ch_encomenda);
    $httpcode_encomenda = curl_getinfo($ch_encomenda, CURLINFO_HTTP_CODE);
    curl_close($ch_encomenda);

    if ($httpcode_encomenda !== 201) {
        throw new Exception('Erro ao criar encomenda.');
    }

    $encomenda_criada = json_decode($response_encomenda, true);
    $id_encomenda = $encomenda_criada[0]['id_encomenda'];

    // Adiciona os itens da encomenda
    $itens_data = [];
    foreach ($itens_carrinho as $item) {
        $itens_data[] = [
            'id_encomenda' => $id_encomenda,
            'id_produto' => $item->id,
            'quantidade' => $item->quantity,
            'preco_unitario' => $produtos_map[$item->id]
        ];
    }

    $itens_endpoint = $supabase_url . '/rest/v1/encomenda_itens';
    $ch_itens = curl_init($itens_endpoint);
    curl_setopt($ch_itens, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_itens, CURLOPT_POST, true);
    curl_setopt($ch_itens, CURLOPT_POSTFIELDS, json_encode($itens_data));
    curl_setopt($ch_itens, CURLOPT_HTTPHEADER, array_merge($headers, [
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]));
    curl_exec($ch_itens);
    $httpcode_itens = curl_getinfo($ch_itens, CURLINFO_HTTP_CODE);
    curl_close($ch_itens);

    if ($httpcode_itens !== 201) {
        throw new Exception('Erro ao adicionar itens à encomenda.');
    }

    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Encomenda realizada com sucesso!',
        'id_encomenda' => $id_encomenda
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Não foi possível processar a encomenda: ' . $e->getMessage()
    ]);
}
?>