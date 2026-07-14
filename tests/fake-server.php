<?php
// Servidor fake pros testes (php -S): grava a última request em um arquivo e
// devolve a resposta enfileirada em outro. Estado em arquivos porque cada
// request do php -S roda num processo isolado.

$dir = sys_get_temp_dir() . '/carecapay-sdk-php-tests';
@mkdir($dir);

file_put_contents("$dir/last-request.json", json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $_SERVER['REQUEST_URI'],
    'auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
    'body' => file_get_contents('php://input'),
]));

$response = json_decode(file_get_contents("$dir/next-response.json"), true);
http_response_code($response['status']);
header('Content-Type: application/json');
echo json_encode($response['body']);
