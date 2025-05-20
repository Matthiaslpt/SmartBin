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
        // Récupérer toutes les poubelles
        $stmt = $this->db->query("SELECT id, address FROM bins");
        $bins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // D'abord, vérifions la structure de la table history pour trouver la colonne de date
        $columns = $this->db->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'history'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        // Cherchons un nom de colonne qui pourrait correspondre à une date/heure
        $dateColumn = null;
        foreach ($columns as $column) {
            if (strpos($column, 'date') !== false) {
                $dateColumn = $column;
                break;
            }
        }
        
        
        $growthData = [];
        foreach ($bins as $bin) {
            // Récupérer l'historique des niveaux pour cette poubelle avec la colonne de date correcte
            $stmt = $this->db->prepare("
                SELECT level as fill_level, {$dateColumn} as timestamp 
                FROM history 
                WHERE id = ? 
                ORDER BY {$dateColumn} ASC 
                LIMIT 14
            ");
            $stmt->execute([$bin['id']]);
            $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($readings) > 1) {
                $dailyGrowth = [];
                $prevReading = null;
                $validGrowthValues = []; // Pour calculer la moyenne sans les vidages
                
                foreach ($readings as $reading) {
                    if ($prevReading) {
                        $diff = $reading['fill_level'] - $prevReading['fill_level'];
                        $timeDiff = (strtotime($reading['timestamp']) - strtotime($prevReading['timestamp'])) / 86400; // Différence en jours
                        
                        // Si la différence est fortement négative (par exemple moins de -20%), on considère que c'est un vidage
                        if ($diff < -20) {
                            // On ignore cette baisse dans le calcul de croissance car c'est un vidage
                            $dailyGrowth[] = [
                                'date' => date('Y-m-d', strtotime($reading['timestamp'])),
                                'growth' => $diff / ($timeDiff > 0 ? $timeDiff : 1), // Pour l'affichage
                                'is_collection' => true // Marquer qu'il s'agit d'un vidage
                            ];
                        } else {
                            // Calcul normal du taux de croissance
                            $growth = $timeDiff > 0 ? $diff / $timeDiff : 0;
                            $dailyGrowth[] = [
                                'date' => date('Y-m-d', strtotime($reading['timestamp'])),
                                'growth' => $growth,
                                'is_collection' => false
                            ];
                            
                            // N'ajouter à la moyenne que les valeurs de croissance positives
                            if ($growth > 0) {
                                $validGrowthValues[] = $growth;
                            }
                        }
                    }
                    $prevReading = $reading;
                }
                
                // Calculer la croissance moyenne en excluant les vidages et les valeurs négatives
                $avgDailyGrowth = !empty($validGrowthValues) ? 
                    array_sum($validGrowthValues) / count($validGrowthValues) : 0;
                
                $growthData[$bin['id']] = [
                    'bin_id' => $bin['id'],
                    'bin_address' => $bin['address'],
                    'bin_number' => $bin['id'], // Ajout du numéro de la poubelle
                    'daily_growth' => $dailyGrowth,
                    'avg_daily_growth' => $avgDailyGrowth
                ];
            }
        }
        
        return $growthData;
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
                if ($rate['bin_id'] == $bin['id']) {
                    $binGrowth = $rate['avg_daily_growth'];
                    break;
                }
            }
            
            $currentLevel = $bin['trash_level'];
            
            // Si le taux de croissance est positif et le niveau n'est pas encore critique
            if ($binGrowth > 0 && $currentLevel < $criticalLevel) {
                $daysUntilCritical = ($criticalLevel - $currentLevel) / $binGrowth;
                // Limiter les prévisions à un an maximum
                if ($daysUntilCritical > 365) {
                    $daysUntilCritical = 365;
                }
                // Assurons-nous que la date est correctement formatée
                $daysToAdd = (int)ceil($daysUntilCritical);
                $criticalDate = date('Y-m-d', strtotime("+{$daysToAdd} days"));
                
                $results[] = [
                    'id' => $bin['id'],
                    'address' => $bin['address'],
                    'current_level' => $currentLevel,
                    'days_until_critical' => ceil($daysUntilCritical),
                    'critical_date' => $criticalDate
                ];
            } 
            // Si le niveau est déjà critique
            else if ($currentLevel >= $criticalLevel) {
                $results[] = [
                    'id' => $bin['id'],
                    'address' => $bin['address'],
                    'current_level' => $currentLevel,
                    'days_until_critical' => 0,
                    'critical_date' => date('Y-m-d') // Aujourd'hui
                ];
            }
            // Si le taux de croissance n'est pas positif
            else {
                $results[] = [
                    'id' => $bin['id'],
                    'address' => $bin['address'],
                    'current_level' => $currentLevel,
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
     * @param array $bins Liste des poubelles
     * @return array|null La déchetterie la plus proche ou null si aucune déchetterie
     */
    private function getNearestWasteCenter(array $bins): ?array
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
        
        // Trouver la déchetterie la plus proche du centre
        $minDistance = PHP_FLOAT_MAX;
        $nearestCenter = null;
        
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