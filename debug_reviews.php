<?php
require_once 'config.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>KORTZEN Reviews Debugger</h1>";

// 1. Check DB Connection
try {
    $pdo = getConnection();
    echo "<p style='color:green'>✅ Database Connection Successful</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database Connection Failed: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Check Table Existence
try {
    $check = query("SHOW TABLES LIKE 'resenas'");
    if (count($check) > 0) {
        echo "<p style='color:green'>✅ Table 'resenas' exists</p>";
    } else {
        echo "<p style='color:red'>❌ Table 'resenas' DOES NOT EXIST. Please run migration_reviews.php</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error checking table: " . $e->getMessage() . "</p>";
}

// 3. Dump Data
echo "<h2>Current Reviews in DB:</h2>";
try {
    $reviews = query("SELECT * FROM resenas");

    if (count($reviews) == 0) {
        echo "<p>No reviews found in table.</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Cliente</th><th>Comentario</th><th>Visible (1=Yes)</th><th>Fecha</th></tr>";
        foreach ($reviews as $r) {
            echo "<tr>";
            echo "<td>" . $r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['cliente_nombre']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($r['comentario'], 0, 50)) . "...</td>";
            echo "<td>" . ($r['visible'] == 1 ? '<span style="color:green">YES (1)</span>' : '<span style="color:red">NO (0)</span>') . "</td>";
            echo "<td>" . $r['fecha'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error fetching reviews: " . $e->getMessage() . "</p>";
}

// 4. Test API response simulation
echo "<h2>API Response Simulation (Visible Only):</h2>";
try {
    $visible = query("SELECT * FROM resenas WHERE visible = 1 LIMIT 10");
    echo "<pre>" . json_encode($visible, JSON_PRETTY_PRINT) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error simulating API: " . $e->getMessage() . "</p>";
}
?>