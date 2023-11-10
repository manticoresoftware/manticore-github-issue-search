<?php declare(strict_types=1);

final class App {
  /** @property bool $debug */
	public static bool $debug;
	public static int $log_level;

	/** @var array<string,callable> */
	protected static array $e_handlers = [];
	/** @var array<string,string> */
	protected static array $action_map;

  /**
   * Fetch annotated variables from $file using $map_file
   * @param string $file File that was annotated with import params (action or something else)
   * @param string $map_file File with map of args or empty to use default
   * @return array
   */
	public static function getImportVarsArgs(string $file, string $map_file = null): array {
		$params = Env::load($map_file ?: config('common.param_map_file'));
		$args = [];
		if (isset($params[$file])) {
			foreach ($params[$file] as $param) {
				$args[] = $param['name'] . ':' . $param['type']
					. (isset($param['default']) ? '=' . $param['default'] : '')
				;
			}
		}
		return $args;
	}

  /**
   * Write json data into file
   * @param string $file File path to json
   * @param mixed $data Data to put in json file
   * @return bool
   */
	public static function writeJSON(string $file, mixed $data): bool {
		return !!file_put_contents(
			$file,
			json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
		);
	}

  /**
   * Get json data from file
   * @param string $file
   * @return mixed
   */
	public static function getJSON(string $file): mixed {
		if (!is_file($file)) {
			throw new Error('Cant find file ' . $file . '. Be sure you started init script to compile application');
		}

		return json_decode(file_get_contents($file), true);
	}

  /**
   * Log any message
   * @param string $message
   * @param array $dump
   * @param string $type error, info, wanr, notice
   * @return string идентификатор исключения
   */
	public static function log(string $message, array $dump = [], string $type = 'error'): string {
		$id = hash('sha256', $message . ':' . implode('.', array_keys($dump)) . ':' . $type);
		$log_file = getenv('LOG_DIR') . '/' . gmdate('Ymd') . '-' . $type . '.log';
		$message =
		gmdate('[Y-m-d H:i:s T]')
		. "\t" . $id
		. "\t" . $message
		. "\t" . json_encode($dump, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\t"
		. json_encode(filter_input_array(INPUT_COOKIE), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
		;
		error_log($message, 3, $log_file);
		return $id;
	}

  /**
   * Иницилизация работы приложения
   * @param array $config
   */
	public static function start(array $config = []): void {
		foreach ($config as $param => $value) {
			static::${$param} = $value;
		}

		if (!isset(static::$debug)) {
			static::$debug = getenv('PROJECT_ENV') === 'dev';
			static::$log_level = (int)config('common.cli_level');
		}

	  // Locale settings
		setlocale(LC_ALL, 'en_US.UTF8');

	  // Timezone settings
		date_default_timezone_set(timezone_name_from_abbr('', (int)Cookie::get('tz_offset'), 0) ?: 'UTC');

	  // Error handler
		set_error_handler([static::class, 'handleError'], E_ALL);

	  // Handle uncatched exceptions
		set_exception_handler([static::class, 'handleException']);

	  // Register default Exception handler
		static::setExceptionHandler(Throwable::class, static::createExceptionHandler());

		Autoload::register('Plugin', getenv('APP_DIR') . '/plugin');
		Autoload::register('App', getenv('APP_DIR') . '/src');
		Autoload::register('App\Model', getenv('APP_DIR') . '/src/model');
		Autoload::register('App\Component', getenv('APP_DIR') . '/src/component');
		Autoload::register('App\Lib', getenv('APP_DIR') . '/src/lib');
		Autoload::register('App\Error', getenv('APP_DIR') . '/src/error');

	  // If we have vendor dir with autoload file load it
	  // This is required for composer packages
		$vendor_autoload_file = getenv('APP_DIR') . '/vendor/autoload.php';
		if (file_exists($vendor_autoload_file)) {
			include_once $vendor_autoload_file;
		}

		include_once getenv('APP_DIR') . '/start.php';
	}

	/**
	 * @param Throwable $T
	 * @param string $type
	 * @return string
	 */
	public static function logException(Throwable $T, string $type = 'error'): string {
		return App::log($T->getMessage(), ['trace' => $T->getTraceAsString()], $type);
	}

  /**
   * Завершение исполнени приложени
	 *
	 * @return void
   */
	public static function stop(): void {
		include_once getenv('APP_DIR') . '/stop.php';
	}

	/**
	 * @param ?callable $fn
	 * @return void
	 */
	public static function checkExit(?callable $fn = null): void {
		pcntl_signal_dispatch();
		if (!container('exit')) {
			return;
		}

		Cli::print('[exit] request to stop app received');
		if (isset($fn)) {
			$fn();
		}
		exit(0);
	}

  /**
   * @return View
   */
	public static function process(): View {
		if (!isset(static::$action_map)) {
			static::$action_map = Env::load(config('common.action_map_file'));
		}

		$Request = Request::current();
		$Response = Response::current();

		$process = function () use ($Request): array {
			$action = static::$action_map[$Request->getAction()];
			extract(Input::get(static::getImportVarsArgs($action)));
			$response = include $action;

			return [get_defined_vars(), $response];
		};

		[$vars, $response] = $process();

		switch (true) {
			case $response === 1:
				$Response->header('Content-type', 'text/html;charset=utf-8');
			return View::create($Request->getAction())->set($vars);
			break;

			case $response instanceof View:
				$Response->header('Content-type', 'text/html;charset=utf-8');
			return $response->set($vars);
			break;

			case is_string($response):
				$Response->header('Content-type', 'text/plain;charset=utf-8');
			return View::fromString($response);
			break;

			case is_array($response):
			case is_object($response):
				$accept = filter_input(INPUT_SERVER, 'HTTP_ACCEPT') ?? '';
				$type = match (true) {
					str_contains('application/json', $accept) => 'json',
					str_contains('application/msgpack', $accept) => 'msgpack',
					default => Input::isMsgpack() ? 'msgpack' : 'json',
				};

				$Response->header('Content-type', 'application/' . $type . ';charset=utf-8');
				$encoded = $type === 'msgpack'
					? msgpack_pack($response)
					: json_encode(
						$response,
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE
					);
				if (false === $encoded) {
					throw new Error('Failed to encode ' . $type  . ' response');
				}
			return View::fromString($encoded);
			break;

			default:
				$Response->header('Content-type', 'text/plain;charset=utf-8');
			return View::fromString((string)$response);
		}
	}

  /**
   * Замена стандартного обработчика ошибок на эксепшены
   */
	public static function handleError(int $errno, string $errstr, string $errfile, int $errline): void {
		assert(isset($errno) && isset($errfile) && isset($errline)); // for phpcs
		static::error($errstr);
	}

  /**
   * Handle exception. Call handlers and do some staff
   * @param Throwable $Exception
   */
	public static function handleException(Throwable $Exception) {
		static::log($Exception->getMessage(), ['trace' => $Exception->getTraceAsString()], 'error');

		$exception = $Exception::class;
		do {
			if (isset(static::$e_handlers[$exception])) {
				return static::$e_handlers[$exception]($Exception);
			}
			$exception = get_parent_class($exception);
		} while (false !== $exception);

		$implements = class_implements($Exception);
		while ($implement = array_pop($implements)) {
			if (isset(static::$e_handlers[$implement])) {
				return static::$e_handlers[$implement]($Exception);
			}
		}
	}

	/**
	 * @param int $code
	 * @param ?string $type
	 * @param ?callable $format_func
	 * @return callable
	 */
	public static function createExceptionHandler(
		int $code = 500,
		?string $type = null,
		?callable $format_func = null
	): callable {
		static $types = [
			'json' => 'application/json',
			'html' => 'text/html',
			'text' => 'text/plain',
		];

		if (!isset($type)) {
			$type = match (true) {
				Input::isJson() => 'json',
				Input::isCli() => 'text',
				default => 'html'
			};
		}

		return function (Throwable $Exception) use ($code, $type, $format_func, $types) {
			switch (true) {
				case isset($format_func):
					$response = $format_func($Exception);
				break;
				case $type === 'json':
					$response = json_encode(
						[
							'error' => $Exception->getMessage(),
							'trace' => App::$debug ? $Exception->getTrace() : [],
						], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					);
				break;

				case $type === 'html':
					$response = '<html><head><title>Error</title></head><body>'
					 . '<p>Unhandled exception <b>'
					 . $Exception::class . '</b> with message "' . $Exception->getMessage()
					 . (static::$debug ? '" in file "' . $Exception->getFile() . ':' . $Exception->getLine() : '')
					 . '"</p>';

					if (static::$debug) {
						$response .= '<p><ul>'
						. implode(
							'<br/>', array_map(
								function ($item) {
									return '<li>' . $item . '</li>';
								}, explode(PHP_EOL, $Exception->getTraceAsString())
							)
						)
						. '</ul></p>'
						. '</body></html>'
						;
					}
				break;

				default:
					$response = 'Error: ' . $Exception->getMessage();
					if (static::$debug) {
						$response .= PHP_EOL . $Exception->getTraceAsString();
					}
			}

			return Response::current()
			->status($code)
			->header('Content-type', $types[$type] . ';charset=utf8')
			->send($response);
		};
	}

  /**
   * Assign handler for special exception that will be called when exception raises
	 *
   * @param string $exception
   * @param callable $handler
	 * @return void
   */
	public static function setExceptionHandler(string $exception, callable $handler): void {
		static::$e_handlers[$exception] = $handler;
	}

  /**
   * Хэндлер для управления ошибками ассертов
   * @param	string  $file
   * @param	string	$line
   * @param	int	$code
   * @throws Exception
   */
	public static function handleAssertion(string $file, string $line, ?int $code): void {
		throw new Error('Assertion failed in file ' . $file . ' at line ' . $line . ' with code ' . $code);
	}

  /**
   * Generate error to stop next steps using special exception class name
   * @param string $error Message that describes error
   * @param string $class Exception class name to be raised
   * @throws \Exception
   */
	public static function error(string $error, string $class = 'Exception'): void {
		throw new $class($error);
	}

  /**
   * Execute shell command in KISS core environment
   * @param string $cmd Command to be executed
   * @return string Result of execution
   */
	public static function exec(string $cmd): string {
		$project_dir = getenv('PROJECT_DIR');
		$output = `
        set -e
        cd $project_dir
        source ./env.sh
        $cmd
        `;
		return $output ? trim($output) : '';
	}
}


final class Autoload {
	protected static bool $inited = false;
	/** @var string[] $prefixed */
	protected static array $prefixes = [];

  /**
   * Init autoload mecahnism
	 *
	 * @return void
   */
	protected static function init(): void {
		spl_autoload_register([static::class, 'load']);
		static::$inited = true;
	}

  /**
   * @param string $class Class to be loaded
   * @return bool|string
   */
	protected static function load(string $class): bool|string {
		$prefix = $class;
		while (false !== $pos = strrpos($prefix, '\\')) {
			$prefix = substr($class, 0, $pos + 1);
			$relative = substr($class, $pos + 1);
			$mapped = static::loadMapped($prefix, $relative);
			if ($mapped) {
				return $mapped;
			}
			$prefix = rtrim($prefix, '\\');
		}

		return false;
	}

  /**
   * @param string $prefix
   * @param string $class
   */
	protected static function loadMapped(string $prefix, string $class): false|string {
		if (!isset(static::$prefixes[$prefix])) {
			return false;
		}

		foreach (static::$prefixes[$prefix] as $dir) {
			$file = $dir . strtr($class, ['\\' => '/']) . '.php';
			if (is_file($file)) {
				include $file;
				return $file;
			}
		}
		return false;
	}

  /**
   * Register new namespace and folder to be loaded from
   * @param string $prefix
   * @param string $dir
   * @param bool $prepend Priority for this
   */
	public static function register(string $prefix, string $dir, bool $prepend = false): void {
		assert(is_dir($dir) /* Dir $dir does not exist */);

		if (!static::$inited) {
			static::init();
		}

		$prefix = trim($prefix, '\\') . '\\';
		$dir = rtrim($dir, '/') . '/';

		if (!isset(static::$prefixes[$prefix])) {
			static::$prefixes[$prefix] = [];
		}

		if ($prepend) {
			array_unshift(static::$prefixes[$prefix], $dir);
		} else {
			static::$prefixes[$prefix][] = $dir;
		}
	}
}


final class Cli {
	const LEVEL_DEBUG = 0;
	const LEVEL_WARNING = 1;
	const LEVEL_INFO = 2;

  /**
   * This function reads hidden input (password) from stdin
   *
   * @param string|null $promt
   * @return string
   */
	public static function readSecret(?string $promt = null): string {
		if ($promt) {
			echo $promt;
		}

		system('stty -echo');
		$secret = trim(fgets(STDIN));
		system('stty echo');

		return $secret;
	}

	/**
	 * @param string|string[] $lines
	 * @param int $level
	 * @return void
	 */
	public static function print(string|array $lines, int $level = 2): void {
		if (App::$log_level > $level) {
			return;
		}

		if (is_string($lines)) {
			$lines = [$lines];
		}
		$date = gmdate('[Y-m-d H:i:s T]');
		foreach ($lines as $line) {
			echo $date . ' ' . rtrim($line) . PHP_EOL;
		}
	}

	/**
	 * @param mixed $var
	 * @return void
	 */
	public static function dump(mixed $var): void {
		$date = gmdate('[Y-m-d H:i:s T]');
		echo $date . ' ' . var_export($var, true) . PHP_EOL;
	}

	/**
	 * @param string $line
	 * @param int $error_code
	 * @return void
	 */
	public static function error(string $line, int $error_code = 1): void {
		static::print($line);
		exit($error_code);
	}
}


/**
 * Class Cookie
 * Work with cookies
 *
 * <code>
 * Cookie::add('first', 'value', time() + 100);
 * Cookie::add('onemore', 'value', time() + 100);
 * Cookie::send(); // Be sure to send cookie before headers sent
 * </code>
 *
 * <code>
 * $first = Cookie:get('first');
 * </code>
 */
final class Cookie {
	public static bool $is_parsed = false;

	protected static array $update = [];
	protected static array $cookies = [];
	protected static Closure $parse_fn;

  /**
   * Set parser for the cookie
   * @param Closure $fn [description]
   */
	public static function setParser(Closure $fn): void {
		static::$is_parsed = false;
		static::$parse_fn = $fn;
	}

	protected static function parse(): void {
		$fn = static::$parse_fn ?? function () {
			$cookies = (array)filter_input_array(INPUT_COOKIE);
			foreach ($cookies as $name => $value) {
				static::set($name, $value);
			}
			return static::$cookies;
		};
		static::$cookies = $fn();
		static::$is_parsed = true;
	}

  /**
   * Get cookie by name
   * @param string $name
   * @param mixed $default
   */
	public static function get(string $name, mixed $default = null): mixed {
		static::$is_parsed || static::parse();
		return filter_has_var(INPUT_COOKIE, $name) ? filter_input(INPUT_COOKIE, $name) : $default;
	}

  /**
   * Set new cookie. Replace if exists
   * @param string $name
   * @param string $value
   * @param array $options
   * @return void
   */
	public static function set(string $name, string $value, array $options = []): void {
		static::$cookies[$name] = [
			'name' => $name,
			'value' => $value,
			'options' => $options,
		];
	}

  /**
   * Add new cookie. Create new only if not exists
   * @param string $name
   * @param string $value
   * @param array $options
   * @return void
   */
	public static function add(string $name, string $value, array $options = []): void {
		if (filter_has_var(INPUT_COOKIE, $name)) {
			return;
		}

		static::set($name, $value, $options);
	}

  /**
   * Send cookies headers
   */
	public static function send(): void {
		foreach (static::$cookies as $cookie) {
			$options = array_merge(
				$cookie['options'], [
					'domain' => $cookie['domain'] ?? config('common.domain'),
					'path' => $cookie['path'] ?? '/',
					'expires' => $cookie['expires'] ?? 0,
					'secure' => $cookie['secure'] ?? config('common.proto') === 'https',
					'httponly' => $cookie['httponly'] ?? str_starts_with(getenv('SERVER_PROTOCOL'), 'HTTP'),
				]
			);
			setcookie($cookie['name'], $cookie['value'], $options);
		}
	}
}


final class Env {
	protected static $params = [
		'PROJECT',
		'PROJECT_DIR',
		'PROJECT_ENV',
		'PROJECT_REV',
		'APP_DIR',
		'STATIC_DIR',
		'CONFIG_DIR',
		'ENV_DIR',
		'BIN_DIR',
		'RUN_DIR',
		'LOG_DIR',
		'VAR_DIR',
		'TMP_DIR',
		'KISS_CORE',
	];

  /**
   * Initialization of Application
   *
   * @return void
   */
	public static function init(): void {
		App::$debug = getenv('PROJECT_ENV') === 'dev';
		App::$log_level = Cli::LEVEL_DEBUG;
		static::configure(getenv('APP_DIR') . '/config/app.ini.tpl');
		static::compileConfig();
		static::generateActionMap();
		static::generateURIMap();
		static::generateParamMap();
		static::generateTriggerMap();
		static::generateConfigs();
		static::prepareDirs();
	}

  // This method should be called in CLI only
	/**
	 * @param int $timeout
	 * @return void
	 */
	public static function waitInit(int $timeout = 5): void {
		$t = time();
		$cnf_file = getenv('CONFIG_DIR') . '/config.php';
		do {
			$tpl_ts = filemtime(getenv('APP_DIR') . '/config/app.ini.tpl');
			$cnf_ts = file_exists($cnf_file) ? filemtime($cnf_file) : 0;

			if ($cnf_ts > $tpl_ts) {
				return;
			}

			usleep(250000); // 25ms
		} while ((time() - $t) <= $timeout);

		Cli::error('Env: wait init timeouted');
	}

  /**
   * Configure all config templates in dir $template or special $template file
   *
   * @param string $template
   * @param array $params
   * @return void
   */
	public static function configure(string $template, array $params = []): void {
	  // Add default params
		foreach (static::$params as $param) {
			$params['{{' . $param . '}}'] = getenv($param);
		}

	  // Add extra params
		$params += [
			'{{DEBUG}}' => (int)App::$debug,
		];

		foreach (is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
			file_put_contents(
				getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'),
				strtr(file_get_contents($file), $params)
			);
		}
	}

  /**
   * Compile config.json into fast php array to include it ready to use optimized config
	 *
	 * @return void
   */
	protected static function compileConfig(): void {

		$config = static::parseConfig();

	  // Iterate to make dot.notation.direct.access
		$Iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($config));
		foreach ($Iterator as $leaf_value) {
			$keys = [];
			foreach (range(0, $Iterator->getDepth()) as $depth) {
				$keys[] = $Iterator->getSubIterator($depth)->key();
			}
			$config[join('.', $keys)] = $leaf_value;
		}

		static::store(getenv('CONFIG_DIR') . '/config.php', $config);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected static function parseConfig(): array {
		$config = [];

		$env = getenv('PROJECT_ENV');
		// Prepare production config replacement
		foreach (parse_ini_file(getenv('CONFIG_DIR') . '/app.ini', true) as $group => $block) {
			if (str_contains($group, ':') && explode(':', $group)[1] === $env) {
				$origin = strtok($group, ':');
				$config[$origin] = array_merge($config[$origin], $block);
				$group = $origin;
			} else {
				$config[$group] = $block;
			}

			$config = static::appendDotNotationToConfig($config, $group);
		}
		return $config;
	}

	/**
	 * Make dot.notation for group access
	 *
	 * @param array<string,mixed> $config
	 * @param string $group
	 * @return array<string,mixed>
	 */
	protected static function appendDotNotationToConfig(array $config, string $group): array {
		foreach ($config[$group] as $key => $val) {
			$parts = explode('.', $key);
			$ref = &$config;
			for ($i = 0, $max_i = sizeof($parts) - 1; $i <= $max_i; $i++) {
				$key = ($i === 0 ? $group . '.' : '') . $parts[$i];

				if ($i === $max_i) {
					$ref[$key] = $val;
					$config[$group . '.' . $key] = &$ref[$key];
					unset($ref);
				} else {
					$ref[$key] ??= [];
					$ref = &$ref[$key];
				}
			}
		}

		return $config;
	}

  /**
   * Generate all configs for configurable plugins. It includes all plugin/_/configure.php files
	 *
   * @return void
   */
	protected static function generateConfigs(): void {
		$configure = function ($file) {
			return include $file;
		};

		foreach (glob(getenv('APP_DIR') . '/config/*/configure.php') as $file) {
			$configure($file);
		}
	}

	/**
	 * @return void
	 */
	protected static function prepareDirs(): void {
		static::createViewDirs();
		static::createSessionDirs();
	}

	/**
	 * @return void
	 */
	protected static function createViewDirs(): void {
		if (!is_dir(config('view.compile_dir'))) {
			mkdir(config('view.compile_dir'), 0700, true);
		}

		if (config('common.lang_type') === 'none') {
			return;
		}

		foreach (config('common.languages') as $lang) {
			$lang_dir = config('view.compile_dir') . '/' . $lang;
			if (is_dir($lang_dir)) {
				continue;
			}

			mkdir($lang_dir, 0700);
		}
	}

	/**
	 * @return void
	 */
	protected static function createSessionDirs(): void {
		$save_handler = config('session.save_handler');
		if ($save_handler !== 'files') {
			return;
		}
		$bits = ini_get('session.sid_bits_per_character');
		$chars = '0123456789abcdef';
		if ($bits >= 5) {
			$chars .= 'ghijklmnopqrstuv';
		}

		if ($bits >= 6) {
			$chars .= 'wxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-,';
		}

		$save_path = config('session.save_path');
		if (!is_dir($save_path)) {
			mkdir($save_path, 0700, true);
		}

		$depth = config('session.save_depth');
		if ($depth === 0) {
			return;
		}

		$arrays = [];
		for ($i = 0; $i < $depth; $i++) {
			$arrays[] = str_split($chars);
		}

		foreach (array_cartesian($arrays) as $paths) {
			$dir_path = $save_path . '/' . implode('/', $paths);
			if (is_dir($dir_path)) {
				continue;
			}

			mkdir($dir_path, 0700, true);
		}
	}

  /**
   * Generate nginx URI map for route request to special file
	 *
	 *
	 * @return void
   */
	protected static function generateURIMap(): void {
		$map = [];
		foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
			$content = file_get_contents($file);
			if (false === $content) {
				throw new Exception("Failed to read file: $file");
			}

			if (!preg_match_all('/^\s*\*\s*@route\s+([^:]+?)(:(.+))?$/ium', $content, $m)) {
				continue;
			}

			foreach (array_keys($m[0]) as $k) {
				$pattern = trim($m[1][$k]);
				$params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
				array_unshift($params, static::getActionByFile($file));
				$map[$pattern] = $params;
			}
		}
		static::store(config('common.uri_map_file'), $map);
	}

  /**
   * Generate action => file_path map
	 *
	 * @return void
   */
	protected static function generateActionMap(): void {
		$map = [];
		foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
			$map[static::getActionByFile($file)] = $file;
		}
		static::store(config('common.action_map_file'), $map);
	}

  /**
   * Generate parameters map from annotations in actions and triggers files
	 *
	 * @return void
   */
	protected static function generateParamMap(): void {
		$map_files = [
			'actions'  => config('common.param_map_file'),
			'triggers' => config('common.trigger_param_file'),
		];
		foreach ($map_files as $folder => $map_file) {
			$map = [];
			foreach (static::getPHPFiles(getenv('APP_DIR') . '/' . $folder) as $file) {
				$content = file_get_contents($file);
				if (!preg_match_all('/^\s*\*\s*@(?:param|var)\s+([a-z]+)\s+(.+?)$/ium', $content, $m)) {
					continue;
				}

				foreach (array_keys($m[0]) as $k) {
					$param = substr(strtok($m[2][$k], ' '), 1);
					$map[$file][] = [
						'name'    => $param,
						'type'    => $m[1][$k],
						'default' => trim(substr($m[2][$k], strlen($param) + 1)) ?: null,
					];
				}
			}
			static::store($map_file, $map);
		}
	}

  /**
   * Generate trigger map to be called on some event
	 *
	 * @return void
   */
	protected static function generateTriggerMap(): void {
		$map = [];
		foreach (static::getPHPFiles(getenv('APP_DIR') . '/triggers') as $file) {
			$content = file_get_contents($file);
			if (!preg_match_all('/^\s*\*\s*@event\s+([^\$]+?)$/ium', $content, $m)) {
				continue;
			}

			foreach (array_keys($m[0]) as $k) {
				$pattern = trim($m[1][$k]);
				if (!isset($map[$pattern])) {
					$map[$pattern] = [];
				}
				$map[$pattern] = array_merge($map[$pattern], [$file]);
			}
		}
		static::store(config('common.trigger_map_file'), $map);
	}

	/**
	 * @param string $file
	 * @return string
	 */
	protected static function getActionByFile(string $file): string {
		return substr(trim(str_replace(getenv('APP_DIR') . '/actions', '', $file), '/'), 0, -4);
	}

  /**
   * Helper for getting list of all php files in dir
   * @param string $dir
   * @return string[]
   */
	protected static function getPHPFiles(string $dir): array {
		assert(is_dir($dir));
		$output = `find -L $dir -name '*.php'`;
		return $output ? explode(PHP_EOL, trim($output)) : [];
	}

  /**
   * This function uses for store variable in php file for next load
   * Its much faster than parse and encode jsons or whatever
   *
   * @param string $file
   * @param mixed $data
   * @return bool
   */
	protected static function store(string $file, mixed $data): bool {
		return !!file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
	}

	/**
	 * @param string $file
	 * @return array
	 */
	public static function load(string $file): array {
		assert(is_file($file));
		return include $file;
	}
}


final class Input {
	public static bool $is_parsed = false;

	/** @var array<string,mixed> */
	public static array $params = [];

	protected static Closure $parse_fn;
	/**
	 * @return bool
	 */
	public static function isCli(): bool {
		return !!filter_input(INPUT_SERVER, 'argc');
	}

	/**
	 * @return bool
	 */
	public static function isJson(): bool {
		return str_starts_with(filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '', 'application/json');
	}

	/**
	 * @return bool
	 */
	public static function isMsgpack(): bool {
		return str_starts_with(filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '', 'application/msgpack');
	}

	/**
	 * @return bool
	 */
	public static function isRaw(): bool {
		return filter_has_var(INPUT_SERVER, 'REQUEST_URI') && !static::isJson();
	}

  /**
   * Парсит и сохраняет все параметры в переменной self::$params
   *
   * @return void
   */
	protected static function parse(): void {
		if (static::$is_parsed) {
			return;
		}

		if (static::isCli()) {
			$argv = filter_input(INPUT_SERVER, 'argv');
			array_shift($argv); // file
			static::$params['ACTION'] = array_shift($argv);
			static::$params += $argv;
		} elseif (static::isJson()) {
			static::$params = (array)filter_input_array(INPUT_GET)
				+ (array)json_decode(file_get_contents('php://input'), true);
		} elseif (static::isMsgpack()) {
			static::$params = (array)filter_input_array(INPUT_GET)
				+ (array)msgpack_unpack(file_get_contents('php://input'));
		} else {
			static::$params = (array)filter_input_array(INPUT_POST)
				+ (array)filter_input_array(INPUT_GET);
		}

		static::$is_parsed = true;
	}

	/**
	 * Set parser
	 * @param Callable $fn [description]
	 * @return void
	 */
	public static function setParser(callable $fn): void {
		static::$is_parsed = false;
		static::$parse_fn = $fn;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public static function set(string $key, mixed $value): void {
		static::$is_parsed || static::parse();
		static::$params[$key] = $value;
	}

  /**
   * Получение переменной запроса
   *
   * <code>
   * $test = Input::get('test');
   *
   * $params = Input::get(['test:int=1']);
   * </code>
	 *
	 * @param string|string[] $args
	 * @return mixed
   */
	public static function get(...$args): mixed {
		static::$is_parsed || static::parse();

		if (!isset($args[0])) {
			return static::$params;
		}

	  // String key?
		if (is_string($args[0])) {
			return static::$params[$args[0]] ?? ($args[1] ?? null);
		}

		if (is_array($args[0])) {
			return static::extractTypified(
				$args[0], function ($key, $default = null) {
					return static::get($key, $default);
				}
			);
		}
	  // Exctract typifie var by mnemonic rules as array


		trigger_error('Error while fetch key from input');
	}

  /**
   * Извлекает и типизирует параметры из массива args с помощью функции $fetcher, которая
   * принимает на вход ключ из массива args и значение по умолчанию, если его там нет
   *
   * @param array $args
   * @param Closure $fetcher ($key, $default)
	 * @return array<string,string|int|bool>
   */
	public static function extractTypified(array $args, Closure $fetcher): array {
		$params = [];
		foreach ($args as $arg) {
			preg_match('#^([a-zA-Z0-9_]+)(?::([a-z]+))?(?:=(.+))?$#', $arg, $m);
			$params[$m[1]]  = $fetcher($m[1], $m[3] ?? '');

		  // Нужно ли типизировать
			if (!isset($m[2])) {
				continue;
			}

			$params[$m[1]] = typify($params[$m[1]], $m[2]);
		}
		return $params;
	}
}


final class Lang {
	const DEFAULT_LANG = 'en';

	const LANGUAGE_MAP = [
		'ru' => 'Русский',
		'en' => 'English',
		'it' => 'Italiano',
		'ko' => '한국어',
		'zh' => '中文',
		'th' => 'ไทย',
		'ar' => 'العربية',
		'ja' => '日本語',
		'vi' => 'Tiếng Việt',
		'fr' => 'Français',
		'de' => 'Deutsch',
		'es' => 'Español',
		'pt' => 'Português',
		'tl' => 'Filipino',
		'eo' => 'Esperanto',
		'eu' => 'Euskara',
		'fy' => 'Frysk',
		'ff' => 'Fula',
		'fo' => 'Føroyskt',
		'ga' => 'Gaeilge',
		'gl' => 'Galego',
		'gn' => 'Guarani',
		'ha' => 'Hausa',
		'hr' => 'Hrvatski',
		'pl' => 'Polski',
		'ro' => 'Română',
		'cs' => 'Čeština',
		'tr' => 'Türkçe',
		'fi' => 'Suomi',
		'sv' => 'Svenska',
		'el' => 'Ελληνικά',
		'be' => 'Беларуская',
		'uk' => 'Українська',
		'kk' => 'Қазақша',
	];

	protected static string $current;
	protected static bool $is_enabled = true;

	/**
	 * @param Request|string $Request
	 * @return string
	 */
	public static function init(Request|string $Request): string {
		$lang_type = config('common.lang_type');
		assert(in_array($lang_type, ['path', 'domain', 'none']));
		if ($lang_type === 'none') {
			static::$is_enabled = false;
			static::$current = static::DEFAULT_LANG;
			return static::$current;
		}

	  // Try to find current language from url match
		if (is_string($Request)) {
			$lang = $Request;
		} else {
			$lang = match ($lang_type) {
				'domain' => strtok(getenv('HTTP_HOST'), '.'),
				'path' => strtok(substr($Request->getUrlPath(), 1), '/'),
				default => ''
			};
		}

	  // If we find current language we return as string
		if (isset(static::LANGUAGE_MAP[$lang]) && in_array($lang, config('common.languages'))) {
			static::$current = $lang;
			return static::$current;
		}

	  // No supported language found try to find in headers
		static::$current = static::parse();

		$url_path = match ($lang_type) {
			'domain' => $Request->getUrlPath(),
			'path' => substr($Request->getUrlPath(), 3)
		};

		$query_str = $Request->getUrlQuery();
		Response::redirect(static::getUrlPrefix() . ($url_path ?: '/') . ($query_str ? '?' . $query_str : ''));
	}

	/**
	 * @return string
	 */
	public static function current(): string {
		return static::$current;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled(): bool {
		return static::$is_enabled;
	}

	/**
	 * @return string
	 */
	public static function getUrlPrefix(): string {
		$lang_domain = match (config('common.lang_type')) {
			'domain' => static::$current . '.' . config('common.domain'),
			'path' => config('common.domain') . '/' . static::$current,
			'none' => config('common.domain')
		};

		return config('common.proto') . '://' . $lang_domain;
	}
  /**
   * Try to parse locale from headers and auto detect it
   *
   * @return string locale that we found in headers
   */
	public static function parse(): string {
		$accept_language = getenv('HTTP_ACCEPT_LANGUAGE') ?? '';
		$languages = config('common.languages');
		foreach (array_keys(static::LANGUAGE_MAP) as $lang) {
			if (!isset($languages[$lang])) {
				continue;
			}

			if (str_contains($accept_language, $lang)) {
				return $lang;
			}
		}

		return static::DEFAULT_LANG;
	}

  /**
   * Get compiler for View to replace patter with values
   *
   * @param string $lang
   * @return callable
   */
	public static function getViewCompiler(string $lang): callable {
		return function ($body, $template) use ($lang) {
			return preg_replace_callback(
				'#\#([A-Za-z0-9_]+)\##ius', function ($matches) use ($template, $lang) {
					return static::translate($template . '.' . $matches[1], $lang);
				}, $body
			);
		};
	}

	/**
	 * @param string $lang
	 * @return array
	 */
	public static function getInfo(string $lang): array {
		return [
			'name' => static::LANGUAGE_MAP[$lang],
			'language' => $lang,
			'is_active' => true,
		];
	}

	/**
	 * @param string $lang
	 * @return array
	 */
	public static function getList(string $lang): array {
		$languages = config('common.languages');
		$list = [];
		foreach (array_keys(static::LANGUAGE_MAP) as $key) {
			if (!in_array($key, $languages)) {
				continue;
			}
			$list[] = [
				'language' => $key,
				'name' => static::LANGUAGE_MAP[$key],
				'is_active' => $lang === $key,
			];
		}

		return $list;
	}

	/**
	 * @param string $key
	 * @param ?string $lang
	 * @return string
	 */
	public static function translate(string $key, ?string $lang = null): string {
		assert(str_contains($key, '.'));
		static $map = [];
		if (!$map) {
			$lang_file = getenv('APP_DIR') . '/lang/' . ($lang ?: static::$current) . '.yml';
			assert(is_file($lang_file));
			$map = yaml_parse_file($lang_file);
		}

		[$template, $translation] = explode('.', $key);
		return $map[$template][$translation] ?? ($map['common'][$translation] ?? '[missing:' . $translation . ']');
	}
}


/**
 * Класс для работы с запросом и переменными запроса
 *
 * @final
 */
final class Request {
  /**
   * @property array $params все параметры, переданные в текущем запросе
   *
   * @property string $route имя действия, которое должно выполнится в выполняемом запросе
   * @property string $url адрес обрабатываемого запроса
   *
   * @property string $method вызываемый метод на данном запросе (GET | POST)
   * @property string $protocol протокол соединения, например HTTP, CLI и т.п.
   * @property string $referer реферер, если имеется
   * @property string $ip IP-адрес клиента
   * @property string $xff ip адрес при использовании прокси, заголовок: X-Forwarded-For
   * @property string $user_agent строка, содержащая USER AGENT браузера клиента
   * @property string $host Хост, который выполняет запрос
   * @property bool $is_ajax запрос посылается через ajax
   */

	private string $action = '';
	private string $route = '';

	public static int $time = 0;
	public static float $time_float = 0;

	public static string $request_uri = '';
	public static string $content_type = '';
	public static string $accept_lang = '';
	public static string $method = 'GET';
	public static string $protocol = 'HTTP';
	public static string $referer = '';
	public static string $ip = '0.0.0.0';
	public static string $real_ip = '0.0.0.0';
	public static string $xff = '';
	public static string $host = '';
	public static string $user_agent = '';

	/** @var array<string,int> */
	public static array $languages = [];

	public static bool $is_ajax = false;

  /**
   * @param string|bool $url адрес текущего запроса
   */
	final protected function __construct(protected string $url) {
	}

  /**
   * Получение ссылки на экземпляр объекта исходного запроса
   *
   * @static
   * @return self ссылка на объекта запроса
   */
	final protected static function create(): self {
		if (self::$accept_lang) {
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', self::$accept_lang, $lang);
			if ($lang && sizeof($lang[1]) > 0) {
				$langs = array_combine($lang[1], $lang[4]);

				foreach ($langs as $k => $v) {
					if ($v !== '') {
						continue;
					}

					$langs[$k] = 1;
				}
				arsort($langs, SORT_NUMERIC);
				static::$languages = $langs;
			}
		}

		$url = rtrim(static::$request_uri, ';&?') ?: '/';
		$Request = (new static($url))
		->setRoute(Input::get('ROUTE'))
		->setAction(Input::get('ACTION'));

	// Init language
		Lang::init($Request);

		return $Request;
	}

  /**
   * Return current instance or initialize and parse
   */
	public static function current(?Closure $init_fn = null): self {
		static $instance;
		if (!isset($instance) || isset($init_fn)) {
			$init_fn ??= static::init(...);
			$init_fn();
			static::parseRealIp();
			$instance = static::create();
		}

		return $instance;
	}

	protected static function init(): void {
		self::$time = $_SERVER['REQUEST_TIME'];
		self::$time_float = $_SERVER['REQUEST_TIME_FLOAT'];
		self::$protocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL') ?? 'HTTP/1.1';
		self::$is_ajax = !!filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH');
		self::$referer = filter_input(INPUT_SERVER, 'HTTP_REFERER') ?? '';
		self::$xff = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR') ?? '';

	  // Эти переменные всегда определены в HTTP-запросе
		self::$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
		self::$user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT') ?: 'undefined';
		self::$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');

		self::$request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI') ?? '';
		self::$content_type = filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '';

		self::$accept_lang = filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE') ?? '';
	}


  /**
   * Parse IPS to prepare request
	 *
	 * @static
   * @return void
   */
	protected static function parseRealIp(): void {
		self::$real_ip = self::$ip;
		if (!self::$xff || self::$xff === self::$ip) {
			return;
		}

		self::$real_ip = trim(strtok(self::$xff, ','));
	}

  /**
   * Get current handled url for this request
	 *
   * @return string
   */
	public function getUrl(): string {
		return $this->url;
	}

  /**
   * Get part of url as path. /some/path for url /some/path?fuck=yea
	 *
   * @return string
   */
	public function getUrlPath(): string {
		return parse_url($this->url, PHP_URL_PATH);
	}

  /**
   * Get url query
	 *
   * @return string
   */
	public function getUrlQuery(): string {
		return parse_url($this->url, PHP_URL_QUERY) ?? '';
	}


  /**
   * Get requested header
	 *
   * @param string $header
   * @return string
   */
	public function getHeader(string $header): string {
		return filter_input(INPUT_SERVER, 'HTTP_' . strtoupper(str_replace('-', '_', $header))) ?? '';
	}

  /**
   * Установка текущего роута с последующим парсингом его в действие и модуль
   *
   * @access public
   * @param string|null $route
   * @return $this
   */
	public function setRoute(?string $route): self {
		$this->route = $route ?? '/home';
		return $this;
	}

  /**
   * Current route
   * @access public
   * @return string
   */
	public function getRoute(): string {
		return $this->route ?? '';
	}

  /**
   * Set action that's processing now
   * @access public
   * @param string|null $action
   * @return self
   */
	public function setAction(?string $action): self {
		$this->action = $action
		? trim(preg_replace('|[^a-z0-9\_\-/]+|is', '', $action), '/')
		: 'home'
		;
		return $this;
	}

  /**
   * Get current action
   * @access public
   * @return string
   */
	public function getAction(): string {
		return $this->action ?? config('default.action');
	}
}


/**
 * Класс для формирования ответа клиенту
 *
 * @final
 */

final class Response {
	/** @var array<string,string> $headers */
	protected array $headers = [
		'Referrer-Policy' => 'origin-when-cross-origin',
		'X-Frame-Options' => 'DENY',
		'X-XSS-Protection' => '1; mode=block',
		'X-Content-Type-Options' => 'nosniff',
		'Content-Security-Policy' => "frame-ancestors 'none'",
	];

	/** @var string */
	protected string $body = '';

	/** @var int */
	protected int $status = 200;

	/** @var array<int,string> */
	protected static array $messages = [
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',

		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',

		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		429 => 'Too Many Requests',

		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
	];

  /**
   * Init of new response
   * @param int $status HTTP Status of response
   * @return void
   */
	final protected function __construct(int $status = 200) {
		$this->status($status);
	}

  /**
   * Return current instance or initialize and parse
   */
	public static function current(): self {
		static $instance;
		if (!isset($instance)) {
			$instance = new static(200);
		}

		return $instance;
	}


  /**
   * Change HTTP status of response
   * @param int $status New HTTP status to be set
   * @return $this
   */
	public function status(int $status): self {
		assert(isset(self::$messages[$status]));
		if (isset(self::$messages[$status])) {
			$this->status = $status;
		}
		return $this;
	}

  /**
  * Get response body
  * @access public
  * @return string данные ответа клиенту
  */
	public function __toString(): string {
		return $this->body;
	}

  /**
   * Send body to output
   * @return $this
   */
	public function sendBody(): self {
		echo (string)$this;
		return $this;
	}

  /**
   * Send all staff to output: headers, body and so on
	 *
	 * @param string $content
   * @return $this
   */
	public function send(string $content = ''): self {
		return $this->sendHeaders()->setBody($content)->sendBody();
	}

  /**
  * Relocate user to url
	*
  * @param string $url полный HTTP-адрес страницы для редиректа
  * @param int $code код редиректа (301 | 302)
  * @return void
  */
	public static function redirect(string $url, int $code = 302): void {
		assert(in_array($code, [301, 302]));

		if ($url[0] === '/') {
			$url = Lang::getUrlPrefix() . $url;
		}

		(new static($code))
		->header('Content-type', '')
		->header('Location', $url)
		->sendHeaders();
		exit;
	}

  /**
  * Reset headers stack
  * @return Response
  */
	public function flushHeaders(): self {
		$this->headers = [];
		return $this;
	}

  /**
  * Push header to stack to be sent
  * @param string $header
  * @param string $value
  * @return Response
  */
	public function header(string $header, string $value): self {
		$this->headers[$header] = $value;
		return $this;
	}

	/**
	 * Send stacked headers to output
	 * @return Response
	 */
	public function sendHeaders(?callable $header_fn = null, ?callable $cookie_fn = null): self {
		if (!$header_fn) {
			$header_fn = function (string $key, string $value, bool $replace = true) {
				return header($key . ': ' . $value, $replace);
			};
		}
		Cookie::send($cookie_fn); // This is not good but fuck it :D
		if (headers_sent()) {
			return $this;
		}

	  // HTTP-строка статуса
		http_response_code($this->status);

		foreach ($this->headers as $header => $value) {
			$header_fn($header, $value, true);
		}

	  // Send header with execution time
		$header_fn('X-Server-Time', strval(intval(Request::$time_float * 1000)));
		$header_fn('X-Response-Time', strval(intval((microtime(true) - Request::$time_float) * 1000)));
		return $this;
	}

  /**
  * Set boy data to response
  * @access public
  * @param string $body
  * @return $this
  */
	public function setBody(string $body): self {
		$this->body = $body;
		return $this;
	}
}


class Secret {
	/**
	 *
	 * @param string $key
	 * @return void
	 */
	public function __construct(protected string $key) {
		$this->key = hex2bin($key);
	}

	/**
	 * Static helper to initialize with request key
	 * @param mixed $key
	 * @return static
	 */
	public static function with(string $key): static {
		return new static($key);
	}

	/**
	 *
	 * @param string $payload
	 * @return array{0:string,1:string}
	 * @throws Exception
	 * @throws SodiumException
	 */
	public function encrypt(string $payload): array {
		$nonce = random_bytes(12);
		$encrypted = sodium_crypto_aead_aes256gcm_encrypt($payload, '', $nonce, $this->key);
		return [$encrypted, $nonce];
	}

	/**
	 *
	 * @param string $encrypted
	 * @param string $nonce
	 * @return string
	 * @throws SodiumException
	 */
	public function decrypt(string $encrypted, string $nonce): string {
		return sodium_crypto_aead_aes256gcm_decrypt($encrypted, '', $nonce, $this->key);
	}
}


/**
 * Class Session
 * Work with sessions
 *
 * <code>
 * Session::start();
 * Session::set('key', 'Test value');
 * Session::get('key');
 * Session::remove('key');
 * if (Session::has('key')) echo 'Found key in Session';
 * Session::regenerate();
 * </code>
 *
 * Add calculated data if key not exists
 * <code>
 * Session::add('key', function () { return time(); });
 * </code>
 *
 * Get key from session with default value
 * <code>
 * Session:get('key', 'default');
 * </code>
 */
final class Session {
  /** @var Session $Instance */
	protected static self $Instance;

  /** @var array $container */
	protected static array $container = [];

	final public function __construct() {
	}

	/**
	 * Launch the session
	 *
	 * @return void
	 */
	public static function start(): void {
		session_name(config('session.name'));
		session_start();
		static::$container = &$_SESSION;
	}

	/**
	 * Get the current active session id
	 *
	 * @return string
	 */
	public static function id(): string {
		return session_id();
	}

	/**
	 * Destroy the current active session
	 *
	 * @return bool
	 */
	public static function destroy(): bool {
		return session_destroy();
	}

  /**
   * Regenerate new session ID
	 *
	 * @param bool $destroy
	 * @return void
   */
	public static function regenerate(bool $destroy = false): void {
		session_regenerate_id($destroy);
	}

  /**
   * @param string $key
   * @return bool
   */
	public static function has(string $key): bool {
		return isset(static::$container[$key]);
	}

  /**
   * Add new session var if it not exists
   * @param string $key
   * @param mixed $value Can be callable function, so it executes and pushes
   * @return void
   */
	public static function add(string $key, mixed $value): void {
		if (static::has($key)) {
			return;
		}

		static::set($key, is_callable($value) ? $value() : $value);
	}

  /**
   * Set new var into session
   * @param string $key
   * @param mixed $value
   * @return void
   */
	public static function set(string $key, mixed $value): void {
		static::$container[$key] = $value;
	}

  /**
   * Remove the key from session array
   * @param string $key
   * @return bool
   */
	public static function remove(string $key): bool {
		if (isset(static::$container[$key])) {
			unset(static::$container[$key]);
			return true;
		}
		return  false;
	}

  /**
   * Alias for self::remove
   * @see self::remove
   */
	public static function delete(string $key): bool {
		return static::remove($key);
	}

  /**
   * Get var with key from session array
   * @param string $key
   * @param mixed $default Return default there is no such key, set on closure
   * @return mixed
   */
	public static function get(string $key, mixed $default = null): mixed {
		if (!static::has($key) && $default && is_callable($default)) {
			$default = $default();
			static::set($key, $default);
		}
		return static::has($key) ? static::$container[$key] : $default;
	}
}


/**
 * Класс реализации представления
 *
 * @final
 *
 * <code>
 * View::create('template')->set(['test_var' => 'test_val'])->render();
 * </code>
 */
final class View {
	const VAR_PTRN = '\!?[a-z\_]{1}[a-z0-9\.\_]*';

	/** @var array<string,mixed> */
	protected array $data = [];
	/** @var string[] $routes */
	protected array $routes = [];
	/** @var string[] $output_filters */
	protected array $output_filters = [];
	/** @var string[] $compilers */
	protected array $compilers = [];

	protected string $body;
	protected string $source_dir;
	protected string $compile_dir;
	protected string $prefix = 'c';

	/**
	 * @static
	 * @var array<string,string>
	 */
	protected static array $filter_funcs = [
		'html' => 'htmlspecialchars',
		'url'  => 'rawurlencode',
		'json' => 'json_encode',
		'upper' => 'strtoupper',
		'lower' => 'strtolower',
		'ucfirst' => 'ucfirst',
		'md5' => 'md5',
		'nl2br' => 'nl2br',
		'count' => 'sizeof',
		'base64' => 'base64_encode',
		'lang' => 'Lang::translate',
		'date' => 'view_filter_date',
		'time' => 'view_filter_time',
		'datetime' => 'view_filter_datetime',
		'timestamp' => 'view_filter_timestamp',
		'raw'  => '',
	];

  /** @var string $template_extension */
	protected string $template_extension = 'tpl';

  /** @var array $block_path */
	protected array $block_path = [];

  /**
   * Финальный приватный конструктор, через него создания вида закрыто
   *
   * @see self::create
   */
	final protected function __construct() {
		$this->routes = [config('default.action')];

	  // Setup default settings
		$this->template_extension = config('view.template_extension');
		$this->source_dir = config('view.source_dir');
		$this->compile_dir = config('view.compile_dir');
	}

  /**
   * Add custom filter function that can be used with template var to modify it
   *
   * @param string $name alias to use in template
   * @param string $func global name of function
   * @return void
   */
	public static function registerFilterFunc(string $name, string $func): void {
		assert(!isset(static::$filter_funcs[$name]));
		if (str_contains($func, '::')) {
			[$class, $method] = explode('::', $func);
			assert(method_exists($class, $method));
		} else {
			assert(function_exists($func));
		}
		static::$filter_funcs[$name] = $func;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return self
	 */
	public function configure(array $config): self {
		foreach ($config as $prop => $val) {
			if (!property_exists($this, $prop)) {
				continue;
			}

			$this->$prop = $val;
		}
		return $this;
	}

  /**
   * @param string $template
   * @return self
   */
	public function prepend(string $template): self {
		array_unshift($this->routes, $template);
		return $this;
	}

  /**
   * @param string $template
   * @return self
   */
	public function append(string $template): self {
		$this->routes[] = $template;
		return $this;
	}

  /**
   * Создание нового объекта вида
   *
   * @static
   * @access public
   * @param string[] ...$routes Список всех роутов в нужной последовательности для сборки
   * @return self
   */
	public static function create(...$routes): self {
		$View = new static;
		$View->routes = $routes;
		return $View;
	}

	/**
	 * @param string $content
	 * @return self
	 */
	public static function fromString(string $content): self {
		$View = new static;
		$View->body = $content;
		return $View;
	}

  /**
   * Получает уже обработанные и готовые данные для вывода функцией self::render()
   *
   * @access public
   * @return string
   */
	public function __toString(): string {
		return $this->getBody();
	}

	/**
	 * @param callable $filter
	 * @return self
	 */
	public function addOutputFilter(callable $filter): self {
		$this->output_filters = $filter;
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getBody(): string {
		$body = $this->body;
		foreach ($this->output_filters as $filter) {
			$body = $filter($body);
		}
		return $body;
	}

  /**
   * Прикрепление массива как разных переменных в шаблон
   *
   * @access public
   * @param array<string,mixed> $data
   * @return self
   */
	public function set(array $data): self {
		$this->data = $data;
		return $this;
	}

	/**
	 * @param string|string[] $key
	 * @param mixed $val
	 * @return self
	 */
	public function assign(string|array $key, mixed $val = null): self {
		if (is_string($key)) {
			$this->data[$key] = $val;
		} elseif (is_array($key)) {
			$this->data = array_merge($this->data, $key);
		}
		return $this;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function &access(string $key): mixed {
		return $this->data[$key];
	}

  /**
   * Обработчик блочных элементов скомпилированном шаблоне
   *
   * @param string $key
   *   Имя переменной
   * @param mixed $param
   *   Сам параметр, его значение
   * @param mixed $item
   *   Текущий айтем, т.к. возможно блок является вложенным и нужно передать текущий
   *   обходной элемент, если блок не является массивом
   * @param Closure $block
   *   Скомпилированный код, которые отображается внутри блока
   * @return self
   */
	protected function block(string $key, mixed $param, mixed $item, Closure $block): self {
		static $arrays = [];
		$arrays[$key] = is_array($param);
		if ($arrays[$key] && is_int(key($param))) {
			$last = sizeof($param) - 1;
			$i = 0;
			foreach ($param as $value) {
				if (!is_array($value)) {
					$value = ['parent' => $item, 'this' => $value];
				}

				$value['global']     = &$this->data;
				$value['first']      = $i === 0;
				$value['last']       = $i === $last;
				$value['even']       = $i % 2 ? true : false;
				$value['odd']        = !$value['even'];
				$value['iteration']  = ++$i;
				$block($value);
			}
		} elseif ($param) {
			if ($arrays[$key]) {
				$item   = $param + ['global' => &$this->data, 'parent' => $item];
				$block($item);
				$item = $item['parent'];
			} else {
				$block($item);
			}
		}
		return $this;
	}

	/**
	 * @param string $v
	 * @param string $container
	 * @return string
	 */
	protected static function chunkVar(string $v, string $container = '$item'): string {
		$var = '';
		foreach (explode('.', $v) as $p) {
			$var .= ($var ? '' : $container) . '[\'' . $p . '\']';
		}
		return $var;
	}

	/**
	 * @param string $v
	 * @param string $container
	 * @return string
	 */
	protected static function chunkVarExists(string $v, string $container = '$item'): string {
		$parts = explode('.', $v);
		$sz = sizeof($parts);
		$var = '';
		$i = 0;
		foreach ($parts as $p) {
			++$i;
			if ($i === $sz) {
				continue;
			}

			$var .= ($var ? '' : $container) . '[\'' . $p . '\']';
		}
		$array = ($var ?: $container);
		return 'isset(' . $array . ') && array_key_exists(\'' . $parts[array_key_last($parts)] . '\', ' . $array . ')';
	}

	/**
	 * @param string $str
	 * @return string
	 */
	protected static function chunkParseParams(string $str): string {
		$str = trim($str);
		if (!$str) {
			return '';
		}

		$code = '';
		foreach (array_map('trim', explode(' ', $str)) as $item) {
			[$key, $val] = array_map('trim', explode('=', $item));
			$code .= '<?php ' . static::chunkVar($key) . ' = ' . static::chunkVar($val) . '; ?>';
		}
		return $code;
	}

  /**
   * @param string $str
   * @return string
   */
	protected static function chunkTransformVars(string $str): string {
		$filter_ptrn = implode(
			'|',
			array_map(
				function ($v) {
					return '\:' . $v;
				},
				array_keys(static::$filter_funcs)
			)
		);

		return preg_replace_callback(
			'#\{(' . static::VAR_PTRN . ')(' . $filter_ptrn . ')?\}#ium',
			function ($matches) {
				$filter = 'raw';
				if (isset($matches[2])) {
					$filter = substr($matches[2], 1);
				}

				return '<?php if (isset(' . ($v = static::chunkVar($matches[1], '$item')) . ')) {'
				. 'echo ' . static::$filter_funcs[$filter] . '(' . $v . ');'
				. '} ?>';
			},
			$str
		);
	}

  /**
   * Transform one line blocks to closed blocks
   * @param string $str
   * @return string
   */
	protected function chunkCloseBlocks(string $str): string {
		$line_block = '#\{(' . static::VAR_PTRN . ')\:\}(.+)$#ium';

	  // Могут быть вложенные
		while (preg_match($line_block, $str) > 0) {
			$str = preg_replace($line_block, '{$1}' . PHP_EOL . '$2' . PHP_EOL . '{/$1}', $str);
		}

		return $str;
	}

  /**
   * @param string $str
   * @return string
   */
	protected function chunkCompileBlocks(string $str): string {
		return preg_replace_callback(
			'#\{(' . static::VAR_PTRN . ')\}(.+?){\/\\1}#ius',
			function ($m) {
				// Oh Shit so magic :)
				$this->block_path[] = $m[1];
				$compiled  = static::chunkTransformVars($this->chunkCompileBlocks($m[2]));
				array_pop($this->block_path);

				// Если стоит отрицание
				$denial = false;
				$key    = $m[1];

				if (str_starts_with($m[1], '!')) {
					$key = substr($m[1], 1);
				}

				if (strlen($m[1]) !== strlen($key)) {
					$denial = true;
				}

				return
				'<?php $param = ' . static::chunkVarExists($m[1], '$item')
					. ' ? ' . static::chunkVar($m[1], '$item')
					. ' : null;'
				// Блок с тегом отрицанием (no_ | not_) только если не существует переменной как таковой
				. ($denial ? ' if (!isset($param)) $param = !( ' . static::chunkVarExists($key, '$item')
					. ' ? ' . static::chunkVar($key, '$item')
					. ' : null);' : '')
				. '$this->block(\'' . $key . '\', $param, $item, function ($item) { ?>'
				. $compiled
				. '<?php }); ?>';
			},
			$str
		);
	}

  /**
   * Optimize output of compiled chunk if needed
   * @param string $str
   * @return string
   */
	protected function chunkMinify(string $str): string {
	  // Remove tabs and merge into single line
		if (config('view.merge_lines')) {
			$str = preg_replace(['#^\s+#ium', "|\>\s*\r?\n\<|ius", "|\s*\r?\n|ius"], ['', '><', ' '], $str);
		}

	  // Remove comments
		if (config('view.strip_comments')) {
			$str = preg_replace('/<!\-\-.+?\-\->/is', '', $str);
		}

		return $str;
	}

  /**
   * Компиляция примитивов шаблона
   *
   * @param string $route
   *   Роут шаблона для компиляции
   * @return string
   *   Имя скомпилированного файла
   */
	protected function compileChunk(string $route): string {
		$source_file = $this->getSourceFile($route);
		$file_c = $this->getCompiledFile([$route]);
		if (!App::$debug && is_file($file_c)) {
			return $file_c;
		}

		$str = file_get_contents($source_file);
	  // Do precompile by custom compiler to make it possible to change vars after
		$compilers = array_merge($this->compilers[$route] ?? [], $this->compilers['*'] ?? []);
		if ($compilers) {
			foreach ($compilers as $compiler) {
				$str = $compiler($str, $route);
			}
		}

		$str = $this->chunkCloseBlocks($str);

	  // Компиляция блоков
		$str = $this->chunkCompileBlocks($str);

		$str = $this->chunkMinify($str);

	  // Замена подключений файлов
		$str = preg_replace_callback(
			'#\{\>([a-z\_0-9\/]+)(.*?)\}#ium', function ($matches) {
				return static::chunkParseParams($matches[2]) . $this->getChunkContent($matches[1]);
			}, $str
		);

	  // Замена динамичных подключений файлов
		$str = preg_replace_callback(
			'#\{\>\>([a-z\_0-9\.]+)(.*?)\}#ium', function ($matches) {
				$route = static::chunkVar($matches[1], '$item');
				return '<?php '
				. '$this->compileChunk(' . $route . ');'
				.'include $this->getCompiledFile([' . $route . ']);'
				.'?>'
				;
			}, $str
		);

	  // Переменные: {array.index}
		$str = static::chunkTransformVars($str);

		file_put_contents($file_c, $str, LOCK_EX);
		return $file_c;
	}

  /**
   * Компиляция всех чанков и получение результата
   *
   * @return self
   */
	protected function compile(): self {
		$file_c = $this->getCompiledFile();
		if (App::$debug || !is_file($file_c)) {
		  // Init global context
			$content = '<?php $item = &$this->data; ?>';
			foreach ($this->routes as $template) {
				$content .= $this->getChunkContent($template);
			}

			file_put_contents($file_c, $content, LOCK_EX);
		}
		include $file_c;
		return $this;
	}

  // This methods initialize and configure language if its required by config
	/**
	 * @return self
	 */
	protected function initLanguage(): self {
		if (Lang::isEnabled()) {
			$lang = Lang::current();
			$this->configure(
				[
					'compile_dir' => config('view.compile_dir') . '/' . $lang,
				]
			)
			->addCompiler(Lang::getViewCompiler($lang))
			->assign('LANGUAGE_LIST', Lang::getList($lang))
			->assign('CURRENT_LANGUAGE', Lang::getInfo($lang))
			->assign('LANG', $lang);
		}

		return $this;
	}

	/**
	 * @param string $template
	 * @return string
	 */
	protected function getChunkContent(string $template): string {
		return file_get_contents($this->compileChunk($template));
	}

	/**
	 * @param callable $compiler
	 * @param string $template
	 * @return self
	 */
	public function addCompiler(callable $compiler, string $template = '*'): self {
		$this->compilers[$template][] = $compiler;
		return $this;
	}

	/**
	 * @param string $route
	 * @return string
	 */
	protected function getSourceFile(string $route): string {
		assert(is_dir($this->source_dir));
		assert(isset($this->template_extension[0]));

		return $this->source_dir . '/' . $route . '.' . $this->template_extension;
	}

	/**
	 * @param string[] $routes
	 * @return string
	 */
	protected function getCompiledFile(array $routes = []): string {
		assert(is_dir($this->compile_dir) && is_writable($this->compile_dir));
		return $this->compile_dir
			. '/view-' . $this->prefix . '-'
			. md5($this->source_dir . ':' . implode(':', $routes ?: $this->routes))
			. '.tplc'
		;
	}

  /**
   * Рендеринг и подготовка данных шаблона на вывод
   *
   * @access public
   * @param bool $quiet Quiet mode render empty string if no template found
   * @return self
   *   Записывает результат во внутреннюю переменную $body
   *   и возвращает ссылку на объект
   */
	public function render(bool $quiet = false): self {
		$this->initLanguage();

		if (isset($this->body)) {
			return $this;
		}

		try {
			ob_start();
			$this->compile();
			$this->body = ob_get_clean();
		} catch (Throwable $e) {
			if (!$quiet) {
				throw $e;
			}

			$this->body = '';
		}
		return $this;
	}

	/**
	 * @return void
	 */
	public static function flush(): void {
		$dir = escapeshellarg(config('view.compile_dir'));
		system('for file in `find ' . $dir . ' -name \'view-*\'`; do rm -f $file; done');
	}
}


/**
 * Config workout for whole app
 * @param  string $param Param using dot for separate packages
 * @return mixed
 */
function config(string $param): mixed {
	static $config = [];
	if (!$config) {
		$config = include getenv('CONFIG_DIR') . '/config.php';
	}

	return $config[$param];
}

/**
 * Typify var to special type
 * @param mixed $var Reference to the var that should be typified
 * @param string $type [int|integer, uint|uinteger, double|float, udboule|ufloat, bool|boolean, array, string]
 * @return mixed
 *
 * <code>
 * $var = '1'; // string(1) "1"
 * $var = typify($var, $type);
 * var_dump($var); // int(1)
 * </code>
 */
function typify(mixed $var, string $type): mixed {
	switch ($type) {
		case 'int':
		case 'integer':
			$var = (int)$var;
			break;
		case 'uinteger':
		case 'uint':
			$var = (int)$var;
			if ($var < 0) {
				$var = 0;
			}
			break;
		case 'double':
		case 'float':
			$var = (float)$var;
			break;
		case 'udouble':
		case 'ufloat':
			$var = (float)$var;
			if ($var < 0) {
				$var = 0.0;
			}
			break;
		case 'boolean':
		case 'bool':
			$var = (in_array($var, ['no', 'none', 'false', 'off'], true) ? false : (bool)$var);
			break;
		case 'array':
			$var = $var ? (array)$var : [];
			break;
		case 'string':
			$var = (string)$var;
			break;
		default: // Do nothing here
			break;
	}

	return $var;
}

/**
 * Triggered events
 * @param string $event
 * @param array $payload Дополнительные данные для манипуляции
 * @return void
 */
function trigger_event(string $event, array $payload = []): void {
	static $map;
	if (!isset($map)) {
		$map = Env::load(config('common.trigger_map_file'));
	}

	if (!isset($map[$event])) {
		return;
	}

	array_walk(
		$map[$event], function (string $file) use ($payload) {
			extract(
				Input::extractTypified(
					App::getImportVarsArgs($file, config('common.trigger_param_file')),
					function ($key, $default = null) use ($payload) {
						return $payload[$key] ?? $default;
					}
				)
			);
			include $file;
		}
	);
}
/**
 * This is helper function to control dependencies in one container
 *
 * @param string $name
 * @param mixed $value if not set we do get container if set we do set container value
 * @return mixed
 */
function container(string $name, mixed $value = null): mixed {
	static $container = [];

  // Set container logic
	if (isset($value)) {
		assert(!isset($container[$name]));
		$container[$name] = $value;
		return $value;
	}

  // Get container logic
	assert(isset($container[$name]));
	$res = &$container[$name];
	if (is_callable($res)) {
		$res = $res();
	}
	return $res;
}

/**
 * Get short name for full qualified class name
 * @param string $class The name of class with namespaces
 * @return string
 */
function get_class_name(string $class): string {
	return (new ReflectionClass($class))->getShortName();
}

// Missed functions for large integers for BCmath
/**
 * @param string $hex
 * @return string
 */
function bchexdec(string $hex): string {
	$dec = 0;
	$len = strlen($hex);
	for ($i = 1; $i <= $len; $i++) {
		$dec = bcadd("$dec", bcmul((string)(hexdec($hex[$i - 1])), bcpow('16', (string)($len - $i))));
	}
	return $dec;
}

/**
 * @param string $dec
 * @return string
 */
function bcdechex(string $dec): string {
	$hex = '';
	do {
		$last = bcmod("$dec", '16');
		$hex = dechex((int)$last) . $hex;
		$dec = bcdiv(bcsub($dec, $last), '16');
	} while ($dec > 0);
	return $hex;
}


// bench("a") -> start benching labeled
// bench() -> return array with results
// bench("reset") -> reset data (reserved)
/**
 * @param ?string $txt
 * @return ?string[]
 */
function bench(?string $txt = null): ?array {
	static $t = [], $r = [];
	if ($txt === null) {
		$lines = [];
		foreach ($r as $label => $vals) {
			$lines[$label] = sprintf('%.3f', (array_sum($vals) / sizeof($vals)) * 1000) . 'ms';
		}
		$t = $r = [];
		return $lines;
	}

	if ($txt === 'reset') {
		$t = $r = [];
		return null;
	}
	$n = microtime(true);

	if ($txt && !isset($r[$txt])) {
		$r[$txt] = [];
	}

	if ($txt && isset($t[$txt])) {
		$r[$txt][] = $n - $t[$txt][array_key_last($t[$txt])];
	}
	$t[$txt][] = $n;

	return null;
}

/**
 * @param array $arrays
 * @return array
 */
function array_cartesian(array $arrays): array {
	$result = [];
	$keys = array_keys($arrays);
	$reverse_keys = array_reverse($keys);
	$size = (int)(sizeof($arrays) > 0);
	foreach ($arrays as $array) {
		$size *= sizeof($array);
	}
	for ($i = 0; $i < $size; $i ++) {
		$result[$i] = [];
		foreach ($keys as $j) {
			$result[$i][$j] = current($arrays[$j]);
		}
		foreach ($reverse_keys as $j) {
			if (next($arrays[$j])) {
				break;
			}

			if (!isset($arrays[$j])) {
				continue;
			}

			reset($arrays[$j]);
		}
	}
	return $result;
}

// $state_map = array_order_by($state_map, 'created_at', SORT_DESC, SORT_NUMERIC/*, 'id', SORT_DESC, SORT_NUMERIC*/);
/**
 * @return array
 */
function array_order_by(): array {
	$args = func_get_args();
	$data = array_shift($args);
	foreach ($args as $n => $field) {
		if (!is_string($field)) {
			continue;
		}

		$tmp = [];
		foreach ($data as $key => $row) {
			$tmp[$key] = $row[$field];
		}
		$args[$n] = $tmp;
	}
	$args[] = &$data;
	call_user_func_array('array_multisort', $args);
	return array_pop($args);
}

/**
 * This is simple helper in case we need to throw exception when has error
 *
 * @param array{0:?string,1:mixed} $response
 *   Stanadrd array in presentation [err, result]
 *   Where err should be string and result mixed
 * @param string $error
 * @return mixed
 */
function result(array $response, string $error = 'result'): mixed {
	if (isset($response[0]) && is_array($response[0])) {
		$errors = array_filter(array_column($response, 0));
		if ($errors) {
			throw new Error('Errors while ' . $error . ' in multiple result: ' . var_export($errors, true));
		}
		return array_column($response, 1);
	}

	[$err, $result] = $response;
	if ($err) {
		throw new Error('Error while ' . $error . ': ' . $err . '. Got result: ' . var_export($result, true));
	}
	return $result;
}

if (!function_exists('defer')) {
	/**
	 * @param ?SplStack $ctx
	 * @param callable $cb
	 * @return void
	 */
	function defer(?SplStack &$ctx, callable $cb): void {
		$ctx ??= new SplStack();

		$ctx->push(
			new class($cb) {
				protected $cb;
				public function __construct(callable $cb) {
					$this->cb = $cb;
				}

				public function __destruct() {
					\call_user_func($this->cb);
				}
			}
		);
	}
}

// Filter function to format output
/**
 * @param string $v
 * @return string
 */
function view_filter_date(string $v): string {
	$ts = is_numeric($v) ? (int)$v : strtotime("$v UTC");
	return $ts ? date('Y-m-d', $ts) : $v;
}

/**
 * @param string $v
 * @return string
 */
function view_filter_time(string $v): string {
	$ts = is_numeric($v) ? (int)$v : strtotime("$v UTC");
	return $ts ? date('H:i', $ts) : $v;
}

/**
 * @param string $v
 * @Return string
 */
function view_filter_datetime(string $v): string {
	$ts = is_numeric($v) ? (int)$v : strtotime("$v UTC");
	return $ts ? date('Y-m-d H:i:s', $ts) : $v;
}

/**
 * @param string $v
 * @return string
 */
function view_filter_timestamp(string $v): string {
	return (string)(strtotime($v) ?: $v);
}
