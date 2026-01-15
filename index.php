<?php
session_start();
$registerError = null;
$loginError = null;
$dbError = null;

$host = "db"; 
$dbname = "pokedex";
$user = "root";
$password = "root";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Tworzenie kont domyślnych
    $defaultUsers = [
        ['u' => 'admin', 'p' => 'admin', 'r' => 'Admin'],
        ['u' => 'common', 'p' => 'common', 'r' => 'Common']
    ];

    foreach ($defaultUsers as $def) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM public.users WHERE username = ?");
        $stmt->execute([$def['u']]);
        if ($stmt->fetchColumn() == 0) {
            $h = password_hash($def['p'], PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO public.users (username, password, role) VALUES (?, ?, ?::public.user_role)");
            $ins->execute([$def['u'], $h, $def['r']]);
        }
    }

    // OBSŁUGA LOGOWANIA
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        $stmtUser = $pdo->prepare("SELECT * FROM public.users WHERE username = ?");
        $stmtUser->execute([$username]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($userData && password_verify($pass, $userData['password'])) {
            $_SESSION['user'] = $userData['username'];
            $_SESSION['role'] = $userData['role'];
            $_SESSION['user_id'] = $userData['user_id'];
            header("Location: index.php");
            exit;
        } else {
            $loginError = "Nieprawidłowy użytkownik lub hasło.";
        }
    }

    // OBSŁUGA REJESTRACJI
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        $username = $_POST['username'] ?? '';
        $passRaw = $_POST['password'] ?? '';
        $hashedPassword = password_hash($passRaw, PASSWORD_DEFAULT);
        $role = 'Common';

        try {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM public.users WHERE username = ?");
            $checkStmt->execute([$username]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $registerError = "Użytkownik o tej nazwie już istnieje!";
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO public.users (username, password, role) VALUES (?, ?, ?::public.user_role)");
                $insertStmt->execute([$username, $hashedPassword, $role]);
                
                $_SESSION['user'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['user_id'] = $pdo->lastInsertId(); // Opcjonalnie: automatyczne logowanie po rejestracji (dla user_id)
                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $registerError = "Błąd rejestracji: " . $e->getMessage();
        }
    }

    // WYLOGOWANIE
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // POBIERANIE DANYCH POKEMONÓW I ATAKÓW
    $stmtPkmn = $pdo->query("SELECT *, pokemon_type::text as type_1, secondary_type::text as type_2 FROM public.pokemon ORDER BY pokedex_number ASC");
    $pokemons = $stmtPkmn->fetchAll(PDO::FETCH_ASSOC);

    // USUNIĘTO BŁĘDNY KOD Z $id TUTAJ

    $stmtMoves = $pdo->query("SELECT id, name, LOWER(move_type::text) as type, LOWER(category::text) as category, power, accuracy, pp FROM public.move");
    $movesFromDb = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

    // POBIERANIE MY PARTY (Przeniesione tutaj, żeby było bezpieczne)
    $myPartyIds = [];
    if (isset($_SESSION['user_id'])) {
        try {
            $stmtParty = $pdo->prepare("SELECT pokemon_id FROM public.user_party WHERE user_id = ?");
            $stmtParty->execute([$_SESSION['user_id']]);
            // Pobieramy jako prostą tablicę liczb (FETCH_COLUMN), a nie tablicę tablic
            $myPartyIds = $stmtParty->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            // Ignorujemy błąd jeśli tabela jeszcze nie istnieje
        }
    }

    // POBIERANIE DANYCH DO TYPE CHART (EFEKTYWNOŚĆ)
    $typeChart = [];
    try {
        $stmtEff = $pdo->query("SELECT attacking_type::text as atk, defending_type::text as def, multiplier FROM public.type_effectiveness");
        $effData = $stmtEff->fetchAll(PDO::FETCH_ASSOC);
        foreach ($effData as $row) {
            $typeChart[$row['atk']][$row['def']] = (float)$row['multiplier'];
        }
    } catch (PDOException $e) {
        // Ignorujemy brak tabeli effectiveness
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokedex App</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; margin: 0; scroll-behavior: auto; }
        body { font-family: 'Poppins', sans-serif; overflow-y: scroll; scroll-snap-type: y mandatory; }
        section { height: 100vh; width: 100%; position: relative; scroll-snap-align: start; scroll-snap-stop: always; display: flex; flex-direction: column; }
        #landing { background-color: #DC0A2D; justify-content: center; align-items: center; color: white; }
        .pokeball-svg { width: 200px; height: 200px; animation: bounce 3s infinite ease-in-out; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3)); }
        #app-interface { background-color: #f8f9fa; justify-content: flex-start; align-items: center; }
        .menu-sticky-wrapper { position: sticky; top: 0; z-index: 100; background: #f8f9fa; width: 100%; padding: 60px 20px 20px 20px; border-bottom: 1px solid #e0e0e0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .menu-bar { display: flex; justify-content: space-between; align-items: center; max-width: 900px; margin: 0 auto; flex-wrap: wrap; gap: 20px; }
        .menu-group { display: flex; gap: 15px; align-items: center; }
        .menu-btn { padding: 10px 25px; border: 2px solid #DC0A2D; background: white; color: #DC0A2D; font-weight: 700; font-size: 0.9rem; cursor: pointer; border-radius: 50px; transition: all 0.2s ease; text-transform: uppercase; }
        .menu-btn:hover, .menu-btn.active { background-color: #DC0A2D; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(220, 10, 45, 0.3); }
        
        .user-tag { color: #333; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; background: #eee; padding: 5px 12px; border-radius: 10px; border: 1px solid #ddd; }
        .menu-group.auth .menu-btn { border-color: #333; color: #333; }
        .menu-group.auth .menu-btn:hover, .menu-group.auth .menu-btn.active { background-color: #333; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        
        .content-wrapper { width: 100%; max-width: 900px; padding: 30px 20px; flex-grow: 1; overflow-y: auto; }
        .content-display { display: none; opacity: 0; transition: opacity 0.4s ease; }
        .content-display.visible { display: block; opacity: 1; animation: slideUp 0.4s ease-out; }
        #moves, #signin, #signup, #type-chart, #my-party { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 5px 25px rgba(0,0,0,0.05); }
        .content-header { font-size: 1.8rem; color: #333; margin-bottom: 25px; font-weight: 800; border-bottom: 4px solid #DC0A2D; display: inline-block; }
        
        .poke-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 30px; justify-items: center; }
        .poke-tile { width: 200px; height: 200px; background: white; border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); border: 2px solid transparent; display: flex; flex-direction: column; align-items: center; justify-content: space-between; padding: 20px; cursor: pointer; transition: all 0.3s ease; }
        .poke-tile:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(220, 10, 45, 0.15); border-color: #DC0A2D; }
        .tile-content { position: relative; width: 100%; height: 120px; display: flex; align-items: center; justify-content: center; }
        .poke-img { max-width: 100%; max-height: 100%; object-fit: contain; transition: opacity 0.3s ease; opacity: 1; }
        .poke-name { position: absolute; font-size: 1.2rem; font-weight: 800; color: #DC0A2D; text-transform: uppercase; opacity: 0; transform: scale(0.8); transition: all 0.3s ease; }
        .poke-tile:hover .poke-img { opacity: 0; }
        .poke-tile:hover .poke-name { opacity: 1; transform: scale(1); }
        .poke-index { font-size: 0.9rem; color: #ccc; font-weight: 600; letter-spacing: 1px; }

        .types-grid, .category-grid { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .type-btn { border: none; padding: 10px 20px; border-radius: 5px; color: white; font-family: inherit; font-weight: 700; text-transform: uppercase; cursor: pointer; opacity: 0.5; transition: 0.3s; }
        .type-btn.active { opacity: 1; transform: scale(1.1); box-shadow: 0 0 0 4px rgba(0,0,0,0.1); }
        .reset-btn { display: block; margin: 0 auto 40px auto; border: none; padding: 10px 20px; border-radius: 5px; color: white; font-family: inherit; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: 0.3s; background:#333; }
        
        /* Kolory typów */
        .type-fire { background-color: #F07F31; } .type-water { background-color: #698FEF; } .type-grass { background-color: #77C750; }
        .type-electric { background-color: #F8D12E; color: #333;} .type-psychic { background-color: #F95587; } .type-rock { background-color: #B89F39; }
        .type-normal { background-color: #A7A779; } .type-ice { background-color: #97D8D8; color: #333; } .type-fighting {background-color: #C03028; } .type-poison {background-color: #A140A1; }
        .type-ground {background-color: #E1BF67; } .type-flying {background-color: #A790EF; } .type-bug {background-color: #A7B920; } .type-ghost {background-color: #705898; }
        .type-dragon {background-color: #7038F7; } .type-dark {background-color: #705848; } .type-steel {background-color: #B8B8CF; } .type-fairy {background-color: #ED99AB; }
        .category-physical { background-color: #EB5629; } .category-special { background-color: #375AB1; } .category-status { background-color: #818181; }

        .move-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fff; border: 1px solid #f0f0f0; border-radius: 8px; margin-bottom: 10px; }
        .move-name { font-weight: 600; font-size: 1.1rem; color: #333; }
        
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-family: inherit; }
        .submit-btn { width: 100%; padding: 12px; background: #DC0A2D; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; text-transform: uppercase; }

        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <section id="landing">
        <svg class="pokeball-svg" viewBox="0 0 100 100">
            <path d="M 5,50 A 45,45 0 0,0 95,50 Z" fill="white" stroke="#222" stroke-width="3" />
            <path d="M 5,50 A 45,45 0 0,1 95,50 Z" fill="#222" />
            <path d="M 5,50 A 45,45 0 0,1 95,50 Z" fill="#DC0A2D" stroke="#222" stroke-width="3"/>
            <rect x="5" y="47" width="90" height="6" fill="#222" />
            <circle cx="50" cy="50" r="12" fill="white" stroke="#222" stroke-width="3"/>
        </svg>
    </section>

    <section id="app-interface">
        <div class="menu-sticky-wrapper">
            <div class="menu-bar">
                <div class="menu-group">
                    <button class="menu-btn active" data-target="pokemons">Pokemons</button>
                    <button class="menu-btn" data-target="moves">Moves</button>
                    <button class="menu-btn" data-target="type-chart">Type Chart</button>
                    <?php if (isset($_SESSION['user'])) :?>  
                    <button class="menu-btn" data-target="my-party">My Party</button>
                    <?php endif; ?>
                </div>
                
                <div class="menu-group auth">
                    <?php if (isset($_SESSION['user'])): ?>
                        <span class="user-tag">Trainer: <?= htmlspecialchars($_SESSION['user']) ?></span>
                        <a href="?logout=1" class="menu-btn" style="text-decoration: none;">Logout</a>
                    <?php else: ?>
                        <button class="menu-btn" data-target="signin">Sign In</button>
                        <button class="menu-btn" data-target="signup">Sign Up</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
    <!-- POKEMONS TAB -->
    <div id="pokemons" class="content-display visible">        
        <!-- POPRAWIONY FRAGMENT -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <a href="add_pokemon.php" class="reset-btn" style="background-color: #28a745; text-decoration: none; display: inline-block;">+ Add Pokemon</a>
            </div>
        <?php endif; ?>
<h2 class="content-header" style="text-align: center; border: none; display: block;">Search & Filter</h2>
        <div class="form-group" style="max-width: 600px; margin: 0 auto 20px auto;">
            <input type="text" id="poke-search" placeholder="Search Pokemon by name..." oninput="updatePokeSearch(this.value)">
        </div>
        
        <div class="types-grid" id="poke-types-container">
            <?php 
            $types = ['fire','water','grass','electric','normal','ice','flying','poison','fighting','dragon','fairy','bug','psychic','dark','ghost','ground','rock','steel'];
            foreach($types as $t): ?>
                <button class="type-btn type-<?= $t ?>" onclick="togglePokeType('<?= $t ?>', this)"><?= ucfirst($t) ?></button>
            <?php endforeach; ?>
        </div>
        
        <button class="reset-btn" onclick="resetPokeFilters()">Reset Filters</button>
        <div id="poke-grid-container" class="poke-grid"></div>
    </div>

            <!-- MOVES TAB -->
            <div id="moves" class="content-display">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <a href="add_move.php" class="reset-btn" style="background-color: #28a745; text-decoration: none; display: inline-block;">+ Add Move</a>
            </div>
        <?php endif; ?>
                <h2 class="content-header" style="text-align: center; border: none; display: block;">Filter by Type</h2>
                <div class="types-grid">
                    <?php foreach($types as $t): ?>
                        <button class="type-btn type-<?= $t ?>" onclick="setMoveFilter('type', '<?= $t ?>')"><?= ucfirst($t) ?></button>
                    <?php endforeach; ?>
                </div>
                <h2 class="content-header" style="text-align: center; border: none; display: block;">Filter by Category</h2>
                <div class="category-grid">
                    <button class="type-btn category-physical" onclick="setMoveFilter('category', 'physical')">Physical</button>
                    <button class="type-btn category-special" onclick="setMoveFilter('category', 'special')">Special</button>
                    <button class="type-btn category-status" onclick="setMoveFilter('category', 'status')">Status</button>
                </div>
                <button class="reset-btn" onclick="setMoveFilter('reset', '')">Reset All</button>
                <div class="form-group" style="margin-top: 20px;">
                    <input type="text" id="move-search" placeholder="Search move by name..." oninput="updateMoveSearch(this.value)">
                </div>
                <div id="moves-list-container" class="moves-list">
                    <p style="text-align: center; color: #999; margin-top: 30px;">Select filters above.</p>
                </div>
            </div>

            <!-- TYPE CHART TAB -->
            <div id="type-chart" class="content-display">
                <h2 class="content-header" style="text-align: center; border: none; display: block;">Move type (Attacker):</h2>
                <div class="types-grid" id="chart-attacker-container">
                    <?php foreach($types as $t): ?>
                        <button class="type-btn type-<?= $t ?>" onclick="selectChartAttacker('<?= $t ?>', this)"><?= ucfirst($t) ?></button>
                    <?php endforeach; ?>
                </div>

                <h2 class="content-header" style="text-align: center; border: none; display: block;">Pokemon type (Defender):</h2>
                <div class="types-grid" id="chart-defender-container">
                    <?php foreach($types as $t): ?>
                        <button class="type-btn type-<?= $t ?>" onclick="toggleChartDefender('<?= $t ?>', this)"><?= ucfirst($t) ?></button>
                    <?php endforeach; ?>
                </div>

                <div id="chart-result-container" style="text-align: center; margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px; border: 2px solid #eee;">
                    <div style="font-size: 0.9rem; text-transform: uppercase; color: #888; font-weight: 700;">Efficiency</div>
                    <div id="chart-value" style="font-size: 3rem; font-weight: 800; color: #333;">-</div>
                    <div id="chart-desc" style="font-size: 1.2rem; font-weight: 600; color: #666;">Select Attacker & Defender</div>
                </div>
            </div>

            <!-- MY PARTY TAB -->
            <div id="my-party" class="content-display">
                <h2 class="content-header" style="text-align: center; display: block;">My Party</h2>
                <div id="party-grid-container" class="poke-grid"></div>
            </div>

            <!-- LOGIN -->
            <div id="signin" class="content-display" style="max-width: 500px; margin: 0 auto;">
                <h2 class="content-header">Log In</h2>
                <?php if ($loginError): ?>
                    <p style="color: #DC0A2D; margin-bottom: 10px; font-weight: 600;"><?= $loginError ?></p>
                <?php endif; ?>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group"><input type="text" name="username" placeholder="Username" required></div>
                    <div class="form-group"><input type="password" name="password" placeholder="Password" required></div>
                    <button type="submit" class="submit-btn">Login</button>
                </form>
            </div>

            <!-- REGISTER -->
            <div id="signup" class="content-display" style="max-width: 500px; margin: 0 auto;">
                <h2 class="content-header">Create Account</h2>
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($registerError)): ?>
                    <p style="color: #DC0A2D; margin-bottom: 10px; font-weight: 600;"><?= $registerError ?></p>
                <?php endif; ?>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group"><input type="text" name="username" placeholder="Trainer Name" required></div>
                    <div class="form-group"><input type="password" name="password" placeholder="Password" required></div>
                    <button type="submit" class="submit-btn">Register</button>
                </form>
            </div>
        </div>
    </section>

    <script>
        const buttons = document.querySelectorAll('.menu-btn');
        const contents = document.querySelectorAll('.content-display');

        // LOGIKA AUTOMATYCZNEGO PRZEWIJANIA PRZY BŁĘDZIE
        window.addEventListener('DOMContentLoaded', () => {
            const hasLoginError = <?php echo isset($loginError) ? 'true' : 'false'; ?>;
            const hasRegisterError = <?php echo isset($registerError) ? 'true' : 'false'; ?>;

            renderPokemons();

            if (hasLoginError || hasRegisterError) {
                document.getElementById('app-interface').scrollIntoView();
                const targetId = hasLoginError ? 'signin' : 'signup';
                switchTab(targetId);
            }
        });

        function switchTab(targetId) {
            contents.forEach(c => {
                c.classList.remove('visible');
                c.style.display = 'none';
            });
            buttons.forEach(b => b.classList.remove('active'));

            const targetBtn = document.querySelector(`.menu-btn[data-target="${targetId}"]`);
            const targetContent = document.getElementById(targetId);
            
            if (targetBtn && targetContent) {
                targetBtn.classList.add('active');
                targetContent.style.display = 'block';
                setTimeout(() => targetContent.classList.add('visible'), 10);
            }
        }

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.tagName === 'A') return;
                const targetId = button.getAttribute('data-target');
                if (targetId === 'my-party') {
                    renderMyParty();
                }
                switchTab(targetId);
            });
        });

        const allPokemons = <?php echo json_encode($pokemons); ?>;
        
        let pokeState = {
            search: "",
            selectedTypes: []
        };

        function updatePokeSearch(val) {
            pokeState.search = val.toLowerCase();
            renderPokemons();
        }

        function togglePokeType(type, btnElement) {
            const index = pokeState.selectedTypes.indexOf(type);
            if (index > -1) {
                pokeState.selectedTypes.splice(index, 1);
            } else {
                if (pokeState.selectedTypes.length >= 2) pokeState.selectedTypes.shift(); 
                pokeState.selectedTypes.push(type);
            }
            updateTypeButtonsUI();
            renderPokemons();
        }

        function updateTypeButtonsUI() {
            const btns = document.querySelectorAll('#poke-types-container .type-btn');
            btns.forEach(btn => {
                const typeName = Array.from(btn.classList).find(c => c.startsWith('type-') && c !== 'type-btn').replace('type-', '');
                if (pokeState.selectedTypes.includes(typeName)) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }

        function resetPokeFilters() {
            pokeState.search = "";
            pokeState.selectedTypes = [];
            document.getElementById('poke-search').value = "";
            updateTypeButtonsUI();
            renderPokemons();
        }

        function renderPokemons() {
            const container = document.getElementById('poke-grid-container');
            const filtered = allPokemons.filter(p => {
                if (!p.name.toLowerCase().includes(pokeState.search)) return false;
                if (pokeState.selectedTypes.length === 0) return true;

                const pType1 = p.pokemon_type.toLowerCase();
                const pType2 = p.secondary_type ? p.secondary_type.toLowerCase() : null;

                if (pokeState.selectedTypes.length === 1) {
                    const filter = pokeState.selectedTypes[0];
                    return pType1 === filter || pType2 === filter;
                } 
                if (pokeState.selectedTypes.length === 2) {
                    const filter1 = pokeState.selectedTypes[0];
                    const filter2 = pokeState.selectedTypes[1];
                    const pokemonTypes = [pType1, pType2];
                    return pokemonTypes.includes(filter1) && pokemonTypes.includes(filter2);
                }
                return true;
            });

            if (filtered.length === 0) {
                container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #999;">No Pokemon found matching criteria.</p>';
                return;
            }

            container.innerHTML = filtered.map(p => `
                <a href="pokemon_card.php?id=${p.id}" class="poke-tile" style="text-decoration: none; color: inherit;">
                    <div class="tile-content">
                        <img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" class="poke-img">
                        <span class="poke-name">${escapeHtml(p.name)}</span>
                    </div>
                    <span class="poke-index">#${String(p.pokedex_number).padStart(3, '0')}</span>
                    <div style="margin-bottom:10px; display:flex; gap:5px; justify-content:center;">
                         <span style="font-size:0.6rem; padding:2px 6px; border-radius:4px; color:white;" class="type-${p.pokemon_type.toLowerCase()}">${p.pokemon_type}</span>
                         ${p.secondary_type ? `<span style="font-size:0.6rem; padding:2px 6px; border-radius:4px; color:white;" class="type-${p.secondary_type.toLowerCase()}">${p.secondary_type}</span>` : ''}
                    </div>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            if (!text) return text;
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        /* =========================================
           LOGIKA ATAKÓW
           ========================================= */
        const allMoves = <?php echo json_encode($movesFromDb); ?>;
        let moveState = { type: null, category: null, search: "" };

        function updateMoveSearch(value) {
            moveState.search = value.toLowerCase();
            renderMoves();
        }

        function setMoveFilter(filterType, value) {
            if (filterType === 'reset') {
                moveState.type = null;
                moveState.category = null;
                moveState.search = "";
                document.getElementById('move-search').value = "";
            } else {
                moveState[filterType] = (moveState[filterType] === value) ? null : value;
            }
            document.querySelectorAll('#moves .type-btn').forEach(btn => {
                btn.classList.remove('active');
                if (moveState.type && btn.classList.contains('type-' + moveState.type)) btn.classList.add('active');
                if (moveState.category && btn.classList.contains('category-' + moveState.category)) btn.classList.add('active');
            });
            renderMoves();
        }

        function renderMoves() {
            const listContainer = document.getElementById('moves-list-container');
            const filtered = allMoves.filter(move => {
                const matchType = moveState.type ? move.type === moveState.type : true;
                const matchCategory = moveState.category ? move.category === moveState.category : true;
                const matchSearch = move.name.toLowerCase().includes(moveState.search);
                return matchType && matchCategory && matchSearch;
            });

            if (!moveState.type && !moveState.category && !moveState.search) {
                listContainer.innerHTML = '<p style="text-align: center; color: #999; margin-top: 30px;">Select filters or type a name.</p>';
                return;
            }
            listContainer.innerHTML = filtered.length ? filtered.map(move => `
                <a href="move_card.php?id=${move.id}" class="move-item" style="text-decoration:none; color:inherit; display:flex;">
                    <div>
                        <div class="move-name">${move.name}</div>
                        <div style="font-size:0.7rem; color:#888;">
                            PWR: ${move.power || '--'} | ACC: ${move.accuracy ? (move.accuracy*100)+'%' : '--'} | PP: ${move.pp}
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="type-btn type-${move.type}" style="opacity:1; font-size:0.6rem; padding: 4px 10px; cursor: default; pointer-events: none;">${move.type}</span>
                        <span class="type-btn category-${move.category}" style="opacity:1; font-size:0.6rem; padding: 4px 10px; cursor: default; pointer-events: none;">${move.category}</span>
                    </div>
                </div>
            `).join('') : '<p style="text-align:center; margin-top:20px;">No moves match your criteria.</p>';
        }

        /* =========================================
           LOGIKA TYPE CHART
           ========================================= */
        const chartData = <?php echo json_encode($typeChart); ?>;
        let chartState = { attacker: null, defenders: [] };

        function selectChartAttacker(type, btnElement) {
            if (chartState.attacker === type) {
                chartState.attacker = null;
            } else {
                chartState.attacker = type;
            }
            updateChartUI();
            calculateChart();
        }

        function toggleChartDefender(type, btnElement) {
            const index = chartState.defenders.indexOf(type);
            if (index > -1) {
                chartState.defenders.splice(index, 1);
            } else {
                if (chartState.defenders.length >= 2) chartState.defenders.shift();
                chartState.defenders.push(type);
            }
            updateChartUI();
            calculateChart();
        }

        function updateChartUI() {
            document.querySelectorAll('#chart-attacker-container .type-btn').forEach(btn => {
                const typeName = Array.from(btn.classList).find(c => c.startsWith('type-') && c !== 'type-btn').replace('type-', '');
                if (chartState.attacker === typeName) btn.classList.add('active');
                else btn.classList.remove('active');
            });
            document.querySelectorAll('#chart-defender-container .type-btn').forEach(btn => {
                const typeName = Array.from(btn.classList).find(c => c.startsWith('type-') && c !== 'type-btn').replace('type-', '');
                if (chartState.defenders.includes(typeName)) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }

        function calculateChart() {
            const valDiv = document.getElementById('chart-value');
            const descDiv = document.getElementById('chart-desc');
            if (!chartState.attacker || chartState.defenders.length === 0) {
                valDiv.innerText = '-';
                descDiv.innerText = 'Select Attacker & Defender';
                valDiv.style.color = '#333';
                return;
            }
            const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1);
            const atk = capitalize(chartState.attacker);
            let multiplier = 1.0;
            chartState.defenders.forEach(defLower => {
                const def = capitalize(defLower);
                let val = 1.0;
                if (chartData[atk] && chartData[atk][def] !== undefined) {
                    val = chartData[atk][def];
                }
                multiplier *= val;
            });
            valDiv.innerText = multiplier + 'x';
            if (multiplier === 0) { valDiv.style.color = '#333'; descDiv.innerText = 'No Effect!'; }
            else if (multiplier < 1) { valDiv.style.color = '#a04040'; descDiv.innerText = 'Not very effective...'; }
            else if (multiplier === 1) { valDiv.style.color = '#333'; descDiv.innerText = 'Normal damage'; }
            else if (multiplier > 1) { valDiv.style.color = '#4caf50'; descDiv.innerText = 'Super Effective!'; }
            if (multiplier >= 4) { valDiv.style.color = '#DC0A2D'; descDiv.innerText = 'Ultra Effective!'; }
        }

        const partyIds = <?php echo json_encode($myPartyIds ?? []); ?>;
        const partyContainer = document.getElementById('party-grid-container');

        function renderMyParty() {
            const partyPokemons = allPokemons.filter(p => partyIds.includes(p.id));

            if (partyPokemons.length === 0) {
                partyContainer.innerHTML = '<p style="text-align:center; color:#999; grid-column:1/-1;">Your party is empty. Add pokemons from their cards!</p>';
                return;
            }

            partyContainer.innerHTML = partyPokemons.map(p => `
                <a href="pokemon_card.php?id=${p.id}" class="poke-tile" style="text-decoration: none; color: inherit;">
                    <div class="tile-content">
                        <img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" class="poke-img">
                        <span class="poke-name">${escapeHtml(p.name)}</span>
                    </div>
                    <span class="poke-index">#${String(p.pokedex_number).padStart(3, '0')}</span>
                    <div style="margin-bottom:10px; display:flex; gap:5px; justify-content:center;">
                         <span style="font-size:0.6rem; padding:2px 6px; border-radius:4px; color:white;" class="type-${p.pokemon_type.toLowerCase()}">${p.pokemon_type}</span>
                         ${p.secondary_type ? `<span style="font-size:0.6rem; padding:2px 6px; border-radius:4px; color:white;" class="type-${p.secondary_type.toLowerCase()}">${p.secondary_type}</span>` : ''}
                    </div>
                </a>
            `).join('');
        }

    </script>
</body>
</html>