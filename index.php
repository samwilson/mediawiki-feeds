<?php

use Samwilson\MediaWikiFeeds\Controller;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

require 'vendor/autoload.php';

// Get site configuration.
require 'config.php';
if (!isset($defaults)) {
    throw new Exception('$defaults array must be set in config.php');
}

// Set up Slim.
$app = new App([
    'settings' => [
        'displayErrorDetails' => (isset($displayErrorDetails) && $displayErrorDetails),
        'config' => [
            'defaults' => $defaults,
            'vardir' => __DIR__ . '/var',
            'cdn' => (isset($cdn) && strtolower($cdn) == 'toolforge') ? 'toolforge' : 'maxcdn',
        ],
    ],
]);
$container = $app->getContainer();
$container['view'] = function ($container) {
    $view = new Twig(__DIR__.'/tpl', [
        'cache' => __DIR__.'/var/cache'
    ]);
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new TwigExtension($container['router'], $basePath));
    return $view;
};

// Routes.
$app->get('/', Controller::class.':home')->setName('home');
$app->get('/feed', Controller::class.':feed')->setName('feed');
$app->get('/{catname}.rss', Controller::class.':feed');
$app->get('/feed.php', Controller::class.':feed');

// Run the application.
$app->run();
