-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 01 mai 2026 à 22:24
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `directaid`
--

-- --------------------------------------------------------

--
-- Structure de la table `actes_medicaux`
--

CREATE TABLE `actes_medicaux` (
  `id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(200) NOT NULL,
  `tarif` int(10) UNSIGNED NOT NULL DEFAULT 300,
  `est_gratuit` tinyint(1) NOT NULL DEFAULT 0,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `actes_medicaux`
--

INSERT INTO `actes_medicaux` (`id`, `libelle`, `tarif`, `est_gratuit`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'Consultation Générale', 300, 0, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(2, 'Consultation Prénatale (CPN)', 0, 1, '2026-03-31 20:13:00', 1, 0, '2026-04-23 14:54:22'),
(3, 'Consultation Nourrissons', 0, 1, '2026-03-31 20:13:00', 1, 0, '2026-04-23 14:54:16'),
(4, 'Accouchement', 0, 1, '2026-03-31 20:13:00', 1, 0, '2026-04-23 14:54:01'),
(5, 'Planning Familial', 300, 1, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(6, 'Consultation Pédiatrique', 300, 0, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(7, 'Consultation d\'urgence', 300, 0, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(8, 'Visite Domicile', 500, 0, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(9, 'Renouvellement Ordonnance', 200, 0, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(10, 'TEST MED', 300, 0, '2026-03-31 21:14:14', 1, 0, '2026-03-31 20:14:14'),
(11, 'TEST MED 1', 300, 1, '2026-03-31 21:14:44', 1, 0, '2026-03-31 20:14:44');

-- --------------------------------------------------------

--
-- Structure de la table `approvisionnements_pharmacie`
--

CREATE TABLE `approvisionnements_pharmacie` (
  `id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL,
  `date_appro` date NOT NULL,
  `commentaire` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `approvisionnements_pharmacie`
--

INSERT INTO `approvisionnements_pharmacie` (`id`, `produit_id`, `quantite`, `date_appro`, `commentaire`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 11, 12, '2026-03-31', '', '2026-03-31 21:15:42', 1, 0, '2026-03-31 20:15:42');

-- --------------------------------------------------------

--
-- Structure de la table `config_systeme`
--

CREATE TABLE `config_systeme` (
  `id` int(10) UNSIGNED NOT NULL,
  `cle` varchar(100) NOT NULL,
  `valeur` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `config_systeme`
--

INSERT INTO `config_systeme` (`id`, `cle`, `valeur`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'nom_centre', 'Centre de Santé Intégré AMA Maradi', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(2, 'adresse', 'B.P. XXX, Maradi – République du Niger', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(3, 'telephone', '+227 20 XX XX XX', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(4, 'logo_filename', 'logo_csi.png', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(5, 'pied_de_page', 'Merci de votre visite. Votre santé est notre priorité.', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00');

-- --------------------------------------------------------

--
-- Structure de la table `examens`
--

CREATE TABLE `examens` (
  `id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(200) NOT NULL,
  `cout_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pourcentage_labo` decimal(5,2) NOT NULL DEFAULT 30.00,
  `montant_labo` int(10) UNSIGNED GENERATED ALWAYS AS (round(`cout_total` * `pourcentage_labo` / 100,0)) STORED,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `examens`
--

INSERT INTO `examens` (`id`, `libelle`, `cout_total`, `pourcentage_labo`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'Numération Formule Sanguine (NFS)', 2500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(2, 'Glycémie à jeun', 1500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(3, 'Test de Dépistage Paludisme (TDR)', 1000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(4, 'Bilan Hépatique complet', 4000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(5, 'ECBU (Examen Cytobactériologique)', 2000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(6, 'Créatininémie', 1500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(7, 'Transaminases ALAT/ASAT', 3000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(8, 'Groupage Sanguin + Rhésus', 1500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(9, 'Test de grossesse (β-HCG)', 1000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(10, 'VIH (Screening ELISA)', 1500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(11, 'Goutte épaisse (Plasmodium)', 1000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(12, 'Urée sanguine', 1500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(13, 'Protéinurie de Bence-Jones', 2000, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(14, 'Sérologie Hépatite B (HBs Ag)', 2500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(15, 'Ionogramme (Na, K, Cl)', 3500, 30.00, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(16, 'TEST MED', 20000, 30.00, '2026-03-31 21:13:19', 1, 1, '2026-03-31 20:13:50'),
(17, 'EXAM 11', 1111, 30.00, '2026-03-31 21:15:05', 1, 1, '2026-03-31 20:15:22'),
(18, 'examen NFS', 5000, 10.00, '2026-04-23 15:57:12', 1, 0, '2026-04-23 14:57:12');

-- --------------------------------------------------------

--
-- Structure de la table `inventaire_physique`
--

CREATE TABLE `inventaire_physique` (
  `id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `stock_physique` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stock_theorique` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ecart` int(11) GENERATED ALWAYS AS (`stock_physique` - `stock_theorique`) STORED,
  `commentaire` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lignes_consultation`
--

CREATE TABLE `lignes_consultation` (
  `id` int(10) UNSIGNED NOT NULL,
  `recu_id` int(10) UNSIGNED NOT NULL,
  `acte_id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(200) NOT NULL,
  `tarif` int(10) UNSIGNED NOT NULL DEFAULT 300,
  `est_gratuit` tinyint(1) NOT NULL DEFAULT 0,
  `avec_carnet` tinyint(1) NOT NULL DEFAULT 0,
  `tarif_carnet` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `lignes_consultation`
--

INSERT INTO `lignes_consultation` (`id`, `recu_id`, `acte_id`, `libelle`, `tarif`, `est_gratuit`, `avec_carnet`, `tarif_carnet`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 1, 1, 'Consultation Générale', 300, 1, 0, 0, '2026-03-31 21:41:01', 3, 0, '2026-03-31 20:41:01'),
(2, 2, 1, 'Consultation Générale', 300, 0, 0, 0, '2026-03-31 21:41:30', 3, 0, '2026-03-31 20:41:30'),
(3, 3, 1, 'Consultation Générale', 300, 0, 1, 100, '2026-03-31 21:42:04', 3, 0, '2026-03-31 20:42:04'),
(4, 4, 5, 'Planning Familial', 300, 1, 0, 0, '2026-03-31 21:43:07', 3, 0, '2026-03-31 20:43:07'),
(5, 8, 1, 'Consultation Générale', 300, 0, 1, 100, '2026-04-23 15:30:58', 5, 0, '2026-04-23 14:30:58'),
(6, 11, 1, 'Consultation Générale', 300, 1, 0, 0, '2026-04-23 15:46:19', 5, 0, '2026-04-23 14:46:19'),
(7, 12, 2, 'Consultation Prénatale (CPN)', 300, 1, 0, 0, '2026-04-23 15:48:33', 5, 0, '2026-04-23 14:48:33'),
(8, 14, 1, 'Consultation Générale', 300, 1, 0, 0, '2026-04-25 10:07:45', 5, 0, '2026-04-25 09:07:45'),
(9, 15, 4, 'Accouchement', 0, 1, 0, 0, '2026-05-01 16:43:32', 5, 0, '2026-05-01 15:43:32'),
(10, 16, 1, 'Consultation Générale', 300, 0, 1, 100, '2026-05-01 16:43:57', 5, 0, '2026-05-01 15:43:57'),
(11, 17, 1, 'Consultation Générale', 300, 1, 0, 0, '2026-05-01 18:35:47', 5, 0, '2026-05-01 17:35:47');

-- --------------------------------------------------------

--
-- Structure de la table `lignes_examen`
--

CREATE TABLE `lignes_examen` (
  `id` int(10) UNSIGNED NOT NULL,
  `recu_id` int(10) UNSIGNED NOT NULL,
  `examen_id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(200) NOT NULL,
  `cout_total` int(10) UNSIGNED NOT NULL,
  `pourcentage_labo` decimal(5,2) NOT NULL DEFAULT 30.00,
  `montant_labo` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `lignes_examen`
--

INSERT INTO `lignes_examen` (`id`, `recu_id`, `examen_id`, `libelle`, `cout_total`, `pourcentage_labo`, `montant_labo`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 5, 1, 'Numération Formule Sanguine (NFS)', 2500, 30.00, 750, '2026-03-31 21:45:57', 3, 0, '2026-03-31 20:45:57'),
(2, 5, 5, 'ECBU (Examen Cytobactériologique)', 2000, 30.00, 600, '2026-03-31 21:45:57', 3, 0, '2026-03-31 20:45:57'),
(3, 5, 6, 'Créatininémie', 1500, 30.00, 450, '2026-03-31 21:45:57', 3, 0, '2026-03-31 20:45:57'),
(4, 5, 8, 'Groupage Sanguin + Rhésus', 1500, 30.00, 450, '2026-03-31 21:45:57', 3, 0, '2026-03-31 20:45:57'),
(5, 6, 1, 'Numération Formule Sanguine (NFS)', 2500, 30.00, 750, '2026-03-31 21:47:38', 3, 0, '2026-03-31 20:47:38'),
(6, 6, 9, 'Test de grossesse (β-HCG)', 1000, 30.00, 300, '2026-03-31 21:47:38', 3, 0, '2026-03-31 20:47:38'),
(7, 10, 2, 'Glycémie à jeun', 1500, 30.00, 450, '2026-04-23 15:40:35', 5, 0, '2026-04-23 14:40:35'),
(8, 10, 4, 'Bilan Hépatique complet', 4000, 30.00, 1200, '2026-04-23 15:40:35', 5, 0, '2026-04-23 14:40:35'),
(9, 10, 6, 'Créatininémie', 1500, 30.00, 450, '2026-04-23 15:40:35', 5, 0, '2026-04-23 14:40:35'),
(10, 10, 9, 'Test de grossesse (β-HCG)', 1000, 30.00, 300, '2026-04-23 15:40:35', 5, 0, '2026-04-23 14:40:35'),
(11, 19, 4, 'Bilan Hépatique complet', 4000, 30.00, 0, '2026-05-01 18:36:40', 5, 1, '2026-05-01 20:15:42'),
(12, 19, 5, 'ECBU (Examen Cytobactériologique)', 2000, 30.00, 0, '2026-05-01 18:36:40', 5, 1, '2026-05-01 20:15:42'),
(13, 19, 6, 'Créatininémie', 1500, 30.00, 0, '2026-05-01 18:36:40', 5, 1, '2026-05-01 20:15:42'),
(14, 19, 18, 'examen NFS', 5000, 10.00, 0, '2026-05-01 18:36:40', 5, 1, '2026-05-01 20:15:42'),
(15, 19, 4, 'Bilan Hépatique complet', 0, 30.00, 0, '2026-05-01 21:15:42', 5, 0, '2026-05-01 20:15:42'),
(16, 19, 18, 'examen NFS', 0, 10.00, 0, '2026-05-01 21:15:42', 5, 0, '2026-05-01 20:15:42'),
(17, 20, 4, 'Bilan Hépatique complet', 4000, 30.00, 1200, '2026-05-01 21:21:52', 5, 1, '2026-05-01 20:22:39'),
(18, 20, 5, 'ECBU (Examen Cytobactériologique)', 2000, 30.00, 600, '2026-05-01 21:21:52', 5, 1, '2026-05-01 20:22:39'),
(19, 20, 6, 'Créatininémie', 1500, 30.00, 450, '2026-05-01 21:21:52', 5, 1, '2026-05-01 20:22:39'),
(20, 20, 11, 'Goutte épaisse (Plasmodium)', 1000, 30.00, 300, '2026-05-01 21:21:52', 5, 1, '2026-05-01 20:22:39'),
(21, 20, 18, 'examen NFS', 5000, 10.00, 500, '2026-05-01 21:21:52', 5, 1, '2026-05-01 20:22:39'),
(22, 20, 4, 'Bilan Hépatique complet', 4000, 30.00, 1200, '2026-05-01 21:22:39', 5, 0, '2026-05-01 20:22:39'),
(23, 20, 6, 'Créatininémie', 1500, 30.00, 450, '2026-05-01 21:22:39', 5, 0, '2026-05-01 20:22:39'),
(24, 20, 11, 'Goutte épaisse (Plasmodium)', 1000, 30.00, 300, '2026-05-01 21:22:39', 5, 0, '2026-05-01 20:22:39');

-- --------------------------------------------------------

--
-- Structure de la table `lignes_pharmacie`
--

CREATE TABLE `lignes_pharmacie` (
  `id` int(10) UNSIGNED NOT NULL,
  `recu_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `forme` varchar(50) NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL,
  `prix_unitaire` int(10) UNSIGNED NOT NULL,
  `total_ligne` int(10) UNSIGNED NOT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `lignes_pharmacie`
--

INSERT INTO `lignes_pharmacie` (`id`, `recu_id`, `produit_id`, `nom`, `forme`, `quantite`, `prix_unitaire`, `total_ligne`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(5, 7, 11, 'Albendazole 400mg', 'comprimé', 1, 60, 60, '2026-03-31 22:01:10', 3, 0, '2026-03-31 21:01:10'),
(6, 7, 2, 'Amoxicilline 500mg', 'gélule', 1, 75, 75, '2026-03-31 22:01:10', 3, 0, '2026-03-31 21:01:10'),
(7, 7, 13, 'Cétrizine 10mg', 'comprimé', 1, 35, 35, '2026-03-31 22:01:10', 3, 0, '2026-03-31 21:01:10'),
(8, 9, 11, 'Albendazole 400mg', 'comprimé', 3, 60, 180, '2026-04-23 15:39:40', 5, 0, '2026-04-23 14:39:40'),
(9, 9, 12, 'Ciprofloxacine 500mg', 'comprimé', 1, 120, 120, '2026-04-23 15:39:40', 5, 0, '2026-04-23 14:39:40'),
(10, 9, 3, 'Ibuprofène 400mg', 'comprimé', 1, 50, 50, '2026-04-23 15:39:40', 5, 0, '2026-04-23 14:39:40'),
(11, 9, 7, 'Sirop Toux Enfant', 'sirop', 1, 500, 500, '2026-04-23 15:39:40', 5, 0, '2026-04-23 14:39:40'),
(12, 13, 11, 'Albendazole 400mg', 'comprimé', 2, 60, 120, '2026-04-23 16:45:49', 5, 0, '2026-04-23 15:45:49'),
(13, 13, 2, 'Amoxicilline 500mg', 'gélule', 3, 75, 225, '2026-04-23 16:45:49', 5, 0, '2026-04-23 15:45:49'),
(14, 18, 11, 'Albendazole 400mg', 'comprimé', 23, 60, 1380, '2026-05-01 18:36:16', 5, 1, '2026-05-01 20:15:30'),
(15, 18, 12, 'Ciprofloxacine 500mg', 'comprimé', 12, 120, 1440, '2026-05-01 18:36:16', 5, 1, '2026-05-01 20:15:30'),
(16, 18, 11, 'Albendazole 400mg', 'comprimé', 3, 60, 0, '2026-05-01 21:15:14', 5, 1, '2026-05-01 20:15:30'),
(17, 18, 12, 'Ciprofloxacine 500mg', 'comprimé', 2, 120, 0, '2026-05-01 21:15:14', 5, 1, '2026-05-01 20:15:30'),
(18, 18, 11, 'Albendazole 400mg', 'comprimé', 3, 60, 0, '2026-05-01 21:15:30', 5, 0, '2026-05-01 20:15:30'),
(19, 18, 12, 'Ciprofloxacine 500mg', 'comprimé', 2, 120, 0, '2026-05-01 21:15:30', 5, 0, '2026-05-01 20:15:30'),
(20, 21, 11, 'Albendazole 400mg', 'comprimé', 12, 60, 720, '2026-05-01 21:22:14', 5, 1, '2026-05-01 20:23:08'),
(21, 21, 13, 'Cétrizine 10mg', 'comprimé', 4, 35, 140, '2026-05-01 21:22:14', 5, 1, '2026-05-01 20:23:08'),
(22, 21, 12, 'Ciprofloxacine 500mg', 'comprimé', 5, 120, 600, '2026-05-01 21:22:14', 5, 1, '2026-05-01 20:23:08'),
(23, 21, 11, 'Albendazole 400mg', 'comprimé', 2, 60, 120, '2026-05-01 21:23:08', 5, 0, '2026-05-01 20:23:08'),
(24, 21, 13, 'Cétrizine 10mg', 'comprimé', 1, 35, 35, '2026-05-01 21:23:08', 5, 0, '2026-05-01 20:23:08'),
(25, 21, 12, 'Ciprofloxacine 500mg', 'comprimé', 1, 120, 120, '2026-05-01 21:23:08', 5, 0, '2026-05-01 20:23:08');

-- --------------------------------------------------------

--
-- Structure de la table `modifications_recus`
--

CREATE TABLE `modifications_recus` (
  `id` int(10) UNSIGNED NOT NULL,
  `recu_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type_recu` enum('consultation','examen','pharmacie') NOT NULL,
  `motif` varchar(500) NOT NULL,
  `detail_avant` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail_avant`)),
  `detail_apres` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail_apres`)),
  `whendone` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `modifications_recus`
--

INSERT INTO `modifications_recus` (`id`, `recu_id`, `user_id`, `type_recu`, `motif`, `detail_avant`, `detail_apres`, `whendone`) VALUES
(1, 15, 5, 'consultation', 'Quantité incorrecte', '{\"montant_total\":0,\"montant_encaisse\":0}', '{\"avec_carnet\":1,\"montant_total\":400,\"montant_encaisse\":400}', '2026-05-01 21:05:33'),
(2, 16, 5, 'consultation', 'Erreur de saisie (patient)', '{\"montant_total\":400,\"montant_encaisse\":400}', '{\"avec_carnet\":0,\"montant_total\":300,\"montant_encaisse\":300}', '2026-05-01 21:05:43'),
(3, 18, 5, 'pharmacie', 'Erreur de saisie (patient)', '{\"lignes\":[{\"produit_id\":11,\"quantite\":23,\"nom\":\"Albendazole 400mg\",\"prix_unitaire\":60,\"total_ligne\":1380},{\"produit_id\":12,\"quantite\":12,\"nom\":\"Ciprofloxacine 500mg\",\"prix_unitaire\":120,\"total_ligne\":1440}],\"montant_total\":2820}', '{\"lignes\":[{\"produit_id\":11,\"nom\":\"Albendazole 400mg\",\"forme\":\"comprimé\",\"quantite\":3,\"prix\":60,\"montant\":0},{\"produit_id\":12,\"nom\":\"Ciprofloxacine 500mg\",\"forme\":\"comprimé\",\"quantite\":2,\"prix\":120,\"montant\":0}],\"montant_total\":0}', '2026-05-01 21:15:14'),
(4, 18, 5, 'pharmacie', 'Erreur de saisie (montant)', '{\"lignes\":[{\"produit_id\":11,\"quantite\":3,\"nom\":\"Albendazole 400mg\",\"prix_unitaire\":60,\"total_ligne\":0},{\"produit_id\":12,\"quantite\":2,\"nom\":\"Ciprofloxacine 500mg\",\"prix_unitaire\":120,\"total_ligne\":0}],\"montant_total\":0}', '{\"lignes\":[{\"produit_id\":11,\"nom\":\"Albendazole 400mg\",\"forme\":\"comprimé\",\"quantite\":3,\"prix\":60,\"montant\":0},{\"produit_id\":12,\"nom\":\"Ciprofloxacine 500mg\",\"forme\":\"comprimé\",\"quantite\":2,\"prix\":120,\"montant\":0}],\"montant_total\":0}', '2026-05-01 21:15:30'),
(5, 19, 5, 'examen', 'Erreur produit/examen sélectionné', '{\"examens\":[{\"examen_id\":4,\"libelle\":\"Bilan Hépatique complet\",\"cout_total\":4000},{\"examen_id\":5,\"libelle\":\"ECBU (Examen Cytobactériologique)\",\"cout_total\":2000},{\"examen_id\":6,\"libelle\":\"Créatininémie\",\"cout_total\":1500},{\"examen_id\":18,\"libelle\":\"examen NFS\",\"cout_total\":5000}],\"montant_total\":12500}', '{\"examens\":[{\"id\":4,\"libelle\":\"Bilan Hépatique complet\",\"cout_total\":4000,\"pourcentage_labo\":\"30.00\"},{\"id\":18,\"libelle\":\"examen NFS\",\"cout_total\":5000,\"pourcentage_labo\":\"10.00\"}],\"montant_total\":0}', '2026-05-01 21:15:42'),
(6, 20, 5, 'examen', 'Erreur produit/examen sélectionné', '{\"examens\":[{\"examen_id\":4,\"libelle\":\"Bilan Hépatique complet\",\"cout_total\":4000},{\"examen_id\":5,\"libelle\":\"ECBU (Examen Cytobactériologique)\",\"cout_total\":2000},{\"examen_id\":6,\"libelle\":\"Créatininémie\",\"cout_total\":1500},{\"examen_id\":11,\"libelle\":\"Goutte épaisse (Plasmodium)\",\"cout_total\":1000},{\"examen_id\":18,\"libelle\":\"examen NFS\",\"cout_total\":5000}],\"montant_total\":13500}', '{\"examens\":[{\"id\":4,\"libelle\":\"Bilan Hépatique complet\",\"cout_total\":4000,\"pourcentage_labo\":\"30.00\"},{\"id\":6,\"libelle\":\"Créatininémie\",\"cout_total\":1500,\"pourcentage_labo\":\"30.00\"},{\"id\":11,\"libelle\":\"Goutte épaisse (Plasmodium)\",\"cout_total\":1000,\"pourcentage_labo\":\"30.00\"}],\"montant_total\":6500}', '2026-05-01 21:22:39'),
(7, 21, 5, 'pharmacie', 'Erreur de saisie (montant)', '{\"lignes\":[{\"produit_id\":11,\"quantite\":12,\"nom\":\"Albendazole 400mg\",\"prix_unitaire\":60,\"total_ligne\":720},{\"produit_id\":13,\"quantite\":4,\"nom\":\"Cétrizine 10mg\",\"prix_unitaire\":35,\"total_ligne\":140},{\"produit_id\":12,\"quantite\":5,\"nom\":\"Ciprofloxacine 500mg\",\"prix_unitaire\":120,\"total_ligne\":600}],\"montant_total\":1460}', '{\"lignes\":[{\"produit_id\":11,\"nom\":\"Albendazole 400mg\",\"forme\":\"comprimé\",\"quantite\":2,\"prix\":60,\"montant\":120},{\"produit_id\":13,\"nom\":\"Cétrizine 10mg\",\"forme\":\"comprimé\",\"quantite\":1,\"prix\":35,\"montant\":35},{\"produit_id\":12,\"nom\":\"Ciprofloxacine 500mg\",\"forme\":\"comprimé\",\"quantite\":1,\"prix\":120,\"montant\":120}],\"montant_total\":275}', '2026-05-01 21:23:08');

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(10) UNSIGNED NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `nom` varchar(200) NOT NULL,
  `sexe` enum('M','F') NOT NULL DEFAULT 'M',
  `age` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `provenance` varchar(150) DEFAULT NULL,
  `est_orphelin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = orphelin DirectAid AMA (toujours M, toujours Maradi)',
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `telephone`, `nom`, `sexe`, `age`, `provenance`, `est_orphelin`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, '90413363', 'Fatouma Souleymane', 'M', 35, 'MARADAOUA', 0, '2026-03-31 21:41:01', 5, 0, '2026-05-01 15:43:32'),
(2, '90445566', 'MOUSSA SANDA', 'F', 28, 'KKK', 0, '2026-03-31 21:42:04', 5, 0, '2026-04-23 14:30:58'),
(3, '88665566', 'issaka Ali', 'M', 5, '', 0, '2026-04-23 15:46:19', 5, 0, '2026-04-23 14:46:19'),
(4, '90323344', 'ALI SANDA', 'M', 23, 'ddd', 0, '2026-04-25 10:07:45', 5, 0, '2026-04-25 09:07:45'),
(5, '88998899', 'SOFIANI SANDA', 'M', 12, 'Maradi', 1, '2026-05-01 18:35:47', 5, 0, '2026-05-01 17:35:47');

-- --------------------------------------------------------

--
-- Structure de la table `produits_pharmacie`
--

CREATE TABLE `produits_pharmacie` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `forme` enum('comprimé','sirop','ampoule','gélule','suppositoire','pommade','solution','autre') NOT NULL DEFAULT 'comprimé',
  `prix_unitaire` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stock_initial` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stock_actuel` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `seuil_alerte` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `date_peremption` date DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits_pharmacie`
--

INSERT INTO `produits_pharmacie` (`id`, `nom`, `forme`, `prix_unitaire`, `stock_initial`, `stock_actuel`, `seuil_alerte`, `date_peremption`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'Paracétamol 500mg', 'comprimé', 25, 500, 480, 50, '2027-12-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(2, 'Amoxicilline 500mg', 'gélule', 75, 200, 186, 30, '2026-12-31', '2026-03-31 20:13:00', 5, 0, '2026-04-23 15:45:49'),
(3, 'Ibuprofène 400mg', 'comprimé', 50, 300, 294, 30, '2027-06-30', '2026-03-31 20:13:00', 5, 0, '2026-04-23 14:39:40'),
(4, 'Artésunate 200mg', 'comprimé', 350, 100, 95, 20, '2026-09-30', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(5, 'Sulfate de Zinc 20mg', 'comprimé', 30, 400, 385, 40, '2027-03-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(6, 'Métronidazole 250mg', 'comprimé', 40, 200, 196, 25, '2026-08-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(7, 'Sirop Toux Enfant', 'sirop', 500, 50, 44, 10, '2026-06-30', '2026-03-31 20:13:00', 5, 0, '2026-04-23 14:39:40'),
(8, 'SRO (Sels Réhydratation)', 'solution', 150, 100, 98, 20, '2027-01-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(9, 'Vitamine C 1g', 'comprimé', 20, 300, 298, 30, '2027-12-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(10, 'Fer + Acide Folique', 'comprimé', 15, 400, 394, 50, '2027-06-30', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(11, 'Albendazole 400mg', 'comprimé', 60, 150, 141, 20, '2026-10-31', '2026-03-31 20:13:00', 5, 0, '2026-05-01 20:23:08'),
(12, 'Ciprofloxacine 500mg', 'comprimé', 120, 100, 86, 15, '2026-12-31', '2026-03-31 20:13:00', 5, 0, '2026-05-01 20:23:08'),
(13, 'Cétrizine 10mg', 'comprimé', 35, 200, 193, 20, '2027-09-30', '2026-03-31 20:13:00', 5, 0, '2026-05-01 20:23:08'),
(14, 'Quinine 300mg', 'comprimé', 180, 80, 75, 15, '2026-11-30', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(15, 'Produit périmé (test)', 'comprimé', 50, 10, 5, 10, '2024-01-01', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(16, 'Produit en rupture (test)', 'sirop', 200, 0, 0, 10, '2027-12-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(17, 'Stock faible (test)', 'ampoule', 450, 15, 8, 10, '2026-07-31', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(18, 'EFERALGAN 1G', 'sirop', 200, 500, 500, 20, '2027-04-23', '2026-04-23 15:59:01', 1, 0, '2026-04-23 14:59:01');

-- --------------------------------------------------------

--
-- Structure de la table `recus`
--

CREATE TABLE `recus` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_recu` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `type_recu` enum('consultation','examen','pharmacie') NOT NULL,
  `type_patient` enum('normal','orphelin','acte_gratuit') NOT NULL DEFAULT 'normal',
  `montant_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_encaisse` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recus`
--

INSERT INTO `recus` (`id`, `numero_recu`, `patient_id`, `type_recu`, `type_patient`, `montant_total`, `montant_encaisse`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 1, 1, 'consultation', 'orphelin', 300, 0, '2026-03-31 21:41:01', 3, 0, '2026-03-31 20:41:01'),
(2, 2, 1, 'consultation', 'normal', 300, 300, '2026-03-31 21:41:30', 3, 0, '2026-03-31 20:41:30'),
(3, 3, 2, 'consultation', 'normal', 400, 400, '2026-03-31 21:42:04', 3, 0, '2026-03-31 20:42:04'),
(4, 4, 2, 'consultation', 'acte_gratuit', 300, 0, '2026-03-31 21:43:07', 3, 0, '2026-03-31 20:43:07'),
(5, 5, 1, 'examen', 'normal', 7500, 7500, '2026-03-31 21:45:57', 3, 0, '2026-03-31 20:45:57'),
(6, 6, 2, 'examen', 'normal', 3500, 3500, '2026-03-31 21:47:38', 3, 0, '2026-03-31 20:47:38'),
(7, 7, 2, 'pharmacie', 'normal', 170, 170, '2026-03-31 22:01:10', 3, 0, '2026-03-31 21:01:10'),
(8, 8, 2, 'consultation', 'normal', 400, 400, '2026-04-23 15:30:58', 5, 0, '2026-04-23 14:30:58'),
(9, 9, 2, 'pharmacie', 'normal', 850, 850, '2026-04-23 15:39:40', 5, 0, '2026-04-23 14:39:40'),
(10, 10, 2, 'examen', 'normal', 8000, 8000, '2026-04-23 15:40:35', 5, 0, '2026-04-23 14:40:35'),
(11, 11, 3, 'consultation', 'orphelin', 300, 0, '2026-04-23 15:46:19', 5, 0, '2026-04-23 14:46:19'),
(12, 12, 1, 'consultation', 'acte_gratuit', 300, 0, '2026-04-23 15:48:33', 5, 0, '2026-04-23 14:48:33'),
(13, 13, 3, 'pharmacie', 'normal', 345, 345, '2026-04-23 16:45:49', 5, 0, '2026-04-23 15:45:49'),
(14, 14, 4, 'consultation', 'orphelin', 300, 0, '2026-04-25 10:07:45', 5, 0, '2026-04-25 09:07:45'),
(15, 15, 1, 'consultation', 'acte_gratuit', 400, 400, '2026-05-01 16:43:32', 5, 0, '2026-05-01 20:05:33'),
(16, 16, 2, 'consultation', 'normal', 300, 300, '2026-05-01 16:43:57', 5, 0, '2026-05-01 20:05:43'),
(17, 17, 5, 'consultation', 'orphelin', 300, 0, '2026-05-01 18:35:47', 5, 0, '2026-05-01 17:35:47'),
(18, 18, 5, 'pharmacie', 'orphelin', 0, 0, '2026-05-01 18:36:16', 5, 0, '2026-05-01 20:15:30'),
(19, 19, 5, 'examen', 'orphelin', 0, 0, '2026-05-01 18:36:40', 5, 0, '2026-05-01 20:15:42'),
(20, 20, 2, 'examen', 'normal', 6500, 6500, '2026-05-01 21:21:52', 5, 0, '2026-05-01 20:22:39'),
(21, 21, 1, 'pharmacie', 'acte_gratuit', 275, 275, '2026-05-01 21:22:14', 5, 0, '2026-05-01 20:23:08');

-- --------------------------------------------------------

--
-- Structure de la table `types_carnets`
--

CREATE TABLE `types_carnets` (
  `id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `tarif` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `est_gratuit` tinyint(1) NOT NULL DEFAULT 0,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `types_carnets`
--

INSERT INTO `types_carnets` (`id`, `libelle`, `tarif`, `est_gratuit`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'Carnet de Soins', 100, 0, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(2, 'Carnet de Santé', 0, 1, '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `login` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','comptable','percepteur') NOT NULL DEFAULT 'percepteur',
  `est_actif` tinyint(1) NOT NULL DEFAULT 1,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `login`, `password`, `role`, `est_actif`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'Admin', 'CSI', 'admin', '$2b$12$ZFL8oJS1PcyaLRMinoz/Be0wZjHso46Am/HNHTYX4kkrw4Z/3oR9.', 'admin', 1, '2026-03-31 20:27:06', 1, 0, '2026-03-31 19:27:06'),
(2, 'Comptable', 'CSI', 'comptable', '$2b$12$evFhfalrJbYhLak4.vpkzO9/FavYsnUw1axvxvPZ1bAXWxw8xKxfC', 'comptable', 1, '2026-03-31 20:27:06', 1, 0, '2026-03-31 19:27:06'),
(3, 'Percepteur', 'Un', 'percepteur1', '$2b$12$QsbqT1bajUMQsaVZkv60HOV9vBQQ.dv4sllUTk5zdPepORv6iyerm', 'percepteur', 1, '2026-03-31 20:27:06', 1, 0, '2026-03-31 19:27:06'),
(4, 'Percepteur', 'Deux', 'percepteur2', '$2b$12$VG5tf3CFIoF4S/a7XxthYe28DBfjcAmdp7nqEPLeOmnyL3WVlcsmi', 'percepteur', 1, '2026-03-31 20:27:06', 1, 0, '2026-03-31 19:27:06'),
(5, 'Abdoul Nasser', 'kailou', 'nasser', '$2y$12$eGtaHNHmDvgw9fbvf/HoEeKXEFxiVffbt0UtByTVX1IS.MS.eokyO', 'percepteur', 1, '2026-04-23 15:26:57', 1, 0, '2026-04-23 14:26:57');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `actes_medicaux`
--
ALTER TABLE `actes_medicaux`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `approvisionnements_pharmacie`
--
ALTER TABLE `approvisionnements_pharmacie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appro_produit` (`produit_id`);

--
-- Index pour la table `config_systeme`
--
ALTER TABLE `config_systeme`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`);

--
-- Index pour la table `examens`
--
ALTER TABLE `examens`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `inventaire_physique`
--
ALTER TABLE `inventaire_physique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inv_produit` (`produit_id`);

--
-- Index pour la table `lignes_consultation`
--
ALTER TABLE `lignes_consultation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lc_recu` (`recu_id`),
  ADD KEY `fk_lc_acte` (`acte_id`);

--
-- Index pour la table `lignes_examen`
--
ALTER TABLE `lignes_examen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_le_recu` (`recu_id`),
  ADD KEY `fk_le_examen` (`examen_id`);

--
-- Index pour la table `lignes_pharmacie`
--
ALTER TABLE `lignes_pharmacie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lp_recu` (`recu_id`),
  ADD KEY `fk_lp_produit` (`produit_id`);

--
-- Index pour la table `modifications_recus`
--
ALTER TABLE `modifications_recus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modif_recu` (`recu_id`),
  ADD KEY `idx_modif_user` (`user_id`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_patients_orphelin` (`est_orphelin`);

--
-- Index pour la table `produits_pharmacie`
--
ALTER TABLE `produits_pharmacie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock` (`stock_actuel`),
  ADD KEY `idx_peremption` (`date_peremption`);

--
-- Index pour la table `recus`
--
ALTER TABLE `recus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_recu` (`numero_recu`),
  ADD KEY `idx_date` (`whendone`),
  ADD KEY `idx_percepteur` (`whodone`),
  ADD KEY `idx_type_recu` (`type_recu`),
  ADD KEY `idx_patient` (`patient_id`);

--
-- Index pour la table `types_carnets`
--
ALTER TABLE `types_carnets`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `idx_login` (`login`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `actes_medicaux`
--
ALTER TABLE `actes_medicaux`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `approvisionnements_pharmacie`
--
ALTER TABLE `approvisionnements_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `config_systeme`
--
ALTER TABLE `config_systeme`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `examens`
--
ALTER TABLE `examens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `inventaire_physique`
--
ALTER TABLE `inventaire_physique`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lignes_consultation`
--
ALTER TABLE `lignes_consultation`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `lignes_examen`
--
ALTER TABLE `lignes_examen`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `lignes_pharmacie`
--
ALTER TABLE `lignes_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `modifications_recus`
--
ALTER TABLE `modifications_recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `produits_pharmacie`
--
ALTER TABLE `produits_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `recus`
--
ALTER TABLE `recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `types_carnets`
--
ALTER TABLE `types_carnets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `approvisionnements_pharmacie`
--
ALTER TABLE `approvisionnements_pharmacie`
  ADD CONSTRAINT `fk_appro_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie` (`id`);

--
-- Contraintes pour la table `inventaire_physique`
--
ALTER TABLE `inventaire_physique`
  ADD CONSTRAINT `fk_inv_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie` (`id`);

--
-- Contraintes pour la table `lignes_consultation`
--
ALTER TABLE `lignes_consultation`
  ADD CONSTRAINT `fk_lc_acte` FOREIGN KEY (`acte_id`) REFERENCES `actes_medicaux` (`id`),
  ADD CONSTRAINT `fk_lc_recu` FOREIGN KEY (`recu_id`) REFERENCES `recus` (`id`);

--
-- Contraintes pour la table `lignes_examen`
--
ALTER TABLE `lignes_examen`
  ADD CONSTRAINT `fk_le_examen` FOREIGN KEY (`examen_id`) REFERENCES `examens` (`id`),
  ADD CONSTRAINT `fk_le_recu` FOREIGN KEY (`recu_id`) REFERENCES `recus` (`id`);

--
-- Contraintes pour la table `lignes_pharmacie`
--
ALTER TABLE `lignes_pharmacie`
  ADD CONSTRAINT `fk_lp_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie` (`id`),
  ADD CONSTRAINT `fk_lp_recu` FOREIGN KEY (`recu_id`) REFERENCES `recus` (`id`);

--
-- Contraintes pour la table `modifications_recus`
--
ALTER TABLE `modifications_recus`
  ADD CONSTRAINT `modifications_recus_ibfk_1` FOREIGN KEY (`recu_id`) REFERENCES `recus` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recus`
--
ALTER TABLE `recus`
  ADD CONSTRAINT `fk_recu_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
