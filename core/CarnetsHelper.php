<?php
/**
 * CarnetsHelper – Gestion centralisée des stocks de carnets
 * Deux types distincts :
 *   - 'soins' : carnet de soins (reçus normaux avec carnet)
 *   - 'sante' : carnet de santé (actes gratuits avec carnet)
 */
class CarnetsHelper
{
    public const TYPE_SOINS = 'soins';
    public const TYPE_SANTE = 'sante';

    private const CLES = [
        self::TYPE_SOINS => [
            'stock' => 'stock_carnets_soins',
            'seuil' => 'seuil_alerte_carnets_soins',
            'label' => 'Carnet de soins',
        ],
        self::TYPE_SANTE => [
            'stock' => 'stock_carnets_sante',
            'seuil' => 'seuil_alerte_carnets_sante',
            'label' => 'Carnet de santé',
        ],
    ];

    /**
     * Initialise les clés manquantes dans config_systeme (idempotent).
     */
    public static function ensureConfig(PDO $pdo, int $userId = 0): void
    {
        foreach (self::CLES as $type => $info) {
            // Stock
            $pdo->prepare(
                "INSERT IGNORE INTO config_systeme (cle, valeur, whodone) VALUES (:k, '0', :w)"
            )->execute([':k' => $info['stock'], ':w' => $userId]);
            // Seuil
            $pdo->prepare(
                "INSERT IGNORE INTO config_systeme (cle, valeur, whodone) VALUES (:k, '10', :w)"
            )->execute([':k' => $info['seuil'], ':w' => $userId]);
        }
    }

    /**
     * Récupère stock + seuil d'un type de carnet.
     */
    public static function getStock(PDO $pdo, string $type): array
    {
        if (!isset(self::CLES[$type])) {
            throw new InvalidArgumentException("Type de carnet invalide : $type");
        }
        $info = self::CLES[$type];
        $rows = $pdo->prepare(
            "SELECT cle, valeur FROM config_systeme WHERE cle IN (:s, :a) AND isDeleted=0"
        );
        $rows->execute([':s' => $info['stock'], ':a' => $info['seuil']]);
        $data = $rows->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'type'   => $type,
            'label'  => $info['label'],
            'stock'  => (int)($data[$info['stock']] ?? 0),
            'seuil'  => (int)($data[$info['seuil']] ?? 10),
        ];
    }

    /**
     * Récupère les deux stocks d'un coup.
     */
    public static function getAllStocks(PDO $pdo): array
    {
        return [
            self::TYPE_SOINS => self::getStock($pdo, self::TYPE_SOINS),
            self::TYPE_SANTE => self::getStock($pdo, self::TYPE_SANTE),
        ];
    }

    /**
     * Décrémente le stock d'un type de carnet + trace le mouvement.
     * Retourne ['stock_avant'=>..,'stock_apres'=>..,'alerte'=>..].
     * Si stock_avant <= 0, on ne décrémente pas mais on retourne une alerte.
     */
    public static function decrement(
        PDO $pdo,
        string $type,
        int $recuId,
        string $commentaire,
        int $userId
    ): array {
        if (!isset(self::CLES[$type])) {
            throw new InvalidArgumentException("Type de carnet invalide : $type");
        }
        $info = self::CLES[$type];
        $infos = self::getStock($pdo, $type);
        $stockAvant = $infos['stock'];
        $seuil      = $infos['seuil'];

        if ($stockAvant <= 0) {
            return [
                'stock_avant' => 0,
                'stock_apres' => 0,
                'seuil'       => $seuil,
                'alerte'      => 'ATTENTION : Stock de ' . strtolower($info['label']) . ' épuisé — carnet non décompté.',
                'decremente'  => false,
            ];
        }

        $stockApres = $stockAvant - 1;

        // Mise à jour stock
        $pdo->prepare(
            "INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w)
             ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2"
        )->execute([
            ':k' => $info['stock'], ':v' => $stockApres, ':w' => $userId,
            ':v2' => $stockApres, ':w2' => $userId,
        ]);

        // Mouvement
        $pdo->prepare(
            "INSERT INTO mouvements_carnets
                (type_mvt, type_carnet, quantite, stock_avant, stock_apres, recu_id, commentaire, whodone)
             VALUES ('sortie', :tc, -1, :sb, :sa, :rid, :cmt, :w)"
        )->execute([
            ':tc'  => $type,
            ':sb'  => $stockAvant,
            ':sa'  => $stockApres,
            ':rid' => $recuId,
            ':cmt' => $commentaire,
            ':w'   => $userId,
        ]);

        $alerte = '';
        if ($stockApres === 0) {
            $alerte = 'ATTENTION : Plus aucun ' . strtolower($info['label']) . ' disponible !';
        } elseif ($stockApres <= $seuil) {
            $alerte = 'Attention : Stock ' . strtolower($info['label']) . ' bas — Reste ' . $stockApres . ' carnet(s).';
        }

        return [
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres,
            'seuil'       => $seuil,
            'alerte'      => $alerte,
            'decremente'  => true,
        ];
    }

    /**
     * Incrémente le stock (réapprovisionnement / correction).
     */
    public static function increment(
        PDO $pdo,
        string $type,
        int $quantite,
        string $commentaire,
        int $userId,
        string $typeMvt = 'initialisation'
    ): array {
        if (!isset(self::CLES[$type])) {
            throw new InvalidArgumentException("Type de carnet invalide : $type");
        }
        if ($quantite <= 0) {
            throw new InvalidArgumentException("La quantité doit être > 0.");
        }
        $info  = self::CLES[$type];
        $infos = self::getStock($pdo, $type);
        $stockAvant = $infos['stock'];
        $stockApres = $stockAvant + $quantite;

        $pdo->prepare(
            "INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w)
             ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2"
        )->execute([
            ':k' => $info['stock'], ':v' => $stockApres, ':w' => $userId,
            ':v2' => $stockApres, ':w2' => $userId,
        ]);

        $pdo->prepare(
            "INSERT INTO mouvements_carnets
                (type_mvt, type_carnet, quantite, stock_avant, stock_apres, commentaire, whodone)
             VALUES (:tm, :tc, :q, :sb, :sa, :cmt, :w)"
        )->execute([
            ':tm'  => $typeMvt,
            ':tc'  => $type,
            ':q'   => $quantite,
            ':sb'  => $stockAvant,
            ':sa'  => $stockApres,
            ':cmt' => $commentaire,
            ':w'   => $userId,
        ]);

        return ['stock_avant' => $stockAvant, 'stock_apres' => $stockApres];
    }

    /**
     * Met à jour le seuil d'alerte.
     */
    public static function updateSeuil(PDO $pdo, string $type, int $seuil, int $userId): void
    {
        if (!isset(self::CLES[$type])) return;
        $cle = self::CLES[$type]['seuil'];
        $pdo->prepare(
            "INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w)
             ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2"
        )->execute([
            ':k' => $cle, ':v' => max(0, $seuil), ':w' => $userId,
            ':v2' => max(0, $seuil), ':w2' => $userId,
        ]);
    }
}
