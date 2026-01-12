-- enum for types (pokemon, moves, evolutions)
DO $$ BEGIN
    CREATE TYPE public.element_type AS ENUM (
        'Normal', 'Fire', 'Water', 'Grass', 'Electric', 'Ice', 
        'Fighting', 'Poison', 'Ground', 'Flying', 'Psychic', 
        'Bug', 'Rock', 'Ghost', 'Dragon', 'Steel', 'Fairy', 'Dark', 'Level', 'Item', 'Other'
    );
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- enum for user types
DO $$ BEGIN
    CREATE TYPE public.user_role AS ENUM ('Admin', 'Common');
EXCEPTION WHEN duplicate_object THEN null; END $$;

-- generation table
CREATE TABLE IF NOT EXISTS public.generation (
    id SMALLSERIAL PRIMARY KEY,
    name TEXT NOT NULL
);

-- pokemon table
CREATE TABLE IF NOT EXISTS public.pokemon (
    id SMALLSERIAL PRIMARY KEY,
    pokedex_number INT2 NOT NULL,
    name TEXT NOT NULL,
    image_url TEXT,
    pokemon_type public.element_type NOT NULL,
    secondary_type public.element_type, -- POPRAWIONO LITERÓWKĘ (było elementy_type)
    height NUMERIC(5, 2) NOT NULL,
    weight NUMERIC(5, 1) NOT NULL,
    description TEXT NOT NULL,
    hp INT2 NOT NULL,
    attack INT2 NOT NULL,
    defense INT2 NOT NULL,
    sp_attack INT2 NOT NULL,
    sp_defense INT2 NOT NULL,
    speed INT2 NOT NULL,
    generation_id INT2 NOT NULL
);

-- move table
CREATE TABLE IF NOT EXISTS public.move (
    id SMALLSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    move_type public.element_type NOT NULL,
    category TEXT NOT NULL,
    pp INT2 NOT NULL,
    power INT2,
    accuracy NUMERIC(3, 2),
    description TEXT, -- DODANO KOLUMNĘ DESCRIPTION (zgodnie z wymaganiami)
    generation_id INT2 NOT NULL
);

-- pokemon_moves association table
CREATE TABLE IF NOT EXISTS public.pokemon_moves (
    pokemon_id INT4 NOT NULL,
    move_id INT4 NOT NULL,
    PRIMARY KEY (pokemon_id, move_id)
);

-- evolution table
CREATE TABLE IF NOT EXISTS public.evolution (
    pre_evolution_id INT4 NOT NULL,
    post_evolution_id INT4 NOT NULL,
    trigger_type public.element_type NOT NULL,
    min_level INT4,
    item TEXT,
    notes JSON,
    PRIMARY KEY (pre_evolution_id, post_evolution_id)
);

-- users table
CREATE TABLE IF NOT EXISTS public.users (
    user_id SMALLSERIAL PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role public.user_role NOT NULL DEFAULT 'Common'
);

-- type effectivness table

-- TABELA EFEKTYWNOŚCI
CREATE TABLE IF NOT EXISTS public.type_effectiveness (
    attacking_type public.element_type NOT NULL,
    defending_type public.element_type NOT NULL,
    multiplier NUMERIC(3, 1) NOT NULL DEFAULT 1.0,
    PRIMARY KEY (attacking_type, defending_type)
);


INSERT INTO public.type_effectiveness (attacking_type, defending_type, multiplier) VALUES
-- NORMAL
('Normal', 'Rock', 0.5), ('Normal', 'Ghost', 0.0), ('Normal', 'Steel', 0.5),
-- FIRE
('Fire', 'Fire', 0.5), ('Fire', 'Water', 0.5), ('Fire', 'Grass', 2.0), ('Fire', 'Ice', 2.0), ('Fire', 'Bug', 2.0), ('Fire', 'Rock', 0.5), ('Fire', 'Dragon', 0.5), ('Fire', 'Steel', 2.0),
-- WATER
('Water', 'Fire', 2.0), ('Water', 'Water', 0.5), ('Water', 'Grass', 0.5), ('Water', 'Ground', 2.0), ('Water', 'Rock', 2.0), ('Water', 'Dragon', 0.5),
-- GRASS
('Grass', 'Fire', 0.5), ('Grass', 'Water', 2.0), ('Grass', 'Grass', 0.5), ('Grass', 'Poison', 0.5), ('Grass', 'Ground', 2.0), ('Grass', 'Flying', 0.5), ('Grass', 'Bug', 0.5), ('Grass', 'Rock', 2.0), ('Grass', 'Dragon', 0.5), ('Grass', 'Steel', 0.5),
-- ELECTRIC
('Electric', 'Water', 2.0), ('Electric', 'Grass', 0.5), ('Electric', 'Electric', 0.5), ('Electric', 'Ground', 0.0), ('Electric', 'Flying', 2.0), ('Electric', 'Dragon', 0.5),
-- ICE
('Ice', 'Fire', 0.5), ('Ice', 'Water', 0.5), ('Ice', 'Grass', 2.0), ('Ice', 'Ice', 0.5), ('Ice', 'Ground', 2.0), ('Ice', 'Flying', 2.0), ('Ice', 'Dragon', 2.0), ('Ice', 'Steel', 0.5),
-- FIGHTING
('Fighting', 'Normal', 2.0), ('Fighting', 'Ice', 2.0), ('Fighting', 'Poison', 0.5), ('Fighting', 'Flying', 0.5), ('Fighting', 'Psychic', 0.5), ('Fighting', 'Bug', 0.5), ('Fighting', 'Rock', 2.0), ('Fighting', 'Ghost', 0.0), ('Fighting', 'Dark', 2.0), ('Fighting', 'Steel', 2.0), ('Fighting', 'Fairy', 0.5),
-- POISON
('Poison', 'Grass', 2.0), ('Poison', 'Poison', 0.5), ('Poison', 'Ground', 0.5), ('Poison', 'Rock', 0.5), ('Poison', 'Ghost', 0.5), ('Poison', 'Steel', 0.0), ('Poison', 'Fairy', 2.0),
-- GROUND
('Ground', 'Fire', 2.0), ('Ground', 'Grass', 0.5), ('Ground', 'Electric', 2.0), ('Ground', 'Poison', 2.0), ('Ground', 'Flying', 0.0), ('Ground', 'Bug', 0.5), ('Ground', 'Rock', 2.0), ('Ground', 'Steel', 2.0),
-- FLYING
('Flying', 'Grass', 2.0), ('Flying', 'Electric', 0.5), ('Flying', 'Fighting', 2.0), ('Flying', 'Bug', 2.0), ('Flying', 'Rock', 0.5), ('Flying', 'Steel', 0.5),
-- PSYCHIC
('Psychic', 'Fighting', 2.0), ('Psychic', 'Poison', 2.0), ('Psychic', 'Psychic', 0.5), ('Psychic', 'Dark', 0.0), ('Psychic', 'Steel', 0.5),
-- BUG
('Bug', 'Fire', 0.5), ('Bug', 'Grass', 2.0), ('Bug', 'Fighting', 0.5), ('Bug', 'Poison', 0.5), ('Bug', 'Flying', 0.5), ('Bug', 'Psychic', 2.0), ('Bug', 'Ghost', 0.5), ('Bug', 'Dark', 2.0), ('Bug', 'Steel', 0.5), ('Bug', 'Fairy', 0.5),
-- ROCK
('Rock', 'Fire', 2.0), ('Rock', 'Ice', 2.0), ('Rock', 'Fighting', 0.5), ('Rock', 'Ground', 0.5), ('Rock', 'Flying', 2.0), ('Rock', 'Bug', 2.0), ('Rock', 'Steel', 0.5),
-- GHOST
('Ghost', 'Normal', 0.0), ('Ghost', 'Psychic', 2.0), ('Ghost', 'Ghost', 2.0), ('Ghost', 'Dark', 0.5),
-- DRAGON
('Dragon', 'Dragon', 2.0), ('Dragon', 'Steel', 0.5), ('Dragon', 'Fairy', 0.0),
-- DARK
('Dark', 'Fighting', 0.5), ('Dark', 'Psychic', 2.0), ('Dark', 'Ghost', 2.0), ('Dark', 'Dark', 0.5), ('Dark', 'Fairy', 0.5),
-- STEEL
('Steel', 'Fire', 0.5), ('Steel', 'Water', 0.5), ('Steel', 'Electric', 0.5), ('Steel', 'Ice', 2.0), ('Steel', 'Rock', 2.0), ('Steel', 'Steel', 0.5), ('Steel', 'Fairy', 2.0),
-- FAIRY
('Fairy', 'Fire', 0.5), ('Fairy', 'Fighting', 2.0), ('Fairy', 'Poison', 0.5), ('Fairy', 'Dragon', 2.0), ('Fairy', 'Dark', 2.0), ('Fairy', 'Steel', 0.5)
ON CONFLICT (attacking_type, defending_type) DO UPDATE SET multiplier = EXCLUDED.multiplier;

-- relations:
ALTER TABLE public.pokemon 
    ADD CONSTRAINT fk_pokemon_generation FOREIGN KEY (generation_id) REFERENCES public.generation(id);

ALTER TABLE public.move 
    ADD CONSTRAINT fk_move_generation FOREIGN KEY (generation_id) REFERENCES public.generation(id),
    ADD CONSTRAINT move_name_unique UNIQUE (name);

ALTER TABLE public.pokemon_moves 
    ADD CONSTRAINT fk_pm_pokemon FOREIGN KEY (pokemon_id) REFERENCES public.pokemon(id),
    ADD CONSTRAINT fk_pm_move FOREIGN KEY (move_id) REFERENCES public.move(id);

ALTER TABLE public.evolution 
    ADD CONSTRAINT fk_evo_pre FOREIGN KEY (pre_evolution_id) REFERENCES public.pokemon(id),
    ADD CONSTRAINT fk_evo_post FOREIGN KEY (post_evolution_id) REFERENCES public.pokemon(id);


-- INSERTS
INSERT INTO public.generation (name) VALUES ('I');

-- gen I starters
-- DODANO KOLUMNĘ secondary_type DO LISTY INSERT
INSERT INTO public.pokemon (
    pokedex_number, name, image_url, pokemon_type, secondary_type, height, weight, 
    description, hp, attack, defense, sp_attack, sp_defense, speed, generation_id
) VALUES 
(1, 'Bulbasaur', 'https://archives.bulbagarden.net/media/upload/thumb/f/fb/0001Bulbasaur.png/500px-0001Bulbasaur.png', 'Grass', 'Poison', 0.7, 6.9, 'A strange seed was planted on its back at birth.', 45, 49, 49, 65, 65, 45, 1),
(2, 'Ivysaur', 'https://archives.bulbagarden.net/media/upload/thumb/8/81/0002Ivysaur.png/500px-0002Ivysaur.png', 'Grass', 'Poison',1.0, 13.0, 'When the bulb on its back grows large, it appears to lose the ability to stand on its hind legs.', 60, 62, 63, 80, 80, 60, 1),
(3, 'Venusaur', 'https://archives.bulbagarden.net/media/upload/thumb/6/6b/0003Venusaur.png/500px-0003Venusaur.png', 'Grass', 'Poison', 2.0, 100.0, 'Its plant blooms when it is absorbing solar energy. It stays on the move to seek sunlight.', 80, 82, 83, 100, 100, 80, 1),
(4, 'Charmander', 'https://archives.bulbagarden.net/media/upload/thumb/2/27/0004Charmander.png/500px-0004Charmander.png', 'Fire', NULL, 0.6, 8.5, 'Obviously prefers hot places. When it rains, steam is said to spout from the tip of its tail.', 39, 52, 43, 60, 50, 65, 1),
(5, 'Charmeleon', 'https://archives.bulbagarden.net/media/upload/thumb/0/05/0005Charmeleon.png/500px-0005Charmeleon.png', 'Fire', NULL, 1.1, 19.0, 'When it swings its burning tail, it elevates the temperature to unbearably high levels.', 58, 64, 58, 80, 65, 80, 1),
(6, 'Charizard', 'https://archives.bulbagarden.net/media/upload/thumb/3/38/0006Charizard.png/500px-0006Charizard.png', 'Fire', 'Flying', 1.7, 90.5, 'Spits fire that is hot enough to melt boulders. Known to cause forest fires unintentionally.', 78, 84, 78, 109, 85, 100, 1),
(7, 'Squirtle', 'https://archives.bulbagarden.net/media/upload/thumb/5/54/0007Squirtle.png/500px-0007Squirtle.png', 'Water', NULL, 0.5, 9.0, 'After birth, its back swells and hardens into a shell. Powerfully sprays foam from its mouth.', 44, 48, 65, 50, 64, 43, 1),
(8, 'Wartortle', 'https://archives.bulbagarden.net/media/upload/thumb/0/0f/0008Wartortle.png/500px-0008Wartortle.png', 'Water', NULL, 1.0, 22.5, 'Often hides in water to stalk unwary prey. For swimming fast, it moves its ears to maintain balance.', 59, 63, 80, 65, 80, 58, 1),
(9, 'Blastoise', 'https://archives.bulbagarden.net/media/upload/thumb/0/0a/0009Blastoise.png/500px-0009Blastoise.png', 'Water', NULL, 1.6, 85.5, 'A brutal Pokémon with pressurized water jets on its shell. They are used for high speed tackles.', 79, 83, 100, 85, 105, 78, 1);

-- moves
-- ZAKTUALIZOWANO INSERT O OPISY (pole description)
INSERT INTO public.move (name, move_type, category, pp, power, accuracy, description, generation_id) VALUES 
('Pound', 'Normal', 'Physical', 35, 40, 1.00, 'Pounds with forelegs or tail.', 1),
('Karate Chop', 'Fighting', 'Physical', 25, 50, 1.00, 'High critical hit ratio.', 1),
('Double Slap', 'Normal', 'Physical', 10, 15, 0.85, 'Repeatedly slaps 2-5 times.', 1),
('Comet Punch', 'Normal', 'Physical', 15, 18, 0.85, 'Repeatedly punches 2-5 times.', 1),
('Mega Punch', 'Normal', 'Physical', 20, 80, 0.85, 'A powerful punch thrown very hard.', 1),
('Pay Day', 'Normal', 'Physical', 20, 40, 1.00, 'Throws coins. Money is recovered after battle.', 1),
('Fire Punch', 'Fire', 'Physical', 15, 75, 1.00, 'May burn the opponent.', 1),
('Ice Punch', 'Ice', 'Physical', 15, 75, 1.00, 'May freeze the opponent.', 1),
('Thunder Punch', 'Electric', 'Physical', 15, 75, 1.00, 'May paralyze the opponent.', 1),
('Scratch', 'Normal', 'Physical', 35, 40, 1.00, 'Scratches with sharp claws.', 1),
('Vise Grip', 'Normal', 'Physical', 30, 55, 1.00, 'Grips with powerful pincers.', 1),
('Guillotine', 'Normal', 'Physical', 5, NULL, 0.30, 'A one-hit KO move.', 1),
('Razor Wind', 'Normal', 'Special', 10, 80, 1.00, 'Charges on first turn, attacks on second.', 1),
('Swords Dance', 'Normal', 'Status', 20, NULL, NULL, 'Sharply raises user Attack.', 1),
('Cut', 'Normal', 'Physical', 30, 50, 0.95, 'Cuts using claws, scythes, etc.', 1),
('Gust', 'Flying', 'Special', 35, 40, 1.00, 'Strikes the target with wings.', 1),
('Wing Attack', 'Flying', 'Physical', 35, 60, 1.00, 'Strikes the target with wings.', 1),
('Whirlwind', 'Normal', 'Status', 20, NULL, NULL, 'Blows away the opponent.', 1),
('Fly', 'Flying', 'Physical', 15, 90, 0.95, 'Flies up on first turn, attacks on second.', 1),
('Bind', 'Normal', 'Physical', 20, 15, 0.85, 'Binds the target for 2-5 turns.', 1),
('Slam', 'Normal', 'Physical', 20, 80, 0.75, 'Slams the target with a long tail, vine etc.', 1),
('Stomp', 'Normal', 'Physical', 20, 65, 1.00, 'Stomps on the enemy.', 1),
('Double Kick', 'Fighting', 'Physical', 30, 30, 1.00, 'Kicks twice in a row.', 1),
('Mega Kick', 'Normal', 'Physical', 5, 120, 0.75, 'A powerful kicking attack.', 1),
('Tackle', 'Normal', 'Physical', 35, 40, 1.00, 'Charges the foe with a full-body tackle.', 1),
('Growl', 'Normal', 'Status', 40, NULL, 1.00, 'Lowers the foe Attack.', 1),
('Vine Whip', 'Grass', 'Physical', 25, 45, 1.00, 'Strikes the foe with slender whips.', 1),
('Growth', 'Normal', 'Status', 20, NULL, NULL, 'Raises user Attack and Sp. Atk.', 1),
('Leech Seed', 'Grass', 'Status', 10, NULL, 0.90, 'Steals HP from the foe on every turn.', 1),
('Razor Leaf', 'Grass', 'Physical', 25, 55, 0.95, 'High critical hit ratio.', 1),
('Poison Powder', 'Poison', 'Status', 35, NULL, 0.75, 'Poisons the foe.', 1),
('Sleep Powder', 'Grass', 'Status', 15, NULL, 0.75, 'Puts the foe to sleep.', 1),
('Seed Bomb', 'Grass', 'Physical', 15, 80, 1.00, 'Slams a barrage of hard seeds at the target.', 1),
('Take Down', 'Normal', 'Physical', 20, 90, 0.85, 'User receives recoil damage.', 1),
('Sweet Scent', 'Normal', 'Status', 20, NULL, 1.00, 'Lowers the foe Evasion.', 1),
('Synthesis', 'Grass', 'Status', 5, NULL, NULL, 'Restores HP based on weather.', 1),
('Worry Seed', 'Grass', 'Status', 10, NULL, 1.00, 'Changes the foe ability to Insomnia.', 1),
('Power Whip', 'Grass', 'Physical', 10, 120, 0.85, 'Violently lashes the foe with vines.', 1),
('Solar Beam', 'Grass', 'Special', 10, 120, 1.00, 'Charges on first turn, attacks on second.', 1)
ON CONFLICT (name) DO NOTHING;

--inserty ataków dla pokemonów
INSERT INTO public.pokemon_moves (pokemon_id, move_id)
SELECT p.id, m.id
FROM public.pokemon p
CROSS JOIN public.move m
WHERE p.name = 'Bulbasaur' 
AND m.name IN (
    'Tackle',
    'Growl',
    'Vine Whip',
    'Growth',
    'Leech Seed',
    'Razor Leaf',
    'Poison Powder',
    'Sleep Powder',
    'Seed Bomb',
    'Take Down',
    'Sweet Scent',
    'Synthesis',
    'Worry Seed',
    'Power Whip',
    'Solar Beam'
)
ON CONFLICT (pokemon_id, move_id) DO NOTHING;

INSERT INTO public.evolution (pre_evolution_id, post_evolution_id, trigger_type, min_level, item, notes) VALUES 
(1, 2, 'Level', 16, NULL, NULL),
(2, 3, 'Level', 32, NULL, NULL),
(4, 5, 'Level', 16, NULL, NULL),
(5, 6, 'Level', 36, NULL, NULL),
(7, 8, 'Level', 16, NULL, NULL),
(8, 9, 'Level', 36, NULL, NULL);