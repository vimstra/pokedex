<?php
session_start();

// Tylko Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    die("Access denied");
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die("Invalid request");
}

$id = (int)$_GET['id'];
$type = $_GET['type'];

$host = "db"; $dbname = "pokedex"; $user = "root"; $password = "root";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($type === 'pokemon') {
        // Usuwamy zależności ręcznie dla pewności (jeśli w bazie nie ma ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM pokemon_moves WHERE pokemon_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM evolution WHERE pre_evolution_id = ? OR post_evolution_id = ?")->execute([$id, $id]);
        $pdo->prepare("DELETE FROM user_party WHERE pokemon_id = ?")->execute([$id]);
        
        // Usuwamy pokemona
        $stmt = $pdo->prepare("DELETE FROM pokemon WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: index.php");
    } 
    elseif ($type === 'move') {
        $pdo->prepare("DELETE FROM pokemon_moves WHERE move_id = ?")->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM move WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: index.php?tab=moves"); // Opcjonalnie przekierowanie na zakładkę
    }

} catch (PDOException $e) {
    die("Error deleting entry: " . $e->getMessage());
}
?>