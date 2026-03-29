<?php
// ============================================================
// BILAN CARBONE COLLÈGE E3D — index.php
// Architecture : PHP 8+ / SQLite / PicoCSS / Chart.js
// ============================================================

$db_file = 'bilan_carbone.db';
if (!file_exists($db_file)) {
    header("Location: install.php");
    exit;
}

session_start();

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("<div style='color:red;padding:20px;'>Erreur BDD : " . $e->getMessage() . "</div>");
}

// ── HELPERS ──────────────────────────────────────────────────────────────────

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function equivalences(float $kg): string {
    $items = [];
    if ($kg > 0)    $items[] = round($kg / 0.1)   . " bouteilles plastique 1,5L";
    if ($kg > 10)   $items[] = round($kg / 2.9, 1)  . " jours de chauffage (logement moyen)";
    if ($kg > 50)   $items[] = round($kg / 150, 1)  . " trajets Paris→Lyon en voiture";
    if ($kg > 200)  $items[] = round($kg / 30, 1)   . " smartphones fabriqués";
    if ($kg > 1000) $items[] = round($kg / 1000, 2) . " tCO₂e (tonnes)";
    return implode(' · ', array_slice($items, 0, 3));
}

function themesDisponibles(): array {
    return [
        'Énergie'                   => '🔌',
        'Transport & Déplacements'  => '🚌',
        'Alimentation & Restauration'=> '🥗',
        'Déchets'                   => '♻️',
        'Immobilisations'           => '💻',
        'Fournitures & Achats'      => '🛒',
        'Bâtiment & Travaux'        => '🏗️',
        'Numérique'                 => '📡',
    ];
}

// ── EXPORT CSV ───────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv'
    && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bilan_carbone_' . date('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Thème','Poste','Quantité','Unité','Facteur kgCO2e/unité','Total kgCO2e','% du total','Saisi par'], ';');

    $total_g = (float)$pdo->query("SELECT COALESCE(SUM(total_co2),0) FROM saisies")->fetchColumn();
    $rows = $pdo->query("
        SELECT s.theme, s.poste, s.quantite, f.unite, f.valeur, s.total_co2, s.saisie_par
        FROM saisies s LEFT JOIN facteurs f ON f.nom_simplifie = s.poste
        ORDER BY s.theme, s.total_co2 DESC
    ")->fetchAll();

    foreach ($rows as $r) {
        $pct = $total_g > 0 ? round(($r['total_co2'] / $total_g) * 100, 2) : 0;
        fputcsv($out, [
            $r['theme'], $r['poste'],
            str_replace('.', ',', $r['quantite']),
            $r['unite'],
            str_replace('.', ',', $r['valeur']),
            str_replace('.', ',', round($r['total_co2'], 2)),
            str_replace('.', ',', $pct) . '%',
            $r['saisie_par']
        ], ';');
    }
    fclose($out);
    exit;
}

// ── DÉCONNEXION ──────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// ── CONNEXION ────────────────────────────────────────────────────────────────
$erreur_login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_POST['username'] ?? '']);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        $_SESSION['user_id']        = $user['id'];
        $_SESSION['username']       = $user['username'];
        $_SESSION['nom_affiche']    = $user['nom_affiche'];
        $_SESSION['role']           = $user['role'];
        $_SESSION['theme_autorise'] = $user['theme_autorise'];
        header("Location: index.php");
        exit;
    }
    $erreur_login = "<div class='err'>❌ Identifiants incorrects.</div>";
}

// ── PAGE CONNEXION ────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) { ?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — Bilan Carbone E3D</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
<style>
.err{color:#e74c3c;margin-bottom:15px;padding:10px;background:rgba(231,76,60,.1);border-radius:5px;}
</style>
</head>
<body>
<main class="container" style="max-width:420px;margin-top:8vh;">
<article>
    <div style="text-align:center;margin-bottom:20px;">
        <div style="font-size:3em;">🌍</div>
        <h2 style="margin:5px 0;">Bilan Carbone Collège</h2>
        <p style="color:#aaa;font-size:.9em;">Outil éco-délégués — Label E3D</p>
    </div>
    <?= $erreur_login ?>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <label>Identifiant<input type="text" name="username" required autofocus></label>
        <label>Mot de passe<input type="password" name="password" required></label>
        <button type="submit" style="width:100%;">Se connecter</button>
    </form>
    <p style="text-align:center;font-size:.8em;color:#666;margin-top:20px;">
        Source des facteurs : <a href="https://base-empreinte.ademe.fr/" target="_blank">Base Empreinte® ADEME</a>
    </p>
</article>
</main>
</body>
</html>
<?php exit; }

// ── VARIABLES GLOBALES ────────────────────────────────────────────────────────
$role           = $_SESSION['role'];
$theme_autorise = $_SESSION['theme_autorise'];
$message        = '';
$themes_all     = themesDisponibles();

// ── TRAITEMENT POST ──────────────────────────────────────────────────────────

// Mise à jour effectifs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_college' && $role === 'admin') {
    $exists = $pdo->query("SELECT id FROM college_info LIMIT 1")->fetch();
    $data = [
        $_POST['nom_college']  ?? 'Collège',
        (int)($_POST['nb_eleves']    ?? 0),
        (int)($_POST['nb_profs']     ?? 0),
        (int)($_POST['nb_personnel'] ?? 0),
        (int)($_POST['nb_jours_an']  ?? 180),
        $_POST['ville'] ?? 'Paris'
    ];
    if ($exists) {
        $pdo->prepare("UPDATE college_info SET nom_college=?,nb_eleves=?,nb_profs=?,nb_personnel=?,nb_jours_an=?,ville=?,date_maj=CURRENT_TIMESTAMP")
            ->execute($data);
    } else {
        $pdo->prepare("INSERT INTO college_info (nom_college,nb_eleves,nb_profs,nb_personnel,nb_jours_an,ville) VALUES (?,?,?,?,?,?)")
            ->execute($data);
    }
    $message = "<div class='ok'>✅ Informations du collège mises à jour !</div>";
}

// Création utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user' && $role === 'admin') {
    try {
        $pdo->prepare("INSERT INTO users (username,password,role,theme_autorise,nom_affiche) VALUES (?,?,?,?,?)")
            ->execute([
                trim($_POST['new_username']),
                password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                $_POST['new_role'],
                $_POST['new_theme'],
                trim($_POST['new_nom_affiche'])
            ]);
        $message = "<div class='ok'>✅ Compte <strong>" . h($_POST['new_username']) . "</strong> créé !</div>";
    } catch (Exception $e) {
        $message = "<div class='err'>❌ Erreur : identifiant déjà utilisé.</div>";
    }
}

// Modification mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_pwd' && $role === 'admin') {
    $uid = (int)$_POST['uid'];
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")
        ->execute([password_hash($_POST['new_pwd'], PASSWORD_DEFAULT), $uid]);
    $message = "<div class='ok'>✅ Mot de passe mis à jour.</div>";
}

// Modification thème utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_theme' && $role === 'admin') {
    $pdo->prepare("UPDATE users SET theme_autorise=?,nom_affiche=? WHERE id=?")
        ->execute([$_POST['new_theme_u'], $_POST['nom_affiche_u'], (int)$_POST['uid']]);
    $message = "<div class='ok'>✅ Utilisateur mis à jour.</div>";
}

// Suppression utilisateur
if (isset($_GET['del_user']) && $role === 'admin') {
    $uid = (int)$_GET['del_user'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $message = "<div class='ok'>✅ Compte supprimé.</div>";
    } else {
        $message = "<div class='err'>❌ Vous ne pouvez pas supprimer votre propre compte.</div>";
    }
}

// Saisie d'une donnée carbone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'saisie') {
    $facteur_id = (int)($_POST['facteur_id'] ?? 0);
    $quantite   = (float)str_replace(',', '.', $_POST['quantite'] ?? '0');

    $f = $pdo->prepare("SELECT * FROM facteurs WHERE id=?");
    $f->execute([$facteur_id]);
    $facteur = $f->fetch();

    if ($facteur && $quantite >= 0
        && ($theme_autorise === 'Tous' || $theme_autorise === $facteur['theme'])) {

        $total_co2 = round($quantite * $facteur['valeur'], 4);
        $existing  = $pdo->prepare("SELECT id FROM saisies WHERE poste=?");
        $existing->execute([$facteur['nom_simplifie']]);

        if ($existing->fetch()) {
            $pdo->prepare("UPDATE saisies SET quantite=?,total_co2=?,saisie_par=?,updated_at=CURRENT_TIMESTAMP WHERE poste=?")
                ->execute([$quantite, $total_co2, $_SESSION['username'], $facteur['nom_simplifie']]);
            $message = "<div class='ok'>✅ Valeur mise à jour !</div>";
        } else {
            $pdo->prepare("INSERT INTO saisies (theme,poste,quantite,total_co2,saisie_par) VALUES (?,?,?,?,?)")
                ->execute([$facteur['theme'], $facteur['nom_simplifie'], $quantite, $total_co2, $_SESSION['username']]);
            $message = "<div class='ok'>✅ Donnée enregistrée !</div>";
        }
    }
}

// ── DONNÉES COMMUNES ─────────────────────────────────────────────────────────
$college = $pdo->query("SELECT * FROM college_info ORDER BY date_maj DESC LIMIT 1")->fetch()
    ?: ['nom_college'=>'Collège','nb_eleves'=>0,'nb_profs'=>0,'nb_personnel'=>0,'nb_jours_an'=>180,'ville'=>'Paris'];

$total_global = round((float)$pdo->query("SELECT COALESCE(SUM(total_co2),0) FROM saisies")->fetchColumn(), 2);
$nb_postes_total  = (int)$pdo->query("SELECT COUNT(*) FROM facteurs")->fetchColumn();
$nb_postes_saisis = (int)$pdo->query("SELECT COUNT(*) FROM saisies")->fetchColumn();

$theme_actif = ($role !== 'admin' && $theme_autorise !== 'Tous')
    ? $theme_autorise
    : ($_GET['theme'] ?? 'Énergie');

$vue = $_GET['view'] ?? ($role === 'admin' ? 'dashboard' : 'saisie');

?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bilan Carbone — <?= h($college['nom_college']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ── Base ── */
body { font-size: 15px; }
.ok  { color:#2ecc71; padding:10px 15px; background:rgba(46,204,113,.1); border-radius:6px; margin-bottom:15px; }
.err { color:#e74c3c; padding:10px 15px; background:rgba(231,76,60,.1);  border-radius:6px; margin-bottom:15px; }
/* ── Nav ── */
nav.topnav { border-bottom:1px solid #333; padding-bottom:12px; margin-bottom:20px; }
nav.topnav ul { margin:0; padding:0; }
/* ── Thèmes ── */
.theme-tabs { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:20px; }
.theme-tabs a { padding:6px 12px; font-size:.85em; border-radius:20px; text-decoration:none;
                border:1px solid #444; color:#ccc; }
.theme-tabs a.active { background:#2980b9; border-color:#2980b9; color:#fff; }
/* ── Saisie ── */
.poste-card { border:1px solid #2a2a2a; border-radius:8px; padding:18px; margin-bottom:16px;
              background:#111; transition:border-color .2s; }
.poste-card:hover { border-color:#2980b9; }
.poste-title { font-weight:700; font-size:1em; margin-bottom:4px; }
.infobulle { font-size:.82em; color:#888; font-style:italic; margin-bottom:12px; line-height:1.5; }
.badge-ok { display:inline-block; background:rgba(46,204,113,.15); color:#2ecc71;
            font-size:.78em; padding:2px 8px; border-radius:10px; margin-left:8px; }
.badge-co2 { display:inline-block; background:rgba(52,152,219,.12); color:#3498db;
             font-size:.8em; padding:3px 10px; border-radius:8px; margin-top:6px; }
.equiv { font-size:.78em; color:#777; margin-top:4px; font-style:italic; }
/* ── Dashboard ── */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
.kpi { background:#111; border:1px solid #2a2a2a; border-radius:8px; padding:16px; text-align:center; }
.kpi-val { font-size:1.8em; font-weight:700; color:#3498db; }
.kpi-lab { font-size:.78em; color:#888; margin-top:4px; }
.progress-bar { background:#222; border-radius:4px; height:10px; overflow:hidden; margin:8px 0; }
.progress-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#2980b9,#2ecc71); transition:width .5s; }
/* ── Table ── */
table { font-size:.88em; }
th { color:#aaa; font-weight:600; }
.bar-inline { display:inline-block; height:8px; border-radius:4px; background:#e74c3c;
              vertical-align:middle; margin-left:6px; }
/* ── Prompt ── */
#prompt-box { font-family:monospace; font-size:.8em; background:#0d1117; color:#c9d1d9;
              border:1px solid #333; border-radius:6px; width:100%; resize:vertical; }
/* ── Responsive ── */
@media(max-width:600px){
  .kpi-grid { grid-template-columns:1fr 1fr; }
  .theme-tabs { gap:4px; }
  .theme-tabs a { font-size:.75em; padding:5px 8px; }
}
</style>
</head>
<body>
<main class="container">

<!-- ═══════════════════════════════════════════════════
     NAVIGATION PRINCIPALE
═══════════════════════════════════════════════════ -->
<nav class="topnav">
  <ul>
    <li><strong>🌍 <?= h($college['nom_college']) ?></strong>
        <small style="color:#666;"> — Bilan Carbone E3D</small></li>
  </ul>
  <ul>
    <?php if ($role === 'admin'): ?>
      <li><a href="?view=dashboard"  <?= $vue==='dashboard'  ?'aria-current="page"':'' ?>>📊 Tableau de bord</a></li>
      <li><a href="?view=comptes"    <?= $vue==='comptes'    ?'aria-current="page"':'' ?>>👤 Comptes</a></li>
      <li><a href="?view=saisie"     <?= $vue==='saisie'     ?'aria-current="page"':'' ?>>🎯 Saisie</a></li>
    <?php else: ?>
      <li><a href="?view=saisie">🎯 Saisie — <?= h($theme_autorise) ?></a></li>
    <?php endif; ?>
    <li>
      <a href="?logout=1" role="button" class="secondary outline" style="font-size:.85em;">
        Déconnexion (<?= h($_SESSION['username']) ?>)
      </a>
    </li>
  </ul>
</nav>

<?= $message ?>

<?php
// ════════════════════════════════════════════════════════════════
// VUE : SAISIE
// ════════════════════════════════════════════════════════════════
if ($vue === 'saisie'):

    // Onglets thèmes
    if ($theme_autorise === 'Tous'):
?>
<div class="theme-tabs">
<?php foreach ($themes_all as $t => $ico):
    $url = '?view=saisie&theme=' . urlencode($t);
    $cls = ($theme_actif === $t) ? 'active' : '';
    echo "<a href='" . h($url) . "' class='$cls'>$ico " . h($t) . "</a>";
endforeach; ?>
</div>
<?php else: ?>
<div style="background:#1e3a5f;padding:10px 15px;border-radius:6px;margin-bottom:20px;">
  🔒 Pôle assigné : <strong><?= h($theme_actif) ?></strong>
  <?php if ($nb_postes_saisis > 0): ?>
    — <span style="color:#2ecc71;"><?= $nb_postes_saisis ?> poste(s) saisi(s)</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<h2><?= $themes_all[$theme_actif] ?? '📋' ?> <?= h($theme_actif) ?></h2>

<?php
    // Valeurs déjà saisies
    $saisies_theme = $pdo->prepare("SELECT poste, quantite, total_co2 FROM saisies WHERE theme=?");
    $saisies_theme->execute([$theme_actif]);
    $vals = [];
    foreach ($saisies_theme->fetchAll() as $r) {
        $vals[$r['poste']] = ['q' => $r['quantite'], 'co2' => $r['total_co2']];
    }

    $facteurs_theme = $pdo->prepare("SELECT * FROM facteurs WHERE theme=? ORDER BY id");
    $facteurs_theme->execute([$theme_actif]);
    $liste = $facteurs_theme->fetchAll();

    if (empty($liste)) echo "<p style='color:#888;'>Aucun poste trouvé pour ce thème.</p>";

    foreach ($liste as $f):
        $poste   = $f['nom_simplifie'];
        $saisi   = isset($vals[$poste]);
        $qte     = $saisi ? $vals[$poste]['q']   : '';
        $co2     = $saisi ? $vals[$poste]['co2']  : 0;
?>
<div class="poste-card">
  <form method="POST" action="?view=saisie&theme=<?= urlencode($theme_actif) ?>">
    <input type="hidden" name="action"     value="saisie">
    <input type="hidden" name="facteur_id" value="<?= $f['id'] ?>">
    <div class="poste-title">
      <?= h($poste) ?>
      <?php if ($saisi): ?><span class="badge-ok">✅ Saisi</span><?php endif; ?>
    </div>
    <p class="infobulle">💡 <?= h($f['explication']) ?></p>
    <small style="color:#555;">Source : <?= h($f['source'] ?? 'Base Empreinte® ADEME') ?>
      · Facteur : <?= $f['valeur'] ?> kgCO₂e/<?= h($f['unite']) ?>
    </small>
    <div class="grid" style="margin-top:10px;gap:10px;">
      <input type="number" name="quantite" step="0.01" min="0" required
             placeholder="Quantité en <?= h($f['unite']) ?>"
             value="<?= h((string)$qte) ?>"
             style="margin:0;">
      <button type="submit" style="margin:0;"><?= $saisi ? 'Mettre à jour' : 'Enregistrer' ?></button>
    </div>
    <?php if ($saisi): ?>
    <div class="badge-co2">⚡ <?= round($co2, 2) ?> kgCO₂e</div>
    <?php if ($co2 > 0): ?>
    <div class="equiv">≈ <?= equivalences($co2) ?></div>
    <?php endif; ?>
    <?php endif; ?>
  </form>
</div>
<?php endforeach; ?>

<?php
// ════════════════════════════════════════════════════════════════
// VUE : TABLEAU DE BORD (admin)
// ════════════════════════════════════════════════════════════════
elseif ($vue === 'dashboard' && $role === 'admin'):

    $stats_themes = $pdo->query("
        SELECT theme, ROUND(SUM(total_co2),2) as total
        FROM saisies GROUP BY theme ORDER BY total DESC
    ")->fetchAll();

    $postes_top = $pdo->query("
        SELECT s.poste, s.theme, ROUND(s.quantite,2) as quantite,
               ROUND(s.total_co2,2) as total, f.unite
        FROM saisies s LEFT JOIN facteurs f ON f.nom_simplifie=s.poste
        ORDER BY s.total_co2 DESC
    ")->fetchAll();

    $nb_e  = (int)$college['nb_eleves'];
    $nb_p  = (int)$college['nb_profs'];
    $nb_a  = (int)$college['nb_personnel'];
    $nb_t  = $nb_e + $nb_p + $nb_a;
    $ratio_eleve  = ($total_global > 0 && $nb_e > 0) ? round($total_global / $nb_e, 2) : 0;
    $ratio_pers   = ($total_global > 0 && $nb_t > 0) ? round($total_global / $nb_t, 2) : 0;
    $total_t      = round($total_global / 1000, 3);
    $bouteilles   = floor($total_global / 0.1);
    $smartphones  = round($total_global / 30);
    $paris_ny     = round($total_global / 1000 * 2.19); // 1t ≈ 2,19 Paris-NY (base ADEME avion)
    $completude   = $nb_postes_total > 0 ? round(($nb_postes_saisis / $nb_postes_total) * 100) : 0;

    $labels_json = json_encode(array_column($stats_themes, 'theme'));
    $data_json   = json_encode(array_map('floatval', array_column($stats_themes, 'total')));
    $colors_json = json_encode(['#e74c3c','#3498db','#f1c40f','#2ecc71','#9b59b6','#e67e22','#1abc9c','#e91e63']);
?>
<h2>📊 Tableau de bord Global</h2>

<!-- KPI -->
<div class="kpi-grid">
  <div class="kpi">
    <div class="kpi-val"><?= $total_t ?></div>
    <div class="kpi-lab">tCO₂e total</div>
  </div>
  <div class="kpi">
    <div class="kpi-val"><?= $total_global ?></div>
    <div class="kpi-lab">kgCO₂e total</div>
  </div>
  <div class="kpi">
    <div class="kpi-val"><?= $ratio_eleve ?></div>
    <div class="kpi-lab">kg CO₂e / élève</div>
  </div>
  <div class="kpi">
    <div class="kpi-val"><?= $ratio_pers ?></div>
    <div class="kpi-lab">kg CO₂e / personne</div>
  </div>
  <div class="kpi">
    <div class="kpi-val"><?= $completude ?>%</div>
    <div class="kpi-lab">Complétude (<?= $nb_postes_saisis ?>/<?= $nb_postes_total ?> postes)</div>
  </div>
  <div class="kpi">
    <div class="kpi-val"><?= $nb_e ?></div>
    <div class="kpi-lab">Élèves</div>
  </div>
</div>

<!-- Barre de complétude -->
<div style="margin-bottom:20px;">
  <small style="color:#888;">Progression de la saisie : <?= $nb_postes_saisis ?> / <?= $nb_postes_total ?> postes</small>
  <div class="progress-bar"><div class="progress-fill" style="width:<?= $completude ?>%;"></div></div>
</div>

<!-- Équivalences -->
<?php if ($total_global > 0): ?>
<article style="background:#111;border:1px solid #2a2a2a;margin-bottom:20px;">
  <header>🌏 Équivalences parlantes</header>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;font-size:.9em;">
    <div>🍾 <strong><?= number_format($bouteilles,0,',',' ') ?></strong> bouteilles plastique 1,5L</div>
    <div>📱 <strong><?= number_format($smartphones,0,',',' ') ?></strong> smartphones fabriqués</div>
    <div>✈️ <strong><?= $paris_ny ?></strong> trajets Paris↔New York (avion)</div>
    <div>🔋 <strong><?= round($total_global / 12, 0) ?></strong> ans de chauffage d'un appartement</div>
  </div>
</article>
<?php endif; ?>

<!-- Graphiques -->
<div class="grid">
  <article>
    <header>Répartition par thème</header>
    <?php if ($total_global > 0): ?>
      <canvas id="chartDonut" style="max-height:280px;"></canvas>
    <?php else: ?>
      <p style="color:#666;text-align:center;padding:40px 0;">Aucune donnée saisie</p>
    <?php endif; ?>
  </article>
  <article>
    <header>Top 5 postes émetteurs</header>
    <?php if (count($postes_top) > 0): ?>
      <canvas id="chartBars" style="max-height:280px;"></canvas>
    <?php else: ?>
      <p style="color:#666;text-align:center;padding:40px 0;">Aucune donnée saisie</p>
    <?php endif; ?>
  </article>
</div>

<!-- Tableau détaillé -->
<?php if (count($postes_top) > 0):
    $max_co2 = $postes_top[0]['total'];
?>
<article style="margin-top:20px;overflow-x:auto;">
  <header>📋 Détail de tous les postes saisis</header>
  <table>
    <thead>
      <tr><th>Thème</th><th>Poste</th><th>Quantité</th><th>Unité</th><th>kgCO₂e</th><th>%</th></tr>
    </thead>
    <tbody>
    <?php foreach ($postes_top as $p):
        $pct = $total_global > 0 ? round(($p['total'] / $total_global) * 100, 1) : 0;
        $bar_w = $max_co2 > 0 ? round(($p['total'] / $max_co2) * 80) : 0;
        $color = $pct > 20 ? '#e74c3c' : ($pct > 10 ? '#e67e22' : '#2ecc71');
    ?>
      <tr>
        <td style="color:#888;font-size:.85em;"><?= h($p['theme']) ?></td>
        <td><?= h($p['poste']) ?></td>
        <td><?= $p['quantite'] ?></td>
        <td style="color:#888;"><?= h($p['unite'] ?? '–') ?></td>
        <td><strong><?= $p['total'] ?></strong>
          <span class="bar-inline" style="width:<?= $bar_w ?>px;background:<?= $color ?>;"></span>
        </td>
        <td style="color:<?= $color ?>;"><?= $pct ?>%</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</article>
<?php endif; ?>

<!-- Informations du collège -->
<article style="margin-top:20px;">
  <header>🏫 Informations du Collège</header>
  <form method="POST">
    <input type="hidden" name="action" value="update_college">
    <div class="grid">
      <label>Nom du collège<input type="text" name="nom_college" value="<?= h($college['nom_college']) ?>" required></label>
      <label>Ville<input type="text" name="ville" value="<?= h($college['ville']) ?>"></label>
    </div>
    <div class="grid">
      <label>Nombre d'élèves<input type="number" name="nb_eleves"    value="<?= $nb_e ?>" min="0" required></label>
      <label>Nombre de profs<input type="number" name="nb_profs"     value="<?= $nb_p ?>" min="0" required></label>
      <label>Personnel admin<input type="number" name="nb_personnel" value="<?= $nb_a ?>" min="0" required></label>
      <label>Jours scolaires/an<input type="number" name="nb_jours_an" value="<?= (int)$college['nb_jours_an'] ?>" min="100" max="220" required></label>
    </div>
    <button type="submit">💾 Enregistrer</button>
  </form>
</article>

<!-- Export & Prompt IA -->
<div class="grid" style="margin-top:20px;">

  <article>
    <header>💾 Export CSV</header>
    <p style="font-size:.85em;color:#888;">Toutes les saisies, compatible Excel (séparateur ;, UTF-8 BOM).</p>
    <?php if ($total_global > 0): ?>
      <a href="?action=export_csv" role="button" class="outline">📥 Télécharger le bilan CSV</a>
    <?php else: ?>
      <button disabled class="outline secondary">📥 Aucune donnée à exporter</button>
    <?php endif; ?>
    <hr>
    <p style="font-size:.8em;color:#666;">
      Références :<br>
      <a href="https://base-empreinte.ademe.fr/" target="_blank">Base Empreinte® ADEME</a><br>
      <a href="https://agirpourlatransition.ademe.fr/acteurs-education/enseigner/calculez-bilan-carbone-college-lycee" target="_blank">ADEME — Bilan carbone collège</a><br>
      <a href="https://eduscol.education.fr/1118/la-labellisation-e3d" target="_blank">Eduscol — Label E3D</a>
    </p>
  </article>

  <article>
    <header>🤖 Prompt IA pour les Éco-délégués</header>
    <?php if ($total_global > 0):

        // ── CONSTRUCTION DU PROMPT COMPLET ──────────────────────
        $p  = "=== BILAN CARBONE — " . strtoupper($college['nom_college']) . " ({$college['ville']}) ===\n\n";

        $p .= "EFFECTIFS DU COLLÈGE :\n";
        $p .= "• Élèves : {$nb_e}\n";
        $p .= "• Professeurs : {$nb_p}\n";
        $p .= "• Personnel administratif/divers : {$nb_a}\n";
        $p .= "• Total : {$nb_t} personnes\n";
        $p .= "• Jours scolaires/an : {$college['nb_jours_an']}\n\n";

        $p .= "RÉSULTATS GLOBAUX :\n";
        $p .= "• Émissions totales : {$total_global} kgCO₂e ({$total_t} tCO₂e)\n";
        if ($nb_e > 0) $p .= "• Ratio par élève : {$ratio_eleve} kgCO₂e/élève/an\n";
        if ($nb_t > 0) $p .= "• Ratio par personne : {$ratio_pers} kgCO₂e/personne/an\n";
        $p .= "• Équivalences : " . number_format($bouteilles,0,',',' ') . " bouteilles plastique · {$smartphones} smartphones · {$paris_ny} trajets Paris↔New York\n";
        $p .= "• Complétude du bilan : {$nb_postes_saisis}/{$nb_postes_total} postes renseignés ({$completude}%)\n\n";

        $p .= "RÉPARTITION PAR THÈME :\n";
        foreach ($stats_themes as $th) {
            $pct = $total_global > 0 ? round(($th['total'] / $total_global) * 100, 1) : 0;
            $p  .= "• {$th['theme']} : {$th['total']} kgCO₂e ({$pct}%)\n";
        }

        $p .= "\nDÉTAIL PAR POSTE D'ÉMISSION :\n";
        foreach ($postes_top as $pt) {
            $unite = $pt['unite'] ?? '?';
            $pct2  = $total_global > 0 ? round(($pt['total'] / $total_global) * 100, 1) : 0;
            $p    .= "• [{$pt['theme']}] {$pt['poste']} : {$pt['quantite']} {$unite} → {$pt['total']} kgCO₂e ({$pct2}%)\n";
        }

        $p .= "\n=== DEMANDE D'ANALYSE ===\n";
        $p .= "Tu es un expert en transition écologique scolaire, pédagogue, à l'aise avec des élèves de collège (11-15 ans).\n";
        $p .= "À partir des données ci-dessus, réalise l'analyse complète suivante :\n\n";
        $p .= "1. DIAGNOSTIC — POINTS CHAUDS\n";
        $p .= "   Identifie les 3 postes les plus émetteurs, explique pourquoi ils sont prioritaires et leur part relative.\n\n";
        $p .= "2. PLAN D'ACTION PAR THÈME\n";
        $p .= "   Pour chaque thème présent dans le bilan :\n";
        $p .= "   a) 3 actions concrètes adaptées à un collège (coût, facilité de mise en œuvre, impact estimé)\n";
        $p .= "   b) 1 projet pédagogique impliquant les élèves (atelier, défi, expo, enquête de terrain...)\n";
        $p .= "   c) 1 indicateur de suivi simple et mesurable sur 1 an\n\n";
        $p .= "3. OBJECTIF CHIFFRÉ\n";
        $p .= "   Propose un objectif de réduction réaliste sur 1 an (en kgCO₂e et en %) pour ce collège de {$nb_e} élèves.\n\n";
        $p .= "4. COMPARAISONS & BENCHMARKS\n";
        $p .= "   Compare notre bilan à la moyenne nationale d'un collège français (si disponible).\n\n";
        $p .= "5. MESSAGE MOTIVANT POUR LES ÉCO-DÉLÉGUÉS\n";
        $p .= "   Rédige un texte court (5-7 lignes) encourageant et accessible expliquant l'enjeu à des collégiens.\n\n";
        $p .= "6. PRÉSENTATION POUR LE LABEL E3D NIVEAU 3\n";
        $p .= "   Propose un plan de présentation (titres de diapositives) pour valoriser ce bilan carbone ";
        $p .= "   auprès de l'inspection académique dans le cadre de la candidature au label E3D niveau 3.";
    ?>
    <textarea id="prompt-box" rows="16" readonly><?= h($p) ?></textarea>
    <button onclick="copyPrompt()" class="secondary" style="width:100%;margin-top:8px;">
      📋 Copier le prompt (ChatGPT / NotebookLM / Gemini)
    </button>
    <script>
      function copyPrompt(){
        var el=document.getElementById('prompt-box');
        el.select(); el.setSelectionRange(0,999999);
        navigator.clipboard.writeText(el.value).then(()=>{
          alert('✅ Prompt copié ! Collez-le dans votre outil IA préféré.');
        });
      }
    </script>
    <?php else: ?>
      <p style="color:#666;font-size:.9em;text-align:center;padding:20px 0;">
        Saisissez des données pour générer le prompt d'analyse IA.
      </p>
    <?php endif; ?>
  </article>
</div>

<!-- Scripts graphiques -->
<?php if ($total_global > 0): ?>
<script>
(function(){
  var colors = <?= $colors_json ?>;
  // Donut
  var c1 = document.getElementById('chartDonut');
  if(c1) new Chart(c1,{
    type:'doughnut',
    data:{
      labels: <?= $labels_json ?>,
      datasets:[{data: <?= $data_json ?>, backgroundColor:colors, borderWidth:0}]
    },
    options:{responsive:true,maintainAspectRatio:false,
      plugins:{legend:{position:'bottom',labels:{color:'#ccc',font:{size:11}}}}}
  });
  // Barres top 5
  var top5l = <?= json_encode(array_map(fn($p)=>mb_substr($p['poste'],0,22).'…', array_slice($postes_top,0,5))) ?>;
  var top5d = <?= json_encode(array_map(fn($p)=>floatval($p['total']), array_slice($postes_top,0,5))) ?>;
  var c2 = document.getElementById('chartBars');
  if(c2) new Chart(c2,{
    type:'bar',
    data:{
      labels:top5l,
      datasets:[{label:'kgCO₂e',data:top5d,backgroundColor:colors,borderRadius:4}]
    },
    options:{responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false}},
      scales:{x:{ticks:{color:'#aaa',font:{size:10}}},y:{ticks:{color:'#aaa'}}}}
  });
})();
</script>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════════
// VUE : GESTION DES COMPTES (admin)
// ════════════════════════════════════════════════════════════════
elseif ($vue === 'comptes' && $role === 'admin'):

    $all_users = $pdo->query("SELECT * FROM users ORDER BY role, username")->fetchAll();
    $themes_options = array_merge(['Tous'], array_keys($themes_all));
?>
<h2>👤 Gestion des comptes</h2>

<!-- Créer un compte -->
<article>
  <header>➕ Créer un nouveau compte</header>
  <form method="POST">
    <input type="hidden" name="action" value="create_user">
    <div class="grid">
      <label>Identifiant<input type="text" name="new_username" required placeholder="eleve4"></label>
      <label>Mot de passe<input type="password" name="new_password" required placeholder="••••••••"></label>
      <label>Nom affiché<input type="text" name="new_nom_affiche" placeholder="Éco-délégué Transport"></label>
    </div>
    <div class="grid">
      <label>Rôle
        <select name="new_role">
          <option value="eleve">Élève</option>
          <option value="professeur">Professeur</option>
          <option value="admin">Admin</option>
        </select>
      </label>
      <label>Thème autorisé
        <select name="new_theme">
          <?php foreach ($themes_options as $t): ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <button type="submit">Créer le compte</button>
  </form>
</article>

<!-- Liste des comptes -->
<article style="overflow-x:auto;">
  <header>📋 Comptes existants (<?= count($all_users) ?>)</header>
  <table>
    <thead>
      <tr><th>Identifiant</th><th>Nom affiché</th><th>Rôle</th><th>Thème</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($all_users as $u): ?>
      <tr>
        <td><strong><?= h($u['username']) ?></strong></td>
        <td><?= h($u['nom_affiche']) ?></td>
        <td>
          <span style="font-size:.8em;padding:2px 8px;border-radius:10px;background:<?=
            $u['role']==='admin' ? 'rgba(231,76,60,.2)' :
            ($u['role']==='professeur' ? 'rgba(52,152,219,.2)' : 'rgba(46,204,113,.2)')
          ?>">
            <?= h($u['role']) ?>
          </span>
        </td>
        <td><?= h($u['theme_autorise']) ?></td>
        <td>
          <!-- Modifier thème / nom -->
          <details>
            <summary style="font-size:.85em;cursor:pointer;">✏️ Modifier</summary>
            <form method="POST" style="margin-top:8px;">
              <input type="hidden" name="action" value="update_theme">
              <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
              <input type="text"   name="nom_affiche_u" value="<?= h($u['nom_affiche']) ?>" placeholder="Nom affiché" style="margin-bottom:6px;">
              <select name="new_theme_u" style="margin-bottom:6px;">
                <?php foreach ($themes_options as $t): ?>
                  <option value="<?= h($t) ?>" <?= $u['theme_autorise']===$t?'selected':'' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="outline" style="font-size:.8em;">Enregistrer</button>
            </form>
            <!-- Nouveau mot de passe -->
            <form method="POST" style="margin-top:8px;">
              <input type="hidden"   name="action" value="update_pwd">
              <input type="hidden"   name="uid"    value="<?= $u['id'] ?>">
              <input type="password" name="new_pwd" placeholder="Nouveau mot de passe" required style="margin-bottom:6px;">
              <button type="submit" class="outline secondary" style="font-size:.8em;">🔑 Changer le MDP</button>
            </form>
          </details>
          <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
            <a href="?view=comptes&del_user=<?= $u['id'] ?>"
               style="color:#e74c3c;font-size:.8em;"
               onclick="return confirm('Supprimer <?= h($u['username']) ?> ?')">🗑️ Supprimer</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</article>

<?php endif; // fin vues ?>

</main>
</body>
</html>
