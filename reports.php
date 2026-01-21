<?php
session_start();
$host = "db"; 
$dbname = "pokedex"; 
$user = "root"; 
$password = "root";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $statsByType = $pdo->query("SELECT * FROM public.v_type_statistics")->fetchAll(PDO::FETCH_ASSOC);

    $genStats = $pdo->query("SELECT * FROM public.v_generation_counts")->fetchAll(PDO::FETCH_ASSOC);

    $topMoves = $pdo->query("SELECT * FROM public.v_top_moves")->fetchAll(PDO::FETCH_ASSOC);

    $topMoves = $pdo->query("SELECT * FROM public.v_top_moves")->fetchAll(PDO::FETCH_ASSOC);

    $strongTypes = $pdo->query("SELECT * FROM public.v_strong_types")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokedex Reports</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #333; padding-bottom: 50px; }
        
        .top-bar {
            background-color: #DC0A2D;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 100;
            display: flex; align-items: center; gap: 20px;
        }
        .back-btn {
            background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px 20px;
            border-radius: 20px; cursor: pointer; font-weight: 600; text-decoration: none; transition: 0.3s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.4); }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        
        .report-card {
            background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 30px; margin-bottom: 40px; overflow: hidden;
        }
        
        .report-header {
            border-bottom: 2px solid #f0f0f0; margin-bottom: 20px; padding-bottom: 10px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .report-title { font-size: 1.5rem; font-weight: 800; color: #333; }
        .report-desc { color: #888; font-size: 0.9rem; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f9f9f9; color: #666; font-size: 0.85rem; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fafafa; }

        .bar-wrapper { width: 100px; height: 6px; background: #eee; border-radius: 3px; overflow: hidden; display: inline-block; margin-right: 10px; vertical-align: middle; }
        .bar-fill { height: 100%; border-radius: 3px; }
        
        .type-badge { padding: 4px 10px; border-radius: 4px; color: white; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
        .type-fire { background-color: #F07F31; } .type-water { background-color: #698FEF; } .type-grass { background-color: #77C750; }
        .type-electric { background-color: #F8D12E; color:#333; } .type-psychic { background-color: #F95587; } .type-rock { background-color: #B89F39; }
        .type-normal { background-color: #A7A779; } .type-ice { background-color: #97D8D8; color:#333; } .type-fighting {background-color: #C03028; } .type-poison {background-color: #A140A1; }
        .type-ground {background-color: #E1BF67; } .type-flying {background-color: #A790EF; } .type-bug {background-color: #A7B920; } .type-ghost {background-color: #705898; }
        .type-dragon {background-color: #7038F7; } .type-dark {background-color: #705848; } .type-steel {background-color: #B8B8CF; } .type-fairy {background-color: #ED99AB; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" class="back-btn">&larr; Back to Pokedex</a>
    <span style="font-weight: 800; font-size: 1.2rem;">Data Reports</span>
</div>

<div class="container">

    <div class="report-card">
        <div class="report-header">
            <div>
                <div class="report-title">Type Statistics</div>
                <div class="report-desc">Average stats for each Pokemon type</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Count</th>
                    <th>Avg Attack</th>
                    <th>Avg HP</th>
                    <th>Avg Speed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($statsByType as $row): ?>
                <tr>
                    <td><span class="type-badge type-<?= strtolower($row['pokemon_type']) ?>"><?= $row['pokemon_type'] ?></span></td>
                    <td style="font-weight: 700;"><?= $row['count'] ?></td>
                    <td>
                        <div class="bar-wrapper"><div class="bar-fill" style="width: <?= min(100, ($row['avg_attack']/120)*100) ?>%; background: #ff5252;"></div></div>
                        <?= $row['avg_attack'] ?>
                    </td>
                    <td>
                        <div class="bar-wrapper"><div class="bar-fill" style="width: <?= min(100, ($row['avg_hp']/120)*100) ?>%; background: #4caf50;"></div></div>
                        <?= $row['avg_hp'] ?>
                    </td>
                    <td><?= $row['avg_speed'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div>
                <div class="report-title">Most Common Moves</div>
                <div class="report-desc">Moves learned by the most Pokemons (Top 10)</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Move Name</th>
                    <th>Type</th>
                    <th>Power</th>
                    <th>Learned By</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach($topMoves as $row): ?>
                <tr>
                    <td style="font-weight: 800; color: #ccc;">#<?= $i++ ?></td>
                    <td style="font-weight: 600;"><?= htmlspecialchars($row['name']) ?></td>
                    <td><span class="type-badge type-<?= strtolower($row['move_type']) ?>"><?= $row['move_type'] ?></span></td>
                    <td><?= $row['power'] ?? '-' ?></td>
                    <td>
                        <div class="bar-wrapper" style="width: 50px;"><div class="bar-fill" style="width: 100%; background: #2196F3;"></div></div>
                        <strong><?= $row['learned_by_count'] ?></strong> Pokemon
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="report-card">
        <div class="report-header">
            <div>
                <div class="report-title">Generation Distribution</div>
                <div class="report-desc">Number of Pokemon discovered per generation</div>
                </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Generation</th>
                    <th>Total Pokemon</th>
                    <th>Distribution</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($genStats as $row): ?>
                <tr>
                    <td style="font-weight: 700;">Gen <?= htmlspecialchars($row['generation_name']) ?></td>
                    <td style="font-size: 1.2rem; font-weight: 800;"><?= $row['pokemon_count'] ?></td>
                    <td style="width: 60%;">
                        <div class="bar-wrapper" style="width: 100%; height: 12px;"><div class="bar-fill" style="width: <?= min(100, $row['pokemon_count']*10) ?>%; background: #9C27B0;"></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="report-card" style="border-left: 5px solid #FF9800;">
        <div class="report-header">
            <div>
                <div class="report-title">Elite Types (Total Stats > 300)</div>
                <div class="report-desc">Types with high average stats total.</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Pokemon Count</th>
                    <th>Avg Total Stats</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($strongTypes as $row): ?>
                <tr>
                    <td><span class="type-badge type-<?= strtolower($row['pokemon_type']) ?>"><?= $row['pokemon_type'] ?></span></td>
                    <td><?= $row['pokemon_count'] ?></td>
                    <td>
                        <div class="bar-wrapper" style="width: 150px;">
                            <div class="bar-fill" style="width: <?= min(100, ($row['avg_total_stats']/600)*100) ?>%; background: #FF9800;"></div>
                        </div>
                        <strong><?= $row['avg_total_stats'] ?></strong>
                    </td>
                    
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>