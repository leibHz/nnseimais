<?php
// ARQUIVO: api/api_sessoes_tags.php (VERSÃO REST API - SEM PDO)
// API pública para buscar todas as sessões e tags para os filtros do cliente.

require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    // Busca sessões via REST API do Supabase
    $endpoint_sessoes = $supabase_url . '/rest/v1/sessoes?select=id_sessao,nome&order=nome.asc';
    $ch_sessoes = curl_init($endpoint_sessoes);
    $headers = ['apikey: ' . $supabase_publishable_key];
    curl_setopt($ch_sessoes, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_sessoes, CURLOPT_HTTPHEADER, $headers);
    $response_sessoes = curl_exec($ch_sessoes);
    $httpcode_sessoes = curl_getinfo($ch_sessoes, CURLINFO_HTTP_CODE);
    curl_close($ch_sessoes);

    if ($httpcode_sessoes !== 200) {
        throw new Exception('Erro ao buscar sessões do banco de dados.');
    }

    // Busca tags via REST API do Supabase
    $endpoint_tags = $supabase_url . '/rest/v1/tags?select=id_tag,nome&order=nome.asc';
    $ch_tags = curl_init($endpoint_tags);
    curl_setopt($ch_tags, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_tags, CURLOPT_HTTPHEADER, $headers);
    $response_tags = curl_exec($ch_tags);
    $httpcode_tags = curl_getinfo($ch_tags, CURLINFO_HTTP_CODE);
    curl_close($ch_tags);

    if ($httpcode_tags !== 200) {
        throw new Exception('Erro ao buscar tags do banco de dados.');
    }

    $sessoes = json_decode($response_sessoes, true);
    $tags = json_decode($response_tags, true);

    // Retorna os dados combinados
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'sessoes' => $sessoes ?: [],
        'tags' => $tags ?: []
    ]);

} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'Serviço temporariamente indisponível.',
        'error_details' => $e->getMessage()
    ]);
}
?>