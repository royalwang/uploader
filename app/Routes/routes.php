<?php

use Minute\Routing\Router;

/** @var Router $router */

$router->post('/generic/uploader', 'Uploader/Uploader.php@upload', false)->setPriority(99);

$router->post('/generic/sounder-recorder/:fn', 'Uploader/SoundRecorder.php@save', true)->setPriority(99);

$router->post('/generic/url-proxy', 'Uploader/UrlProxy.php', true)->setPriority(99);
$router->post('/generic/image-proxy', 'Uploader/ImageProxy.php', true)->setPriority(99);