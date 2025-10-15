<?php
// ARQUIVO: api/api_site_info.php (VERSÃO MELHORADA)
// API PÚBLICA que calcula e retorna o status real da loja com informações detalhadas
require 'config.php';
require 'status_logic.php';

// Previne cache agressivo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$endpoint = $supabase_url . '/rest/v1/configuracoes_site?id_config=eq.1&select=*';
$context = stream_context_create(['http' => ['header' => "apikey: $supabase_publishable_key\r\n"]]);
$response = @file_get_contents($endpoint, false, $context);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['message' => 'Não foi possível buscar as informações do site.']);
    exit();
}

$config_array = json_decode($response);
$config = $config_array[0] ?? null;

if ($config) {
    // Calcula o status real usando a função melhorada
    $status_calculado = calcularStatusLoja($config);

    // Adiciona as informações calculadas ao objeto de resposta
    $config->status_loja_real = $status_calculado['status_real'];
    $config->mensagem_loja_real = $status_calculado['mensagem_real'];
    $config->proxima_abertura = $status_calculado['proxima_abertura'];
    
    // Flag final: só pode encomendar se o serviço estiver ativo E a loja estiver aberta
    $config->pode_encomendar = $config->encomendas_ativas && $status_calculado['status_real'];
    
    // Adiciona informação sobre feriado (útil para o frontend)
    $config->eh_feriado = ehFeriado();
    
    // Adiciona timestamp para o frontend poder verificar quando a informação foi gerada
    $config->timestamp = date('Y-m-d H:i:s');
}

echo json_encode($config, JSON_PRETTY_PRINT);
?>