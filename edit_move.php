<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin' || !isset($_GET['id'])) { header("Location: index.php"); exit; }

$host = "db"; $dbname = "pokedex"; $user = "root"; $password = "root";
$id = (int)$_GET['id'];
$types = ['Normal', 'Fire', 'Water', 'Grass', 'Electric', 'Ice', 'Fighting', 'Poison', 'Ground', 'Flying', 'Psychic', 'Bug', 'Rock', 'Ghost', 'Dragon', 'Steel', 'Fairy', 'Dark'];
$categories = ['Physical', 'Special', 'Status'];

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("SELECT * FROM move WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acc = !empty($_POST['accuracy']) ? $_POST['accuracy'] / 100 : null;
        $pow = !empty($_POST['power']) ? $_POST['power'] : null;
        
        $sql = "UPDATE move SET name=?, move_type=?::public.element_type, category=?, power=?, accuracy=?, pp=?, description=? WHERE id=?";
        $pdo->prepare($sql)->execute([
            $_POST['name'], $_POST['type'], $_POST['category'], $pow, $acc, $_POST['pp'], $_POST['description'], $id
        ]);
        header("Location: move_card.php?id=$id");
        exit;
    }
} catch (PDOException $e) { die($e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Move</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; padding: 40px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        input, select, textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; }
        button { width: 100%; padding: 15px; background: #2196F3; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; text-transform: uppercase; }
    </style>
</head>
<body>
<div class="container">
    <h1>Edit Move</h1>
    <form method="POST">
        <label>Name</label> <input type="text" name="name" value="<?= htmlspecialchars($m['name']) ?>" required>
        
        <label>Type</label>
        <select name="type">
            <?php foreach($types as $t) echo "<option value='$t' ".($m['move_type']==$t?'selected':'').">$t</option>"; ?>
        </select>

        <label>Category</label>
        <select name="category">
            <?php foreach($categories as $c) echo "<option value='$c' ".($m['category']==$c?'selected':'').">$c</option>"; ?>
        </select>

        <label>Power</label> <input type="number" name="power" value="<?= $m['power'] ?>">
        <label>Accuracy %</label> <input type="number" name="accuracy" value="<?= $m['accuracy'] ? $m['accuracy']*100 : '' ?>">
        <label>PP</label> <input type="number" name="pp" value="<?= $m['pp'] ?>" required>
        <label>Description</label> <textarea name="description"><?= htmlspecialchars($m['description']) ?></textarea>

        <button type="submit">Update Move</button>
    </form>
</div>
</body>
</html>