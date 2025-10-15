<?php
// ARQUIVO: api/config_pdo.php (VERSÃO MELHORADA COM MAIS DIAGNÓSTICO)
if (defined('CONFIG_PDO_INCLUDED')) {
    return;
}
define('CONFIG_PDO_INCLUDED', true);

// Carrega as variáveis de ambiente, caso este arquivo seja chamado diretamente.
if (!isset($_ENV['DB_HOST'])) {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PORT'])->notEmpty();
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Erro crítico de configuração do banco de dados',
            'error_details' => 'Arquivo .env não encontrado ou variáveis de ambiente faltando: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Credenciais de conexão lidas das variáveis de ambiente
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];
$port = $_ENV['DB_PORT'];

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(503);
    
    // Mensagem de erro mais detalhada para facilitar o diagnóstico
    $errorMessage = 'Erro crítico: Não foi possível conectar ao serviço de banco de dados.';
    $errorDetails = 'Detalhes: ' . $e->getMessage();
    
    // Em ambiente de produção, você pode querer esconder os detalhes
    // Para depuração, é útil ter essas informações
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'error_details' => $errorDetails,
        'connection_info' => [
            'host' => $host,
            'port' => $port,
            'database' => $dbname,
            'user' => $user
        ]
    ]);
    exit();
}
?>