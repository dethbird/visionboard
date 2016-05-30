<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
// ini_set('display_startup_errors',1);
define("APPLICATION_PATH", __DIR__ . "/../");
date_default_timezone_set('America/New_York');
session_cache_limiter(false);
session_start();

// Ensure src/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    APPLICATION_PATH ,
    APPLICATION_PATH . 'library',
    get_include_path(),
)));


require '../vendor/autoload.php';
require_once APPLICATION_PATH . 'src/library/View/Extension/TemplateHelpers.php';
require_once APPLICATION_PATH . 'src/library/ExternalData/InstagramData.php';
require_once APPLICATION_PATH . 'src/library/ExternalData/PocketData.php';

use Aptoma\Twig\Extension\MarkdownExtension;
use Aptoma\Twig\Extension\MarkdownEngine;
use Cocur\Slugify\Slugify;
use Symfony\Component\Yaml\Yaml;
use Guzzle\Http\Client;

// Load configs and add to the app container
$configs = Yaml::parse(file_get_contents("../configs/configs.yml"));
$app = new \Slim\Slim(
    array(
        'view' => new Slim\Views\Twig(),
        'templates.path' => APPLICATION_PATH . 'src/views',
        'cookies.encrypt' => true,
        'cookies.secret_key' => $configs['security']['secret'],
        'cookies.cipher' => MCRYPT_RIJNDAEL_256,
        'cookies.cipher_mode' => MCRYPT_MODE_CBC
    )
);
$markdownEngine = new MarkdownEngine\MichelfMarkdownEngine();
$view = $app->view();
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new TemplateHelpers(),
    new MarkdownExtension($markdownEngine)
);
// $view->addExtension(new MarkdownExtension($markdownEngine));
$app->container->set('configs', $configs);


$app->notFound(function () use ($app) {
    $app->render(
        'pages/404.html.twig'
    );
});



$app->get("/logout", function () use ($app) {
  $app->deleteCookie('securityContext');
  $app->redirect("/");
});

$app->get("/", function () use ($app) {

    $configs = $app->container->get('configs');
    $layout = Yaml::parse(file_get_contents("../configs/layout.yml"));
    $gallery = Yaml::parse(file_get_contents("../configs/gallery.yml"));
    $instagramData = new InstagramData($configs['instagram']['client_id']);
    $pocketData = new PocketData($configs['pocket']['consumer_key'], $configs['pocket']['access_token']);
    $comics = Yaml::parse(file_get_contents("../configs/comics.yml"));

    $templateVars = array(
        "configs" => $configs,
        "section" => "index",
        "layout" => $layout,
        "gallery" => $gallery,
        "instagram_posts" => $instagramData->getRecentMedia($configs['instagram']['user_id'], 25, array(
            "art",
            "drawing",
            "sketchbook",
            "characterdesign"
        )),
        "pocket_articles" => $pocketData->getArticles(10, 3600),
        "comics" => $comics
    );

    $app->render(
        'pages/index.html.twig',
        $templateVars,
        200
    );
});


$app->run();
