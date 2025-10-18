<?php
// ARQUIVO: api/api_site_info.php (VERSÃO OTIMIZADA COM CACHE)
require 'config.php';
require 'status_logic.php';
require 'cache-manager.php';

// Previne cache agressivo do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Inicializa gerenciador de cache
$cache = new CacheManager();

// Define TTL baseado no status da loja
// Se fechada: cache de 5 minutos
// Se aberta: cache de 1 minuto
$cache_key = 'site_info_status';

// Tenta buscar do cache
$cached_data = $cache->get($cache_key);

// Se tem cache válido, retorna imediatamente
if ($cached_data !== null) {
    echo json_encode($cached_data, JSON_PRETTY_PRINT);
    exit();
}

// Se não tem cache, busca do banco
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
    $config->pode_encomendar = $config->encomendas_ativas && $status_calculado['status_real'];
    $config->eh_feriado = ehFeriado();
    $config->timestamp = date('Y-m-d H:i:s');
    
    // Define TTL dinâmico
    $ttl = $config->pode_encomendar ? 60 : 300; // 1 min se aberto, 5 min se fechado
    
    // Salva no cache
    $cache->set($cache_key, $config, $ttl);
}

echo json_encode($config, JSON_PRETTY_PRINT);
?>