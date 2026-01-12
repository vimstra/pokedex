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

// Dane do bazy
$host = "db"; 
$dbname = "pokedex";
$user = "root";
$password = "root";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 2. Pobranie danych Pokemona
    $stmt = $pdo->prepare("SELECT *, pokemon_type::text as type_1, secondary_type::text as type_2 FROM public.pokemon WHERE id = ?");
    $stmt->execute([$id]);
    $pokemon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pokemon) {
        die("Pokemon not found!");
    }

    // 3. Pobranie ataków dla tego Pokemona (Relacja wiele-do-wielu)
    // Łączymy tabele: move JOIN pokemon_moves JOIN pokemon
    $sqlMoves = "
        SELECT m.name, m.move_type::text as type, m.category::text as category, 
               m.power, m.accuracy, m.pp, m.description
        FROM public.move m
        JOIN public.pokemon_moves pm ON m.id = pm.move_id
        WHERE pm.pokemon_id = ?
        ORDER BY m.min_level ASC, m.name ASC -- opcjonalnie sortowanie
    ";
    // Uwaga: w init.sql w pokemon_moves nie mamy kolumny min_level, więc sortuję po nazwie,
    // ale jeśli w przyszłości dodasz poziom nauki ataku, warto po nim sortować.
    
    $stmtMoves = $pdo->prepare("SELECT m.*, m.move_type::text as type, m.category::text as category 
                                FROM public.move m 
                                JOIN public.pokemon_moves pm ON m.id = pm.move_id 
                                WHERE pm.pokemon_id = ?");
    $stmtMoves->execute([$id]);
    $moves = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper do kolorów pasków statystyk
function getStatColor($val) {
    if ($val < 50) return '#ff4e42'; // Czerwony
    if ($val < 90) return '#ffb636'; // Żółty
    return '#5bc963'; // Zielony
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
        
        /* ZASYSAMY STYLE BAZOWE Z INDEXU DLA SPÓJNOŚCI */
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

        /* Sekcja górna: Obrazek i Podstawy */
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
        
        .poke-img {
            width: 100%;
            max-width: 300px;
            height: auto;
            
        }

        .info-wrapper {
            flex: 1.5;
            min-width: 300px;
        }

        .poke-id { color: #aaa; font-weight: 700; font-size: 1.2rem; }
        .poke-name { font-size: 3rem; font-weight: 800; text-transform: uppercase; line-height: 1; margin-bottom: 10px; color: #333; }
        .poke-desc { color: #666; font-size: 1rem; margin: 20px 0; font-style: italic; line-height: 1.6; }

        /* Typy */
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

        /* Statystyki */
        .stats-container { margin-top: 30px; }
        .stat-row { display: flex; align-items: center; margin-bottom: 12px; }
        .stat-label { width: 100px; font-weight: 600; color: #888; font-size: 0.9rem; }
        .stat-val { width: 40px; font-weight: 700; text-align: right; margin-right: 15px; }
        .bar-bg { flex-grow: 1; height: 10px; background: #eee; border-radius: 10px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 10px; transition: width 1s ease-out; }

        /* Moves Section */
        .moves-section { padding: 40px; }
        .section-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; color: #DC0A2D; }
        
        .moves-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

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

        /* Kolory typów (kopia z index.php) */
        .type-fire { background-color: #F07F31; } .type-water { background-color: #698FEF; } .type-grass { background-color: #77C750; }
        .type-electric { background-color: #F8D12E; color: #333;} .type-psychic { background-color: #F95587; } .type-rock { background-color: #B89F39; }
        .type-normal { background-color: #A7A779; } .type-ice { background-color: #97D8D8; color: #333; } .type-fighting {background-color: #C03028; } .type-poison {background-color: #A140A1; }
        .type-ground {background-color: #E1BF67; } .type-flying {background-color: #A790EF; } .type-bug {background-color: #A7B920; } .type-ghost {background-color: #705898; }
        .type-dragon {background-color: #7038F7; } .type-dark {background-color: #705848; } .type-steel {background-color: #B8B8CF; } .type-fairy {background-color: #ED99AB; }

        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
    </style>
</head>
<body>

    <div class="top-bar">
        <a href="index.php" class="back-btn">&larr; Back to Pokedex</a>
        <span style="font-weight: 800; font-size: 1.2rem;"><?= htmlspecialchars($pokemon['name']) ?></span>
    </div>

    <div class="container">
        <div class="card">
            
            <!-- GÓRNA CZĘŚĆ KARTY -->
            <div class="card-header-section">
                <div class="img-wrapper">
                    <img src="<?= htmlspecialchars($pokemon['image_url']) ?>" alt="<?= htmlspecialchars($pokemon['name']) ?>" class="poke-img">
                </div>
                
                <div class="info-wrapper">
                    <div class="poke-id">#<?= str_pad($pokemon['pokedex_number'], 3, '0', STR_PAD_LEFT) ?></div>
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
                    </div>

                    <!-- PASKI STATYSTYK -->
                    <div class="stats-container">
                        <?php
                        // Tablica statystyk do wyświetlenia
                        $stats = [
                            'HP' => $pokemon['hp'],
                            'Attack' => $pokemon['attack'],
                            'Defense' => $pokemon['defense'],
                            'Sp. Atk' => $pokemon['sp_attack'],
                            'Sp. Def' => $pokemon['sp_defense'],
                            'Speed' => $pokemon['speed']
                        ];
                        // Max stat w grze to ok 255, ale dla wizualizacji 150 wygląda lepiej dla starterów
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

            <!-- DOLNA CZĘŚĆ: ATAKI -->
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

</body>
</html>