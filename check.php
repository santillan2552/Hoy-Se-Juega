<?php
require_once 'class/config.php';
$pdo = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>";
print_r($tables);

// Mostrar contenido de cada tabla
foreach($tables as $table) {
    echo "\n\n=== $table ===\n";
    $rows = $pdo->query("SELECT * FROM $table LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
}
echo "</pre>";
?>
