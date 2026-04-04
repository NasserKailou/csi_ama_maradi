#!/usr/bin/env php
<?php
/**
 * Script d'installation – Système CSI AMA Maradi v1.0
 * =====================================================
 * Utilisation : php install.php
 * - Crée la base de données
 * - Applique le schéma SQL
 * - Insère les données de référence
 * - Crée les comptes de test avec hachage BCRYPT
 * - Crée les dossiers nécessaires
 */

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/config/config.php';

// ── Couleurs terminal ─────────────────────────────────────────────────────────
function ok(string $msg): void  { echo "\033[32m[OK]\033[0m  {$msg}\n"; }
function err(string $msg): void { echo "\033[31m[ERR]\033[0m {$msg}\n"; }
function info(string $msg): void{ echo "\033[36m[..]\033[0m  {$msg}\n"; }
function sep(): void            { echo str_repeat('─', 60) . "\n"; }

sep();
echo "\033[1;32m  Système CSI Direct Aid Maradi – Installation v1.0\033[0m\n";
sep();
echo "\n";

// ── 1. Connexion MySQL (sans sélectionner de BDD) ─────────────────────────────
info('Connexion à MySQL...');
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ok('Connexion MySQL établie (' . DB_HOST . ':' . DB_PORT . ')');
} catch (PDOException $e) {
    err('Connexion MySQL impossible : ' . $e->getMessage());
    err('Vérifiez DB_HOST, DB_PORT, DB_USER, DB_PASS dans votre .env');
    exit(1);
}

// ── 2. Création de la BDD ────────────────────────────────────────────────────
info('Création de la base de données `' . DB_NAME . '`...');
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    ok('Base de données `' . DB_NAME . '` créée / vérifiée');
} catch (PDOException $e) {
    err('Erreur création BDD : ' . $e->getMessage());
    exit(1);
}

// ── 3. Application du schéma SQL ─────────────────────────────────────────────
info('Application du schéma SQL...');
$schemaFile = ROOT_PATH . '/database/migrations/001_schema.sql';
if (!file_exists($schemaFile)) {
    err('Fichier schéma introuvable : ' . $schemaFile);
    exit(1);
}

$schema = file_get_contents($schemaFile);
// Diviser sur les ';' en dehors des commentaires
$statements = array_filter(array_map('trim', explode(';', $schema)));
$count = 0;
foreach ($statements as $sql) {
    $stripped = ltrim($sql);
    if (empty($stripped) || str_starts_with($stripped, '--')) continue;
    try {
        $pdo->exec($sql);
        $count++;
    } catch (PDOException $e) {
        // Ignorer les erreurs "already exists"
        if (str_contains($e->getMessage(), 'already exists')) continue;
        err('SQL warning: ' . substr($e->getMessage(), 0, 80));
    }
}
ok("Schéma appliqué ({$count} instructions exécutées)");

// ── 4. Données de référence ───────────────────────────────────────────────────
info('Insertion des données de référence...');
$seedFile = ROOT_PATH . '/database/seeds/001_seed_data.sql';
if (file_exists($seedFile)) {
    $seed = file_get_contents($seedFile);
    $statements = array_filter(array_map('trim', explode(';', $seed)));
    $countSeed = 0;
    foreach ($statements as $sql) {
        $stripped = ltrim($sql);
        if (empty($stripped) || str_starts_with($stripped, '--')) continue;
        try {
            $pdo->exec($sql);
            $countSeed++;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) continue;
        }
    }
    ok("Données de référence insérées ({$countSeed} instructions)");
}

// ── 5. Comptes utilisateurs de test ─────────────────────────────────────────
info('Création des comptes de test...');
echo "\n";

$users = [
    [
        'nom'    => 'Administrateur',
        'prenom' => 'Système',
        'login'  => 'admin',
        'pass'   => 'Admin@CSI2026',
        'role'   => 'admin',
    ],
    [
        'nom'    => 'Diallo',
        'prenom' => 'Mamane',
        'login'  => 'comptable',
        'pass'   => 'Compta@CSI2026',
        'role'   => 'comptable',
    ],
    [
        'nom'    => 'Issoufou',
        'prenom' => 'Abdou',
        'login'  => 'percepteur1',
        'pass'   => 'Percep1@CSI2026',
        'role'   => 'percepteur',
    ],
    [
        'nom'    => 'Moussa',
        'prenom' => 'Halima',
        'login'  => 'percepteur2',
        'pass'   => 'Percep2@CSI2026',
        'role'   => 'percepteur',
    ],
];

$stmt = $pdo->prepare("
    INSERT INTO utilisateurs (nom, prenom, login, password, role, est_actif, whodone)
    VALUES (:nom, :prenom, :login, :password, :role, 1, 0)
    ON DUPLICATE KEY UPDATE
        password  = VALUES(password),
        role      = VALUES(role),
        est_actif = 1
");

foreach ($users as $u) {
    $hash = password_hash($u['pass'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $stmt->execute([
        ':nom'      => $u['nom'],
        ':prenom'   => $u['prenom'],
        ':login'    => $u['login'],
        ':password' => $hash,
        ':role'     => $u['role'],
    ]);
    ok(sprintf("Compte créé : %-14s / %-18s (%s)", $u['login'], $u['pass'], $u['role']));
}

// ── 6. Dossiers nécessaires ───────────────────────────────────────────────────
echo "\n";
info('Création des dossiers...');
$dirs = [
    ROOT_PATH . '/uploads/logos',
    ROOT_PATH . '/uploads/pdf',
    ROOT_PATH . '/vendor',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        ok("Dossier créé : " . str_replace(ROOT_PATH, '.', $dir));
    } else {
        ok("Dossier OK : " . str_replace(ROOT_PATH, '.', $dir));
    }
}

// ── 7. Vérifier logo ─────────────────────────────────────────────────────────
echo "\n";
info('Vérification du logo...');
if (file_exists(ROOT_PATH . '/uploads/logos/logo_csi.png')) {
    ok('Logo trouvé : uploads/logos/logo_csi.png');
} else {
    echo "\033[33m[!!]\033[0m  Logo non trouvé. Déposez votre logo dans uploads/logos/logo_csi.png\n";
}

// ── Récapitulatif ─────────────────────────────────────────────────────────────
echo "\n";
sep();
echo "\033[1;32m  Installation terminée avec succès !\033[0m\n";
sep();
echo "\n";
echo "┌─────────────────┬──────────────────────┬────────────────┐\n";
echo "│ Login           │ Mot de passe          │ Rôle           │\n";
echo "├─────────────────┼──────────────────────┼────────────────┤\n";
foreach ($users as $u) {
    printf("│ %-15s │ %-20s │ %-14s │\n", $u['login'], $u['pass'], $u['role']);
}
echo "└─────────────────┴──────────────────────┴────────────────┘\n\n";
echo "  Accédez à l'application : http://localhost/csi_ama_maradi/\n\n";
