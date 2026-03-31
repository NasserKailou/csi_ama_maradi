# 🏥 Système de Gestion CSI – AMA Maradi

> **Version** : 1.0 · **Auteur** : NasserKailou · **Dernière mise à jour** : Mars 2026  
> **Stack** : PHP 8 · MySQL 8 · Bootstrap 5 · JavaScript ES6+

---

## 📋 Présentation

Application web complète pour la gestion du **Centre de Santé Intégré (CSI) AMA Maradi** (Niger).  
Elle couvre :
- **Réception & facturation** des patients (consultation, examens, pharmacie)
- **Gestion du stock pharmaceutique** avec alertes et décrément automatique
- **Suivi des examens** et état de paie du laborantin
- **Tableau de bord administrateur** avec KPIs et graphiques
- **Gestion des utilisateurs** avec 3 niveaux de rôle

---

## 🔑 Comptes de Test

| Login | Mot de passe | Rôle | Accès |
|-------|-------------|------|-------|
| `admin` | `Admin@CSI2026` | Administrateur | Accès total |
| `comptable` | `Compta@CSI2026` | Comptable | Paramétrage (sans gestion utilisateurs) |
| `percepteur1` | `Percep1@CSI2026` | Percepteur | Réception patients & reçus |
| `percepteur2` | `Percep2@CSI2026` | Percepteur | Réception patients & reçus |

---

## 🏗️ Architecture du Projet

```
csi_ama_maradi/
│
├── index.php                    # Point d'entrée unique (routeur)
├── install.php                  # Script d'installation (exécuter 1x)
├── .htaccess                    # Config Apache + réécriture d'URL
├── .env.example                 # Template variables d'environnement
├── .gitignore
│
├── config/
│   └── config.php               # Constantes globales (BDD, rôles, tarifs)
│
├── core/
│   ├── Database.php             # Singleton PDO
│   ├── Session.php              # Gestion session + CSRF
│   ├── helpers.php              # Fonctions utilitaires globales
│   └── autoload.php             # Autoloader PSR-4 simplifié
│
├── modules/
│   ├── auth/
│   │   ├── login.php            # Page de connexion
│   │   ├── logout.php           # Déconnexion
│   │   └── utilisateurs.php     # CRUD utilisateurs (Admin)
│   │
│   ├── percepteur/
│   │   ├── index.php            # Interface principale percepteur
│   │   ├── save_consultation.php # API: Enregistrer consultation
│   │   ├── save_examens.php     # API: Prescrire examens
│   │   ├── save_pharmacie.php   # API: Délivrance pharmacie
│   │   ├── save_acte_gratuit.php # API: Acte gratuit
│   │   └── get_recap.php        # API: Récapitulatif patient
│   │
│   ├── parametrage/
│   │   └── index.php            # Module paramétrage (6 sections)
│   │
│   ├── dashboard/
│   │   └── index.php            # Tableau de bord Admin
│   │
│   ├── pdf/
│   │   ├── PdfGenerator.php     # Classe génération PDF (HTML imprimable)
│   │   └── etat_labo.php        # Endpoint état de paie laborantin
│   │
│   └── api/
│       └── patients.php         # API autocomplete téléphone (AJAX)
│
├── templates/
│   ├── layouts/
│   │   ├── header.php           # Navbar + head HTML commun
│   │   └── footer.php           # Scripts JS + footer
│   └── errors/
│       ├── 403.php              # Page accès refusé
│       └── 404.php              # Page introuvable
│
├── assets/
│   ├── css/
│   │   └── main.css             # Charte graphique VERT #2e7d32
│   └── js/
│       └── app.js               # JS global (CSRF, Toast, DataTables, Autocomplete)
│
├── database/
│   ├── migrations/
│   │   └── 001_schema.sql       # Schéma complet BDD (13 tables)
│   └── seeds/
│       └── 001_seed_data.sql    # Données de référence + produits test
│
└── uploads/
    ├── logos/                   # Logo du centre (upload)
    └── pdf/                     # Reçus générés (HTML imprimables)
```

---

## 🗄️ Architecture Base de Données

### Convention Universelle (toutes les tables)
| Champ | Type | Description |
|-------|------|-------------|
| `whendone` | DATETIME | Date/heure de création (auto) |
| `whodone` | INT UNSIGNED | ID utilisateur créateur |
| `isDeleted` | TINYINT(1) | Soft delete : 0=actif, 1=archivé |
| `lastUpdate` | TIMESTAMP | Dernière modification (auto) |

> ⚠️ **INTERDICTION** : Aucune commande `DELETE SQL` dans le code. Utiliser `UPDATE ... SET isDeleted=1`

### Tables Principales
| Table | Description |
|-------|-------------|
| `utilisateurs` | Comptes système (admin, comptable, percepteur) |
| `config_systeme` | Configuration centre (nom, logo, adresse) |
| `patients` | Patients – identifiés uniquement par téléphone |
| `actes_medicaux` | Actes médicaux configurés (payants + gratuits) |
| `types_carnets` | Carnets de soins/santé |
| `examens` | Examens + % laborantin |
| `produits_pharmacie` | Stock pharmaceutique |
| `approvisionnements_pharmacie` | Historique réapprovisionnements |
| `recus` | Table maîtresse de toutes les opérations |
| `lignes_consultation` | Détail reçus consultation |
| `lignes_examen` | Détail reçus examens |
| `lignes_pharmacie` | Détail reçus pharmacie |
| `inventaire_physique` | Rapprochement stock théorique/physique |

---

## 🚀 Guide de Déploiement

### Prérequis
- **PHP** ≥ 8.0 avec extensions : `pdo_mysql`, `fileinfo`, `mbstring`, `json`
- **MySQL** ≥ 8.0
- **Apache** avec `mod_rewrite` activé (ou Nginx avec config équivalente)

### Installation Locale (XAMPP/WAMP/Laragon)

#### Étape 1 – Cloner le dépôt
```bash
git clone https://github.com/NasserKailou/csi_ama_maradi.git
cd csi_ama_maradi
```

#### Étape 2 – Configurer l'environnement
```bash
cp .env.example .env
```
Éditer `.env` :
```env
APP_ENV=development
BASE_URL=http://localhost/csi_ama_maradi
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=csi_ama
DB_USER=root
DB_PASS=votre_mot_de_passe
```

#### Étape 3 – Exécuter l'installation
```bash
php install.php
```
Ce script va :
1. Créer la base de données `csi_ama`
2. Appliquer le schéma (13 tables)
3. Insérer les données de référence (actes, examens, produits)
4. Créer les 4 comptes de test avec mots de passe hachés (bcrypt cost=12)
5. Créer les dossiers `uploads/`

#### Étape 4 – Configurer le VirtualHost Apache
```apache
<VirtualHost *:80>
    DocumentRoot "/chemin/vers/csi_ama_maradi"
    ServerName csi.local
    <Directory "/chemin/vers/csi_ama_maradi">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Étape 5 – Accéder à l'application
Ouvrir : `http://csi.local` ou `http://localhost/csi_ama_maradi`

---

### Déploiement sur Hébergement Mutualisé (cPanel / Plesk)

1. **Uploader** les fichiers via FTP dans `public_html/` ou un sous-dossier
2. **Créer la base MySQL** via phpMyAdmin
3. **Configurer** `.env` avec les identifiants BDD de l'hébergeur
4. **Exécuter** `install.php` via le terminal ou en y accédant via navigateur
5. **Supprimer** `install.php` après installation !

---

### Déploiement sur Serveur VPS (Ubuntu/Debian)

```bash
# 1. Installer LAMP
sudo apt update
sudo apt install apache2 mysql-server php8.2 php8.2-mysql php8.2-mbstring php8.2-fileinfo libapache2-mod-php

# 2. Activer mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# 3. Cloner et configurer
cd /var/www/html
sudo git clone https://github.com/NasserKailou/csi_ama_maradi.git
sudo chown -R www-data:www-data csi_ama_maradi/
sudo chmod -R 755 csi_ama_maradi/
sudo chmod -R 775 csi_ama_maradi/uploads/

# 4. Configurer MySQL
sudo mysql -u root -p
CREATE DATABASE csi_ama CHARACTER SET utf8mb4;
CREATE USER 'csi_user'@'localhost' IDENTIFIED BY 'MotDePasseSecurise2026!';
GRANT ALL PRIVILEGES ON csi_ama.* TO 'csi_user'@'localhost';
FLUSH PRIVILEGES;

# 5. Lancer l'installation
cd /var/www/html/csi_ama_maradi
php install.php

# 6. SUPPRIMER install.php en production !
rm install.php
```

---

## ⚡ Fonctionnalités Implémentées

### Module Percepteur
- ✅ 3 boutons d'action : Reçu Normal / Orphelin / Actes Gratuits
- ✅ Formulaire patient avec **autocomplete AJAX** (dès 3 chiffres)
- ✅ **Déduplication patient** par numéro de téléphone
- ✅ Génération reçu consultation (PDF HTML A5 double exemplaire)
- ✅ Modal examens – prescription et reçu avec zone laborantin
- ✅ Modal pharmacie – max 15 produits, décrémentation stock automatique
- ✅ Blocage produits périmés / rupture de stock (UI + serveur)
- ✅ Liste journalière (DataTable) – isolation stricte par percepteur
- ✅ Récapitulatif consolidé par patient
- ✅ Filtre archives par plage de dates

### Module Paramétrage (Admin & Comptable)
- ✅ Gestion actes médicaux (CRUD + flag gratuit)
- ✅ Actes pré-configurés obligatoires (CPN, Accouchement, etc.)
- ✅ Gestion examens + % laborantin (calcul automatique montant)
- ✅ Gestion stock pharmaceutique (CRUD + seuil alerte + date péremption)
- ✅ Approvisionnements avec traçabilité
- ✅ Configuration entête reçus (nom, adresse, téléphone, logo upload)
- ✅ Inventaire comparatif stock théorique vs physique
- ✅ Génération état de paie laborantin (PDF)

### Tableau de Bord Admin
- ✅ KPIs : Patients jour/semaine/mois, Recettes, Coût actes gratuits, Alertes stock
- ✅ Filtre recettes sur période personnalisable
- ✅ Graphique barres : évolution consultations 7 derniers jours (Chart.js)
- ✅ Graphique camembert : répartition revenus par pôle
- ✅ Liste rouge alertes stock (rupture + périmé)
- ✅ Activité de productivité par percepteur

### Gestion Utilisateurs (Admin)
- ✅ CRUD complet (Créer, Modifier, Supprimer)
- ✅ 3 rôles : Administrateur, Comptable, Percepteur
- ✅ Suspension/Activation compte
- ✅ Soft delete (isDeleted = 1)

### Sécurité
- ✅ Protection CSRF (token sur tous les formulaires + AJAX headers)
- ✅ Hachage bcrypt cost=12 pour les mots de passe
- ✅ Sessions PHP sécurisées (httponly, samesite=Strict)
- ✅ Régénération ID session à la connexion
- ✅ Contrôles d'autorisation par rôle sur chaque route
- ✅ Isolation stricte données percepteur (WHERE whodone = $_SESSION['user_id'])
- ✅ Validation serveur sur toutes les entrées (pas de confiance au frontend)
- ✅ Soft delete universel (pas de DELETE SQL)
- ✅ Transactions atomiques (BEGIN/COMMIT/ROLLBACK) pour la pharmacie

---

## 📐 Règles Métier Respectées

| # | Règle | Statut |
|---|-------|--------|
| 1 | Déduplication patient par téléphone | ✅ |
| 2 | Soft Delete obligatoire | ✅ |
| 3 | Isolation données percepteur | ✅ |
| 4 | Numérotation séquentielle globale | ✅ |
| 5 | Cas orphelins – gratuité totale (prix conservés pour bailleurs) | ✅ |
| 6 | Auto-décrémentation stock avec transaction atomique | ✅ |
| 7 | Paramétrage préalable obligatoire (pas de saisie libre percepteur) | ✅ |
| 8 | Format A5 double exemplaire | ✅ |
| 9 | Autocomplete téléphone dès 3ème chiffre | ✅ |
| 10 | Blocage produits périmés / rupture (UI + serveur) | ✅ |

---

## 🔧 Configuration Nginx (alternative Apache)

```nginx
server {
    listen 80;
    server_name csi.local;
    root /var/www/html/csi_ama_maradi;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Protéger les fichiers sensibles
    location ~ \.(env|sql|log)$ { deny all; }
    
    # Uploads
    location /uploads/pdf/ {
        add_header Content-Disposition "inline";
    }
}
```

---

## 📦 Technologies Utilisées

| Composant | Version | Usage |
|-----------|---------|-------|
| PHP | 8.0+ | Backend (sans framework) |
| MySQL | 8.0+ | Base de données |
| Bootstrap | 5.3.3 | UI (modaux, grilles, composants) |
| Bootstrap Icons | 1.11.3 | Icônes |
| jQuery | 3.7.1 | AJAX, manipulation DOM |
| DataTables | 1.13.8 | Tableaux interactifs |
| Chart.js | 4.4.2 | Graphiques dashboard |
| Apache | 2.4+ | Serveur web |

---

## 🗂️ Variables d'Environnement

| Variable | Défaut | Description |
|----------|--------|-------------|
| `APP_ENV` | `development` | `development` ou `production` |
| `BASE_URL` | `http://localhost` | URL de base de l'application |
| `DB_HOST` | `127.0.0.1` | Hôte MySQL |
| `DB_PORT` | `3306` | Port MySQL |
| `DB_NAME` | `csi_ama` | Nom de la base de données |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | *(vide)* | Mot de passe MySQL |

---

## ⏭️ Fonctionnalités à Développer (Backlog)

- [ ] Export Excel inventaire (`PhpSpreadsheet`)
- [ ] Intégration TCPDF pour génération PDF native (à la place du HTML imprimable)
- [ ] Notifications temps réel (alertes stock via WebSocket ou polling)
- [ ] Module rapport mensuel automatique (email)
- [ ] API REST complète pour intégration mobile
- [ ] Sauvegarde automatique BDD planifiée (cron)
- [ ] Module de gestion des ordonnances

---

## 📞 Support

Dépôt GitHub : [https://github.com/NasserKailou/csi_ama_maradi](https://github.com/NasserKailou/csi_ama_maradi)

---

*Système CSI AMA Maradi – Développé selon les spécifications techniques v1.0 – Mars 2026*
