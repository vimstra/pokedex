<?php
session_start();
header('Content-Type: application/json');

$host = "db"; 
$dbname = "pokedex";
$user = "root";
$password = "root";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pokemonId = $input['pokemon_id'] ?? null;
$userId = $_SESSION['user_id'];

if (!$pokemonId) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $check = $pdo->prepare("SELECT COUNT(*) FROM public.user_party WHERE user_id = ? AND pokemon_id = ?");
    $check->execute([$userId, $pokemonId]);
    $exists = $check->fetchColumn() > 0;

    if ($exists) {
        $del = $pdo->prepare("DELETE FROM public.user_party WHERE user_id = ? AND pokemon_id = ?");
        $del->execute([$userId, $pokemonId]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        $ins = $pdo->prepare("INSERT INTO public.user_party (user_id, pokemon_id) VALUES (?, ?)");
        $ins->execute([$userId, $pokemonId]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}