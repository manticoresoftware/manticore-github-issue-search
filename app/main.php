<?php declare(strict_types=1);

include getenv('KISS_CORE');
App::start();

// Process action and get view template if have
$view = App::process()
  ->prepend('_head')
  ->append('_foot');

$view->assign('BUNDLE_HASH', filemtime(getenv('VAR_DIR') . '/bundle.css'));

Response::current()->send((string)$view->render());
App::stop();
