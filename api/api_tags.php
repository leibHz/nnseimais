<?php
// ARQUIVO: api/api_tags.php (VERSÃO REST API - SEM PDO)
require_once __DIR__ . '/init.php';
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

$headers = [
    'apikey: ' . $supabase_secret_key,
    'Authorization: Bearer ' . $supabase_secret_key,
    'Content-Type: application/json'
];

try {
    switch ($method) {
        case 'GET':
            // Listar todas as tags
            $endpoint = $supabase_url . '/rest/v1/tags?select=id_tag,nome&order=nome.asc';
            $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
            $response = file_get_contents($endpoint, false, $context);
            echo $response;
            break;

        case 'POST':
            // Criar uma nova tag
            $data = json_decode(file_get_contents("php://input"));
            if (empty($data->nome)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'O nome da tag é obrigatório.']);
                exit();
            }
            
            $tag_data = ['nome' => $data->nome];
            $endpoint = $supabase_url . '/rest/v1/tags';
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tag_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
            curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpcode === 201) {
                echo json_encode(['status' => 'success', 'message' => 'Tag criada com sucesso.']);
            } else {
                throw new Exception('Erro ao criar tag.');
            }
            break;

        case 'PUT':
            // Atualizar uma tag
            $data = json_decode(file_get_contents("php://input"));
            if (empty($data->id_tag) || empty($data->nome)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID e nome da tag são obrigatórios.']);
                exit();
            }
            
            $tag_data = ['nome' => $data->nome];
            $endpoint = $supabase_url . '/rest/v1/tags?id_tag=eq.' . (int)$data->id_tag;
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tag_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
            curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpcode >= 200 && $httpcode < 300) {
                echo json_encode(['status' => 'success', 'message' => 'Tag atualizada com sucesso.']);
            } else {
                throw new Exception('Erro ao atualizar tag.');
            }
            break;

        case 'DELETE':
            // Deletar uma tag
            $data = json_decode(file_get_contents("php://input"));
            if (empty($data->id_tag)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'O ID da tag é obrigatório.']);
                exit();
            }

            // Primeiro, desassocia a tag de todos os produtos
            $produto_tags_endpoint = $supabase_url . '/rest/v1/produto_tags?id_tag=eq.' . (int)$data->id_tag;
            $ch_unassoc = curl_init($produto_tags_endpoint);
            curl_setopt($ch_unassoc, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_unassoc, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch_unassoc, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
            curl_exec($ch_unassoc);
            curl_close($ch_unassoc);

            // Agora exclui a tag
            $tag_endpoint = $supabase_url . '/rest/v1/tags?id_tag=eq.' . (int)$data->id_tag;
            $ch_delete = curl_init($tag_endpoint);
            curl_setopt($ch_delete, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_delete, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch_delete, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
            curl_exec($ch_delete);
            $httpcode = curl_getinfo($ch_delete, CURLINFO_HTTP_CODE);
            curl_close($ch_delete);
            
            if ($httpcode >= 200 && $httpcode < 300) {
                echo json_encode(['status' => 'success', 'message' => 'Tag removida com sucesso.']);
            } else {
                throw new Exception('Erro ao remover tag.');
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Método não permitido.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
}
?>