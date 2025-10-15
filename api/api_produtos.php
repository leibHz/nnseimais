<?php
// ARQUIVO: api/api_produtos.php (CORREÇÃO FINAL DA LÓGICA DE JOIN)
require 'config.php'; 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$endpoint = $supabase_url . '/rest/v1/produtos';

$queryParams = [
    'disponivel' => 'eq.true'
];

// Por defeito, usamos LEFT JOIN para trazer todos os produtos, mesmo os sem tags.
$queryParams['select'] = '*,sessao:sessoes(nome),tags!left(nome)';

// Se um filtro de tag for ativado, mudamos a lógica para INNER JOIN
if (isset($_GET['tag']) && !empty($_GET['tag'])) {
    // A query é refeita para usar INNER JOIN, garantindo que só vêm produtos com a tag especificada.
    $queryParams['select'] = '*,sessao:sessoes(nome),tags!inner(nome)';
    $queryParams['tags.nome'] = 'eq.' . urlencode($_GET['tag']);
}

// Filtro de busca
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $queryParams['nome'] = 'ilike.*' . urlencode($_GET['q']) . '*';
}
// Filtro de promoção
if (isset($_GET['promocao']) && $_GET['promocao'] === 'true') {
    $queryParams['em_promocao'] = 'eq.true';
}

// Ordenação
$queryParams['order'] = 'nome.asc';
if (isset($_GET['ordenar'])) {
    switch ($_GET['ordenar']) {
        case 'alfabetica_desc': $queryParams['order'] = 'nome.desc'; break;
        case 'preco_asc': $queryParams['order'] = 'preco.asc'; break;
        case 'preco_desc': $queryParams['order'] = 'preco.desc'; break;
    }
}

$full_url = $endpoint . '?' . http_build_query($queryParams);

$ch = curl_init($full_url);
$headers = ['apikey: ' . $supabase_publishable_key];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpcode);
echo $response;
?>

