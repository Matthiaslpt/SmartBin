<?php

namespace SmartBin\Services;

use PDO;
use SmartBin\Services\DatabaseService;

class AnalyticsService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseService::getConnection();
    }

    /**
     * Calcule le taux de remplissage moyen pour chaque poubelle
     * @return array
     */
    public function getAverageFillRates(): array
    {
        $stmt = $this->db->query("
            SELECT b.id, b.address, AVG(h.level) as avg_level
            FROM bins b
            JOIN history h ON b.id = h.id
            GROUP BY b.id, b.address
            ORDER BY avg_level DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Détermine les poubelles qui nécessitent une collecte (niveau > seuil)
     * @param int $threshold Seuil au-delà duquel une collecte est recommandée
     * @return array
     */
    public function getBinsNeedingCollection(int $threshold = 70): array
    {
        $stmt = $this->db->prepare("
            SELECT id, address, lat, lng, trash_level
            FROM bins
            WHERE trash_level >= ?
            ORDER BY trash_level DESC
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule le taux de croissance du niveau des poubelles
     * @return array
     */
    public function getFillRateGrowth(): array
    {
        $stmt = $this->db->query("
            WITH rate_changes AS (
                SELECT 
                    h1.id,
                    h1.date,
                    h1.level,
                    h2.level as prev_level,
                    h2.date as prev_date,
                    (h1.level - h2.level) as level_change,
                    (h1.date - h2.date) as days_diff
                FROM history h1
                JOIN history h2 ON h1.id = h2.id AND h2.date = (
                    SELECT MAX(h3.date) 
                    FROM history h3 
                    WHERE h3.id = h1.id AND h3.date < h1.date
                )
            )
            SELECT 
                b.id, 
                b.address,
                AVG(rc.level_change / GREATEST(rc.days_diff, 1)) as avg_daily_growth
            FROM bins b
            JOIN rate_changes rc ON b.id = rc.id
            GROUP BY b.id, b.address
            ORDER BY avg_daily_growth DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prévoit quand les poubelles atteindront un niveau critique
     * @param int $criticalLevel Niveau considéré comme critique
     * @return array
     */
    public function predictCriticalLevels(int $criticalLevel = 80): array
    {
        $results = [];
        $fillRates = $this->getFillRateGrowth();
        $bins = $this->db->query("SELECT id, address, trash_level FROM bins")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bins as $bin) {
            $binGrowth = 0;
            foreach ($fillRates as $rate) {
                if ($rate['id'] == $bin['id']) {
                    $binGrowth = $rate['avg_daily_growth'];
                    break;
                }
            }
            
            // Si le taux de croissance est positif, calculer les jours restants
            if ($binGrowth > 0) {
                $currentLevel = $bin['trash_level'];
                $daysUntilCritical = ($criticalLevel - $currentLevel) / $binGrowth;
                $criticalDate = date('Y-m-d', strtotime("+{$daysUntilCritical} days"));
                
                $results[] = [
                    'id' => $bin['id'],
                    'address' => $bin['address'],
                    'current_level' => $currentLevel,
                    'days_until_critical' => ceil($daysUntilCritical),
                    'critical_date' => $criticalDate
                ];
            } else {
                // Si le taux de croissance n'est pas positif, la poubelle n'atteindra pas le niveau critique
                $results[] = [
                    'id' => $bin['id'],
                    'address' => $bin['address'],
                    'current_level' => $bin['trash_level'],
                    'days_until_critical' => null,
                    'critical_date' => null
                ];
            }
        }
        
        // Trier par jours restants (les plus urgents d'abord)
        usort($results, function($a, $b) {
            if ($a['days_until_critical'] === null) return 1;
            if ($b['days_until_critical'] === null) return -1;
            return $a['days_until_critical'] <=> $b['days_until_critical'];
        });
        
        return $results;
    }

    /**
     * Récupère toutes les déchetteries
     * @return array
     */
    public function getWasteCenters(): array
    {
        $stmt = $this->db->query("SELECT * FROM waste_centers");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Trouve la déchetterie la plus proche d'une liste de poubelles
     * @param array $bins Liste des poubelles à collecter
     * @return array|null La déchetterie la plus proche ou null si aucune déchetterie
     */
    public function getNearestWasteCenter(array $bins): ?array
    {
        if (empty($bins)) {
            return null;
        }
        
        // Calculer le centre géographique des poubelles
        $totalLat = 0;
        $totalLng = 0;
        foreach ($bins as $bin) {
            $totalLat += $bin['lat'];
            $totalLng += $bin['lng'];
        }
        $centerLat = $totalLat / count($bins);
        $centerLng = $totalLng / count($bins);
        
        // Récupérer toutes les déchetteries
        $wasteCenters = $this->getWasteCenters();
        if (empty($wasteCenters)) {
            return null;
        }
        
        // Trouver la déchetterie la plus proche du centre de toutes les poubelles
        $nearestCenter = null;
        $minDistance = PHP_FLOAT_MAX;
        
        foreach ($wasteCenters as $center) {
            $distance = $this->calculateDistance(
                $centerLat,
                $centerLng,
                $center['lat'],
                $center['lng']
            );
            
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestCenter = $center;
            }
        }
        
        return $nearestCenter;
    }

    /**
     * Génère un itinéraire optimisé pour la collecte des poubelles en partant de la déchetterie la plus proche
     * @param array $binsToCollect Les poubelles à collecter
     * @return array L'itinéraire optimisé incluant la déchetterie de départ
     */
    public function generateOptimizedRoute(array $binsToCollect): array
    {
        if (empty($binsToCollect)) {
            return [];
        }
        
        // Trouver la déchetterie la plus proche
        $wasteCenterStart = $this->getNearestWasteCenter($binsToCollect);
        
        // Si aucune déchetterie n'est trouvée, utiliser l'ancien algorithme
        if (!$wasteCenterStart) {
            return $this->generateSimpleRoute($binsToCollect);
        }
        
        // Convertir la déchetterie en point de l'itinéraire
        $startPoint = [
            'id' => 'wc-' . $wasteCenterStart['id'],
            'name' => $wasteCenterStart['name'],
            'address' => $wasteCenterStart['address'],
            'lat' => $wasteCenterStart['lat'],
            'lng' => $wasteCenterStart['lng'],
            'is_waste_center' => true,
            'trash_level' => 0 // Les déchetteries n'ont pas de niveau de remplissage
        ];
        
        // Initialiser l'itinéraire avec le point de départ
        $route = [$startPoint];
        $remaining = $binsToCollect;
        $current = $startPoint;
        
        // Tant qu'il reste des poubelles à collecter, trouver la plus proche
        while (count($remaining) > 0) {
            $nearest = null;
            $minDistance = PHP_FLOAT_MAX;
            $nearestIndex = -1;
            
            for ($i = 0; $i < count($remaining); $i++) {
                $distance = $this->calculateDistance(
                    $current['lat'],
                    $current['lng'],
                    $remaining[$i]['lat'],
                    $remaining[$i]['lng']
                );
                
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearest = $remaining[$i];
                    $nearestIndex = $i;
                }
            }
            
            // Ajouter la poubelle la plus proche
            $route[] = $nearest;
            $current = $nearest;
            array_splice($remaining, $nearestIndex, 1);
        }
        
        // Ajouter la déchetterie comme point final pour créer une boucle
        $route[] = $startPoint;
        
        return $route;
    }

    /**
     * Génération d'itinéraire simple (ancien algorithme) sans déchetterie
     * @param array $bins Liste des poubelles à collecter
     * @return array
     */
    private function generateSimpleRoute(array $bins): array
    {
        if (count($bins) <= 1) {
            return $bins;
        }
        
        $route = [];
        $remaining = $bins;
        
        // Commencer par la première poubelle
        $current = array_shift($remaining);
        $route[] = $current;
        
        // Parcourir les poubelles restantes par proximité
        while (count($remaining) > 0) {
            $nearest = null;
            $minDistance = PHP_FLOAT_MAX;
            $nearestIndex = -1;
            
            for ($i = 0; $i < count($remaining); $i++) {
                $distance = $this->calculateDistance(
                    $current['lat'],
                    $current['lng'],
                    $remaining[$i]['lat'],
                    $remaining[$i]['lng']
                );
                
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearest = $remaining[$i];
                    $nearestIndex = $i;
                }
            }
            
            $route[] = $nearest;
            $current = $nearest;
            array_splice($remaining, $nearestIndex, 1);
        }
        
        return $route;
    }
    
    /**
     * Calcule la distance entre deux points géographiques
     * @return float Distance en kilomètres
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Formule de la distance de Haversine
        $earthRadius = 6371; // Rayon de la Terre en kilomètres
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;
        
        return $distance;
    }
}