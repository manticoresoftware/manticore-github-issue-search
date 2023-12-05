<?php declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable

$save_handler = config('session.save_handler');
$save_path = config('session.save_path');
Env::configure(
	__DIR__, [
	'{{UPLOAD_MAX_FILESIZE}}' => config('common.upload_max_filesize'),
	'{{ASSERTIONS}}' => App::$debug ? 1 : -1,
	'{{SESSION_SAVE_HANDLER}}' => $save_handler,
	'{{SESSION_COOKIE_SECURE}}' => config('common.proto') === 'https' ? 'on' : 'off',
	'{{SESSION_SAVE_PATH}}' => $save_handler === 'files' ? config('session.save_depth') . ';' . $save_path : $save_path,
	]
);
