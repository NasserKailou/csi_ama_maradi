# 🏥 Système de Gestion CSI AMA Maradi

[![Version](https://img.shields.io/badge/version-1.0-green.svg)](https://github.com/NasserKailou/csi_ama_maradi)
[![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-orange.svg)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)](https://getbootstrap.com)

Système de gestion complet pour le **Centre de Santé Intégré (CSI) AMA de Maradi** (Niger), développé en PHP 8 natif + MySQL 8 + Bootstrap 5, déployable sur XAMPP.

---

## 📋 Table des matières

1. [Présentation](#-présentation)
2. [Fonctionnalités](#-fonctionnalités)
3. [Architecture technique](#-architecture-technique)
4. [Structure des fichiers](#-structure-des-fichiers)
5. [Modèle de données](#-modèle-de-données)
6. [Profils utilisateurs](#-profils-utilisateurs)
7. [Comptes de test](#-comptes-de-test)
8. [Déploiement XAMPP](#-déploiement-xampp-windows)
9. [Déploiement Linux/XAMPP](#-déploiement-linuxxampp)
10. [Configuration](#%EF%B8%8F-configuration)
11. [Génération PDF](#-génération-pdf)
12. [Sécurité](#-sécurité)
13. [Règles métier](#-règles-métier)

---

## 🏥 Présentation

**CSI AMA Maradi** est un logiciel de gestion de centre de santé intégré destiné au **personnel soignant et administratif** du CSI. Il couvre :

- **Accueil patients** : enregistrement, reçus (consultation, examens, pharmacie)
- **Gestion stock** : pharmacie avec alertes et approvisionnements
- **Laboratoire** : suivi des examens et état de paie du laborantin
- **Administration** : tableau de bord KPI, gestion utilisateurs, configuration
- **Reporting** : PDF A5 double exemplaire, état de paie laborantin

---

## ✅ Fonctionnalités

### Module Percepteur
- [x] Reçu **Patient Normal** : Consultation 300 F + Carnet de Soins 100 F (optionnel)
- [x] Reçu **Orphelin** : Gratuité totale, montant conservé pour reporting bailleur
- [x] Reçu **Actes Gratuits** : CPN, Accouchements, Nourrissons, Planning Familial
- [x] Prescription **Examens laborantin** avec calcul automatique part labo
- [x] Délivrance **Pharmacie** (max 15 produits/reçu) avec décrémentation stock
- [x] Autocomplete **téléphone** dès 3 chiffres (déduplication patients)
- [x] Liste journalière par percepteur avec filtre par période
- [x] Impression PDF A5 double exemplaire (percepteur + patient)

### Module Paramétrage (Admin + Comptable)
- [x] CRUD **Actes médicaux** avec tarifs et flag gratuit
- [x] CRUD **Examens laborantin** avec pourcentage de commission
- [x] CRUD **Stock pharmacie** : produits, formes, prix, péremption
- [x] **Approvisionnement** stock avec historique
- [x] **Inventaire** : rapprochement stock physique vs théorique
- [x] **État de paie laborantin** : PDF par période avec total dû
- [x] **Configuration centre** : nom, adresse, téléphone, logo

### Tableau de Bord (Admin uniquement)
- [x] KPIs : patients jour/semaine/mois, recettes, actes gratuits, alertes stock
- [x] Graphique évolution consultations 7 derniers jours (Chart.js)
- [x] Graphique répartition revenus par pôle (consultation/examens/pharmacie)
- [x] Alertes stock en temps réel (ruptures, périmés, sous seuil)
- [x] Activité percepteurs du jour
- [x] Filtre recettes par période

### Gestion Utilisateurs (Admin uniquement)
- [x] Créer/modifier/suspendre/archiver des utilisateurs
- [x] 3 rôles : Administrateur, Comptable, Percepteur
- [x] Hachage BCRYPT des mots de passe
- [x] Soft-delete (jamais de suppression physique)

---

## 🏗 Architecture technique

```
┌────────────────────────────────────────────────────────┐
│                     Navigateur Web                      │
│         Bootstrap 5 + jQuery + Chart.js + DataTables    │
└───────────────────────┬────────────────────────────────┘
                        │ HTTP (XAMPP/Apache)
┌───────────────────────▼────────────────────────────────┐
│                  index.php (Front Controller)           │
│           Router simple basé sur ?page=xxx              │
└──────┬──────────┬──────────┬──────────┬────────────────┘
       │          │          │          │
   ┌───▼───┐  ┌──▼──┐  ┌────▼───┐  ┌──▼──────────┐
   │ Auth  │  │ Perc│  │ Param  │  │  Dashboard  │
   │ login │  │ epteur│ │étrage  │  │   (Admin)   │
   │ users │  │ PDF  │  │ CRUD   │  │  KPIs Charts│
   └───┬───┘  └──┬──┘  └────┬───┘  └──┬──────────┘
       │          │          │          │
       └──────────┴──────────┴──────────┘
                        │
┌───────────────────────▼────────────────────────────────┐
│                     Couche Core                         │
│   Database (PDO Singleton) │ Session (CSRF) │ Helpers  │
└───────────────────────┬────────────────────────────────┘
                        │
┌───────────────────────▼────────────────────────────────┐
│                  MySQL 8 – Base `csi_ama`               │
│  utilisateurs │ patients │ recus │ lignes_* │ produits  │
└────────────────────────────────────────────────────────┘
```

### Stack technique

| Composant       | Technologie                            | Version  |
|-----------------|----------------------------------------|----------|
| Backend         | PHP natif (sans framework)             | ≥ 8.0    |
| Base de données | MySQL                                  | ≥ 8.0    |
| Frontend CSS    | Bootstrap 5                            | 5.3.3    |
| Frontend JS     | jQuery + DataTables + Chart.js         | CDN      |
| PDF             | TCPDF (via Composer) ou fallback HTML  | ^6.6     |
| Icônes          | Bootstrap Icons                        | 1.11.3   |
| Auth            | PHP Sessions natives + CSRF            | —        |
| Déploiement     | XAMPP (Apache + MySQL)                 | ≥ 8.2    |

---

## 📁 Structure des fichiers

```
csi_ama_maradi/
│
├── index.php                  ← Front Controller (point d'entrée unique)
├── install.php                ← Script d'installation (à exécuter une fois)
├── setup_xampp.bat            ← Installation automatisée Windows
├── setup_xampp.sh             ← Installation automatisée Linux
├── composer.json              ← Dépendances PHP (TCPDF)
├── .env.example               ← Template de configuration
├── .env                       ← Configuration locale (ne PAS committer)
├── .htaccess                  ← Règles Apache (Front Controller + sécurité)
│
├── config/
│   └── config.php             ← Constantes et paramètres globaux
│
├── core/
│   ├── autoload.php           ← Autoloader PHP
│   ├── Database.php           ← Connexion PDO Singleton
│   ├── Session.php            ← Gestion sessions + CSRF
│   └── helpers.php            ← Fonctions utilitaires
│
├── modules/
│   ├── auth/
│   │   ├── login.php          ← Page de connexion
│   │   ├── logout.php         ← Déconnexion
│   │   └── utilisateurs.php   ← CRUD utilisateurs (Admin)
│   │
│   ├── percepteur/
│   │   ├── index.php          ← Interface principale percepteur
│   │   ├── save_consultation.php  ← API sauvegarde reçu consultation
│   │   ├── save_acte_gratuit.php  ← API sauvegarde acte gratuit
│   │   ├── save_examens.php       ← API sauvegarde examens
│   │   ├── save_pharmacie.php     ← API sauvegarde pharmacie + décrémentation
│   │   └── get_recap.php          ← API récapitulatif patient (modal)
│   │
│   ├── parametrage/
│   │   ├── index.php          ← Gestion actes, examens, pharmacie, config
│   │   └── etat_labo.php      ← API état de paie laborantin
│   │
│   ├── dashboard/
│   │   └── index.php          ← Tableau de bord Admin (KPIs + graphiques)
│   │
│   ├── pdf/
│   │   ├── PdfGenerator.php   ← Générateur reçus TCPDF/HTML fallback
│   │   └── etat_labo.php      ← PDF état laborantin
│   │
│   └── api/
│       └── patients.php       ← API autocomplete téléphone
│
├── templates/
│   ├── layouts/
│   │   ├── header.php         ← En-tête HTML + Navbar responsive
│   │   └── footer.php         ← Pied de page + scripts JS
│   └── errors/
│       ├── 403.php            ← Page accès refusé
│       └── 404.php            ← Page introuvable
│
├── assets/
│   ├── css/main.css           ← Charte graphique verte #2e7d32
│   └── js/app.js              ← JS global (toasts, DataTables, autocomplete)
│
├── database/
│   ├── migrations/
│   │   └── 001_schema.sql     ← Schéma complet MySQL 8
│   └── seeds/
│       └── 001_seed_data.sql  ← Données de référence
│
├── uploads/
│   ├── logos/
│   │   └── logo_csi.png       ← Logo DirectAid AMA
│   └── pdf/                   ← Reçus générés (gitignored)
│
└── vendor/                    ← Dépendances Composer (gitignored)
```

---

## 🗄 Modèle de données

### Convention de traçabilité obligatoire
Chaque table contient **4 champs de traçabilité** :
```sql
whendone   DATETIME    -- Date/heure de création
whodone    INT UNSIGNED -- ID utilisateur ayant effectué l'action
isDeleted  TINYINT(1)  -- 0 = actif, 1 = archivé (JAMAIS de DELETE physique)
lastUpdate TIMESTAMP   -- Dernière modification automatique
```

### Tables principales

| Table                      | Description                                     |
|----------------------------|-------------------------------------------------|
| `utilisateurs`             | Comptes système (admin/comptable/percepteur)    |
| `config_systeme`           | Paramètres centre (nom, adresse, logo...)       |
| `patients`                 | Patients (déduplication par téléphone)          |
| `actes_medicaux`           | Catalogue actes (tarif, flag gratuit)           |
| `types_carnets`            | Carnets de soins et de santé                    |
| `examens`                  | Examens labo (coût total + % commission labo)   |
| `produits_pharmacie`       | Stock pharmaceutique                            |
| `approvisionnements_pharmacie` | Historique réapprovisionnements            |
| `recus`                    | Table maîtresse toutes transactions             |
| `lignes_consultation`      | Détail reçus consultation                       |
| `lignes_examen`            | Détail reçus examens                            |
| `lignes_pharmacie`         | Détail reçus pharmacie                          |
| `inventaire_physique`      | Rapprochement stock physique vs théorique       |

### Types de reçus
- **consultation** : patient normal (300F ou 400F avec carnet) ou orphelin (0F) ou acte gratuit (0F)
- **examen** : prescription d'examens laborantin avec part labo
- **pharmacie** : délivrance médicaments avec décrémentation stock

---

## 👥 Profils utilisateurs

| Rôle         | Dashboard | Percepteur | Paramétrage | Utilisateurs |
|--------------|:---------:|:----------:|:-----------:|:------------:|
| Administrateur | ✅       | ✅         | ✅          | ✅           |
| Comptable    | ❌        | ❌         | ✅          | ❌           |
| Percepteur   | ❌        | ✅         | ❌          | ❌           |
| Laborantin   | —         | —          | —           | —            |

> **Laborantin** : pas de compte système. Il travaille uniquement avec les documents imprimés (reçus d'examens avec zone de résultats).

### Redirections après connexion
- **admin** → `/index.php?page=dashboard`
- **comptable** → `/index.php?page=parametrage`
- **percepteur** → `/index.php?page=percepteur`

---

## 🔑 Comptes de test

> Créés automatiquement par `php install.php`

| Login          | Mot de passe     | Rôle          | Description                           |
|----------------|------------------|---------------|---------------------------------------|
| `admin`        | `Admin@CSI2026`  | Administrateur | Accès complet + tableau de bord       |
| `comptable`    | `Compta@CSI2026` | Comptable      | Paramétrage + état labo (sans users) |
| `percepteur1`  | `Percep1@CSI2026`| Percepteur     | Abdou Issoufou – caisse principale    |
| `percepteur2`  | `Percep2@CSI2026`| Percepteur     | Halima Moussa – caisse secondaire     |

---

## 🚀 Déploiement XAMPP (Windows)

### Prérequis
- XAMPP ≥ 8.2 avec **Apache** et **MySQL** activés
- PHP ≥ 8.0 avec extensions : `pdo_mysql`, `mbstring`, `gd`, `fileinfo`
- (Optionnel) Composer pour TCPDF

### Installation rapide

```batch
1. Décompresser dans C:\xampp\htdocs\csi_ama_maradi\

2. Double-clic sur setup_xampp.bat
   (ou exécuter en tant qu'Administrateur)

3. Ouvrir http://localhost/csi_ama_maradi/
```

### Installation manuelle

```batch
REM 1. Démarrer Apache + MySQL dans XAMPP Control Panel

REM 2. Aller dans le dossier du projet
cd C:\xampp\htdocs\csi_ama_maradi

REM 3. Copier la configuration
copy .env.example .env

REM 4. Éditer .env si nécessaire (mot de passe root MySQL, etc.)
notepad .env

REM 5. Lancer l'installation
C:\xampp\php\php.exe install.php

REM 6. (Optionnel) Installer TCPDF pour les PDF natifs
C:\xampp\php\php.exe -r "readfile('https://getcomposer.org/installer');" | C:\xampp\php\php.exe
C:\xampp\php\php.exe composer.phar install

REM 7. Accéder à l'application
REM    http://localhost/csi_ama_maradi/
```

### Chemin de configuration Apache
Si XAMPP utilise un sous-dossier, modifier `.env` :
```env
APP_URL=http://localhost/csi_ama_maradi
```

---

## 🐧 Déploiement Linux/XAMPP

```bash
# 1. Cloner ou décompresser dans le dossier web
sudo cp -r csi_ama_maradi/ /opt/lampp/htdocs/
# OU pour LAMP natif :
sudo cp -r csi_ama_maradi/ /var/www/html/

# 2. Permissions
sudo chown -R www-data:www-data /opt/lampp/htdocs/csi_ama_maradi/
sudo chmod -R 755 /opt/lampp/htdocs/csi_ama_maradi/

# 3. Installation automatique
cd /opt/lampp/htdocs/csi_ama_maradi/
chmod +x setup_xampp.sh
./setup_xampp.sh

# 4. Accéder à l'application
# http://localhost/csi_ama_maradi/
```

---

## ⚙️ Configuration

### Fichier `.env`
```env
APP_NAME=Système CSI AMA Maradi
APP_ENV=production          # development | production
APP_URL=http://localhost/csi_ama_maradi
APP_TIMEZONE=Africa/Niamey

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=csi_ama
DB_USER=root
DB_PASS=                    # Laisser vide si pas de mdp
```

### Configuration centre (via interface Admin)
Accéder à **Paramétrage → Config Centre** pour modifier :
- Nom du centre
- Adresse complète
- Numéro(s) de téléphone
- Pied de page des reçus
- Logo (JPG/PNG, max 2Mo)

---

## 🖨 Génération PDF

### Avec TCPDF (recommandé)
```bash
cd /chemin/du/projet
composer install
```
Produit des PDF natifs A5, format portrait, double exemplaire.

### Sans TCPDF (mode de secours)
Les reçus sont générés en **HTML avec CSS @print** et s'ouvrent automatiquement dans l'imprimante du navigateur. Fonctionnalité identique, pas de PDF téléchargeable.

### Format des reçus PDF
- **Format** : A5 portrait
- **Double exemplaire** : ✂ Exemplaire Percepteur ✂ + ✂ Exemplaire Patient ✂
- **En-tête** : logo + nom centre + adresse + téléphone
- **Corps** : tableau des prestations avec tarifs
- **Pied** : mention GRATUIT en filigrane pour orphelins/actes gratuits
- **Signature** : ligne de signature percepteur

---

## 🔒 Sécurité

| Mesure                | Implémentation                                           |
|-----------------------|----------------------------------------------------------|
| CSRF                  | Token CSRF sur tous les formulaires et requêtes AJAX     |
| XSS                   | `htmlspecialchars()` systématique + helper `h()`         |
| SQL Injection         | PDO avec requêtes préparées exclusivement                |
| Authentification      | Sessions PHP strictes (httpOnly, SameSite=Strict)        |
| Mots de passe         | BCRYPT cost=12                                           |
| Soft-delete           | `isDeleted=1` — aucun `DELETE` physique                  |
| Contrôle d'accès      | `requireRole()` sur chaque module                        |
| Upload fichiers       | Validation MIME + taille + extension                     |
| En-têtes HTTP         | X-Frame-Options, X-Content-Type-Options, CSP via .htaccess |
| Répertoires           | `Options -Indexes` dans .htaccess                        |
| Fichiers sensibles    | `.env`, `.sql`, `.log` bloqués via .htaccess             |

---

## 📐 Règles métier

### Tarification
| Type                  | Consultation | Carnet de soins | Total  |
|-----------------------|:------------:|:---------------:|:------:|
| Normal avec carnet    | 300 F        | 100 F           | 400 F  |
| Normal sans carnet    | 300 F        | —               | 300 F  |
| Orphelin              | 0 F (gratuit)| 0 F             | 0 F    |
| Acte gratuit (CPN...) | 0 F (gratuit)| —               | 0 F    |

### Numérotation des reçus
- Numérotation **séquentielle globale** (toutes transactions confondues)
- Format affiché : `#00001`, `#00002`, etc.
- Jamais de réinitialisation, jamais de numéro manquant

### Stock pharmacie
- Décrémentation automatique lors de la validation d'un reçu pharmacie
- Blocage si produit en **rupture de stock** (stock = 0)
- Blocage si produit **périmé** (date_peremption ≤ aujourd'hui)
- **Seuil d'alerte** par défaut : 10 unités
- Maximum **15 produits** par reçu pharmacie

### Patients
- Déduplication par **numéro de téléphone**
- Si téléphone existant : mise à jour des données patient
- Autocomplete dès **3 chiffres** saisis

---

## 📊 KPIs Tableau de Bord

- Nombre de patients (jour / 7 jours / mois)
- Recettes encaissées du jour
- Coût total des actes gratuits (pour reporting bailleur)
- Nombre d'alertes stock actives
- Évolution des consultations sur 7 jours (graphique barres)
- Répartition des revenus par pôle ce mois (graphique donut)
- Activité de chaque percepteur du jour

---

## 📄 Licence

Projet propriétaire – Développé pour le **Centre de Santé Intégré AMA Maradi**.  
Tous droits réservés.

---

*Développé avec PHP 8 + MySQL 8 + Bootstrap 5 pour le CSI AMA Maradi – Mars 2026*
