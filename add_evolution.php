<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin' || !isset($_GET['id'])) { header("Location: index.php"); exit; }

$host = "db"; $dbname = "pokedex"; $user = "root"; $password = "root";
$preId = (int)$_GET['id'];
$msg = null;

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $currentName = $pdo->query("SELECT name FROM pokemon WHERE id = $preId")->fetchColumn();
    $allPkmn = $pdo->query("SELECT id, name FROM pokemon WHERE id != $preId ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postId = $_POST['post_id'];
        $trigger = $_POST['trigger'];
        
        // Zbieramy dane zależnie od typu
        $lvl = (!empty($_POST['min_level']) && $trigger === 'Level') ? $_POST['min_level'] : null;
        $item = (!empty($_POST['item']) && $trigger === 'Item') ? $_POST['item'] : null;
        $notes = (!empty($_POST['notes']) && $trigger === 'Other') ? $_POST['notes'] : null;

        $stmt = $pdo->prepare("INSERT INTO evolution (pre_evolution_id, post_evolution_id, trigger_type, min_level, item, notes) VALUES (?, ?, ?::public.element_type, ?, ?, ?)");
        $stmt->execute([$preId, $postId, $trigger, $lvl, $item, $notes]);
        
        $msg = "Evolution added successfully!";
    }

} catch (Exception $e) { $msg = "Error: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Evolution</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; padding: 40px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        select, input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; }
        button { width: 100%; padding: 15px; background: #FF9800; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; text-transform: uppercase; margin-top:10px; }
        
        /* Klasa do ukrywania pól */
        .hidden { display: none; }
    </style>
</head>
<body>
<div class="container">
    <h1>Add Evolution</h1>
    <p>From: <strong><?= htmlspecialchars($currentName) ?></strong></p>
    
    <?php if($msg) echo "<p style='color:green;text-align:center'>$msg</p>"; ?>

    <form method="POST">
        <div class="form-group">
            <label>Evolves Into:</label>
            <select name="post_id" required>
                <?php foreach($allPkmn as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Trigger Type</label>
            <select name="trigger" id="triggerSelect" onchange="toggleFields()">
                <option value="Level">Level Up</option>
                <option value="Item">Item Use</option>
                <option value="Other">Other / Special Condition</option>
            </select>
        </div>

        <!-- Pole dla Level -->
        <div class="form-group" id="field-level">
            <label>Min Level</label>
            <input type="number" name="min_level" placeholder="e.g. 16">
        </div>

        <!-- Pole dla Item -->
        <div class="form-group hidden" id="field-item">
            <label>Item Name</label>
            <input type="text" name="item" placeholder="e.g. Fire Stone">
        </div>

        <!-- Pole dla Other (Notes) -->
        <div class="form-group hidden" id="field-notes">
            <label>Condition Description</label>
            <input type="text" name="notes" placeholder="e.g. High Friendship during day">
        </div>

        <button type="submit">Save Evolution</button>
        <a href="pokemon_card.php?id=<?= $preId ?>" style="display:block;text-align:center;margin-top:15px;color:#666;text-decoration:none;">Back to Card</a>
    </form>
    
    <script>
        function toggleFields() {
            const val = document.getElementById('triggerSelect').value;
            
            document.getElementById('field-level').classList.add('hidden');
            document.getElementById('field-item').classList.add('hidden');
            document.getElementById('field-notes').classList.add('hidden');

            if (val === 'Level') document.getElementById('field-level').classList.remove('hidden');
            if (val === 'Item') document.getElementById('field-item').classList.remove('hidden');
            if (val === 'Other') document.getElementById('field-notes').classList.remove('hidden');
        }

        toggleFields();
    </script>
</div>
</body>
</html>