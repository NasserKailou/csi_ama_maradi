<?php
/**
 * Génération État de Paie Laborantin – PDF
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin', 'comptable');

$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin']   ?? date('Y-m-d');

$pdo = Database::getInstance();
require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
$pdf     = new PdfGenerator($pdo);
$pdfFile = $pdf->generateEtatLabo($dateDebut, $dateFin);

// Afficher le fichier HTML pour impression navigateur
header('Content-Type: text/html; charset=utf-8');
echo file_get_contents($pdfFile);
