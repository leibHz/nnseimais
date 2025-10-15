<?php
// ARQUIVO: api/api_admin_auth.php
// -----------------------------------------------------------------
// Lida com a tentativa de login do administrador.

require 'config.php'; // Inclui as chaves da API Supabase

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Só permite o método POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Método não permitido']);
    exit();
}

// Pega os dados enviados no corpo da requisição
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->senha)) {
    http_response_code(400);
    echo json_encode(['message' => 'Email e senha são obrigatórios.']);
    exit();
}

$email = $data->email;
$senha = $data->senha;

// --- REQUISIÇÃO PARA O SUPABASE ---
// Busca um administrador com o email fornecido
$endpoint = $supabase_url . '/rest/v1/administradores?select=nome,senha_hash&email=eq.' . urlencode($email) . '&limit=1';

$ch = curl_init($endpoint);
$headers = [
    'apikey: ' . $supabase_secret_key,
    'Authorization: Bearer ' . $supabase_secret_key
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200) {
    http_response_code(500);
    echo json_encode(['message' => 'Erro ao contatar o serviço de autenticação.']);
    exit();
}

$admins = json_decode($response, true);

// Verifica se encontrou um usuário e se a senha corresponde
if (count($admins) > 0) {
    $admin = $admins[0];
    // password_verify() compara a senha enviada com o hash salvo no banco
    if (password_verify($senha, $admin['senha_hash'])) {
        http_response_code(200);
        // Em uma aplicação real, aqui seria gerado um token JWT.
        // Para simplificar, apenas retornamos o nome do usuário.
        echo json_encode(['status' => 'success', 'message' => 'Login bem-sucedido!', 'admin_nome' => $admin['nome']]);
    } else {
        http_response_code(401); // Não autorizado
        echo json_encode(['message' => 'Credenciais inválidas.']);
    }
} else {
    http_response_code(401); // Não autorizado
    echo json_encode(['message' => 'Credenciais inválidas.']);
}

/*
// --- IMPORTANTE: COMO CADASTRAR UM ADMIN ---
// Como o cadastro é manual, use este trecho de código para gerar o hash da senha.
// Crie um arquivo temporário (ex: hash_generator.php), coloque o código abaixo,
// execute-o e copie o hash gerado para a coluna 'senha_hash' no seu banco de dados Supabase.

$senhaParaAdmin = 'admin123'; // Defina uma senha segura
$hash = password_hash($senhaParaAdmin, PASSWORD_DEFAULT);
echo $hash;

// Exemplo de hash gerado: $2y$10$T8.iL8.B... (será diferente a cada vez)
*/
?>