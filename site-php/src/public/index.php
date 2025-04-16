<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Configuration de Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false, // Mettre __DIR__ . '/cache' en production
    'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

// Routage simple
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Router
switch ($uri) {
    case '/':
    case '/index':
    case '/index.php':
        $controller = new \SmartBin\controllers\HomeController($twig);
        $controller->index();
        break;
    
    case '/about':
        $controller = new \SmartBin\controllers\HomeController($twig);
        $controller->about();
        break;
    
    case '/bin':
        $controller = new \SmartBin\controllers\BinController($twig);
        $controller->show($_GET['id'] ?? null);
        break;
    
    case '/api/bins':
        $controller = new \SmartBin\controllers\BinController($twig);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->getAllBins();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->addBin();
        }
        break;
    
    case (preg_match('/^\/api\/bins\/(\d+)$/', $uri, $matches) ? true : false):
        $binId = $matches[1];
        $controller = new \SmartBin\controllers\BinController($twig);
        $controller->getBinById($binId);
        break;
    
    default:
        header('HTTP/1.1 404 Not Found');
        echo '404 Not Found';
        break;
}