<?php
session_start();

// Zabezpieczenie
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$host = "db";
$dbname = "pokedex";
$user = "root";
$password = "root";

$error = null;
$success = null;
$types = ['Normal', 'Fire', 'Water', 'Grass', 'Electric', 'Ice', 'Fighting', 'Poison', 'Ground', 'Flying', 'Psychic', 'Bug', 'Rock', 'Ghost', 'Dragon', 'Steel', 'Fairy', 'Dark'];
$categories = ['Physical', 'Special', 'Status'];

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $type = $_POST['type'];
        $cat = $_POST['category'];
        $power = !empty($_POST['power']) ? $_POST['power'] : null;
        $acc = !empty($_POST['accuracy']) ? $_POST['accuracy'] / 100 : null; // Konwersja 95 -> 0.95
        $pp = $_POST['pp'];
        $desc = $_POST['description'];

        try {
            $sql = "INSERT INTO public.move (name, move_type, category, power, accuracy, pp, description, generation_id) 
                    VALUES (?, ?::public.element_type, ?, ?, ?, ?, ?, 1)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $type, $cat, $power, $acc, $pp, $desc]);
            
            $success = "Move $name added successfully!";
        } catch (Exception $e) {
            $error = "Error adding move: " . $e->getMessage();
        }
    }

} catch (PDOException $e) {
    $error = "DB Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Move - Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; color: #333; margin: 0; padding-bottom: 50px; }
        .container { max-width: 600px; margin: 40px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        h1 { text-align: center; color: #DC0A2D; margin-bottom: 30px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; }
        
        .submit-btn { width: 100%; padding: 15px; background: #DC0A2D; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 1rem; margin-top: 20px; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #666; text-decoration: none; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
        }

        .top-bar {
            background-color: #DC0A2D;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="index.php" class="back-btn">&larr; Back to Pokedex</a>
        <span style="font-weight: 800; font-size: 1.2rem;">Add Move</span>
    </div>

<div class="container">
    <h1>Add New Move</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Move Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Type</label>
            <select name="type" required>
                <?php foreach($types as $t): echo "<option value='$t'>$t</option>"; endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category" required>
                <?php foreach($categories as $c): echo "<option value='$c'>$c</option>"; endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Power (Optional, leave empty for Status moves)</label>
            <input type="number" name="power">
        </div>

        <div class="form-group">
            <label>Accuracy % (0-100, Optional)</label>
            <input type="number" name="accuracy" placeholder="e.g. 95">
        </div>

        <div class="form-group">
            <label>PP</label>
            <input type="number" name="pp" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" style="height: 80px;" required></textarea>
        </div>

        <button type="submit" class="submit-btn">Save Move</button>
        <a href="index.php" class="back-link">Cancel & Return</a>
    </form>
</div>

</body>
</html>