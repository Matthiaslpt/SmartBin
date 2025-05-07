<?php

namespace SmartBin\Controllers;

use SmartBin\Services\AnalyticsService;
use Twig\Environment;

class AnalyticsController
{
    private Environment $twig;
    private AnalyticsService $analyticsService;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
        $this->analyticsService = new AnalyticsService();
    }

    public function dashboard(): void
    {
        $averageFillRates = $this->analyticsService->getAverageFillRates();
        $binsNeedingCollection = $this->analyticsService->getBinsNeedingCollection();
        $fillRateGrowth = $this->analyticsService->getFillRateGrowth();
        $predictedCriticalLevels = $this->analyticsService->predictCriticalLevels();
        
        echo $this->twig->render('analytics/dashboard.twig', [
            'averageFillRates' => $averageFillRates,
            'binsNeedingCollection' => $binsNeedingCollection,
            'fillRateGrowth' => $fillRateGrowth,
            'predictedCriticalLevels' => $predictedCriticalLevels
        ]);
    }

    public function collectionRoute(): void
    {
        $binsNeedingCollection = $this->analyticsService->getBinsNeedingCollection();
        $route = $this->analyticsService->generateOptimizedRoute($binsNeedingCollection);
        
        echo $this->twig->render('analytics/route.twig', [
            'route' => $route,
            'binsCount' => count($binsNeedingCollection)
        ]);
    }

    public function getAverageFillRatesApi(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->analyticsService->getAverageFillRates());
    }

    public function getBinsNeedingCollectionApi(): void
    {
        header('Content-Type: application/json');
        $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 70;
        echo json_encode($this->analyticsService->getBinsNeedingCollection($threshold));
    }

    public function getFillRateGrowthApi(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->analyticsService->getFillRateGrowth());
    }

    public function getPredictionsApi(): void
    {
        header('Content-Type: application/json');
        $criticalLevel = isset($_GET['criticalLevel']) ? (int)$_GET['criticalLevel'] : 80;
        echo json_encode($this->analyticsService->predictCriticalLevels($criticalLevel));
    }

    public function getOptimizedRouteApi(): void
    {
        header('Content-Type: application/json');
        $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 70;
        $binsToCollect = $this->analyticsService->getBinsNeedingCollection($threshold);
        $route = $this->analyticsService->generateOptimizedRoute($binsToCollect);
        echo json_encode($route);
    }
}