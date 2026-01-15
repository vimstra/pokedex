<?php
// 1. Inicjalizacja i Połączenie
session_start();

// Sprawdzenie czy podano ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$pokemon = null;
$moves = [];
$evolutionChain = [];

// Dane do bazy
$host = "db"; 
$dbname = "pokedex";
$user = "root";
$password = "root";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "SELECT p.*, 
                   p.pokemon_type::text as type_1, 
                   p.secondary_type::text as type_2,
                   g.name as gen_name 
            FROM public.pokemon p
            LEFT JOIN public.generation g ON p.generation_id = g.id
            WHERE p.id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $pokemon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pokemon) {
        die("Pokemon not found!");
    }

    // Sprawdzenie ulubionych
    $isFavorite = false;
    if (isset($_SESSION['user_id'])) {
        $stmtFav = $pdo->prepare("SELECT COUNT(*) FROM public.user_party WHERE user_id = ? AND pokemon_id = ?");
        $stmtFav->execute([$_SESSION['user_id'], $id]);
        $isFavorite = $stmtFav->fetchColumn() > 0;
    }

    // 3. LOGIKA EWOLUCJI (Recursive CTE)
    // To zapytanie znajduje całą linię ewolucyjną, niezależnie od tego, którego pokemona z linii oglądamy.
    // Działa w dwóch krokach:
    // 1. Znajduje "korzeń" rodziny (pokemona, który nie ma pre-ewolucji w tej linii).
    // 2. Schodzi w dół po drzewie ewolucji.
    
    $sqlEvo = "
    WITH RECURSIVE family_tree AS (
        -- Krok 1: Idź w górę (szukaj rodziców), aby znaleźć korzeń
        SELECT id, name, image_url, pokedex_number, 0 as level
        FROM pokemon
        WHERE id = :target_id
        
        UNION ALL
        
        SELECT p.id, p.name, p.image_url, p.pokedex_number, ft.level - 1
        FROM pokemon p
        JOIN evolution e ON p.id = e.pre_evolution_id
        JOIN family_tree ft ON e.post_evolution_id = ft.id
    ),
    root_pokemon AS (
        -- Wybierz najbardziej 'górnego' przodka
        SELECT * FROM family_tree ORDER BY level ASC LIMIT 1
    ),
    full_chain AS (
        -- Krok 2: Od korzenia idź w dół (szukaj dzieci)
        SELECT 
            p.id, p.name, p.image_url, p.pokedex_number,
            NULL::int as pre_id,
            NULL::text as trigger_type, NULL::int as min_level, NULL::text as item, NULL::text as notes
        FROM root_pokemon p
        
        UNION ALL
        
        SELECT 
            p.id, p.name, p.image_url, p.pokedex_number,
            e.pre_evolution_id as pre_id,
            e.trigger_type::text, e.min_level, e.item, e.notes::text
        FROM pokemon p
        JOIN evolution e ON p.id = e.post_evolution_id
        JOIN full_chain fc ON e.pre_evolution_id = fc.id
    )
    SELECT * FROM full_chain;
    ";

    $stmtEvo = $pdo->prepare($sqlEvo);
    $stmtEvo->execute(['target_id' => $id]);
    $evolutionChain = $stmtEvo->fetchAll(PDO::FETCH_ASSOC);


    // 4. Pobranie ataków
    $stmtMoves = $pdo->prepare("SELECT m.*, m.move_type::text as type, m.category::text as category 
                                FROM public.move m 
                                JOIN public.pokemon_moves pm ON m.id = pm.move_id 
                                WHERE pm.pokemon_id = ?
                                ORDER BY m.name ASC");
    $stmtMoves->execute([$id]);
    $moves = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

function getStatColor($val) {
    if ($val < 50) return '#ff4e42';
    if ($val < 90) return '#ffb636';
    return '#5bc963';
}

// Helper do formatowania tekstu ewolucji
function formatTrigger($row) {
    $type = $row['trigger_type'];
    
    if ($type === 'Level' && $row['min_level']) {
        return "Lvl " . $row['min_level'];
    }
    if ($type === 'Item' && $row['item']) {
        return "Use " . $row['item'];
    }
    if ($type === 'Other' && !empty($row['notes'])) {
        return $row['notes']; // Wyświetla tekst wpisany w polu "Other"
    }
    
    return $type;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pokemon['name']) ?> - Pokedex</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #333; padding-bottom: 50px;}
        
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

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-header-section {
            display: flex;
            flex-wrap: wrap;
            padding: 40px;
            gap: 40px;
            background: linear-gradient(to bottom, #fff, #fefefe);
            border-bottom: 1px solid #eee;
        }

        .img-wrapper {
            flex: 1;
            min-width: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f4f4;
            border-radius: 20px;
            padding: 20px;
            position: relative;
        }
        
        .poke-img { width: 100%; max-width: 300px; height: auto; }

        .info-wrapper { flex: 1.5; min-width: 300px; }

        .poke-id { color: #aaa; font-weight: 700; font-size: 1.5rem; } 
        .poke-name { font-size: 3rem; font-weight: 800; text-transform: uppercase; line-height: 1; margin-bottom: 10px; color: #333; }
        .poke-desc { color: #666; font-size: 1rem; margin: 20px 0; font-style: italic; line-height: 1.6; }

        .type-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 6px;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
            margin-right: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* STATS */
        .stats-container { margin-top: 30px; }
        .stat-row { display: flex; align-items: center; margin-bottom: 12px; }
        .stat-label { width: 100px; font-weight: 600; color: #888; font-size: 0.9rem; }
        .stat-val { width: 40px; font-weight: 700; text-align: right; margin-right: 15px; }
        .bar-bg { flex-grow: 1; height: 10px; background: #eee; border-radius: 10px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 10px; transition: width 1s ease-out; }

        /* --- EVOLUTION SECTION STYLES --- */
        .evolution-section { padding: 40px; border-bottom: 1px solid #eee; background: #fffcfc; }
        .evo-flex {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .evo-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: 0.3s;
            padding: 15px;
            border-radius: 15px;
        }
        .evo-card:hover { background: white; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transform: translateY(-3px); }
        .evo-card.current { border: 2px solid #DC0A2D; background: white; }
        
        .evo-img { width: 100px; height: 100px; object-fit: contain; margin-bottom: 10px; }
        .evo-name { font-weight: 700; font-size: 0.9rem; }
        
        .evo-arrow {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #ccc;
        }
        .arrow-trigger { font-size: 0.75rem; font-weight: 700; color: #DC0A2D; margin-bottom: 5px; text-align: center;}
        .arrow-symbol { font-size: 1.5rem; color: #ccc; }


        /* MOVES */
        .moves-section { padding: 40px; }
        .section-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; color: #DC0A2D; }
        .moves-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }

        .move-card {
            background: #fcfcfc;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            transition: 0.2s;
        }
        .move-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-color: #DC0A2D; }
        .move-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .move-name { font-weight: 700; color: #333; }
        .move-stats { font-size: 0.8rem; color: #888; display: flex; gap: 10px; margin-top: 5px;}
        .move-desc { font-size: 0.8rem; color: #666; margin-top: 8px; border-top: 1px solid #eee; padding-top: 8px; }

        /* Guzik Serca */
        .fav-btn {
            background: white; border: 2px solid #e0e0e0; border-radius: 50%;
            width: 45px; height: 45px; display: flex; justify-content: center; align-items: center;
            cursor: pointer; transition: all 0.3s ease; outline: none; padding: 0;
        }
        .fav-icon {
            width: 24px; height: 24px; stroke: #ccc; fill: transparent; stroke-width: 2; transition: all 0.3s ease;
        }
        .fav-btn.active { border-color: #DC0A2D; background-color: #fff0f0; box-shadow: 0 4px 10px rgba(220, 10, 45, 0.2); }
        .fav-btn.active .fav-icon { fill: #DC0A2D; stroke: #DC0A2D; animation: heartPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .fav-btn:hover { transform: translateY(-2px); border-color: #aaa; }
        @keyframes heartPop { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }

        /* Kolory typów */
        .type-fire { background-color: #F07F31; } .type-water { background-color: #698FEF; } .type-grass { background-color: #77C750; }
        .type-electric { background-color: #F8D12E; color: #333;} .type-psychic { background-color: #F95587; } .type-rock { background-color: #B89F39; }
        .type-normal { background-color: #A7A779; } .type-ice { background-color: #97D8D8; color: #333; } .type-fighting {background-color: #C03028; } .type-poison {background-color: #A140A1; }
        .type-ground {background-color: #E1BF67; } .type-flying {background-color: #A790EF; } .type-bug {background-color: #A7B920; } .type-ghost {background-color: #705898; }
        .type-dragon {background-color: #7038F7; } .type-dark {background-color: #705848; } .type-steel {background-color: #B8B8CF; } .type-fairy {background-color: #ED99AB; }
    </style>
</head>
<body>

    <div class="top-bar">
        <a href="index.php" class="back-btn">&larr; Back to Pokedex</a>
        <span style="font-weight: 800; font-size: 1.2rem;"><?= htmlspecialchars($pokemon['name']) ?></span>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
        <div style="margin-left: auto; display: flex; gap: 10px;">
            <a href="add_evolution.php?id=<?= $pokemon['id'] ?>" class="back-btn" style="background: #FF9800;">+ Evolution</a>
            <a href="edit_pokemon.php?id=<?= $pokemon['id'] ?>" class="back-btn" style="background: #2196F3;">Edit</a>
            <a href="delete_entry.php?type=pokemon&id=<?= $pokemon['id'] ?>" class="back-btn" style="background: #f44336;" onclick="return confirm('Are you sure you want to delete this Pokemon?');">Delete</a>
        </div>
    <?php endif; ?>
    </div>

    <div class="container">
        <div class="card">
            
            <div class="card-header-section">
                <div class="img-wrapper">
                    <img src="<?= htmlspecialchars($pokemon['image_url']) ?>" alt="<?= htmlspecialchars($pokemon['name']) ?>" class="poke-img">
                </div>
                
                <div class="info-wrapper">
                    <!-- HEADER: ID + SERCE -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <div class="poke-id">#<?= str_pad($pokemon['pokedex_number'], 3, '0', STR_PAD_LEFT) ?></div>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button id="heartBtn" class="fav-btn <?= $isFavorite ? 'active' : '' ?>" onclick="toggleHeart(<?= $pokemon['id'] ?>)" title="Add to My Party">
                                <svg class="fav-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="poke-name"><?= htmlspecialchars($pokemon['name']) ?></div>
                    
                    <div class="types-row">
                        <span class="type-badge type-<?= strtolower($pokemon['type_1']) ?>"><?= $pokemon['type_1'] ?></span>
                        <?php if ($pokemon['type_2']): ?>
                            <span class="type-badge type-<?= strtolower($pokemon['type_2']) ?>"><?= $pokemon['type_2'] ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="poke-desc">
                        <?= htmlspecialchars($pokemon['description']) ?>
                    </p>

                    <div style="display: flex; gap: 20px; color: #555; font-size: 0.9rem;">
                        <div><strong>Height:</strong> <?= $pokemon['height'] ?> m</div>
                        <div><strong>Weight:</strong> <?= $pokemon['weight'] ?> kg</div>
                        <div><strong>Gen:</strong> <?= htmlspecialchars($pokemon['gen_name']) ?></div>
                    </div>

                    <!-- PASKI STATYSTYK -->
                    <div class="stats-container">
                        <?php
                        $stats = [
                            'HP' => $pokemon['hp'],
                            'Attack' => $pokemon['attack'],
                            'Defense' => $pokemon['defense'],
                            'Sp. Atk' => $pokemon['sp_attack'],
                            'Sp. Def' => $pokemon['sp_defense'],
                            'Speed' => $pokemon['speed']
                        ];
                        $maxStat = 150; 
                        ?>
                        <?php foreach($stats as $label => $val): ?>
                            <div class="stat-row">
                                <div class="stat-label"><?= $label ?></div>
                                <div class="stat-val"><?= $val ?></div>
                                <div class="bar-bg">
                                    <div class="bar-fill" style="width: <?= min(100, ($val / $maxStat) * 100) ?>%; background: <?= getStatColor($val) ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- EVOLUTION SECTION (NOWA) -->
            <?php if (!empty($evolutionChain) && count($evolutionChain) > 1): ?>
            <div class="evolution-section">
                <h3 class="section-title" style="text-align: center;">Evolution Chain</h3>
                <div class="evo-flex">
                    <?php foreach($evolutionChain as $index => $evo): ?>
                        
                        <!-- Jeśli to nie pierwszy pokemon, wyświetl strzałkę PRZED nim -->
                        <?php if ($index > 0): ?>
                            <div class="evo-arrow">
                                <span class="arrow-trigger"><?= htmlspecialchars(formatTrigger($evo)) ?></span>
                                <span class="arrow-symbol">&rarr;</span>
                            </div>
                        <?php endif; ?>

                        <!-- Karta Pokemona -->
                        <a href="pokemon_card.php?id=<?= $evo['id'] ?>" class="evo-card <?= ($evo['id'] == $id) ? 'current' : '' ?>">
                            <img src="<?= htmlspecialchars($evo['image_url']) ?>" class="evo-img">
                            <span class="evo-name"><?= htmlspecialchars($evo['name']) ?></span>
                            <span style="font-size: 0.8rem; color: #999;">#<?= str_pad($evo['pokedex_number'], 3, '0', STR_PAD_LEFT) ?></span>
                        </a>

                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- MOVES -->
            <div class="moves-section">
                <h3 class="section-title">Moveset</h3>
                <?php if (empty($moves)): ?>
                    <p style="color: #999;">This Pokemon has no moves assigned in database.</p>
                <?php else: ?>
                    <div class="moves-grid">
                        <?php foreach($moves as $m): ?>
                            <a href="move_card.php?id=<?= $m['id'] ?>" class="move-card" style="text-decoration:none; display:block; color:inherit;">
                                <div class="move-header">
                                    <span class="move-name"><?= htmlspecialchars($m['name']) ?></span>
                                    <span class="type-badge type-<?= strtolower($m['type']) ?>" style="padding: 2px 8px; font-size: 0.6rem; margin:0;"><?= $m['type'] ?></span>
                                </div>
                                <div class="move-stats">
                                    <span>PWR: <strong><?= $m['power'] ?? '-' ?></strong></span>
                                    <span>ACC: <strong><?= $m['accuracy'] ? ($m['accuracy']*100).'%' : '-' ?></strong></span>
                                    <span>PP: <strong><?= $m['pp'] ?></strong></span>
                                </div>
                                <div class="move-stats">
                                    <span>Cat: <?= $m['category'] ?></span>
                                </div>
                                <?php if(!empty($m['description'])): ?>
                                    <div class="move-desc"><?= htmlspecialchars($m['description']) ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

<script>
async function toggleHeart(pokemonId) {
    const btn = document.getElementById('heartBtn');
    
    try {
        const response = await fetch('toggle_party.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pokemon_id: pokemonId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.action === 'added') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        } else {
            alert('Error updating party: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        console.error(e);
    }
}
</script>
</body>
</html>