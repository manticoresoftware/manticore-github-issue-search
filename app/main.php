<?php declare(strict_types=1);

include getenv('KISS_CORE');
App::start();

// Process action and get view template if have
$View = App::process();
if (Request::current()->getHeader('x-requested-with') !== 'navigation') {
	$View->prepend('_head')->append('_foot');
}

$View->assign('BUNDLE_HASH', filemtime(getenv('VAR_DIR') . '/bundle.css'));

Response::current()->send((string)$View->render());
App::stop();
