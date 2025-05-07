<?php

// Script pour synchroniser les niveaux des poubelles avec l'historique le plus récent

require_once __DIR__ . '/../../vendor/autoload.php';

use SmartBin\Services\DatabaseService;

function syncBinLevels() {
    $db = DatabaseService::getConnection();
    
    // Mettre à jour les niveaux des poubelles avec les dernières valeurs de l'historique
    $sql = "
        UPDATE bins b
        SET trash_level = (
            SELECT h.level
            FROM history h
            WHERE h.id = b.id
            AND h.date = (
                SELECT MAX(date) 
                FROM history h2 
                WHERE h2.id = h.id
            )
        )
        WHERE EXISTS (
            SELECT 1 
            FROM history h 
            WHERE h.id = b.id
        )
    ";
    
    try {
        $count = $db->exec($sql);
        echo "Niveaux de $count poubelles synchronisés avec succès.\n";
        return true;
    } catch (\PDOException $e) {
        echo "Erreur lors de la synchronisation: " . $e->getMessage() . "\n";
        return false;
    }
}

// Exécuter la synchronisation
syncBinLevels();