<?php
// ARQUIVO: api/api_cliente_cadastro.php (VERSÃO COM VALIDAÇÃO DE E-MAIL)
require_once __DIR__ . '/init.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header("Access-control-allow-origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

try {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->nome_completo) || !isset($data->email) || !isset($data->senha)) {
        throw new Exception('Todos os campos são obrigatórios.', 400);
    }
    // VALIDAÇÃO ADICIONADA: Verifica se o formato do e-mail é válido
    if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('O formato do e-mail fornecido é inválido.', 400);
    }
    if (strlen($data->senha) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres.', 400);
    }

    $check_endpoint = $supabase_url . '/rest/v1/clientes?select=email,email_verificado&email=eq.' . urlencode($data->email) . '&limit=1';
    $ch_check = curl_init($check_endpoint);
    $headers = ['apikey: ' . $supabase_secret_key, 'Authorization: Bearer ' . $supabase_secret_key];
    curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_check, CURLOPT_HTTPHEADER, $headers);
    $response_check = curl_exec($ch_check);
    curl_close($ch_check);
    $existing_client = json_decode($response_check, true);

    $codigo_verificacao = rand(100000, 999999);
    $agora = new DateTime();
    $agora->add(new DateInterval('PT15M'));
    $codigo_expira_em = $agora->format('Y-m-d H:i:s');

    if (count($existing_client) > 0) {
        if (!$existing_client[0]['email_verificado']) {
            $update_endpoint = $supabase_url . '/rest/v1/clientes?email=eq.' . urlencode($data->email);
            $patchData = [
                'codigo_verificacao' => (string)$codigo_verificacao,
                'codigo_expira_em' => $codigo_expira_em
            ];
            $ch_patch = curl_init($update_endpoint);
            curl_setopt($ch_patch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_patch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch_patch, CURLOPT_POSTFIELDS, json_encode($patchData));
            curl_setopt($ch_patch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
            curl_exec($ch_patch);
            curl_close($ch_patch);
        } else {
            throw new Exception('Este e-mail já está cadastrado e verificado.', 409);
        }
    } else {
        $senha_hash = password_hash($data->senha, PASSWORD_DEFAULT);
        $insert_endpoint = $supabase_url . '/rest/v1/clientes';
        $postData = [
            'nome_completo' => $data->nome_completo, 'email' => $data->email,
            'senha_hash' => $senha_hash, 'codigo_verificacao' => (string)$codigo_verificacao,
            'codigo_expira_em' => $codigo_expira_em, 'email_verificado' => false
        ];
        $ch_insert = curl_init($insert_endpoint);
        curl_setopt($ch_insert, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_insert, CURLOPT_POST, true);
        curl_setopt($ch_insert, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch_insert, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json', 'Prefer: return=minimal']));
        curl_exec($ch_insert);
        $httpcode = curl_getinfo($ch_insert, CURLINFO_HTTP_CODE);
        curl_close($ch_insert);

        if ($httpcode != 201) {
             throw new Exception('Ocorreu um erro ao salvar o cadastro no banco de dados.', 500);
        }
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USERNAME, 'Supermercado Veronesi');
        $mail->addAddress($data->email, $data->nome_completo);

        $mail->isHTML(true);
        $mail->Subject = 'Seu código de verificação - Supermercado Veronesi';
        $mail->Body    = "Seu código de verificação é: <strong>{$codigo_verificacao}</strong>";
        $mail->AltBody = "Seu código de verificação é: {$codigo_verificacao}";

        $mail->send();
        
        http_response_code(201);
        echo json_encode(['status' => 'success', 'message' => 'Enviámos um novo código para o seu e-mail.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'message' => "Cadastro salvo, mas houve uma falha ao enviar o e-mail de verificação.", 
            'error_details' => "Detalhe do PHPMailer: " . $mail->ErrorInfo 
        ]);
    }

} catch (Throwable $th) {
    $http_code = $th->getCode() >= 400 ? $th->getCode() : 500;
    http_response_code($http_code);
    echo json_encode(['message' => $th->getMessage()]);
}
?>

