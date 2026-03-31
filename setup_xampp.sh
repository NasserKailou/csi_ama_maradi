#!/bin/bash
# ================================================================
#  Setup XAMPP/LAMP – Système CSI AMA Maradi
#  Ubuntu/Debian + XAMPP Linux ou LAMP natif
# ================================================================

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN=$(which php 2>/dev/null || echo "/opt/lampp/bin/php")
COMPOSER_BIN=$(which composer 2>/dev/null || echo "")

echo ""
echo "================================================================"
echo "  Système CSI AMA Maradi – Installation Linux/XAMPP"
echo "================================================================"
echo ""

# -- Vérifier PHP --
if ! command -v "$PHP_BIN" &>/dev/null && [ ! -f "$PHP_BIN" ]; then
    echo "[ERR] PHP non trouvé. Installez PHP 8+ ou XAMPP."
    exit 1
fi
echo "[OK]  PHP trouvé : $PHP_BIN"

# -- Copier .env si nécessaire --
if [ ! -f "$PROJECT_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
    echo "[OK]  Fichier .env créé depuis .env.example"
    echo "[!!]  ÉDITEZ .env si nécessaire (DB_PASS, APP_URL, etc.)"
fi

# -- Permissions dossiers --
mkdir -p "$PROJECT_DIR/uploads/logos"
mkdir -p "$PROJECT_DIR/uploads/pdf"
chmod -R 755 "$PROJECT_DIR/uploads"
echo "[OK]  Dossiers uploads créés avec permissions 755"

# -- Installer Composer / TCPDF --
if [ -n "$COMPOSER_BIN" ]; then
    echo "[..]  Installation des dépendances Composer (TCPDF)..."
    cd "$PROJECT_DIR"
    composer install --no-dev --optimize-autoloader 2>&1
    echo "[OK]  Composer terminé"
else
    echo "[!!]  Composer non trouvé. TCPDF peut ne pas être disponible."
    echo "      Reçus générés en HTML (mode de secours)."
fi

# -- Exécuter le script d'installation PHP --
echo ""
echo "[..]  Lancement du script d'installation PHP..."
echo ""
$PHP_BIN "$PROJECT_DIR/install.php"

echo ""
echo "================================================================"
echo "  Installation terminée !"
echo ""
echo "  Pour XAMPP Linux :"
echo "  Copiez ce dossier dans : /opt/lampp/htdocs/csi_ama_maradi/"
echo ""
echo "  Accédez à l'application :"
echo "  http://localhost/csi_ama_maradi/"
echo "================================================================"
echo ""
