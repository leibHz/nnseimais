<?php
// ARQUIVO: api/api_encomenda_historico.php (NOVO)
// API para buscar todas as encomendas de um cliente específico.

require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Valida se o ID do cliente foi fornecido na URL.
if (!isset($_GET['id_cliente']) || empty($_GET['id_cliente'])) {
    http_response_code(400);
    echo json_encode(['message' => 'ID do cliente é obrigatório.']);
    exit();
}

$id_cliente = intval($_GET['id_cliente']);

// Monta a URL da API do Supabase para buscar as encomendas.
// - Filtra pelo id_cliente.
// - Usa 'select' para trazer dados aninhados:
//   - Os itens de cada encomenda (encomenda_itens).
//   - Dentro de cada item, os detalhes do produto (produtos).
// - Ordena as encomendas da mais recente para a mais antiga.
$endpoint = $supabase_url . '/rest/v1/encomendas?id_cliente=eq.' . $id_cliente . '&select=*,encomenda_itens(*,produtos(nome,imagem_url))&order=data_encomenda.desc';

// Faz a requisição usando a chave secreta, pois é uma consulta de dados privados.
$context = stream_context_create([
    'http' => [
        'header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"
    ]
]);

$response = file_get_contents($endpoint, false, $context);
$http_code = $http_response_header[0];

// Verifica se a requisição foi bem-sucedida.
if (strpos($http_code, '200') !== false) {
    echo $response;
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Erro ao buscar o histórico de encomendas.']);
}
?>
