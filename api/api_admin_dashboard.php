<?php
// ARQUIVO: api/api_admin_dashboard.php (VERSÃO REST API - SEM PDO)
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    // Função para contar registros via REST API do Supabase
    function getCount($supabase_url, $supabase_secret_key, $table) {
        $endpoint = $supabase_url . '/rest/v1/' . $table . '?select=*&limit=0';
        
        $ch = curl_init($endpoint);
        $headers = [
            'apikey: ' . $supabase_secret_key,
            'Authorization: Bearer ' . $supabase_secret_key,
            'Prefer: count=exact'
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        curl_exec($ch);
        $header_info = curl_getinfo($ch);
        $headers_string = substr(curl_exec($ch), 0, $header_info['header_size']);
        curl_close($ch);
        
        // Extrai o count do header Content-Range
        if (preg_match('/content-range:\s*\d+-\d+\/(\d+)/i', $headers_string, $matches)) {
            return (int)$matches[1];
        }
        
        // Fallback: faz uma query normal e conta
        $ch2 = curl_init($supabase_url . '/rest/v1/' . $table . '?select=*');
        $headers2 = [
            'apikey: ' . $supabase_secret_key,
            'Authorization: Bearer ' . $supabase_secret_key
        ];
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers2);
        $response = curl_exec($ch2);
        curl_close($ch2);
        
        $data = json_decode($response, true);
        return is_array($data) ? count($data) : 0;
    }

    // Busca as contagens de cada tabela
    $total_produtos = getCount($supabase_url, $supabase_secret_key, 'produtos');
    $total_clientes = getCount($supabase_url, $supabase_secret_key, 'clientes');
    $total_encomendas = getCount($supabase_url, $supabase_secret_key, 'encomendas');

    // Monta o array de resposta
    $stats = [
        'total_produtos' => $total_produtos,
        'total_clientes' => $total_clientes,
        'total_encomendas' => $total_encomendas
    ];

    // Retorna os dados em JSON
    http_response_code(200);
    echo json_encode($stats);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar estatísticas do banco de dados.',
        'debug_info' => $e->getMessage()
    ]);
}
?>