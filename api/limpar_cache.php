<?php
// ARQUIVO: api/limpar_cache.php
// Ferramenta administrativa para limpar o cache de feriados
// Acesse via: https://seu-site.com/api/limpar_cache.php?action=limpar

header('Content-Type: application/json; charset=UTF-8');

// Segurança básica: só funciona se vier do próprio servidor ou localhost
$allowed_ips = ['127.0.0.1', '::1'];
$server_ip = $_SERVER['SERVER_ADDR'] ?? '';
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Permite também se vier da mesma origem do servidor
if (!in_array($remote_ip, $allowed_ips) && $remote_ip !== $server_ip) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Este script só pode ser executado localmente.']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'limpar') {
    $cache_dir = __DIR__ . '/../cache/';
    $deleted = 0;
    $errors = [];
    
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . 'feriados_*.json');
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            } else {
                $errors[] = basename($file);
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => "Cache limpo com sucesso!",
            'deleted' => $deleted,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Diretório de cache não encontrado.'
        ]);
    }
} elseif ($action === 'info') {
    $cache_dir = __DIR__ . '/../cache/';
    $cache_files = [];
    
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . 'feriados_*.json');
        
        foreach ($files as $file) {
            $cache_files[] = [
                'file' => basename($file),
                'size' => filesize($file),
                'modified' => date('d/m/Y H:i:s', filemtime($file)),
                'age_days' => floor((time() - filemtime($file)) / 86400)
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'cache_dir' => $cache_dir,
        'cache_exists' => is_dir($cache_dir),
        'files' => $cache_files,
        'count' => count($cache_files)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ação inválida. Use: ?action=limpar ou ?action=info'
    ]);
}
?>