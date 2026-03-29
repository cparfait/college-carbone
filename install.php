<?php
// install.php — Bilan Carbone Collège E3D — Script d'installation

$db_file = 'bilan_carbone.db';
if (file_exists($db_file)) {
    die("
    <div style='font-family:sans-serif;color:#e74c3c;padding:40px;text-align:center;background:#111;min-height:100vh;'>
        <h2>⚠️ Installation déjà effectuée</h2>
        <p>Le fichier <strong>bilan_carbone.db</strong> existe déjà.</p>
        <p>Pour réinitialiser, supprimez ce fichier et relancez install.php.</p>
        <p><a href='index.php' style='color:#3498db;'>→ Accéder à l'application</a></p>
    </div>");
}

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA journal_mode=WAL");
} catch (Exception $e) {
    die("<p style='color:red;'>Erreur SQLite : " . $e->getMessage() . "</p>");
}

// ── TABLES ──────────────────────────────────────────────────────────────────

$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    username         TEXT    NOT NULL UNIQUE,
    password         TEXT    NOT NULL,
    role             TEXT    NOT NULL CHECK(role IN ('admin','eleve','professeur')),
    theme_autorise   TEXT    DEFAULT 'Tous',
    nom_affiche      TEXT    DEFAULT ''
);

CREATE TABLE IF NOT EXISTS facteurs (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    theme        TEXT    NOT NULL,
    nom_simplifie TEXT   NOT NULL,
    unite        TEXT    NOT NULL,
    valeur       REAL    NOT NULL,
    explication  TEXT,
    source       TEXT    DEFAULT 'Base Empreinte® ADEME'
);

CREATE TABLE IF NOT EXISTS saisies (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    theme      TEXT    NOT NULL,
    poste      TEXT    NOT NULL UNIQUE,
    quantite   REAL    NOT NULL,
    total_co2  REAL    NOT NULL,
    saisie_par TEXT    DEFAULT '',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS college_info (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    nom_college   TEXT    DEFAULT 'Mon Collège',
    nb_eleves     INTEGER DEFAULT 0,
    nb_profs      INTEGER DEFAULT 0,
    nb_personnel  INTEGER DEFAULT 0,
    nb_jours_an   INTEGER DEFAULT 180,
    ville         TEXT    DEFAULT 'Paris',
    date_maj      DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// ── UTILISATEURS ─────────────────────────────────────────────────────────────

$users = [
    ['admin',  password_hash('admin123',  PASSWORD_DEFAULT), 'admin',      'Tous',                       'Administrateur'],
    ['eleve1', password_hash('eleve123',  PASSWORD_DEFAULT), 'eleve',      'Transport & Déplacements',    'Éco-délégué Transport'],
    ['eleve2', password_hash('eleve456',  PASSWORD_DEFAULT), 'eleve',      'Alimentation & Restauration', 'Éco-délégué Cantine'],
    ['eleve3', password_hash('eleve789',  PASSWORD_DEFAULT), 'eleve',      'Déchets',                     'Éco-délégué Déchets'],
    ['prof1',  password_hash('prof123',   PASSWORD_DEFAULT), 'professeur', 'Énergie',                     'Prof référent Énergie'],
];

$stmt = $pdo->prepare("INSERT INTO users (username,password,role,theme_autorise,nom_affiche) VALUES (?,?,?,?,?)");
foreach ($users as $u) $stmt->execute($u);

// ── FACTEURS D'ÉMISSION (42 postes, Base Empreinte® ADEME) ──────────────────

$facteurs = [

    // ── THÈME 1 : ÉNERGIE ──────────────────────────────────────────────────
    ['Énergie', 'Électricité consommée',
        'kWh', 0.052,
        "Electricity du réseau français. Le mix énergétique français est l'un des moins carbonés d'Europe grâce au nucléaire, mais chaque kWh compte ! Vérifiez vos factures EDF/Engie ou demandez au gestionnaire de l'établissement."],

    ['Énergie', 'Gaz naturel (chauffage)',
        'm³', 2.202,
        "Le gaz naturel chauffe la majorité des collèges français. Consultez les relevés de compteur ou les factures annuelles. 1 m³ de gaz = environ 11 kWh thermiques."],

    ['Énergie', 'Fioul domestique',
        'litres', 3.14,
        "Certains bâtiments anciens utilisent encore le fioul. C'est le combustible le plus émetteur ! Renseignez-vous auprès du gestionnaire de la chaufferie."],

    ['Énergie', 'Réseau de chaleur urbain',
        'kWh', 0.117,
        "Les réseaux de chaleur (Paris, Lyon…) combinent chaleur fatale, géothermie et chaufferies collectives. Facteur variable selon la ville — ici valeur moyenne nationale ADEME."],

    ['Énergie', 'Climatisation',
        'kWh', 0.052,
        "La climatisation consomme de l'électricité ET utilise des fluides frigorigènes à fort potentiel de réchauffement (GWP). Ici on compte uniquement l'électricité."],

    // ── THÈME 2 : TRANSPORT & DÉPLACEMENTS ────────────────────────────────
    ['Transport & Déplacements', 'Trajets élèves en voiture',
        'km', 0.218,
        "Voiture particulière thermique moyenne. Pour estimer : distance moyenne A/R × nb d'élèves concernés × 180 jours. Enquête à réaliser auprès des familles."],

    ['Transport & Déplacements', 'Trajets élèves en bus/car scolaire',
        'km', 0.089,
        "Car scolaire au diesel. Facteur par km-passager. Demandez les km annuels parcourus au service de transport du département."],

    ['Transport & Déplacements', 'Trajets élèves en transports en commun',
        'km', 0.004,
        "Métro, RER, bus RATP/SNCF. Le transport en commun émet très peu par passager — c'est la solution à encourager ! Estimez la distance moyenne × nb d'usagers."],

    ['Transport & Déplacements', "Trajets élèves à vélo ou à pied",
        'km', 0.000,
        "Zéro émission directe ! (on peut comptabiliser un très faible impact lié à la fabrication du vélo, négligeable ici). Bravo aux mobilités douces !"],

    ['Transport & Déplacements', 'Trajets domicile-travail du personnel (voiture)',
        'km', 0.218,
        "Voiture des professeurs et personnels. Enquête à mener auprès du personnel. Distance A/R moyenne × nb personnes en voiture × 180 jours."],

    ['Transport & Déplacements', 'Trajets domicile-travail du personnel (TC)',
        'km', 0.004,
        "Personnel utilisant métro, RER, bus, train. Très faible impact. Estimez distance moyenne × nb personnes en TC × 180 jours."],

    ['Transport & Déplacements', 'Sorties scolaires (car)',
        'km', 0.136,
        "Tous les cars affrétés pour les sorties scolaires annuelles. Additionnez tous les kilomètres de l'année (aller + retour)."],

    ['Transport & Déplacements', 'Sorties scolaires (train)',
        'km', 0.004,
        "Le train est l'option la moins carbonée pour les sorties ! SNCF : facteur moyen 0,004 kgCO2e/km-passager. Consultez les billets ou le gestionnaire."],

    ['Transport & Déplacements', 'Voyages scolaires (avion)',
        'km', 0.255,
        "⚠️ L'avion est le mode de transport le plus émetteur ! Un A/R Paris-Rome en avion = ~250 kg CO2e/personne. A éviter ou compenser."],

    ['Transport & Déplacements', 'Livraisons reçues (camion)',
        'km', 0.096,
        "Livraisons de fournitures, nourriture, matériel. Difficile à estimer précisément — commencez par les grandes livraisons (cantine, fournitures scolaires)."],

    // ── THÈME 3 : ALIMENTATION & RESTAURATION ─────────────────────────────
    ['Alimentation & Restauration', 'Repas avec viande rouge',
        'repas', 4.5,
        "Bœuf, agneau, veau : les plus émetteurs à cause des émissions de méthane des ruminants. 1 repas steak = 4,5 kg CO2e ! Consultez les menus annuels de la cantine."],

    ['Alimentation & Restauration', 'Repas avec viande blanche ou poisson',
        'repas', 2.2,
        "Poulet, porc, poisson : 2 fois moins émetteurs que la viande rouge. Restent significatifs à l'échelle d'un collège avec 500 élèves."],

    ['Alimentation & Restauration', 'Repas végétariens',
        'repas', 0.8,
        "Légumes, légumineuses, céréales : la solution la plus efficace ! La loi EGalim impose 1 repas végétarien/semaine minimum. Combien en pratique chez vous ?"],

    ['Alimentation & Restauration', 'Gaspillage alimentaire',
        'kg', 2.5,
        "En France, un élève gaspille en moyenne 150 à 200g par repas. Pesée des déchets alimentaires = action pédagogique ET mesure carbone. Organisez une pesée !"],

    ['Alimentation & Restauration', "Eau en bouteille plastique",
        'litres', 0.215,
        "L'eau du robinet émet 200 fois moins que l'eau en bouteille ! Si votre collège sert encore de l'eau en bouteille, c'est un axe d'amélioration prioritaire."],

    // ── THÈME 4 : DÉCHETS ──────────────────────────────────────────────────
    ['Déchets', 'Ordures ménagères résiduelles',
        'kg', 0.544,
        "Tout ce qui part à l'incinérateur ou en décharge. Contactez le service de propreté de la mairie ou pesez les poubelles sur une semaine type."],

    ['Déchets', 'Papier et carton (tri)',
        'kg', 0.045,
        "Le papier trié est recyclé, d'où son faible facteur. Mais pensez à réduire à la source : impression recto-verso, numérique… Pesez les bacs jaunes !"],

    ['Déchets', 'Plastiques (tri sélectif)',
        'kg', 0.022,
        "Bouteilles, emballages, sachets. Le facteur est faible car le plastique trié est recyclé ou valorisé énergétiquement. Mais mieux vaut éviter d'en produire !"],

    ['Déchets', 'Déchets organiques / compost',
        'kg', 0.136,
        "Si votre collège composte (bac à compost, lombricomposteur), les émissions de méthane sont bien inférieures à un enfouissement. Bravo si vous compostez !"],

    ['Déchets', 'Verre (tri sélectif)',
        'kg', 0.015,
        "Le verre est 100% recyclable à l'infini ! Facteur très faible. Les bouteilles et bocaux de la cantine peuvent être collectés séparément."],

    ['Déchets', 'DEEE (déchets électroniques)',
        'kg', 1.57,
        "Déchets d'équipements électriques et électroniques (vieux ordis, écrans, câbles). Très émetteurs par kg ! Apportez-les en point de collecte agréé."],

    // ── THÈME 5 : IMMOBILISATIONS ─────────────────────────────────────────
    ['Immobilisations', 'Bâtiments (amortissement travaux)',
        'm²', 120,
        "On amorti l'empreinte carbone des matériaux de construction sur la durée de vie du bâtiment. Demandez la surface totale et les travaux récents à la direction."],

    ['Immobilisations', 'Équipements informatiques (ordinateurs)',
        'unités', 156,
        "La fabrication d'un PC = 156 kg CO2e amorti sur 4-5 ans. Comptez tous les ordinateurs fixes et portables de l'établissement (salles info, administration)."],

    ['Immobilisations', 'Tablettes et smartphones',
        'unités', 39,
        "Plus légers à produire qu'un PC mais renouvelés plus souvent. Comptez tous les appareils, y compris ceux du CDI ou des ateliers."],

    ['Immobilisations', 'Vidéoprojecteurs et écrans interactifs',
        'unités', 350,
        "TNI, projecteurs, grands écrans : chacun représente environ 350 kg CO2e de fabrication. Combien y en a-t-il dans votre collège ?"],

    ['Immobilisations', 'Mobilier scolaire (tables, chaises)',
        'unités', 45,
        "Tables et chaises scolaires standard (acier + plastique). Comptez le nombre de sets élèves. Le mobilier en bois local a une empreinte bien moindre."],

    ['Immobilisations', "Véhicules de l'établissement",
        'unités', 6600,
        "Voiture de service, mini-bus… La fabrication d'un véhicule = 6 600 kg CO2e. Si votre collège n'en a pas, mettez 0."],

    // ── THÈME 6 : FOURNITURES & ACHATS ────────────────────────────────────
    ['Fournitures & Achats', 'Rames de papier A4',
        'rames', 2.88,
        "1 rame de 500 feuilles A4 recyclé = ~1,4 kg CO2e ; papier vierge = ~2,88 kg. L'impression reste un poste significatif. Demandez les achats annuels à l'intendance."],

    ['Fournitures & Achats', 'Fournitures scolaires diverses',
        '€ HT', 0.45,
        "Stylos, cahiers, crayons, classeurs… En moyenne 0,45 kg CO2e par euro dépensé (facteur monétaire ADEME). Demandez le budget fournitures annuel."],

    ['Fournitures & Achats', "Produits d'entretien et de nettoyage",
        'kg', 2.1,
        "Détergents, désinfectants, produits de ménage. Les produits éco-labellisés ont un facteur bien inférieur. Demandez les quantités achetées au service technique."],

    ['Fournitures & Achats', "Cartouches d'encre",
        'unités', 4.5,
        "Chaque cartouche représente 4,5 kg CO2e (fabrication + transport). Préférez le rechargement ou les imprimantes laser avec toners recyclés."],

    ['Fournitures & Achats', 'Eau du réseau consommée',
        'm³', 0.344,
        "L'eau du robinet nécessite traitement et pompage. Cherchez le relevé annuel du compteur d'eau de l'établissement auprès du gestionnaire."],

    // ── THÈME 7 : BÂTIMENT & TRAVAUX ──────────────────────────────────────
    ['Bâtiment & Travaux', 'Travaux de rénovation',
        'm²', 120,
        "Rénovation energétique, isolation, toiture… L'empreinte des matériaux de construction est significative mais les économies d'énergie compensent sur le long terme."],

    ['Bâtiment & Travaux', 'Peinture (intérieure/extérieure)',
        'litres', 3.8,
        "La fabrication de peinture (solvants, pigments) est émettrice. Peintures biosourcées ou à l'eau ont un facteur moindre. Demandez les achats à l'équipe technique."],

    ['Bâtiment & Travaux', 'Entretien des espaces verts (engins)',
        'heures', 2.6,
        "Tondeuses, débroussailleuses thermiques. Combien d'heures par an pour entretenir les cours et jardins ? Converser avec le service technique."],

    // ── THÈME 8 : NUMÉRIQUE ────────────────────────────────────────────────
    ['Numérique', 'Usage des équipements numériques',
        'kWh', 0.052,
        "Consommation électrique des ordinateurs, écrans, serveurs, box internet en fonctionnement. Souvent sous-estimé ! Demandez la consommation de la salle serveur."],

    ['Numérique', 'Streaming et usage internet',
        'Go', 0.0015,
        "Chaque Go de données transférées = 1,5 g CO2e (data centers + réseau). 1h de visioconférence HD ≈ 1 Go. À l'échelle d'un collège, ça s'accumule vite !"],
];

$stmt = $pdo->prepare("
    INSERT INTO facteurs (theme, nom_simplifie, unite, valeur, explication)
    VALUES (?, ?, ?, ?, ?)
");
foreach ($facteurs as $f) $stmt->execute($f);

// Données collège par défaut
$pdo->exec("INSERT INTO college_info (nom_college, nb_eleves, nb_profs, nb_personnel, nb_jours_an, ville)
            VALUES ('Collège Demo', 500, 40, 15, 180, 'Paris')");

// ── PAGE DE CONFIRMATION ─────────────────────────────────────────────────────
echo "<!DOCTYPE html>
<html lang='fr' data-theme='dark'>
<head>
<meta charset='UTF-8'>
<title>Installation Bilan Carbone</title>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css'>
</head>
<body>
<main class='container' style='max-width:700px;margin-top:5vh;'>
<article>
<h2>✅ Installation terminée avec succès !</h2>
<p>La base de données <strong>bilan_carbone.db</strong> a été créée avec <strong>" . count($facteurs) . " postes d'émission</strong> (méthode ADEME / Base Empreinte®).</p>
<hr>
<h3>👤 Comptes créés</h3>
<table>
<thead><tr><th>Identifiant</th><th>Mot de passe</th><th>Rôle</th><th>Thème</th></tr></thead>
<tbody>
<tr><td><strong>admin</strong></td><td>admin123</td><td>Admin</td><td>Tous</td></tr>
<tr><td>eleve1</td><td>eleve123</td><td>Élève</td><td>Transport & Déplacements</td></tr>
<tr><td>eleve2</td><td>eleve456</td><td>Élève</td><td>Alimentation & Restauration</td></tr>
<tr><td>eleve3</td><td>eleve789</td><td>Élève</td><td>Déchets</td></tr>
<tr><td>prof1</td><td>prof123</td><td>Professeur</td><td>Énergie</td></tr>
</tbody>
</table>
<hr>
<h3>📋 Thèmes disponibles (8)</h3>
<ul>
<li>🔌 Énergie (5 postes)</li>
<li>🚌 Transport & Déplacements (10 postes)</li>
<li>🥗 Alimentation & Restauration (5 postes)</li>
<li>♻️ Déchets (6 postes)</li>
<li>💻 Immobilisations (6 postes)</li>
<li>🛒 Fournitures & Achats (5 postes)</li>
<li>🏗️ Bâtiment & Travaux (3 postes)</li>
<li>📡 Numérique (2 postes)</li>
</ul>
<p style='color:#e74c3c;'><strong>⚠️ Sécurité :</strong> Pensez à supprimer ou protéger ce fichier install.php après installation !</p>
<a href='index.php' role='button'>🚀 Accéder à l'application</a>
</article>
</main>
</body>
</html>";
?>
