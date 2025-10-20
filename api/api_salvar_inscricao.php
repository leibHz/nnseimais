<?php
// ARQUIVO: api/api_salvar_inscricao.php (VERSÃO REST API - SEM PDO)
header('Content-Type: application/json');
require_once __DIR__ . '/init.php';
require __DIR__ . '/config.php';

$json_str = file_get_contents('php://input');
$data = json_decode($json_str);

$subscription_data = $data->subscription ?? null;
$id_cliente = isset($data->id_cliente) && !empty($data->id_cliente) ? $data->id_cliente : null;

if (!$subscription_data || !isset($subscription_data->endpoint)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados de inscrição inválidos.']);
    exit;
}

try {
    $endpoint = $subscription_data->endpoint;
    $p256dh = $subscription_data->keys->p256dh;
    $auth = $subscription_data->keys->auth;

    // Verifica se o endpoint já existe
    $check_endpoint = $supabase_url . '/rest/v1/notificacoes_push?select=id_inscricao,id_cliente&endpoint=eq.' . urlencode($endpoint);
    $ch_check = curl_init($check_endpoint);
    $headers = [
        'apikey: ' . $supabase_secret_key,
        'Authorization: Bearer ' . $supabase_secret_key
    ];
    curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_check, CURLOPT_HTTPHEADER, $headers);
    $response_check = curl_exec($ch_check);
    $httpcode_check = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
    curl_close($ch_check);

    if ($httpcode_check !== 200) {
        throw new Exception('Erro ao verificar inscrição existente.');
    }

    $existing_subscriptions = json_decode($response_check, true);

    if (!empty($existing_subscriptions)) {
        // O endpoint já existe
        $existing = $existing_subscriptions[0];
        
        // Se o usuário está logado agora, mas a inscrição era anônima, atualiza
        if ($id_cliente !== null && $existing['id_cliente'] === null) {
            $update_data = ['id_cliente' => $id_cliente];
            $update_endpoint = $supabase_url . '/rest/v1/notificacoes_push?endpoint=eq.' . urlencode($endpoint);
            
            $ch_update = curl_init($update_endpoint);
            curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_update, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch_update, CURLOPT_POSTFIELDS, json_encode($update_data));
            curl_setopt($ch_update, CURLOPT_HTTPHEADER, array_merge($headers, [
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]));
            curl_exec($ch_update);
            curl_close($ch_update);
            
            echo json_encode(['status' => 'success', 'message' => 'Inscrição atualizada para o usuário.']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Inscrição já existente.']);
        }
    } else {
        // A inscrição não existe, insere uma nova
        $insert_data = [
            'id_cliente' => $id_cliente,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth
        ];
        
        $insert_endpoint = $supabase_url . '/rest/v1/notificacoes_push';
        $ch_insert = curl_init($insert_endpoint);
        curl_setopt($ch_insert, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_insert, CURLOPT_POST, true);
        curl_setopt($ch_insert, CURLOPT_POSTFIELDS, json_encode($insert_data));
        curl_setopt($ch_insert, CURLOPT_HTTPHEADER, array_merge($headers, [
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]));
        $response_insert = curl_exec($ch_insert);
        $httpcode_insert = curl_getinfo($ch_insert, CURLINFO_HTTP_CODE);
        curl_close($ch_insert);

        if ($httpcode_insert !== 201) {
            throw new Exception('Erro ao salvar inscrição.');
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Inscrição salva com sucesso.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>