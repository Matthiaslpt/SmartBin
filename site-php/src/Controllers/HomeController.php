<?php

namespace SmartBin\Controllers;

use Twig\Environment;

class HomeController
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function index(): void
    {
        echo $this->twig->render('index.twig');
    }

    public function about(): void
    {
        echo $this->twig->render('about.twig');
    }
}