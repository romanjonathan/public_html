<?php
ob_start();
header('Content-Type: application/json');

$apps_script_url = 'https://script.google.com/macros/s/AKfycbxsWa58Mipi2a2ugbqxbcrxRLWa_HxerVGc4Q1zr-nhPdV9supcn-_3xIPt6cz6HuVb/exec';

$data = file_get_contents('php://input');
if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$ch = curl_init($apps_script_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

ob_clean();

if ($err) {
    echo json_encode(['status' => 'error', 'message' => $err]);
} elseif ($http_code >= 200 && $http_code < 500) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'HTTP ' . $http_code]);
}
