<?php
session_start();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$move = null;
$learnedBy = [];

$host = "db"; 
$dbname = "pokedex";
$user = "root";
$password = "root";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "SELECT m.*, 
                   m.move_type::text as type, 
                   m.category::text as cat,
                   g.name as gen_name
            FROM public.move m
            LEFT JOIN public.generation g ON m.generation_id = g.id
            WHERE m.id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $move = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$move) {
        die("Move not found!");
    }

    // pulling pokemons who know this move
    // JOIN: pokemon -> pokemon_moves
    $sqlLearned = "
        SELECT p.id, p.pokedex_number, p.name, p.image_url, 
               p.pokemon_type::text as type_1, 
               p.secondary_type::text as type_2
        FROM public.pokemon p
        JOIN public.pokemon_moves pm ON p.id = pm.pokemon_id
        WHERE pm.move_id = ?
        ORDER BY p.pokedex_number ASC
    ";
    
    $stmtLearned = $pdo->prepare($sqlLearned);
    $stmtLearned->execute([$id]);
    $learnedBy = $stmtLearned->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($move['name']) ?> - Move Details</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #333; padding-bottom: 50px;}
        
        /* Top Bar */
        .top-bar {
            background-color: #333;
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

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 40px;
            margin-bottom: 30px;
        }

        .move-header { border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .move-name { font-size: 2.5rem; font-weight: 800; color: #333; margin-bottom: 10px; }
        .move-desc { font-size: 1.1rem; color: #666; font-style: italic; line-height: 1.6; margin-bottom: 20px; }

        .badges-row { display: flex; gap: 10px; margin-bottom: 20px; }
        .badge { padding: 8px 16px; border-radius: 8px; color: white; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center; }
        .stat-box { background: #f9f9f9; padding: 15px; border-radius: 10px; border: 1px solid #eee; }
        .stat-label { font-size: 0.8rem; color: #999; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: #333; }

        .section-title { font-size: 1.5rem; font-weight: 800; margin: 40px 0 20px 0; color: #333; padding-left: 10px; border-left: 5px solid #333; }
        
        .poke-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; justify-items: center; }
        
        .poke-tile { 
            width: 180px; height: 180px; background: white; border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 2px solid transparent; 
            display: flex; flex-direction: column; align-items: center; justify-content: space-between; 
            padding: 15px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: inherit;
        }
        .poke-tile:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #333; }
        .poke-img { max-width: 80%; max-height: 80px; object-fit: contain; }
        .poke-name { font-weight: 700; font-size: 1rem; text-transform: uppercase; margin-top: 10px; color: #333; }
        .poke-index { font-size: 0.8rem; color: #ccc; font-weight: 600; }
        
        .type-fire { background-color: #F07F31; } .type-water { background-color: #698FEF; } .type-grass { background-color: #77C750; }
        .type-electric { background-color: #F8D12E; color: #333;} .type-psychic { background-color: #F95587; } .type-rock { background-color: #B89F39; }
        .type-normal { background-color: #A7A779; } .type-ice { background-color: #97D8D8; color: #333; } .type-fighting {background-color: #C03028; } .type-poison {background-color: #A140A1; }
        .type-ground {background-color: #E1BF67; } .type-flying {background-color: #A790EF; } .type-bug {background-color: #A7B920; } .type-ghost {background-color: #705898; }
        .type-dragon {background-color: #7038F7; } .type-dark {background-color: #705848; } .type-steel {background-color: #B8B8CF; } .type-fairy {background-color: #ED99AB; }
        
        .category-physical { background-color: #EB5629; } .category-special { background-color: #375AB1; } .category-status { background-color: #818181; }

    </style>
</head>
<body>

    <div class="top-bar">
        <a href="javascript:history.back()" class="back-btn">&larr; Go Back</a>
        <span style="font-weight: 800; font-size: 1.2rem;"><?= htmlspecialchars($move['name']) ?></span>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
        <div style="margin-left: auto; display: flex; gap: 10px;">
            <a href="edit_move.php?id=<?= $move['id'] ?>" class="back-btn" style="background: #2196F3;">Edit</a>
            <a href="delete_entry.php?type=move&id=<?= $move['id'] ?>" class="back-btn" style="background: #f44336;" onclick="return confirm('Are you sure you want to delete this Move?');">Delete</a>
        </div>
    <?php endif; ?>
    </div>

    <div class="container">
        <!-- MOVE DETAILS CARD -->
        <div class="card">
            <div class="move-header">
                <div class="badges-row">
                    <span class="badge type-<?= strtolower($move['type']) ?>"><?= $move['type'] ?></span>
                    <span class="badge category-<?= strtolower($move['cat']) ?>"><?= $move['cat'] ?></span>
                </div>
                <div class="move-name"><?= htmlspecialchars($move['name']) ?></div>
                <div class="move-desc">
                    <?= !empty($move['description']) ? htmlspecialchars($move['description']) : 'No description available.' ?>
                </div>
                <div><strong>Gen:</strong> <?= htmlspecialchars($move['gen_name']) ?></div>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Power</div>
                    <div class="stat-val"><?= $move['power'] ?? '-' ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Accuracy</div>
                    <div class="stat-val"><?= $move['accuracy'] ? ($move['accuracy']*100).'%' : '-' ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">PP</div>
                    <div class="stat-val"><?= $move['pp'] ?></div>
                </div>
            </div>
        </div>

        <!-- POKEMON LIST -->
        <h3 class="section-title">Can be learnd by</h3>
        
        <?php if (empty($learnedBy)): ?>
            <p style="text-align:center; color:#888;">No known Pokemon can learn this move.</p>
        <?php else: ?>
            <div class="poke-grid">
                <?php foreach($learnedBy as $p): ?>
                    <a href="pokemon_card.php?id=<?= $p['id'] ?>" class="poke-tile">
                        <div class="tile-content" style="width:100%; height:80px; display:flex; justify-content:center; align-items:center;">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="poke-img">
                        </div>
                        <span class="poke-name"><?= htmlspecialchars($p['name']) ?></span>
                        <div style="display:flex; gap:5px; margin-top:5px;">
                            <span class="badge type-<?= strtolower($p['type_1']) ?>" style="padding: 2px 6px; font-size: 0.6rem;"><?= $p['type_1'] ?></span>
                            <?php if($p['type_2']): ?>
                                <span class="badge type-<?= strtolower($p['type_2']) ?>" style="padding: 2px 6px; font-size: 0.6rem;"><?= $p['type_2'] ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>