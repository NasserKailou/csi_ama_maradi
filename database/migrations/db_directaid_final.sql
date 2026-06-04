-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 21 mai 2026 à 02:36
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
(1, 'consultation CPN', 0, 1, '2026-05-03 14:10:55', 1, 0, '2026-05-03 13:10:55');

-- --------------------------------------------------------

--
-- Structure de la table `annulations_recus`
--

CREATE TABLE `annulations_recus` (
  `id` int(10) UNSIGNED NOT NULL,
  `recu_id` int(10) UNSIGNED NOT NULL,
  `type_recu` enum('consultation','examen','pharmacie') NOT NULL,
  `motif` varchar(500) NOT NULL DEFAULT '',
  `montant_annule` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `annulations_recus`
--

INSERT INTO `annulations_recus` (`id`, `recu_id`, `type_recu`, `motif`, `montant_annule`, `whendone`, `whodone`) VALUES
(1, 31, 'pharmacie', 'rrrr', 3500, '2026-05-18 12:15:28', 1);

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
(1, 'nom_centre', 'Centre de Santé Intégré AMA Maradi', '2026-03-31 20:13:00', 1, 0, '2026-05-03 14:03:26'),
(2, 'adresse', '', '2026-03-31 20:13:00', 1, 0, '2026-05-03 14:12:10'),
(3, 'telephone', '+227 78 66 95 89', '2026-03-31 20:13:00', 1, 0, '2026-05-03 14:12:10'),
(4, 'logo_filename', 'logo_csi.png', '2026-03-31 20:13:00', 0, 0, '2026-03-31 19:13:00'),
(5, 'pied_de_page', 'Merci de votre visite. Votre santé est notre priorité.', '2026-03-31 20:13:00', 1, 0, '2026-05-03 14:03:27'),
(6, 'logo_ministere', 'logo_ministere.png', '2026-05-03 01:15:54', 0, 0, '2026-05-03 00:15:54'),
(15, 'stock_carnets', '591', '2026-05-16 17:43:40', 1, 0, '2026-05-20 11:32:05'),
(16, 'seuil_alerte_carnets', '50', '2026-05-16 17:43:40', 1, 0, '2026-05-16 17:06:57'),
(17, 'stock_fiches_ag', '296', '2026-05-16 17:43:41', 1, 0, '2026-05-20 10:48:15'),
(18, 'seuil_alerte_fiches_ag', '10', '2026-05-16 17:43:41', 1, 0, '2026-05-16 16:43:41'),
(41, 'stock_carnets_soins', '0', '2026-05-20 18:23:22', 1, 0, '2026-05-20 17:23:22'),
(42, 'seuil_alerte_carnets_soins', '10', '2026-05-20 18:23:22', 1, 0, '2026-05-20 17:23:22'),
(43, 'stock_carnets_sante', '0', '2026-05-20 18:23:22', 1, 0, '2026-05-20 17:23:22'),
(44, 'seuil_alerte_carnets_sante', '10', '2026-05-20 18:23:22', 1, 0, '2026-05-20 17:23:22');

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
(1, 'Antigénémie HBS', 1200, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(2, 'Albuminurie', 700, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(3, 'Test Syphilis BW', 700, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(4, 'Créatininémie', 1500, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(5, 'Culot urinaire', 900, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(6, 'Dosage hémoglobine', 900, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(7, 'Glucosurie', 800, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(8, 'Glycémie', 1100, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(9, 'Goutte Epaisse', 400, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(10, 'Groupe Sanguin/Rhésus', 1300, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(11, 'Test rapide Hépatite Virale C (VHC)', 700, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(12, 'NFS', 1700, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(13, 'Protéinurie', 2100, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(14, 'Selles KOPA', 500, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(15, 'Test de Grossesse', 1100, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(16, 'Test d\'Emmel', 700, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(17, 'Azotémie', 1500, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50'),
(18, 'Widal', 1100, 0.00, '2026-05-03 01:36:50', 0, 0, '2026-05-02 23:36:50');

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
  `acte_id` int(10) UNSIGNED DEFAULT NULL,
  `type_ligne` enum('consultation','observation','carnet','redevance','acte_gratuit','autre') NOT NULL DEFAULT 'consultation',
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

INSERT INTO `lignes_consultation` (`id`, `recu_id`, `acte_id`, `type_ligne`, `libelle`, `tarif`, `est_gratuit`, `avec_carnet`, `tarif_carnet`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(4, 16, NULL, 'observation', 'Mise en observation', 1000, 0, 0, 0, '2026-05-03 22:37:38', 1, 0, '2026-05-03 21:37:38'),
(5, 17, NULL, 'consultation', 'Consultation', 300, 0, 0, 0, '2026-05-03 22:38:09', 1, 0, '2026-05-03 21:38:09'),
(6, 18, NULL, 'consultation', 'Consultation', 300, 0, 1, 100, '2026-05-03 22:38:19', 1, 0, '2026-05-03 21:38:19'),
(7, 19, 1, 'consultation', 'consultation CPN', 0, 1, 2, 400, '2026-05-03 22:38:47', 1, 0, '2026-05-03 21:38:47'),
(8, 20, NULL, 'consultation', 'Consultation', 300, 1, 0, 0, '2026-05-03 22:39:17', 1, 0, '2026-05-03 21:39:17'),
(9, 21, NULL, 'consultation', 'Consultation', 300, 0, 1, 100, '2026-05-03 23:04:08', 1, 0, '2026-05-03 22:04:08'),
(10, 21, NULL, 'redevance', 'Redevance ministère (âge > 5 ans)', 100, 0, 0, 0, '2026-05-03 23:04:08', 1, 0, '2026-05-03 22:04:08'),
(11, 22, 1, 'consultation', 'consultation CPN', 0, 1, 1, 100, '2026-05-03 23:15:57', 1, 0, '2026-05-03 22:15:57'),
(12, 23, NULL, 'consultation', 'Consultation', 300, 0, 0, 0, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33'),
(13, 23, NULL, 'redevance', 'Redevance ministère (âge > 5 ans)', 100, 0, 0, 0, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33'),
(14, 24, 1, 'consultation', 'consultation CPN', 0, 1, 2, 400, '2026-05-16 17:52:48', 1, 0, '2026-05-16 16:52:48'),
(15, 25, 1, 'consultation', 'consultation CPN', 0, 1, 2, 400, '2026-05-16 18:03:27', 1, 0, '2026-05-16 17:03:27'),
(16, 29, NULL, 'consultation', 'Consultation', 300, 0, 1, 100, '2026-05-16 18:49:51', 5, 0, '2026-05-16 17:49:51'),
(17, 30, 1, 'consultation', 'consultation CPN', 0, 1, 0, 0, '2026-05-18 09:40:40', 5, 0, '2026-05-18 11:15:09'),
(18, 33, NULL, 'consultation', 'Consultation', 300, 1, 0, 0, '2026-05-18 09:41:26', 5, 0, '2026-05-18 08:41:26'),
(19, 38, 1, 'consultation', 'consultation CPN', 0, 1, 2, 400, '2026-05-18 17:22:09', 5, 0, '2026-05-18 16:22:09'),
(20, 40, NULL, 'consultation', 'Consultation', 300, 0, 1, 100, '2026-05-20 09:18:51', 5, 0, '2026-05-20 08:18:51'),
(21, 40, NULL, 'redevance', 'Redevance ministère (âge > 5 ans)', 100, 0, 0, 0, '2026-05-20 09:18:51', 5, 0, '2026-05-20 08:18:51'),
(22, 41, NULL, 'consultation', 'Consultation', 300, 1, 0, 0, '2026-05-20 09:19:09', 5, 0, '2026-05-20 08:19:09'),
(23, 42, 1, 'consultation', 'consultation CPN', 0, 1, 2, 400, '2026-05-20 09:19:44', 5, 0, '2026-05-20 08:19:44'),
(24, 49, 1, 'consultation', 'consultation CPN', 0, 1, 0, 0, '2026-05-20 11:47:51', 1, 0, '2026-05-20 10:47:51'),
(25, 50, 1, 'consultation', 'consultation CPN', 0, 1, 2, 400, '2026-05-20 11:48:15', 1, 0, '2026-05-20 10:48:15'),
(26, 51, 1, 'consultation', 'consultation CPN', 0, 1, 0, 0, '2026-05-20 12:15:10', 1, 0, '2026-05-20 11:15:10'),
(27, 52, 1, 'consultation', 'consultation CPN', 0, 1, 0, 0, '2026-05-20 12:27:21', 1, 0, '2026-05-20 11:27:21'),
(28, 53, 1, 'consultation', 'consultation CPN', 0, 1, 3, 300, '2026-05-20 12:32:05', 1, 0, '2026-05-20 11:32:05'),
(29, 54, NULL, 'consultation', 'Consultation', 300, 1, 0, 0, '2026-05-20 17:28:27', 5, 0, '2026-05-20 16:28:27'),
(30, 55, NULL, 'consultation', 'Consultation', 300, 0, 0, 0, '2026-05-20 17:28:49', 5, 0, '2026-05-20 16:28:49'),
(31, 55, NULL, 'redevance', 'Redevance ministère (âge > 5 ans)', 100, 0, 0, 0, '2026-05-20 17:28:49', 5, 0, '2026-05-20 16:28:49'),
(32, 56, NULL, 'consultation', 'Consultation', 300, 1, 0, 0, '2026-05-20 17:35:09', 5, 0, '2026-05-20 16:35:09');

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
(1, 28, 4, 'Créatininémie', 1500, 0.00, 0, '2026-05-16 18:46:14', 1, 0, '2026-05-16 17:46:14'),
(2, 28, 6, 'Dosage hémoglobine', 900, 0.00, 0, '2026-05-16 18:46:14', 1, 0, '2026-05-16 17:46:14'),
(3, 28, 8, 'Glycémie', 1100, 0.00, 0, '2026-05-16 18:46:14', 1, 0, '2026-05-16 17:46:14'),
(4, 28, 10, 'Groupe Sanguin/Rhésus', 1300, 0.00, 0, '2026-05-16 18:46:14', 1, 0, '2026-05-16 17:46:14'),
(5, 32, 2, 'Albuminurie', 700, 0.00, 0, '2026-05-18 09:41:08', 5, 0, '2026-05-18 08:41:08'),
(6, 32, 5, 'Culot urinaire', 900, 0.00, 0, '2026-05-18 09:41:08', 5, 0, '2026-05-18 08:41:08'),
(7, 32, 17, 'Azotémie', 1500, 0.00, 0, '2026-05-18 09:41:08', 5, 0, '2026-05-18 08:41:08'),
(8, 34, 5, 'Culot urinaire', 900, 0.00, 0, '2026-05-18 09:41:36', 5, 0, '2026-05-18 08:41:36'),
(9, 34, 7, 'Glucosurie', 800, 0.00, 0, '2026-05-18 09:41:36', 5, 0, '2026-05-18 08:41:36'),
(10, 34, 9, 'Goutte Epaisse', 400, 0.00, 0, '2026-05-18 09:41:36', 5, 0, '2026-05-18 08:41:36'),
(11, 46, 1, 'Antigénémie HBS', 1200, 0.00, 0, '2026-05-20 09:20:53', 5, 0, '2026-05-20 08:20:53'),
(12, 46, 4, 'Créatininémie', 1500, 0.00, 0, '2026-05-20 09:20:53', 5, 0, '2026-05-20 08:20:53'),
(13, 46, 6, 'Dosage hémoglobine', 900, 0.00, 0, '2026-05-20 09:20:53', 5, 0, '2026-05-20 08:20:53'),
(14, 47, 8, 'Glycémie', 1100, 0.00, 0, '2026-05-20 09:20:59', 5, 0, '2026-05-20 08:20:59'),
(15, 47, 10, 'Groupe Sanguin/Rhésus', 1300, 0.00, 0, '2026-05-20 09:20:59', 5, 0, '2026-05-20 08:20:59'),
(16, 47, 13, 'Protéinurie', 2100, 0.00, 0, '2026-05-20 09:20:59', 5, 0, '2026-05-20 08:20:59'),
(17, 48, 7, 'Glucosurie', 800, 0.00, 0, '2026-05-20 09:21:09', 5, 0, '2026-05-20 08:21:09'),
(18, 48, 9, 'Goutte Epaisse', 400, 0.00, 0, '2026-05-20 09:21:09', 5, 0, '2026-05-20 08:21:09'),
(19, 48, 11, 'Test rapide Hépatite Virale C (VHC)', 700, 0.00, 0, '2026-05-20 09:21:09', 5, 0, '2026-05-20 08:21:09'),
(20, 48, 12, 'NFS', 1700, 0.00, 0, '2026-05-20 09:21:09', 5, 0, '2026-05-20 08:21:09'),
(21, 48, 16, 'Test d\'Emmel', 700, 0.00, 0, '2026-05-20 09:21:09', 5, 0, '2026-05-20 08:21:09');

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
(1, 27, 4, 'Amoxi 1g', 'comprimé', 7, 200, 1400, '2026-05-16 18:04:12', 1, 0, '2026-05-16 17:04:58'),
(2, 27, 1, 'Amoxi sp 125 mg', 'sirop', 9, 650, 5850, '2026-05-16 18:04:12', 1, 0, '2026-05-16 17:04:58'),
(3, 27, 4, 'Amoxi 1g', 'comprimé', 8, 200, 1600, '2026-05-16 18:04:58', 1, 0, '2026-05-16 17:04:58'),
(4, 27, 1, 'Amoxi sp 125 mg', 'sirop', 8, 650, 5200, '2026-05-16 18:04:58', 1, 0, '2026-05-16 17:04:58'),
(5, 31, 4, 'Amoxi 1g', 'comprimé', 4, 200, 800, '2026-05-18 09:40:57', 5, 0, '2026-05-18 08:40:57'),
(6, 31, 1, 'Amoxi sp 125 mg', 'sirop', 3, 650, 1950, '2026-05-18 09:40:57', 5, 0, '2026-05-18 08:40:57'),
(7, 31, 8, 'B complexe', 'comprimé', 3, 250, 750, '2026-05-18 09:40:57', 5, 0, '2026-05-18 08:40:57'),
(8, 31, 42, 'PILLULE CONTRACEPTIVE', 'comprimé', 3, 0, 0, '2026-05-18 09:40:57', 5, 0, '2026-05-18 08:40:57'),
(9, 35, 4, 'Amoxi 1g', 'comprimé', 2, 200, 400, '2026-05-18 09:41:50', 5, 0, '2026-05-18 08:41:50'),
(10, 35, 1, 'Amoxi sp 125 mg', 'sirop', 1, 650, 650, '2026-05-18 09:41:50', 5, 0, '2026-05-18 08:41:50'),
(11, 35, 8, 'B complexe', 'comprimé', 4, 250, 1000, '2026-05-18 09:41:50', 5, 0, '2026-05-18 08:41:50'),
(12, 36, 4, 'Amoxi 1g', 'comprimé', 6, 200, 1200, '2026-05-18 12:42:02', 1, 0, '2026-05-18 11:42:02'),
(13, 36, 1, 'Amoxi sp 125 mg', 'sirop', 6, 650, 3900, '2026-05-18 12:42:02', 1, 0, '2026-05-18 11:42:02'),
(14, 36, 8, 'B complexe', 'comprimé', 5, 250, 1250, '2026-05-18 12:42:02', 1, 0, '2026-05-18 11:42:02'),
(15, 37, 4, 'Amoxi 1g', 'comprimé', 2, 200, 400, '2026-05-18 12:42:46', 5, 0, '2026-05-18 11:42:46'),
(16, 37, 8, 'B complexe', 'comprimé', 2, 250, 500, '2026-05-18 12:42:46', 5, 0, '2026-05-18 11:42:46'),
(17, 37, 42, 'PILLULE CONTRACEPTIVE', 'comprimé', 2, 0, 0, '2026-05-18 12:42:46', 5, 0, '2026-05-18 11:42:46'),
(18, 39, 4, 'Amoxi 1g', 'comprimé', 4, 200, 800, '2026-05-18 17:22:31', 5, 0, '2026-05-18 16:22:31'),
(19, 39, 1, 'Amoxi sp 125 mg', 'sirop', 2, 650, 1300, '2026-05-18 17:22:31', 5, 0, '2026-05-18 16:22:31'),
(20, 39, 8, 'B complexe', 'comprimé', 3, 250, 750, '2026-05-18 17:22:31', 5, 0, '2026-05-18 16:22:31'),
(21, 43, 4, 'Amoxi 1g', 'comprimé', 3, 200, 600, '2026-05-20 09:20:01', 5, 0, '2026-05-20 08:20:01'),
(22, 43, 1, 'Amoxi sp 125 mg', 'sirop', 3, 650, 1950, '2026-05-20 09:20:01', 5, 0, '2026-05-20 08:20:01'),
(23, 43, 8, 'B complexe', 'comprimé', 3, 250, 750, '2026-05-20 09:20:01', 5, 0, '2026-05-20 08:20:01'),
(24, 44, 4, 'Amoxi 1g', 'comprimé', 5, 200, 1000, '2026-05-20 09:20:25', 5, 0, '2026-05-20 08:20:25'),
(25, 44, 1, 'Amoxi sp 125 mg', 'sirop', 5, 650, 3250, '2026-05-20 09:20:25', 5, 0, '2026-05-20 08:20:25'),
(26, 44, 8, 'B complexe', 'comprimé', 5, 250, 1250, '2026-05-20 09:20:25', 5, 0, '2026-05-20 08:20:25'),
(27, 44, 42, 'PILLULE CONTRACEPTIVE', 'comprimé', 5, 0, 0, '2026-05-20 09:20:25', 5, 0, '2026-05-20 08:20:25'),
(28, 45, 4, 'Amoxi 1g', 'comprimé', 2, 200, 400, '2026-05-20 09:20:39', 5, 0, '2026-05-20 08:20:39'),
(29, 45, 1, 'Amoxi sp 125 mg', 'sirop', 2, 650, 1300, '2026-05-20 09:20:39', 5, 0, '2026-05-20 08:20:39'),
(30, 45, 8, 'B complexe', 'comprimé', 22, 250, 5500, '2026-05-20 09:20:39', 5, 0, '2026-05-20 08:20:39');

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
(1, 30, 1, 'consultation', 'Erreur de saisie (montant)', '{\"montant_total\":400,\"montant_encaisse\":400}', '{\"avec_carnet\":0,\"montant_total\":300,\"montant_encaisse\":300}', '2026-05-18 12:15:09');

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_carnets`
--

CREATE TABLE `mouvements_carnets` (
  `id` int(10) UNSIGNED NOT NULL,
  `type_mvt` enum('initialisation','sortie','correction') NOT NULL DEFAULT 'sortie',
  `type_carnet` enum('soins','sante') NOT NULL DEFAULT 'soins',
  `quantite` int(11) NOT NULL COMMENT 'Négatif pour sorties',
  `stock_avant` int(11) NOT NULL DEFAULT 0,
  `stock_apres` int(11) NOT NULL DEFAULT 0,
  `recu_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu consultation lié',
  `commentaire` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mouvements_carnets`
--

INSERT INTO `mouvements_carnets` (`id`, `type_mvt`, `type_carnet`, `quantite`, `stock_avant`, `stock_apres`, `recu_id`, `commentaire`, `whendone`, `whodone`) VALUES
(1, 'initialisation', 'soins', 500, 0, 500, NULL, 'Réapprovisionnement carnets', '2026-05-16 17:48:16', 1),
(2, 'sortie', 'sante', -1, 500, 499, 24, 'Carnet acte gratuit (+fiche) #9', '2026-05-16 17:52:48', 1),
(3, 'sortie', 'sante', -1, 499, 498, 25, 'Carnet acte gratuit (+fiche) #10', '2026-05-16 18:03:27', 1),
(4, 'initialisation', 'soins', 100, 498, 598, NULL, 'Réapprovisionnement carnets', '2026-05-16 18:06:57', 1),
(5, 'sortie', 'soins', -1, 598, 597, 29, 'Carnet consultation #14', '2026-05-16 18:49:51', 5),
(6, 'sortie', 'sante', -1, 597, 596, 30, 'Carnet acte gratuit (+fiche) #15', '2026-05-18 09:40:40', 5),
(7, 'sortie', 'sante', -1, 596, 595, 38, 'Carnet acte gratuit (+fiche) #23', '2026-05-18 17:22:09', 5),
(8, 'sortie', 'soins', -1, 595, 594, 40, 'Carnet consultation #25', '2026-05-20 09:18:51', 5),
(9, 'sortie', 'sante', -1, 594, 593, 42, 'Carnet acte gratuit (+fiche) #27', '2026-05-20 09:19:44', 5),
(10, 'sortie', 'sante', -1, 593, 592, 50, 'Carnet acte gratuit (+fiche) #35', '2026-05-20 11:48:15', 1),
(11, 'sortie', 'sante', -1, 592, 591, 53, 'Carnet acte gratuit #38', '2026-05-20 12:32:05', 1);

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_fiches_ag`
--

CREATE TABLE `mouvements_fiches_ag` (
  `id` int(10) UNSIGNED NOT NULL,
  `type_mvt` enum('initialisation','sortie','correction') NOT NULL DEFAULT 'sortie',
  `quantite` int(11) NOT NULL COMMENT 'Positif pour ajouts, négatif pour sorties',
  `stock_avant` int(11) NOT NULL DEFAULT 0,
  `stock_apres` int(11) NOT NULL DEFAULT 0,
  `recu_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu consultation lié (si sortie)',
  `commentaire` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mouvements_fiches_ag`
--

INSERT INTO `mouvements_fiches_ag` (`id`, `type_mvt`, `quantite`, `stock_avant`, `stock_apres`, `recu_id`, `commentaire`, `whendone`, `whodone`) VALUES
(1, 'initialisation', 100, 0, 100, NULL, '', '2026-05-16 17:46:42', 1),
(2, 'sortie', -1, 100, 99, 25, 'Fiche acte gratuit #10', '2026-05-16 18:03:27', 1),
(3, 'initialisation', 201, 99, 300, NULL, '', '2026-05-16 18:07:10', 1),
(4, 'sortie', -1, 300, 299, 30, 'Fiche acte gratuit #15', '2026-05-18 09:40:40', 5),
(5, 'sortie', -1, 299, 298, 38, 'Fiche acte gratuit #23', '2026-05-18 17:22:09', 5),
(6, 'sortie', -1, 298, 297, 42, 'Fiche acte gratuit #27', '2026-05-20 09:19:44', 5),
(7, 'sortie', -1, 297, 296, 50, 'Fiche acte gratuit #35', '2026-05-20 11:48:15', 1);

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock_pharmacie`
--

CREATE TABLE `mouvements_stock_pharmacie` (
  `id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `type_mvt` enum('entree','sortie','correction') NOT NULL DEFAULT 'entree' COMMENT 'entree=appro, sortie=vente recu, correction=ajustement inventaire',
  `quantite` int(11) NOT NULL COMMENT 'Positif ou négatif selon type',
  `stock_avant` int(11) NOT NULL DEFAULT 0,
  `stock_apres` int(11) NOT NULL DEFAULT 0,
  `recu_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu pharmacie lié (si sortie)',
  `commentaire` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mouvements_stock_pharmacie`
--

INSERT INTO `mouvements_stock_pharmacie` (`id`, `produit_id`, `type_mvt`, `quantite`, `stock_avant`, `stock_apres`, `recu_id`, `commentaire`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 4, 'correction', 4, 68, 72, 31, 'Annulation reçu #16', '2026-05-18 12:15:28', 1, 0, '2026-05-18 11:15:28'),
(2, 1, 'correction', 3, 172, 175, 31, 'Annulation reçu #16', '2026-05-18 12:15:28', 1, 0, '2026-05-18 11:15:28'),
(3, 8, 'correction', 3, 988, 991, 31, 'Annulation reçu #16', '2026-05-18 12:15:28', 1, 0, '2026-05-18 11:15:28'),
(4, 42, 'correction', 3, 997, 1000, 31, 'Annulation reçu #16', '2026-05-18 12:15:28', 1, 0, '2026-05-18 11:15:28');

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(10) UNSIGNED NOT NULL,
  `telephone` varchar(20) NOT NULL DEFAULT '99999999',
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
(1, '99999999', 'ggggg', 'M', 12, '', 0, '2026-05-03 14:11:47', 1, 0, '2026-05-03 13:11:47'),
(2, '99999999', 'gggggg', 'M', 13, 'Maradi', 1, '2026-05-03 14:26:01', 1, 0, '2026-05-03 13:26:01'),
(3, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 1, '2026-05-03 14:26:41', 1, 0, '2026-05-03 13:26:41'),
(4, '99999999', 'JKKJJKJK', 'M', 12, '', 0, '2026-05-03 14:34:40', 1, 0, '2026-05-03 13:34:40'),
(5, '99999999', 'KKKKKKKK', 'F', 12, '', 0, '2026-05-03 15:01:12', 1, 0, '2026-05-03 14:01:12'),
(6, '99999999', 'ggggg', 'M', 12, '', 0, '2026-05-03 20:49:06', 1, 0, '2026-05-03 19:49:06'),
(7, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 21:17:47', 1, 0, '2026-05-03 20:17:47'),
(8, '99999999', 'ggggg', 'M', 12, 'KKKK', 0, '2026-05-03 21:18:17', 1, 0, '2026-05-03 20:18:17'),
(9, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 21:33:32', 1, 0, '2026-05-03 20:33:32'),
(10, '90909090', 'HTEESS', 'M', 13, 'GDT', 0, '2026-05-03 21:43:31', 1, 0, '2026-05-03 20:43:31'),
(11, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 21:44:40', 1, 0, '2026-05-03 20:44:40'),
(12, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 21:56:43', 1, 0, '2026-05-03 20:56:43'),
(13, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 22:03:34', 1, 0, '2026-05-03 21:03:34'),
(14, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 22:03:47', 1, 0, '2026-05-03 21:03:47'),
(16, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 22:37:38', 1, 0, '2026-05-03 21:37:38'),
(17, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 22:38:09', 1, 0, '2026-05-03 21:38:09'),
(18, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 22:38:19', 1, 0, '2026-05-03 21:38:19'),
(19, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 22:38:47', 1, 0, '2026-05-03 21:38:47'),
(20, '99999999', 'ZODI', 'M', 5, 'Maradi', 1, '2026-05-03 22:39:17', 1, 0, '2026-05-03 21:39:17'),
(21, '99999999', 'DDFFGGH BBBB', 'M', 34, 'Maradi', 0, '2026-05-03 23:04:08', 1, 0, '2026-05-03 22:04:08'),
(22, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-03 23:15:57', 1, 0, '2026-05-03 22:15:57'),
(23, '88888888', 'MAICK SY', 'M', 19, '', 0, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33'),
(24, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-16 17:52:48', 1, 0, '2026-05-16 16:52:48'),
(25, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-16 18:03:27', 1, 0, '2026-05-16 17:03:27'),
(26, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-16 18:49:51', 5, 0, '2026-05-16 17:49:51'),
(27, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-18 09:40:40', 5, 0, '2026-05-18 08:40:40'),
(28, '99999999', 'HAFIZOU SANDA', 'M', 12, 'Maradi', 1, '2026-05-18 09:41:26', 5, 0, '2026-05-18 08:41:26'),
(29, '90413363', 'nASSER KAILOU', 'M', 35, 'NNN', 0, '2026-05-18 17:22:09', 5, 0, '2026-05-18 16:22:09'),
(30, '99999999', 'MALICK ISSNOUSSA', 'M', 14, 'Maradi', 1, '2026-05-20 09:19:09', 5, 0, '2026-05-20 08:19:09'),
(31, '96283209', 'KAILOU ASSOUMANE FATI', 'F', 45, 'MARADI', 0, '2026-05-20 09:19:44', 5, 0, '2026-05-20 08:19:44'),
(32, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-20 11:47:51', 1, 0, '2026-05-20 10:47:51'),
(33, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-20 11:48:15', 1, 0, '2026-05-20 10:48:15'),
(34, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-20 12:15:10', 1, 0, '2026-05-20 11:15:10'),
(35, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-20 12:27:21', 1, 0, '2026-05-20 11:27:21'),
(36, '99999999', 'DDFFGGH BBBB', 'M', 5, 'Maradi', 0, '2026-05-20 12:32:05', 1, 0, '2026-05-20 11:32:05'),
(37, '99999999', 'Zalika sanda', 'M', 12, 'Maradi', 1, '2026-05-20 17:28:27', 5, 0, '2026-05-20 16:28:27'),
(38, '99999999', 'test', 'M', 9, 'Maradi', 1, '2026-05-20 17:35:09', 5, 0, '2026-05-20 16:35:09');

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
(1, 'Amoxi sp 125 mg', 'sirop', 650, 0, 157, 10, NULL, '2026-05-03 01:36:19', 5, 0, '2026-05-20 08:20:39'),
(2, 'Amoxi sp 250 mg', 'sirop', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(3, 'Amoxi gel 500 mg', 'gélule', 500, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(4, 'Amoxi 1g', 'comprimé', 200, 0, 50, 10, NULL, '2026-05-03 01:36:19', 5, 0, '2026-05-20 08:20:39'),
(5, 'Analgin inj', 'ampoule', 200, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(6, 'Buthyl cp', 'comprimé', 700, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(7, 'Buthyl inj', 'ampoule', 250, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(8, 'B complexe', 'comprimé', 250, 0, 951, 10, NULL, '2026-05-03 01:36:19', 5, 0, '2026-05-20 08:20:39'),
(9, 'Caha presson', 'autre', 1750, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(10, 'Clox gel', 'gélule', 600, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(11, 'Cipro 500 mg', 'comprimé', 850, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(12, 'Cotri cp', 'comprimé', 150, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(13, 'Dexa inj', 'ampoule', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(14, 'Diazepan', 'comprimé', 500, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(15, 'Genta inj', 'ampoule', 200, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(16, 'Gant sterile', 'autre', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(17, 'Gant en vrac', 'autre', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(18, 'Hydroxyd dl', 'autre', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(19, 'Ibuprofene up', 'comprimé', 150, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(20, 'Metro cp', 'comprimé', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(21, 'Metro sp', 'sirop', 800, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(22, 'Fil à suture', 'autre', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(23, 'Para sp', 'sirop', 750, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(24, 'Para cp', 'comprimé', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(25, 'Promethozine cp', 'comprimé', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(26, 'Cimetidine inj', 'ampoule', 250, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(27, 'Pommade Tetral 1', 'pommade', 200, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(28, 'Vogalene inj', 'ampoule', 500, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(29, 'Serum cilycose', 'solution', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(30, 'Serum salé', 'solution', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(31, 'Serum Ringer', 'solution', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(32, 'Perfuseur', 'autre', 250, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(33, 'Cathéter', 'autre', 500, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(34, 'Serengué 5 cc', 'autre', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(35, 'Serengué 10 cc', 'autre', 150, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(36, 'Carnet de santé', 'autre', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(37, 'Carnet soins', 'autre', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(38, 'Epicranienne', 'autre', 250, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(39, 'Eau distille', 'solution', 100, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(40, 'Metro soluté', 'solution', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(41, 'Para soluté', 'solution', 1250, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(42, 'PILLULE CONTRACEPTIVE', 'comprimé', 0, 0, 993, 10, NULL, '2026-05-03 11:33:30', 5, 0, '2026-05-20 08:20:25');

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
  `statut_reglement` enum('regle','en_instance') NOT NULL DEFAULT 'regle',
  `date_reglement` datetime DEFAULT NULL,
  `reglement_id` int(10) UNSIGNED DEFAULT NULL,
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

INSERT INTO `recus` (`id`, `numero_recu`, `patient_id`, `type_recu`, `type_patient`, `statut_reglement`, `date_reglement`, `reglement_id`, `montant_total`, `montant_encaisse`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(16, 1, 16, 'consultation', 'normal', 'regle', '2026-05-03 22:37:38', NULL, 1000, 1000, '2026-05-03 22:37:38', 1, 0, '2026-05-03 21:37:38'),
(17, 2, 17, 'consultation', 'normal', 'regle', '2026-05-03 22:38:09', NULL, 300, 300, '2026-05-03 22:38:09', 1, 0, '2026-05-03 21:38:09'),
(18, 3, 18, 'consultation', 'normal', 'regle', '2026-05-03 22:38:19', NULL, 400, 400, '2026-05-03 22:38:19', 1, 0, '2026-05-03 21:38:19'),
(19, 4, 19, 'consultation', 'acte_gratuit', 'regle', '2026-05-03 22:38:47', NULL, 400, 400, '2026-05-03 22:38:47', 1, 0, '2026-05-03 21:38:47'),
(20, 5, 20, 'consultation', 'orphelin', 'en_instance', NULL, NULL, 0, 0, '2026-05-03 22:39:17', 1, 0, '2026-05-20 16:32:49'),
(21, 6, 21, 'consultation', 'normal', 'regle', '2026-05-03 23:04:08', NULL, 500, 500, '2026-05-03 23:04:08', 1, 0, '2026-05-03 22:04:08'),
(22, 7, 22, 'consultation', 'acte_gratuit', 'regle', '2026-05-03 23:15:57', NULL, 100, 100, '2026-05-03 23:15:57', 1, 0, '2026-05-03 22:15:57'),
(23, 8, 23, 'consultation', 'normal', 'regle', '2026-05-03 23:18:33', NULL, 400, 400, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33'),
(24, 9, 24, 'consultation', 'acte_gratuit', 'regle', '2026-05-16 17:52:48', NULL, 400, 400, '2026-05-16 17:52:48', 1, 0, '2026-05-16 16:52:48'),
(25, 10, 25, 'consultation', 'acte_gratuit', 'regle', '2026-05-16 18:03:27', NULL, 400, 400, '2026-05-16 18:03:27', 1, 0, '2026-05-16 17:03:27'),
(26, 11, 25, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-16 18:04:12', NULL, 7250, 7250, '2026-05-16 18:04:12', 1, 0, '2026-05-16 17:04:12'),
(27, 12, 25, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-16 18:04:58', NULL, 6800, 6800, '2026-05-16 18:04:58', 1, 0, '2026-05-16 17:04:58'),
(28, 13, 25, 'examen', 'acte_gratuit', 'regle', '2026-05-16 18:46:14', NULL, 4800, 4800, '2026-05-16 18:46:14', 1, 0, '2026-05-16 17:46:14'),
(29, 14, 26, 'consultation', 'normal', 'regle', '2026-05-16 18:49:51', NULL, 400, 400, '2026-05-16 18:49:51', 5, 0, '2026-05-16 17:49:51'),
(30, 15, 27, 'consultation', 'acte_gratuit', 'regle', '2026-05-18 09:40:40', NULL, 300, 300, '2026-05-18 09:40:40', 5, 0, '2026-05-18 11:15:09'),
(31, 16, 27, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-18 09:40:57', NULL, 3500, 3500, '2026-05-18 09:40:57', 5, 1, '2026-05-18 11:15:28'),
(32, 17, 27, 'examen', 'acte_gratuit', 'regle', '2026-05-18 09:41:08', NULL, 3100, 3100, '2026-05-18 09:41:08', 5, 0, '2026-05-18 08:41:08'),
(33, 18, 28, 'consultation', 'orphelin', 'en_instance', NULL, NULL, 0, 0, '2026-05-18 09:41:26', 5, 0, '2026-05-20 16:32:49'),
(34, 19, 28, 'examen', 'orphelin', 'en_instance', NULL, NULL, 2100, 0, '2026-05-18 09:41:36', 5, 0, '2026-05-18 08:41:36'),
(35, 20, 28, 'pharmacie', 'orphelin', 'en_instance', NULL, NULL, 2050, 0, '2026-05-18 09:41:50', 5, 0, '2026-05-18 08:41:50'),
(36, 21, 27, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-18 12:42:02', NULL, 6350, 6350, '2026-05-18 12:42:02', 1, 0, '2026-05-18 11:42:02'),
(37, 22, 27, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-18 12:42:46', NULL, 900, 900, '2026-05-18 12:42:46', 5, 0, '2026-05-18 11:42:46'),
(38, 23, 29, 'consultation', 'acte_gratuit', 'regle', '2026-05-18 17:22:09', NULL, 400, 400, '2026-05-18 17:22:09', 5, 0, '2026-05-18 16:22:09'),
(39, 24, 29, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-18 17:22:31', NULL, 2850, 2850, '2026-05-18 17:22:31', 5, 0, '2026-05-18 16:22:31'),
(40, 25, 29, 'consultation', 'normal', 'regle', '2026-05-20 09:18:51', NULL, 500, 500, '2026-05-20 09:18:51', 5, 0, '2026-05-20 08:18:51'),
(41, 26, 30, 'consultation', 'orphelin', 'regle', '2026-05-20 00:00:00', 1, 0, 0, '2026-05-20 09:19:09', 5, 0, '2026-05-20 16:32:49'),
(42, 27, 31, 'consultation', 'acte_gratuit', 'regle', '2026-05-20 09:19:44', NULL, 400, 400, '2026-05-20 09:19:44', 5, 0, '2026-05-20 08:19:44'),
(43, 28, 29, 'pharmacie', 'normal', 'regle', '2026-05-20 09:20:01', NULL, 3300, 3300, '2026-05-20 09:20:01', 5, 0, '2026-05-20 08:20:01'),
(44, 29, 30, 'pharmacie', 'orphelin', 'regle', '2026-05-20 00:00:00', 1, 5500, 5500, '2026-05-20 09:20:25', 5, 0, '2026-05-20 08:31:35'),
(45, 30, 31, 'pharmacie', 'acte_gratuit', 'regle', '2026-05-20 09:20:39', NULL, 7200, 7200, '2026-05-20 09:20:39', 5, 0, '2026-05-20 08:20:39'),
(46, 31, 29, 'examen', 'normal', 'regle', '2026-05-20 09:20:53', NULL, 3600, 3600, '2026-05-20 09:20:53', 5, 0, '2026-05-20 08:20:53'),
(47, 32, 30, 'examen', 'orphelin', 'regle', '2026-05-20 00:00:00', 1, 4500, 4500, '2026-05-20 09:20:59', 5, 0, '2026-05-20 08:31:35'),
(48, 33, 31, 'examen', 'acte_gratuit', 'regle', '2026-05-20 09:21:09', NULL, 4300, 4300, '2026-05-20 09:21:09', 5, 0, '2026-05-20 08:21:09'),
(49, 34, 32, 'consultation', 'acte_gratuit', 'regle', '2026-05-20 11:47:51', NULL, 0, 0, '2026-05-20 11:47:51', 1, 0, '2026-05-20 10:47:51'),
(50, 35, 33, 'consultation', 'acte_gratuit', 'regle', '2026-05-20 11:48:15', NULL, 400, 400, '2026-05-20 11:48:15', 1, 0, '2026-05-20 10:48:15'),
(51, 36, 34, 'consultation', 'acte_gratuit', 'regle', '2026-05-20 12:15:10', NULL, 0, 0, '2026-05-20 12:15:10', 1, 0, '2026-05-20 11:15:10'),
(52, 37, 35, 'consultation', 'acte_gratuit', 'regle', '2026-05-20 12:27:21', NULL, 0, 0, '2026-05-20 12:27:21', 1, 0, '2026-05-20 11:27:21'),
(53, 38, 36, 'consultation', 'acte_gratuit', 'regle', '2026-05-20 12:32:05', NULL, 300, 300, '2026-05-20 12:32:05', 1, 0, '2026-05-20 11:32:05'),
(54, 39, 37, 'consultation', 'orphelin', 'en_instance', NULL, NULL, 0, 0, '2026-05-20 17:28:27', 5, 0, '2026-05-20 16:28:27'),
(55, 40, 29, 'consultation', 'normal', 'regle', '2026-05-20 17:28:49', NULL, 400, 400, '2026-05-20 17:28:49', 5, 0, '2026-05-20 16:28:49'),
(56, 41, 38, 'consultation', 'orphelin', 'en_instance', NULL, NULL, 0, 0, '2026-05-20 17:35:09', 5, 0, '2026-05-20 16:35:09');

-- --------------------------------------------------------

--
-- Structure de la table `reglements_orphelins`
--

CREATE TABLE `reglements_orphelins` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_reglement` varchar(50) NOT NULL,
  `date_reglement` date NOT NULL,
  `montant_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nb_recus` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `mode_paiement` enum('especes','cheque','virement','mobile_money') NOT NULL DEFAULT 'especes',
  `reference_paiement` varchar(100) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `whendone` datetime NOT NULL DEFAULT current_timestamp(),
  `whodone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `lastUpdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reglements_orphelins`
--

INSERT INTO `reglements_orphelins` (`id`, `numero_reglement`, `date_reglement`, `montant_total`, `nb_recus`, `mode_paiement`, `reference_paiement`, `observations`, `whendone`, `whodone`, `isDeleted`, `lastUpdate`) VALUES
(1, 'RGL-20260520-001', '2026-05-20', 10300, 3, 'especes', NULL, NULL, '2026-05-20 09:31:35', 1, 0, '2026-05-20 08:31:35');

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
  `role` enum('admin','comptable','percepteur','major') NOT NULL DEFAULT 'percepteur',
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
(2, 'Comptable', 'CSI', 'comptable', '$2y$12$VkL5ztVIdyDLYSCk07Ua7OF368.vReO3MDsPlKWcUrUm8C8CXXh0G', 'comptable', 1, '2026-03-31 20:27:06', 1, 0, '2026-05-16 17:20:56'),
(3, 'Percepteur', 'Un', 'percepteur1', '$2b$12$QsbqT1bajUMQsaVZkv60HOV9vBQQ.dv4sllUTk5zdPepORv6iyerm', 'percepteur', 1, '2026-03-31 20:27:06', 1, 0, '2026-03-31 19:27:06'),
(4, 'Percepteur', 'Deux', 'percepteur2', '$2b$12$VG5tf3CFIoF4S/a7XxthYe28DBfjcAmdp7nqEPLeOmnyL3WVlcsmi', 'percepteur', 1, '2026-03-31 20:27:06', 1, 0, '2026-03-31 19:27:06'),
(5, 'Abdoul Nasser', 'kailou', 'nasser', '$2y$12$eGtaHNHmDvgw9fbvf/HoEeKXEFxiVffbt0UtByTVX1IS.MS.eokyO', 'percepteur', 1, '2026-04-23 15:26:57', 1, 0, '2026-04-23 14:26:57'),
(6, 'HH', 'HHHH', 'major', '$2y$12$pYYROzthGx1m9iPSDuStx.okiAoPkmtfEkKKoNUd1zVT2MtJMHWG.', 'major', 1, '2026-05-16 18:08:48', 1, 1, '2026-05-16 17:36:48'),
(7, 'kindo', 'sanda', 'sanda', '$2y$12$wQghMPYINHEB0MGcpGfXlemlMiEt3S06adukO0PSXNbQlxoExfBqe', 'major', 1, '2026-05-16 18:26:55', 1, 0, '2026-05-16 17:36:44'),
(8, 'TEST', 'TEST', 'test', '$2y$12$Zcs3SLf0CPnFnvhH2kcaHOPq.bs8TvETGFXXkwM0gulqvV2DmDk5i', 'percepteur', 1, '2026-05-21 01:02:37', 1, 0, '2026-05-21 00:02:37');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `actes_medicaux`
--
ALTER TABLE `actes_medicaux`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `annulations_recus`
--
ALTER TABLE `annulations_recus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_annul_recu` (`recu_id`),
  ADD KEY `idx_annul_date` (`whendone`);

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
  ADD KEY `idx_type_ligne` (`type_ligne`),
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
-- Index pour la table `mouvements_carnets`
--
ALTER TABLE `mouvements_carnets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mvt_carnet_date` (`whendone`),
  ADD KEY `idx_mvt_carnet_recu` (`recu_id`),
  ADD KEY `idx_mvt_carnet_type` (`type_carnet`);

--
-- Index pour la table `mouvements_fiches_ag`
--
ALTER TABLE `mouvements_fiches_ag`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mvt_fiche_date` (`whendone`),
  ADD KEY `idx_mvt_fiche_recu` (`recu_id`);

--
-- Index pour la table `mouvements_stock_pharmacie`
--
ALTER TABLE `mouvements_stock_pharmacie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mvt_produit` (`produit_id`),
  ADD KEY `idx_mvt_date` (`whendone`),
  ADD KEY `idx_mvt_recu` (`recu_id`),
  ADD KEY `idx_mvt_type` (`type_mvt`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
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
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_recus_statut_reglement` (`statut_reglement`),
  ADD KEY `idx_recus_reglement_id` (`reglement_id`);

--
-- Index pour la table `reglements_orphelins`
--
ALTER TABLE `reglements_orphelins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_numero_reglement` (`numero_reglement`),
  ADD KEY `idx_date_reglement` (`date_reglement`),
  ADD KEY `idx_regle_par` (`whodone`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `annulations_recus`
--
ALTER TABLE `annulations_recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `approvisionnements_pharmacie`
--
ALTER TABLE `approvisionnements_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `config_systeme`
--
ALTER TABLE `config_systeme`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `lignes_examen`
--
ALTER TABLE `lignes_examen`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `lignes_pharmacie`
--
ALTER TABLE `lignes_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `modifications_recus`
--
ALTER TABLE `modifications_recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `mouvements_carnets`
--
ALTER TABLE `mouvements_carnets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `mouvements_fiches_ag`
--
ALTER TABLE `mouvements_fiches_ag`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `mouvements_stock_pharmacie`
--
ALTER TABLE `mouvements_stock_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT pour la table `produits_pharmacie`
--
ALTER TABLE `produits_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `recus`
--
ALTER TABLE `recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT pour la table `reglements_orphelins`
--
ALTER TABLE `reglements_orphelins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `types_carnets`
--
ALTER TABLE `types_carnets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `annulations_recus`
--
ALTER TABLE `annulations_recus`
  ADD CONSTRAINT `fk_annul_recu` FOREIGN KEY (`recu_id`) REFERENCES `recus` (`id`);

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
-- Contraintes pour la table `mouvements_stock_pharmacie`
--
ALTER TABLE `mouvements_stock_pharmacie`
  ADD CONSTRAINT `fk_mvt_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie` (`id`);

--
-- Contraintes pour la table `recus`
--
ALTER TABLE `recus`
  ADD CONSTRAINT `fk_recu_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_recu_reglement` FOREIGN KEY (`reglement_id`) REFERENCES `reglements_orphelins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
