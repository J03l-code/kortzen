<?php
/**
 * API para obtener el estado de autenticación del cliente
 * Usado por el frontend para mostrar/ocultar elementos según el login
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

session_start();
require_once __DIR__ . '/../config.php';

$response = [
    'success' => true,
    'isLoggedIn' => isClienteLoggedIn(),
    'user' => null
];

if (isClienteLoggedIn()) {
    $response['user'] = [
        'id' => $_SESSION['cliente_id'] ?? null,
        'nombre' => $_SESSION['cliente_nombre'] ?? 'Cliente',
        'email' => $_SESSION['cliente_email'] ?? '',
        'foto' => $_SESSION['cliente_foto'] ?? null,
        'inicial' => strtoupper(substr($_SESSION['cliente_nombre'] ?? 'C', 0, 1))
    ];
}

echo json_encode($response);
