<?php
session_start();

// admin only
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
        // deleting from the database just to make sure (in case theres not on delete cascade in db)
        $pdo->prepare("DELETE FROM pokemon_moves WHERE pokemon_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM evolution WHERE pre_evolution_id = ? OR post_evolution_id = ?")->execute([$id, $id]);
        $pdo->prepare("DELETE FROM user_party WHERE pokemon_id = ?")->execute([$id]);
        
        // deleting the pokemon
        $stmt = $pdo->prepare("DELETE FROM pokemon WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: index.php");
    } 
    elseif ($type === 'move') {
        $pdo->prepare("DELETE FROM pokemon_moves WHERE move_id = ?")->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM move WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: index.php?tab=moves");
    }

} catch (PDOException $e) {
    die("Error deleting entry: " . $e->getMessage());
}
?>