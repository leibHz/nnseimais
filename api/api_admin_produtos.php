<?php
// ARQUIVO: api/api_admin_produtos.php (VERSÃO REST API - SEM PDO)
require_once __DIR__ . '/init.php';
require 'config.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: BUSCAR DADOS ---
if ($method === 'GET') {
    try {
        // Rota para buscar todas as sessões
        if (isset($_GET['sessoes'])) {
            $endpoint = $supabase_url . '/rest/v1/sessoes?select=id_sessao,nome&order=nome.asc';
            $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
            $response = file_get_contents($endpoint, false, $context);
            echo $response;
            exit();
        }
        
        // Rota para buscar todas as tags
        if (isset($_GET['tags'])) {
            $endpoint = $supabase_url . '/rest/v1/tags?select=id_tag,nome&order=nome.asc';
            $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
            $response = file_get_contents($endpoint, false, $context);
            echo $response;
            exit();
        }

        // Rota para buscar um único produto
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $endpoint = $supabase_url . '/rest/v1/produtos?id_produto=eq.' . $id . '&select=*';
            $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
            $response = file_get_contents($endpoint, false, $context);
            $produto_array = json_decode($response, true);
            $produto = $produto_array[0] ?? null;
            
            if ($produto) {
                // Busca as tags do produto
                $tags_endpoint = $supabase_url . '/rest/v1/produto_tags?id_produto=eq.' . $id . '&select=id_tag';
                $tags_response = file_get_contents($tags_endpoint, false, $context);
                $tags_data = json_decode($tags_response, true);
                $produto['tags'] = $tags_data ?: [];
            }
            
            echo json_encode($produto);
            exit();
        }

        // Buscar todos os produtos
        $endpoint = $supabase_url . '/rest/v1/produtos?select=*,sessao:sessoes(nome)&order=nome.asc';
        $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
        $response = file_get_contents($endpoint, false, $context);
        $produtos = json_decode($response, true);
        
        // Busca tags para cada produto
        foreach ($produtos as &$produto) {
            $tags_endpoint = $supabase_url . '/rest/v1/produto_tags?id_produto=eq.' . $produto['id_produto'] . '&select=id_tag,tags(nome)';
            $tags_response = file_get_contents($tags_endpoint, false, $context);
            $tags_data = json_decode($tags_response, true);
            $produto['tags'] = array_map(function($t) { return ['nome' => $t['tags']['nome']]; }, $tags_data);
        }
        
        echo json_encode($produtos);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// --- DELETE: REMOVER PRODUTO ---
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id_produto ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID do produto não fornecido.']);
        exit();
    }
    
    try {
        $headers = [
            'apikey: ' . $supabase_secret_key,
            'Authorization: Bearer ' . $supabase_secret_key,
            'Prefer: return=minimal'
        ];
        
        // Remove associações de tags
        $tags_endpoint = $supabase_url . '/rest/v1/produto_tags?id_produto=eq.' . $id;
        $ch_tags = curl_init($tags_endpoint);
        curl_setopt($ch_tags, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_tags, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch_tags, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch_tags);
        curl_close($ch_tags);
        
        // Remove o produto
        $produto_endpoint = $supabase_url . '/rest/v1/produtos?id_produto=eq.' . $id;
        $ch_produto = curl_init($produto_endpoint);
        curl_setopt($ch_produto, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_produto, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch_produto, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch_produto);
        $httpcode = curl_getinfo($ch_produto, CURLINFO_HTTP_CODE);
        curl_close($ch_produto);
        
        if ($httpcode >= 200 && $httpcode < 300) {
            echo json_encode(['status' => 'success', 'message' => 'Produto removido com sucesso.']);
        } else {
            throw new Exception('Erro ao remover produto.');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao remover o produto: ' . $e->getMessage()]);
    }
    exit();
}

// --- POST (CRIAR/ATUALIZAR) ---
if ($method === 'POST') {
    try {
        $data = $_POST;
        $id_produto = !empty($data['id_produto']) ? $data['id_produto'] : null;

        if (!isset($data['tags']) || !is_array($data['tags']) || empty($data['tags'])) {
            throw new Exception("É obrigatório selecionar pelo menos uma tag para o produto.");
        }
        if (empty($data['codigo_barras'])) {
            throw new Exception("O código de barras é obrigatório.");
        }

        // Verifica código de barras duplicado
        $check_endpoint = $supabase_url . '/rest/v1/produtos?select=id_produto&codigo_barras=eq.' . urlencode($data['codigo_barras']);
        if ($id_produto) {
            $check_endpoint .= '&id_produto=neq.' . $id_produto;
        }
        $context = stream_context_create(['http' => ['header' => "apikey: $supabase_secret_key\r\nAuthorization: Bearer $supabase_secret_key\r\n"]]);
        $check_response = file_get_contents($check_endpoint, false, $context);
        $existing = json_decode($check_response, true);
        
        if (!empty($existing)) {
            throw new Exception("Este código de barras já está em uso por outro produto.");
        }
        
        // Processa imagem
        $imagem_path = $data['imagem_atual'] ?? null;
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = uniqid('prod_') . '-' . basename($_FILES["imagem"]["name"]);
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES["imagem"]["tmp_name"], $target_file)) {
                $imagem_path = "uploads/" . $filename;
            } else {
                throw new Exception("Falha ao mover o arquivo de imagem.");
            }
        }

        $disponivel = isset($data['disponivel']) && $data['disponivel'] ? true : false;
        $em_promocao = isset($data['em_promocao']) && $data['em_promocao'] ? true : false;
        $id_sessao = !empty($data['id_sessao']) ? (int)$data['id_sessao'] : null;

        $produto_data = [
            'nome' => $data['nome'],
            'preco' => (float)$data['preco'],
            'codigo_barras' => $data['codigo_barras'],
            'id_sessao' => $id_sessao,
            'descricao' => $data['descricao'] ?? null,
            'unidade_medida' => $data['unidade_medida'],
            'disponivel' => $disponivel,
            'em_promocao' => $em_promocao,
            'imagem_url' => $imagem_path,
            'data_atualizacao' => date('Y-m-d H:i:s')
        ];

        $headers = [
            'apikey: ' . $supabase_secret_key,
            'Authorization: Bearer ' . $supabase_secret_key,
            'Content-Type: application/json'
        ];

        if ($id_produto) {
            // Atualizar produto
            $endpoint = $supabase_url . '/rest/v1/produtos?id_produto=eq.' . $id_produto;
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($produto_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
            curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpcode < 200 || $httpcode >= 300) {
                throw new Exception('Erro ao atualizar produto.');
            }
        } else {
            // Criar novo produto
            unset($produto_data['data_atualizacao']);
            $endpoint = $supabase_url . '/rest/v1/produtos';
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($produto_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=representation']));
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpcode !== 201) {
                throw new Exception('Erro ao criar produto.');
            }
            
            $created = json_decode($response, true);
            $id_produto = $created[0]['id_produto'];
        }

        // Remove tags antigas
        $tags_del_endpoint = $supabase_url . '/rest/v1/produto_tags?id_produto=eq.' . $id_produto;
        $ch_del = curl_init($tags_del_endpoint);
        curl_setopt($ch_del, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_del, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch_del, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
        curl_exec($ch_del);
        curl_close($ch_del);
        
        // Adiciona novas tags
        $tags_data = [];
        foreach ($data['tags'] as $id_tag) {
            $tags_data[] = ['id_produto' => $id_produto, 'id_tag' => (int)$id_tag];
        }
        
        $tags_endpoint = $supabase_url . '/rest/v1/produto_tags';
        $ch_tags = curl_init($tags_endpoint);
        curl_setopt($ch_tags, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_tags, CURLOPT_POST, true);
        curl_setopt($ch_tags, CURLOPT_POSTFIELDS, json_encode($tags_data));
        curl_setopt($ch_tags, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=minimal']));
        curl_exec($ch_tags);
        curl_close($ch_tags);
        
        echo json_encode(['status' => 'success', 'message' => 'Produto salvo com sucesso.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
    exit();
}
?>