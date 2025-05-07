<?php

namespace SmartBin\Controllers;

use SmartBin\Models\BinModel;
use Twig\Environment;

class BinController
{
    private Environment $twig;
    private BinModel $binModel;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
        $this->binModel = new BinModel();
    }

    public function show(?int $binId = null): void
    {
        if (!$binId) {
            echo $this->twig->render('bin.twig', [
                'error' => 'Identifiant de poubelle non spécifié.'
            ]);
            return;
        }

        $bin = $this->binModel->getBinById($binId);

        if (!$bin) {
            echo $this->twig->render('bin.twig', [
                'error' => 'Poubelle non trouvée.'
            ]);
            return;
        }

        echo $this->twig->render('bin.twig', [
            'bin' => $bin
        ]);
    }

    public function getAllBins(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->binModel->getAllBins());
    }

    public function getBinById(int $binId): void
    {
        header('Content-Type: application/json');

        $bin = $this->binModel->getBinById($binId);

        if (!$bin) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Bin not found']);
            return;
        }

        echo json_encode($bin);
    }

    public function addBin(): void
    {
        header('Content-Type: application/json');

        // Récupérer les données JSON de la requête
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Données invalides']);
            return;
        }

        // Vérifier que les champs requis sont présents
        if (!isset($data['address']) || !isset($data['lat']) || !isset($data['lng'])) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Champs requis manquants']);
            return;
        }

        $binId = $this->binModel->addBin($data);

        if ($binId) {
            header('HTTP/1.1 201 Created');
            echo json_encode([
                'message' => 'Poubelle ajoutée avec succès',
                'id' => $binId
            ]);
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Erreur lors de l\'ajout de la poubelle']);
        }
    }
}