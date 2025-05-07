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
            $stmt = $this->db->query("SELECT id, address, lat, lng, trash_level FROM bins");
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
}