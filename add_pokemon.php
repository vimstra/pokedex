<?php
session_start();

// Zabezpieczenie: Tylko Admin
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

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. Pobranie listy ataków
    $stmtMoves = $pdo->query("SELECT id, name, move_type::text as type FROM public.move ORDER BY name ASC");
    $allMoves = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pobranie listy generacji (Tego brakowało)
    $stmtGen = $pdo->query("SELECT * FROM public.generation ORDER BY id ASC");
    $generations = $stmtGen->fetchAll(PDO::FETCH_ASSOC);

    // OBSŁUGA FORMULARZA
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dexNum = $_POST['pokedex_number'];
        $name = $_POST['name'];
        $img = $_POST['image_url'];
        $type1 = $_POST['type1'];
        $type2 = !empty($_POST['type2']) ? $_POST['type2'] : null;
        $desc = $_POST['description'];
        $height = $_POST['height'];
        $weight = $_POST['weight'];
        
        $hp = $_POST['hp'];
        $atk = $_POST['atk'];
        $def = $_POST['def'];
        $spa = $_POST['spa'];
        $spd = $_POST['spd'];
        $spe = $_POST['spe'];
        $genId = $_POST['generation_id']; // Pobieramy generację
        
        $selectedMoves = $_POST['moves'] ?? [];

        $pdo->beginTransaction();

        try {
            $sql = "INSERT INTO public.pokemon 
                    (pokedex_number, name, image_url, pokemon_type, secondary_type, description, height, weight, hp, attack, defense, sp_attack, sp_defense, speed, generation_id, created_by) 
                    VALUES (?, ?, ?, ?::public.element_type, ?::public.element_type, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    RETURNING id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dexNum, $name, $img, $type1, $type2, $desc, $height, $weight, 
                $hp, $atk, $def, $spa, $spd, $spe, 
                $genId, 
                $_SESSION['user_id']
            ]);
            
            $newPokemonId = $stmt->fetchColumn();

            if (!empty($selectedMoves)) {
                $sqlMove = "INSERT INTO public.pokemon_moves (pokemon_id, move_id) VALUES (?, ?)";
                $stmtMove = $pdo->prepare($sqlMove);
                foreach ($selectedMoves as $moveId) {
                    $stmtMove->execute([$newPokemonId, $moveId]);
                }
            }

            $pdo->commit();
            $success = "Pokemon $name added successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error adding pokemon: " . $e->getMessage();
        }
    }

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Pokemon - Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        
        /* KLUCZOWA POPRAWKA: box-sizing zapobiega rozjeżdżaniu się pól input */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; color: #333; padding-bottom: 50px; }
        
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
        .back-btn:hover { background: rgba(255,255,255,0.4); }

        .container { max-width: 800px; margin: 40px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        h1 { text-align: center; color: #DC0A2D; margin-bottom: 30px; }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; /* Odstęp między kolumnami */
        }
        
        .form-group { margin-bottom: 5px; } /* Mniejszy margines dolny wewnątrz grida */
        
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #555; }
        
        input, select, textarea { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            font-family: inherit; 
            background: #fff;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #DC0A2D;
            box-shadow: 0 0 0 3px rgba(220, 10, 45, 0.1);
        }

        textarea { resize: vertical; height: 100px; }
        
        .full-width { grid-column: 1 / -1; margin-bottom: 15px; }
        
        .submit-btn { width: 100%; padding: 15px; background: #DC0A2D; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 1rem; margin-top: 20px; transition: 0.3s; }
        .submit-btn:hover { background: #b00824; }
        
        /* Moves Selector Style */
        .moves-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }
        #move-search-input { margin-bottom: 15px; }
        
        .moves-list-scroll {
            height: 300px;
            overflow-y: scroll;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            grid-auto-rows: max-content;
            gap: 8px;
            padding-right: 5px;
        }
        .move-option { 
            display: flex; align-items: center; gap: 10px; 
            font-size: 0.85rem; background: white; padding: 10px; 
            border-radius: 6px; border: 1px solid #eee; 
            transition: all 0.2s; cursor: pointer; user-select: none;
        }
        .move-option:hover { border-color: #DC0A2D; background-color: #fff5f5; transform: translateY(-1px); }
        .move-option:has(input:checked) { background-color: #ffe6e6; border-color: #DC0A2D; font-weight: 600; }
        
        /* Dots */
        .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .type-fire { background-color: #F07F31; } .type-water { background-color: #698FEF; } .type-grass { background-color: #77C750; }
        .type-electric { background-color: #F8D12E; } .type-psychic { background-color: #F95587; } .type-rock { background-color: #B89F39; }
        .type-normal { background-color: #A7A779; } .type-ice { background-color: #97D8D8; } .type-fighting {background-color: #C03028; } .type-poison {background-color: #A140A1; }
        .type-ground {background-color: #E1BF67; } .type-flying {background-color: #A790EF; } .type-bug {background-color: #A7B920; } .type-ghost {background-color: #705898; }
        .type-dragon {background-color: #7038F7; } .type-dark {background-color: #705848; } .type-steel {background-color: #B8B8CF; } .type-fairy {background-color: #ED99AB; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" class="back-btn">&larr; Back to Pokedex</a>
    <span style="font-weight: 800; font-size: 1.2rem;">Add New Pokemon</span>
</div>

<div class="container">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Pokedex #</label>
                <input type="number" name="pokedex_number" required>
            </div>

            <div class="form-group">
                <label>Primary Type</label>
                <select name="type1" required>
                    <?php foreach($types as $t): echo "<option value='$t'>$t</option>"; endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Secondary Type</label>
                <select name="type2">
                    <option value="">None</option>
                    <?php foreach($types as $t): echo "<option value='$t'>$t</option>"; endforeach; ?>
                </select>
            </div>

            <!-- PRZYWRÓCONE POLE GENERACJI -->
            <div class="form-group">
                <label>Generation</label>
                <select name="generation_id" required>
                    <?php foreach($generations as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= $g['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Pusty div dla zachowania układu siatki (żeby gen nie był samotny) -->
            <div class="form-group"></div>

            <div class="form-group full-width">
                <label>Image URL</label>
                <input type="url" name="image_url" placeholder="https://..." required>
            </div>

            <div class="form-group full-width">
                <label>Description</label>
                <textarea name="description" required></textarea>
            </div>

            <div class="form-group">
                <label>Height (m)</label>
                <input type="number" step="0.01" name="height" required>
            </div>
            <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" step="0.1" name="weight" required>
            </div>

            <!-- Stats -->
            <div class="form-group"><label>HP</label><input type="number" name="hp" required></div>
            <div class="form-group"><label>Attack</label><input type="number" name="atk" required></div>
            <div class="form-group"><label>Defense</label><input type="number" name="def" required></div>
            <div class="form-group"><label>Sp. Atk</label><input type="number" name="spa" required></div>
            <div class="form-group"><label>Sp. Def</label><input type="number" name="spd" required></div>
            <div class="form-group"><label>Speed</label><input type="number" name="spe" required></div>

            <!-- Moves -->
            <div class="form-group full-width">
                <label>Assign Moves (Click to select)</label>
                <div class="moves-container">
                    <input type="text" id="move-search-input" placeholder="Type to search moves...">
                    <div class="moves-list-scroll">
                        <?php foreach($allMoves as $m): ?>
                            <label class="move-option" data-name="<?= strtolower($m['name']) ?>">
                                <input type="checkbox" name="moves[]" value="<?= $m['id'] ?>">
                                <span class="dot type-<?= strtolower($m['type']) ?>"></span>
                                <span style="font-weight:500; color:#333; margin-top:2px;"><?= htmlspecialchars($m['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn">Save Pokemon</button>
    </form>
</div>

<script>
    const searchInput = document.getElementById('move-search-input');
    const moveOptions = document.querySelectorAll('.move-option');

    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        moveOptions.forEach(option => {
            const name = option.getAttribute('data-name');
            option.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });
</script>

</body>
</html>