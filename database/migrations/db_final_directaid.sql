-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 16 mai 2026 à 13:37
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
(6, 'logo_ministere', 'logo_ministere.png', '2026-05-03 01:15:54', 0, 0, '2026-05-03 00:15:54');

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
(13, 23, NULL, 'redevance', 'Redevance ministère (âge > 5 ans)', 100, 0, 0, 0, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33');

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
(23, '88888888', 'MAICK SY', 'M', 19, '', 0, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33');

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
(1, 'Amoxi sp 125 mg', 'sirop', 650, 0, 193, 10, NULL, '2026-05-03 01:36:19', 1, 0, '2026-05-03 11:24:02'),
(2, 'Amoxi sp 250 mg', 'sirop', 1000, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(3, 'Amoxi gel 500 mg', 'gélule', 500, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(4, 'Amoxi 1g', 'comprimé', 200, 0, 89, 10, NULL, '2026-05-03 01:36:19', 1, 0, '2026-05-03 11:24:02'),
(5, 'Analgin inj', 'ampoule', 200, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(6, 'Buthyl cp', 'comprimé', 700, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(7, 'Buthyl inj', 'ampoule', 250, 0, 0, 10, NULL, '2026-05-03 01:36:19', 0, 0, '2026-05-02 23:36:19'),
(8, 'B complexe', 'comprimé', 250, 0, 995, 10, NULL, '2026-05-03 01:36:19', 5, 0, '2026-05-03 09:12:47'),
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
(42, 'PILLULE CONTRACEPTIVE', 'comprimé', 0, 0, 1000, 10, NULL, '2026-05-03 11:33:30', 1, 0, '2026-05-03 09:33:43');

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
(20, 5, 20, 'consultation', 'orphelin', 'en_instance', NULL, NULL, 300, 0, '2026-05-03 22:39:17', 1, 0, '2026-05-03 21:39:17'),
(21, 6, 21, 'consultation', 'normal', 'regle', '2026-05-03 23:04:08', NULL, 500, 500, '2026-05-03 23:04:08', 1, 0, '2026-05-03 22:04:08'),
(22, 7, 22, 'consultation', 'acte_gratuit', 'regle', '2026-05-03 23:15:57', NULL, 100, 100, '2026-05-03 23:15:57', 1, 0, '2026-05-03 22:15:57'),
(23, 8, 23, 'consultation', 'normal', 'regle', '2026-05-03 23:18:33', NULL, 400, 400, '2026-05-03 23:18:33', 1, 0, '2026-05-03 22:18:33');

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
  ADD KEY `idx_type_ligne` (`type_ligne`);

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
-- AUTO_INCREMENT pour la table `approvisionnements_pharmacie`
--
ALTER TABLE `approvisionnements_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `config_systeme`
--
ALTER TABLE `config_systeme`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `lignes_examen`
--
ALTER TABLE `lignes_examen`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lignes_pharmacie`
--
ALTER TABLE `lignes_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `modifications_recus`
--
ALTER TABLE `modifications_recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `produits_pharmacie`
--
ALTER TABLE `produits_pharmacie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `recus`
--
ALTER TABLE `recus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `reglements_orphelins`
--
ALTER TABLE `reglements_orphelins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `types_carnets`
--
ALTER TABLE `types_carnets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `fk_recu_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_recu_reglement` FOREIGN KEY (`reglement_id`) REFERENCES `reglements_orphelins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
