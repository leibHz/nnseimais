<?php
// ARQUIVO: api/api_produtos.php (CORRIGIDO - BUG 6)
require_once __DIR__ . '/init.php';
require 'config.php'; 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$endpoint = $supabase_url . '/rest/v1/produtos';

$queryParams = [
    'disponivel' => 'eq.true'
];

// Por defeito, usamos LEFT JOIN para trazer todos os produtos
$queryParams['select'] = '*,sessao:sessoes(nome),tags!left(nome)';

// ✅ SEGURANÇA: Valida e sanitiza o filtro de tag
if (isset($_GET['tag']) && !empty($_GET['tag'])) {
    // Remove caracteres especiais e limita tamanho
    $tag_filter = trim($_GET['tag']);
    $tag_filter = preg_replace('/[^a-zA-Z0-9\sÀ-ÿ-]/', '', $tag_filter);
    $tag_filter = substr($tag_filter, 0, 50); // Limita a 50 caracteres
    
    if (!empty($tag_filter)) {
        // Se um filtro de tag for ativado, mudamos para INNER JOIN
        $queryParams['select'] = '*,sessao:sessoes(nome),tags!inner(nome)';
        $queryParams['tags.nome'] = 'eq.' . urlencode($tag_filter);
    }
}

// ✅ SEGURANÇA: Valida filtro de busca
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $search_term = trim($_GET['q']);
    // Remove caracteres perigosos mas mantém acentuação
    $search_term = preg_replace('/[^a-zA-Z0-9\sÀ-ÿ-]/', '', $search_term);
    $search_term = substr($search_term, 0, 100); // Limita a 100 caracteres
    
    if (!empty($search_term)) {
        $queryParams['nome'] = 'ilike.*' . urlencode($search_term) . '*';
    }
}

// Filtro de promoção (já é seguro pois é booleano)
if (isset($_GET['promocao']) && $_GET['promocao'] === 'true') {
    $queryParams['em_promocao'] = 'eq.true';
}

// ✅ SEGURANÇA: Valida ordenação com whitelist
$allowed_orders = [
    'alfabetica_asc' => 'nome.asc',
    'alfabetica_desc' => 'nome.desc',
    'preco_asc' => 'preco.asc',
    'preco_desc' => 'preco.desc'
];

$order_param = $_GET['ordenar'] ?? 'alfabetica_asc';
$queryParams['order'] = $allowed_orders[$order_param] ?? 'nome.asc';

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