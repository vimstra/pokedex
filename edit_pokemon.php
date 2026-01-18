<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin' || !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$host = "db"; $dbname = "pokedex"; $user = "root"; $password = "root";
$error = null; $success = null;
$id = (int)$_GET['id'];
$types = ['Normal', 'Fire', 'Water', 'Grass', 'Electric', 'Ice', 'Fighting', 'Poison', 'Ground', 'Flying', 'Psychic', 'Bug', 'Rock', 'Ghost', 'Dragon', 'Steel', 'Fairy', 'Dark'];

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. POBIERZ DANE POKEMONA
    $stmtP = $pdo->prepare("SELECT * FROM pokemon WHERE id = ?");
    $stmtP->execute([$id]);
    $p = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$p) die("Pokemon not found");

    // 2. POBIERZ AKTUALNE ATAKI (ID)
    $stmtMyMoves = $pdo->prepare("SELECT move_id FROM pokemon_moves WHERE pokemon_id = ?");
    $stmtMyMoves->execute([$id]);
    $currentMoveIds = $stmtMyMoves->fetchAll(PDO::FETCH_COLUMN); // Tablica ID [1, 5, 20]

    // 3. POBIERZ WSZYSTKIE ATAKI DO LISTY
    $allMoves = $pdo->query("SELECT id, name, move_type::text as type FROM public.move ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 4. OBSŁUGA ZAPISU
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        try {
            // Update tabeli pokemon
            $sql = "UPDATE pokemon SET 
                    pokedex_number=?, name=?, image_url=?, pokemon_type=?::public.element_type, secondary_type=?::public.element_type, 
                    description=?, height=?, weight=?, hp=?, attack=?, defense=?, sp_attack=?, sp_defense=?, speed=?
                    WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $type2 = !empty($_POST['type2']) ? $_POST['type2'] : null;
            
            $stmt->execute([
                $_POST['pokedex_number'], $_POST['name'], $_POST['image_url'], $_POST['type1'], $type2,
                $_POST['description'], $_POST['height'], $_POST['weight'],
                $_POST['hp'], $_POST['atk'], $_POST['def'], $_POST['spa'], $_POST['spd'], $_POST['spe'],
                $id
            ]);

            // Update ataków (najpierw usuń wszystkie, potem dodaj zaznaczone)
            $pdo->prepare("DELETE FROM pokemon_moves WHERE pokemon_id = ?")->execute([$id]);
            
            if (!empty($_POST['moves'])) {
                $stmtM = $pdo->prepare("INSERT INTO pokemon_moves (pokemon_id, move_id) VALUES (?, ?)");
                foreach ($_POST['moves'] as $mid) {
                    $stmtM->execute([$id, $mid]);
                }
            }

            $pdo->commit();
            header("Location: pokemon_card.php?id=$id"); // Powrót do karty
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }

} catch (PDOException $e) { die("DB Error"); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Pokemon</title>
    <!-- Tu wklej ten sam styl co w add_pokemon.php, żeby było ładnie -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; color: #333; margin: 0; padding-bottom: 50px; }
        .container { max-width: 800px; margin: 40px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .full-width { grid-column: 1 / -1; }
        .submit-btn { width: 100%; padding: 15px; background: #2196F3; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; text-transform: uppercase; margin-top: 20px; }
        
        /* Moves styles copied from add_pokemon.php */
        .moves-list-scroll { height: 300px; overflow-y: scroll; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); grid-auto-rows: max-content; gap: 8px; }
        .move-option { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #eee; border-radius: 6px; }
        .move-option:has(input:checked) { background-color: #e3f2fd; border-color: #2196F3; } /* Niebieski dla edycji */
        .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        /* ... (reszta stylów typów opcjonalna) ... */
    </style>
</head>
<body>
<div class="container">
    <h1 style="text-align:center;">Edit <?= htmlspecialchars($p['name']) ?></h1>
    <?php if($error) echo "<p style='color:red;text-align:center'>$error</p>"; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required></div>
            <div class="form-group"><label>Pokedex #</label><input type="number" name="pokedex_number" value="<?= $p['pokedex_number'] ?>" required></div>

            <div class="form-group">
                <label>Primary Type</label>
                <select name="type1">
                    <?php foreach($types as $t) echo "<option value='$t' ".($p['pokemon_type']==$t?'selected':'').">$t</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Secondary Type</label>
                <select name="type2">
                    <option value="">None</option>
                    <?php foreach($types as $t) echo "<option value='$t' ".($p['secondary_type']==$t?'selected':'').">$t</option>"; ?>
                </select>
            </div>

            <div class="form-group full-width"><label>Image URL</label><input type="url" name="image_url" value="<?= htmlspecialchars($p['image_url']) ?>" required></div>
            <div class="form-group full-width"><label>Description</label><textarea name="description" style="height:100px;width:100%"><?= htmlspecialchars($p['description']) ?></textarea></div>

            <div class="form-group"><label>Height</label><input type="number" step="0.01" name="height" value="<?= $p['height'] ?>" required></div>
            <div class="form-group"><label>Weight</label><input type="number" step="0.1" name="weight" value="<?= $p['weight'] ?>" required></div>

            <div class="form-group"><label>HP</label><input type="number" name="hp" value="<?= $p['hp'] ?>"></div>
            <div class="form-group"><label>Attack</label><input type="number" name="atk" value="<?= $p['attack'] ?>"></div>
            <div class="form-group"><label>Defense</label><input type="number" name="def" value="<?= $p['defense'] ?>"></div>
            <div class="form-group"><label>Sp. Atk</label><input type="number" name="spa" value="<?= $p['sp_attack'] ?>"></div>
            <div class="form-group"><label>Sp. Def</label><input type="number" name="spd" value="<?= $p['sp_defense'] ?>"></div>
            <div class="form-group"><label>Speed</label><input type="number" name="spe" value="<?= $p['speed'] ?>"></div>

            <div class="form-group full-width">
                <label>Moves</label>
                <div class="moves-list-scroll">
                    <?php foreach($allMoves as $m): ?>
                        <label class="move-option">
                            <!-- check if move ID is in currentMoveIds array -->
                            <input type="checkbox" name="moves[]" value="<?= $m['id'] ?>" <?= in_array($m['id'], $currentMoveIds) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($m['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <button type="submit" class="submit-btn">Update Pokemon</button>
        <a href="pokemon_card.php?id=<?= $id ?>" style="display:block;text-align:center;margin-top:15px;color:#666;text-decoration:none;">Cancel</a>
    </form>
</div>
</body>
</html>