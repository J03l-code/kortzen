<?php
require_once 'config.php';
requireLogin();
if (($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Acceso restringido.');
}
$creds = [
    ['root', ''],
    ['root', 'root'],
    ['root', 'password'],
    ['admin', 'admin'],
    ['admin', 'password'],
    ['kortzen', 'kortzen'],
    ['joel', 'password']
];

foreach ($hosts as $host) {
    foreach ($creds as $pair) {
        echo "Trying Host: $host, User: {$pair[0]}, Pass: {$pair[1]} ... ";
        try {
            $pdo = new PDO("mysql:host=$host;connect_timeout=2", $pair[0], $pair[1]);
            echo "SUCCESS!\n";
            echo "Databases:\n";
            $stmt = $pdo->query("SHOW DATABASES");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo " - " . $row['Database'] . "\n";
            }
            exit;
        } catch (PDOException $e) {
            echo "Failed: " . $e->getMessage() . "\n";
        }
    }
}
echo "All attempts failed.\n";
