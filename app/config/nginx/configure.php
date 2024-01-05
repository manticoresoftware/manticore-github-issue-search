<?php declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable

App::exec('echo "' . config('nginx.auth_name') . ':"$(openssl passwd -apr1 ' . escapeshellarg(config('nginx.auth_pass')) . ') > $CONFIG_DIR/.htpasswd');

$routes = Env::load(config('common.uri_map_file'));
uasort(
	$routes, function ($a, $b) {
		return (sizeof($a) > sizeof($b)) ? 1 : -1;
	}
);

$lang_type = config('common.lang_type');

$lang_match = match ($lang_type) {
	'path' => implode('|', config('common.languages')),
	default => null
};

$rewrites = [];
foreach ($routes as $route => $action) {
	$i = 0; // route like (bla (bla bla)) with uff8 cant handle by nginx. so hack it
	$uri = '/?ROUTE='
	. preg_replace_callback(
		'|\([^\)]+\)|is',
		function ($item) use (&$i) {
			return '$' . ++$i;
		},
		$route
	)
	. '&ACTION=' . array_shift($action);

	if ($action) {
		foreach ($action as $k => $v) {
			$uri .= '&' . $v . '=$' . ($k + 1);
		}
	}

  // If we have lang_type = path
	if ($lang_match) {
		if ($route === 'home') { // Set root
			$rewrites[] = "rewrite '(*UTF8)^/(?:$lang_match)/?$' '$uri';";
		}
		$route = "(?:$lang_match)/$route";
	}

	$rewrites[] = "rewrite '(*UTF8)^/$route/?$' '$uri';";
}

$cert_pem = '/etc/nginx/ssl/cert.pem';
$cert_key = '/etc/nginx/ssl/cert.key';
$ssl_port = config('server.ssl_port');

$use_ssl = config('nginx.use_ssl');

Env::configure(
	__DIR__, [
	'{{UPLOAD_MAX_FILESIZE}}' => config('common.upload_max_filesize'),
	'{{SERVER_NAME}}' => config('common.domain'),
	'{{SERVER_PORT}}' => config('server.port'),
	'{{SSL_LISTEN_DIRECTIVE}}' => $use_ssl ? "listen 	 $ssl_port ssl;" : '',
	'{{SSL_CERT_DIRECTIVE}}' => $use_ssl ? "ssl_certificate $cert_pem;" : '',
	'{{SSL_CERT_KEY_DIRECTIVE}}' => $use_ssl ? "ssl_certificate_key $cert_key;" : '',
	'{{AUTH}}' => config('nginx.auth'),
	'{{REWRITE_RULES}}' => implode(PHP_EOL, $rewrites),
	'{{CORS_ORIGIN}}' => config('cors.origin'),
	'{{CORS_METHODS}}' => config('cors.methods'),
	'{{CORS_HEADERS}}' => config('cors.headers'),
	'{{CORS_CREDENTIALS}}' => config('cors.credentials'),
	'{{OPEN_FILE_CACHE}}' => config('nginx.open_file_cache'),
	]
);
