<?php

namespace SmartBin\Models;

use PDO;
use SmartBin\Services\DatabaseService;

class BinModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseService::getConnection();
    }

    public function getAllBins(): array
    {
        try {
            // Utiliser une sous-requête pour obtenir le niveau le plus récent de chaque poubelle
            $stmt = $this->db->query("
                SELECT b.id, b.address, b.lat, b.lng, 
                      COALESCE(h.level, b.trash_level) as trash_level
                FROM bins b
                LEFT JOIN (
                    SELECT id, level
                    FROM history h1
                    WHERE date = (
                        SELECT MAX(date) 
                        FROM history h2 
                        WHERE h2.id = h1.id
                    )
                ) h ON b.id = h.id
                ORDER BY b.id
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function getBinById(int $binId): ?array
    {
        try {
            // Récupérer les informations de base de la poubelle
            $stmtBin = $this->db->prepare("SELECT id, address, lat, lng, trash_level FROM bins WHERE id = ?");
            $stmtBin->execute([$binId]);
            $bin = $stmtBin->fetch(PDO::FETCH_ASSOC);

            if (!$bin) {
                return null;
            }

            // Récupérer l'historique de la poubelle
            $stmtHistory = $this->db->prepare("SELECT level, date FROM history WHERE id = ? ORDER BY date");
            $stmtHistory->execute([$binId]);
            $historyRows = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

            // Formater les dates dans l'historique
            $history = array_map(function ($row) {
                if (isset($row['date'])) {
                    $date = new \DateTime($row['date']);
                    $row['date'] = $date->format('Y-m-d');
                }
                return $row;
            }, $historyRows);

            // Ajouter l'historique aux informations de la poubelle
            $bin['history'] = $history;

            return $bin;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function addBin(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO bins (address, lat, lng, trash_level) 
                VALUES (?, ?, ?, ?) 
                RETURNING id
            ");

            $stmt->execute([
                $data['address'],
                $data['lat'],
                $data['lng'],
                $data['trash_level'] ?? 0
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['id'] ?? null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function updateBinLevel(int $binId, int $level, string $date = null): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Mise à jour du niveau dans la table bins
            $stmtBin = $this->db->prepare("
                UPDATE bins 
                SET trash_level = ?
                WHERE id = ?
            ");
            $stmtBin->execute([$level, $binId]);
            
            // Insertion dans l'historique
            $dateToUse = $date ?? date('Y-m-d');
            $stmtHistory = $this->db->prepare("
                INSERT INTO history (id, level, date)
                VALUES (?, ?, ?)
                ON CONFLICT (id, date) DO UPDATE SET level = ?
            ");
            $stmtHistory->execute([$binId, $level, $dateToUse, $level]);
            
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }
}