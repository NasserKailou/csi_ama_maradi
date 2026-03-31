#!/usr/bin/env php
<?php
/**
 * Script d'installation – Système CSI AMA Maradi
 * Exécuter une seule fois après avoir configuré .env
 * Usage : php install.php
 */

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/config/config.php';

echo "\n=== Installation du Système CSI AMA Maradi ===\n\n";

// 1. Connexion BDD
try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "[OK] Connexion MySQL établie\n";
} catch (PDOException $e) {
    die("[ERREUR] Connexion MySQL : " . $e->getMessage() . "\n");
}

// 2. Création BDD
$pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `" . DB_NAME . "`");
echo "[OK] Base de données `" . DB_NAME . "` créée/vérifiée\n";

// 3. Migration schema
$schema = file_get_contents(ROOT_PATH . '/database/migrations/001_schema.sql');
$statements = array_filter(array_map('trim', explode(';', $schema)));
foreach ($statements as $sql) {
    if (!empty($sql) && !str_starts_with(ltrim($sql), '--')) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* skip CREATE IF NOT EXISTS errors */ }
    }
}
echo "[OK] Schéma BDD appliqué\n";

// 4. Seed données de base
$seed = file_get_contents(ROOT_PATH . '/database/seeds/001_seed_data.sql');
$statements = array_filter(array_map('trim', explode(';', $seed)));
foreach ($statements as $sql) {
    if (!empty($sql) && !str_starts_with(ltrim($sql), '--')) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
    }
}
echo "[OK] Données de référence insérées\n";

// 5. Création des comptes de test
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
    ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)
");

foreach ($users as $u) {
    $hash = password_hash($u['pass'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt->execute([
        ':nom'      => $u['nom'],
        ':prenom'   => $u['prenom'],
        ':login'    => $u['login'],
        ':password' => $hash,
        ':role'     => $u['role'],
    ]);
    echo "[OK] Compte créé : {$u['login']} / {$u['pass']} ({$u['role']})\n";
}

// 6. Créer les dossiers nécessaires
$dirs = [
    ROOT_PATH . '/uploads/logos',
    ROOT_PATH . '/uploads/pdf',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}
echo "[OK] Dossiers uploads créés\n";

echo "\n=== Installation terminée avec succès ! ===\n";
echo "Accédez à l'application via votre navigateur.\n\n";
echo "COMPTES DE TEST :\n";
echo "┌─────────────┬────────────────┬──────────────┐\n";
echo "│ Login       │ Mot de passe   │ Rôle         │\n";
echo "├─────────────┼────────────────┼──────────────┤\n";
foreach ($users as $u) {
    printf("│ %-11s │ %-14s │ %-12s │\n", $u['login'], $u['pass'], $u['role']);
}
echo "└─────────────┴────────────────┴──────────────┘\n\n";
