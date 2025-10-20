<?php
// ARQUIVO: api/api_notificacoes_stats.php
// Retorna estatísticas sobre notificações push
require_once __DIR__ . '/init.php';
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    // Busca total de inscrições ativas
    $endpoint_inscricoes = $supabase_url . '/rest/v1/notificacoes_push?select=*';
    $ch = curl_init($endpoint_inscricoes);
    $headers = [
        'apikey: ' . $supabase_secret_key,
        'Authorization: Bearer ' . $supabase_secret_key
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $subscriptions = json_decode($response, true);
    $total_subscriptions = is_array($subscriptions) ? count($subscriptions) : 0;
    
    // Busca informações sobre último envio (se houver uma tabela de logs)
    // Por enquanto, vamos retornar dados mockados
    $last_sent = 'Nunca';
    $success_rate = '--';
    
    // Se você tiver uma tabela de logs de notificações, pode buscar aqui:
    // $endpoint_logs = $supabase_url . '/rest/v1/notification_logs?select=*&order=created_at.desc&limit=1';
    
    echo json_encode([
        'status' => 'success',
        'total_subscriptions' => $total_subscriptions,
        'last_sent' => $last_sent,
        'success_rate' => $success_rate,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar estatísticas.',
        'error_details' => $e->getMessage()
    ]);
}
?>