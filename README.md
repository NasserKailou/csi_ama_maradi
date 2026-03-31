# 🏥 Système de Gestion CSI AMA Maradi

> **Application web complète** de gestion du Centre de Santé Intégré (CSI) AMA Maradi  
> Stack : **PHP 8** · **MySQL 8** · **Bootstrap 5** · **TCPDF** · Vert DirectAid `#2e7d32`

---

## 📋 Table des matières
1. [Présentation](#présentation)
2. [Fonctionnalités implémentées](#fonctionnalités)
3. [Architecture du projet](#architecture)
4. [Structure BDD](#structure-bdd)
5. [Comptes de test](#comptes-de-test)
6. [Guide d'installation](#installation)
7. [Guide de déploiement](#déploiement)
8. [Règles métier critiques](#règles-métier)
9. [API endpoints](#api-endpoints)
10. [Feuille de route](#roadmap)

---

## 🏥 Présentation

Application **100 % opérationnelle en intranet ou hébergée**, couvrant :
- Réception & facturation des patients
- Gestion stock pharmaceutique avec alertes
- Suivi des examens médicaux et paie laborantin
- Reporting financier pour les bailleurs de fonds
- Tableau de bord administrateur avec graphiques

**Logo intégré** : DirectAid (العون المباشر) – partenaire du CSI AMA Maradi

---

## ✅ Fonctionnalités implémentées

### 🔐 Authentification
- [x] Connexion sécurisée (sessions PHP natives)
- [x] Protection CSRF stricte (token sur tous les formulaires & AJAX)
- [x] Régénération ID de session à la connexion
- [x] Redirection automatique selon le rôle

### 👥 Gestion Utilisateurs (Admin)
- [x] CRUD complet (Créer, Modifier, Suspendre, Archiver)
- [x] 3 rôles : Admin · Comptable · Percepteur
- [x] Soft delete (isDeleted = 1, jamais DELETE SQL)

### 🧾 Module Percepteur (Cœur de métier)
- [x] **3 boutons d'action** bien en évidence : Normal / Orphelin / Actes gratuits
- [x] **Autocomplete téléphone** (dès le 3ème chiffre, AJAX)
- [x] **Déduplication patient** par numéro de téléphone (INSERT ou UPDATE)
- [x] Reçu Normal (Consultation 300F ± Carnet 100F)
- [x] Reçu Orphelin (gratuité totale, prix conservés pour reporting)
- [x] Reçu Actes gratuits (CPN, Nourrissons, Accouchement, Planning Familial)
- [x] Prescription d'examens avec génération reçu examen (zone vide labo)
- [x] Délivrance pharmacie (max 15 produits, décrémentation atomique stock)
- [x] Numérotation séquentielle globale (MAX(numero_recu) + 1)
- [x] Liste journalière (DataTable, isolation stricte par percepteur)
- [x] Filtre archives par plage de dates
- [x] Modal Récapitulatif patient (toutes opérations du jour)
- [x] Génération PDF A5 double exemplaire (logo DirectAid + zone vide labo)

### ⚙️ Module Paramétrage (Admin & Comptable)
- [x] CRUD Actes médicaux (payants + gratuits, tarif configurable)
- [x] CRUD Examens + pourcentage laborantin
- [x] CRUD Produits pharmaceutique (nom, forme, prix, stock, seuil, péremption)
- [x] Approvisionnements avec traçabilité (date + commentaire)
- [x] Inventaire (stock théorique vs actuel, écarts)
- [x] État de paie laborantin PDF (période sélectionnable)
- [x] Config entête reçus (nom, adresse, téléphone, logo, pied de page)
- [x] Upload logo avec validation MIME

### 📊 Tableau de Bord (Admin uniquement)
- [x] KPIs : Patients jour/7j/mois, Recettes jour, Coût actes gratuits, Alertes stock
- [x] Filtre recettes par période
- [x] Graphique barres : Évolution consultations 7 jours (Chart.js)
- [x] Graphique camembert : Répartition revenus (Consultation/Examen/Pharmacie)
- [x] Liste rouge alertes stock (rupture + périmés)
- [x] Productivité par percepteur (reçus + encaissé du jour)

### 🖨️ Génération PDF
- [x] Reçu consultation A5 double exemplaire (logo DirectAid)
- [x] Reçu orphelin avec mention GRATUIT diagonale en rouge
- [x] Reçu examen avec grande zone vide manuscrite pour labo
- [x] Reçu pharmacie (max 15 lignes : Désignation|Forme|Qté|P.U.|Total)
- [x] État de paie laborantin PDF
- [x] Fallback HTML auto-print si TCPDF absent

---

## 🏗️ Architecture du projet

```
csi_ama_maradi/
│
├── index.php                    ← Point d'entrée unique (Front Controller)
├── install.php                  ← Script d'installation (1 fois)
├── .htaccess                    ← Rewrite rules + sécurité Apache
├── .env.example                 ← Template variables d'environnement
├── .gitignore
│
├── config/
│   └── config.php               ← Constantes globales (BDD, tarifs, rôles)
│
├── core/
│   ├── autoload.php             ← Autoloader PSR-4 simplifié
│   ├── Database.php             ← Singleton PDO
│   ├── Session.php              ← Sessions + CSRF + Flash messages
│   └── helpers.php             ← Fonctions utilitaires globales
│
├── modules/
│   ├── auth/
│   │   ├── login.php            ← Page connexion
│   │   ├── logout.php           ← Déconnexion
│   │   └── utilisateurs.php    ← Gestion utilisateurs (Admin)
│   │
│   ├── percepteur/
│   │   ├── index.php            ← Interface principale percepteur
│   │   ├── save_consultation.php ← API : enregistrer consultation
│   │   ├── save_examens.php     ← API : enregistrer examens
│   │   ├── save_pharmacie.php   ← API : enregistrer pharmacie (transaction)
│   │   ├── save_acte_gratuit.php ← API : acte gratuit
│   │   └── get_recap.php        ← API : récapitulatif patient
│   │
│   ├── parametrage/
│   │   ├── index.php            ← Module paramétrage (tous onglets)
│   │   └── etat_labo.php        ← API : état paie laborantin
│   │
│   ├── dashboard/
│   │   └── index.php            ← Tableau de bord Admin
│   │
│   ├── pdf/
│   │   └── PdfGenerator.php     ← Génération PDF TCPDF / Fallback HTML
│   │
│   └── api/
│       └── patients.php         ← API autocomplete téléphone
│
├── templates/
│   ├── layouts/
│   │   ├── header.php           ← Navbar + Flash + Bootstrap
│   │   └── footer.php           ← Scripts JS
│   └── errors/
│       ├── 403.php
│       └── 404.php
│
├── assets/
│   ├── css/main.css             ← Charte graphique VERT #2e7d32
│   └── js/app.js                ← CSRF AJAX, autocomplete, toasts, charts
│
├── uploads/
│   ├── logos/
│   │   └── logo_csi.png         ← Logo DirectAid (العون المباشر)
│   └── pdf/                     ← Reçus générés (gitignored)
│
└── database/
    ├── migrations/
    │   └── 001_schema.sql       ← Schéma complet (13 tables)
    └── seeds/
        └── 001_seed_data.sql    ← Données de référence + actes pré-configurés
```

---

## 🗄️ Structure BDD

### Convention universelle de traçabilité (toutes tables)
| Champ | Type MySQL | Valeur par défaut | Description |
|-------|-----------|-------------------|-------------|
| `whendone` | DATETIME | NOW() | Date/heure création |
| `whodone` | INT UNSIGNED | ID session | Utilisateur créateur |
| `isDeleted` | TINYINT(1) | 0 | Soft delete (jamais DELETE SQL) |
| `lastUpdate` | TIMESTAMP | ON UPDATE | Mise à jour automatique |

### Tables principales
| Table | Description |
|-------|-------------|
| `utilisateurs` | Comptes (admin, comptable, percepteur) |
| `config_systeme` | Configuration centre + logo |
| `patients` | Fiche patient (dédupliqué par téléphone) |
| `actes_medicaux` | Actes payants + gratuits |
| `types_carnets` | Carnet de Soins (100F) / Carnet de Santé (GRATUIT) |
| `examens` | Examens + % laborantin (montant_labo calculé) |
| `produits_pharmacie` | Stock + seuil alerte + date péremption |
| `approvisionnements_pharmacie` | Historique entrées stock |
| `recus` | Table maîtresse (consultation/examen/pharmacie) |
| `lignes_consultation` | Détail reçus consultation |
| `lignes_examen` | Détail reçus examens |
| `lignes_pharmacie` | Détail reçus pharmacie |
| `inventaire_physique` | Rapprochement stock théorique/physique |

---

## 👤 Comptes de test

> ⚠️ **Changer les mots de passe en production !**

| Login | Mot de passe | Rôle | Accès |
|-------|-------------|------|-------|
| `admin` | `Admin@CSI2026` | **Administrateur** | Tout + Dashboard global + Gestion utilisateurs |
| `comptable` | `Compta@CSI2026` | **Comptable** | Paramétrage complet (sans gestion RH ni Dashboard) |
| `percepteur1` | `Percep1@CSI2026` | **Percepteur** | Espace percepteur (ses données uniquement) |
| `percepteur2` | `Percep2@CSI2026` | **Percepteur** | Espace percepteur (ses données uniquement) |

---

## 🚀 Guide d'installation

### Prérequis
- PHP 8.0+ avec extensions : `pdo_mysql`, `fileinfo`, `json`, `mbstring`
- MySQL 8.0+
- Apache 2.4+ avec `mod_rewrite` activé
- Composer (optionnel, pour TCPDF)

### Étape 1 – Cloner le dépôt
```bash
git clone https://github.com/NasserKailou/csi_ama_maradi.git
cd csi_ama_maradi
```

### Étape 2 – Configurer l'environnement
```bash
cp .env.example .env
nano .env
# Modifier DB_HOST, DB_USER, DB_PASS, DB_NAME
```

### Étape 3 – Installer TCPDF (PDF natif, optionnel)
```bash
composer require tecnickcom/tcpdf
```
> Si TCPDF absent, les reçus sont générés en HTML avec auto-impression.

### Étape 4 – Lancer l'installation
```bash
php install.php
```
Ce script :
- Crée la base de données `csi_ama`
- Applique le schéma SQL (13 tables)
- Insère les données de référence (actes, examens, produits)
- Crée les **4 comptes de test** avec mots de passe hachés (bcrypt cost=12)

### Étape 5 – Configurer Apache
```apache
<VirtualHost *:80>
    ServerName csi.local
    DocumentRoot /var/www/csi_ama_maradi
    
    <Directory /var/www/csi_ama_maradi>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Étape 6 – Permissions
```bash
chmod 755 uploads/logos uploads/pdf
chown -R www-data:www-data uploads/
```

### Étape 7 – Accéder à l'application
```
http://csi.local/
http://localhost/csi_ama_maradi/
```

---

## 🌐 Guide de déploiement

### Option A – Serveur local (XAMPP/Laragon/WAMP)
1. Copier le projet dans `htdocs/` ou `www/`
2. Lancer MySQL et créer un user dédié
3. Modifier `.env` avec les credentials
4. Exécuter `php install.php`
5. Accéder via `http://localhost/csi_ama_maradi/`

### Option B – Serveur VPS/dédié (Linux)
```bash
# Installation dépendances
sudo apt update && sudo apt install -y php8.1 php8.1-mysql php8.1-mbstring \
    apache2 mysql-server composer

# Activer mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# Déployer
cd /var/www/html
sudo git clone https://github.com/NasserKailou/csi_ama_maradi.git
cd csi_ama_maradi
sudo cp .env.example .env
sudo nano .env  # configurer BDD

# Optionnel TCPDF
composer require tecnickcom/tcpdf

# Installation
sudo php install.php

# Permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 uploads/
```

### Option C – Hébergement mutualisé (cPanel)
1. Uploader les fichiers via FTP dans `public_html/csi/`
2. Créer la BDD MySQL dans cPanel
3. Configurer `.env` avec les paramètres cPanel
4. Exécuter `install.php` via l'outil Terminal cPanel ou SSH
5. Accéder à `https://domaine.com/csi/`

### Mise à jour
```bash
git pull origin main
# Si nouvelles migrations :
php -r "
  require 'config/config.php';
  \$pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME), DB_USER, DB_PASS);
  \$pdo->exec(file_get_contents('database/migrations/002_....sql'));
  echo 'Migration OK';
"
```

---

## ⚖️ Règles métier critiques

| # | Règle | Implémentation |
|---|-------|----------------|
| 1 | **Déduplication patient** par téléphone | `INSERT ... ON DUPLICATE KEY UPDATE` |
| 2 | **Soft Delete obligatoire** | `UPDATE ... SET isDeleted=1` (jamais DELETE) |
| 3 | **Isolation percepteur** | `WHERE whodone = $_SESSION['user_id']` |
| 4 | **Numérotation séquentielle** | `MAX(numero_recu) + 1` (jamais réinitialisé) |
| 5 | **Orphelins** | montant_encaisse = 0, prix conservés en BDD |
| 6 | **Auto-décrémentation stock** | Transaction `BEGIN/COMMIT/ROLLBACK` atomique |
| 7 | **Paramétrage préalable** | Percepteur = menus déroulants uniquement |
| 8 | **PDF A5 double exemplaire** | 2 copies identiques sur même document |
| 9 | **Autocomplete** | AJAX dès le 3ème chiffre du téléphone |
| 10 | **Blocage périmés/rupture** | Contrôle PHP serveur + visuel grisé frontend |

---

## 🔌 API endpoints

| Méthode | URL | Description | Auth |
|---------|-----|-------------|------|
| `GET` | `/modules/api/patients.php?q=XXX` | Autocomplete patients par téléphone | Percepteur+ |
| `POST` | `/modules/percepteur/save_consultation.php` | Enregistrer consultation + PDF | Percepteur+ |
| `POST` | `/modules/percepteur/save_examens.php` | Enregistrer examens + PDF | Percepteur+ |
| `POST` | `/modules/percepteur/save_pharmacie.php` | Délivrance pharmacie + PDF | Percepteur+ |
| `POST` | `/modules/percepteur/save_acte_gratuit.php` | Acte gratuit + PDF | Percepteur+ |
| `GET` | `/modules/percepteur/get_recap.php?recu_id=X` | Récapitulatif patient | Percepteur+ |
| `POST` | `/index.php?page=parametrage` | CRUD paramétrage | Admin/Comptable |
| `GET` | `/modules/parametrage/etat_labo.php` | État paie labo PDF | Admin/Comptable |
| `POST` | `/index.php?page=utilisateurs` | Gestion utilisateurs | Admin |

> Tous les endpoints POST requièrent le **token CSRF** (`X-CSRF-TOKEN` header ou `csrf_token` champ).

---

## 🗺️ Feuille de route (Roadmap)

### Phase 2 – Améliorations prioritaires
- [ ] Impression directe via navigateur (auto-print au chargement PDF)
- [ ] Export Excel inventaire (PhpSpreadsheet)
- [ ] Rapport mensuel PDF global
- [ ] Historique approvisionnements visible par produit
- [ ] Recherche patient par nom (en plus du téléphone)

### Phase 3 – Fonctionnalités avancées
- [ ] Module infirmier (suivi traitements)
- [ ] Statistiques épidémiologiques (fréquence actes/maladies)
- [ ] Notifications stock (email ou SMS)
- [ ] Sauvegarde automatique BDD (cron job)
- [ ] Application mobile PWA

---

## 🛡️ Sécurité implémentée

- ✅ Protection CSRF (token 64 hex régénéré après login)
- ✅ Mots de passe hachés bcrypt cost=12
- ✅ Sessions PHP strictes (httponly, samesite=Strict)
- ✅ Régénération session_id après connexion
- ✅ Soft delete (aucun DELETE SQL dans le code)
- ✅ Validation et sanitisation serveur PHP
- ✅ PDO avec requêtes préparées (protection SQLi)
- ✅ Upload fichier avec validation MIME réelle (finfo)
- ✅ Headers Apache sécurité (X-Frame-Options, XSS-Protection...)
- ✅ .htaccess bloque l'accès aux fichiers sensibles (.sql, .env, .log)

---

## 📞 Support

**Projet** : Système de Gestion CSI – AMA Maradi  
**Version** : 1.0 · Mars 2026  
**Stack** : PHP 8 + MySQL 8 + Bootstrap 5 + Chart.js + TCPDF  
**Dépôt** : https://github.com/NasserKailou/csi_ama_maradi

---

*Développé pour le Centre de Santé Intégré AMA Maradi avec le soutien de DirectAid (العون المباشر)*
