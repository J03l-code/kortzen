<?php
// Test de escritura en base de datos
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Test Write</title></head><body>";
echo "<h1>Prueba de Escritura en DB</h1>";

try {
    $pdo = getConnection();
    echo "✓ Conexión exitosa<br>";

    // 1. Intentar INSERT en logs
    $microtime = microtime(true);
    $testMsg = "Test Write $microtime";
    echo "• Intentando INSERT...<br>";

    // Usamos logs_actividad que es menos intrusivo
    $sql = "INSERT INTO logs_actividad (accion, descripcion) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute(['TEST', $testMsg]);

    if ($result) {
        $lastId = $pdo->lastInsertId();
        echo "✓ INSERT exitoso. ID: $lastId<br>";

        // 2. Intentar SELECT para verificar
        echo "• Intentando SELECT verificación...<br>";
        $stmt = $pdo->prepare("SELECT * FROM logs_actividad WHERE id = ?");
        $stmt->execute([$lastId]);
        $row = $stmt->fetch();

        if ($row && $row['descripcion'] === $testMsg) {
            echo "✓ SELECT exitoso. Datos coinciden.<br>";

            // 3. Intentar DELETE
            echo "• Intentando DELETE...<br>";
            $stmt = $pdo->prepare("DELETE FROM logs_actividad WHERE id = ?");
            $delResult = $stmt->execute([$lastId]);
            if ($delResult) {
                echo "✓ DELETE exitoso.<br>";
                echo "<h3>RESULTADO: La base de datos funciona correctamente para escritura.</h3>";
            } else {
                echo "✗ ERROR en DELETE.<br>";
            }

        } else {
            echo "✗ ERROR: No se encontró el registro insertado.<br>";
        }

    } else {
        echo "✗ ERROR en INSERT.<br>";
        print_r($stmt->errorInfo());
    }

} catch (PDOException $e) {
    echo "<h2>✗ EXCEPCIÓN PDO:</h2>";
    echo $e->getMessage();
}

echo "<hr><a href='dashboard.php'>Volver al Dashboard</a>";
echo "</body></html>";
