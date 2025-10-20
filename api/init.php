<?php
// ARQUIVO: api/init.php
// Configurações globais do projeto - NOVO ARQUIVO

// Define timezone padrão para todo o projeto
date_default_timezone_set('America/Sao_Paulo');

// Define configurações de erro para produção
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Configura logs de erro
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Retorna true para indicar que foi inicializado
return true;
?>