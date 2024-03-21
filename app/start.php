<?php declare(strict_types=1);

View::registerFilterFunc('highlight', 'htmlspecialchars_without_span');

function htmlspecialchars_without_span(string $string): string {
	$string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
	$string = preg_replace_callback(
		'/&lt;(\/?span.*?)&gt;/i',
		static function (array $matches) {
			return '<' . htmlspecialchars_decode($matches[1]) . '>';
		},
		$string
	);

	return $string;
}

$tokens = array_map('trim', explode(' ', getenv('GITHUB_TOKENS') ?: ''));
putenv('GITHUB_TOKEN=' . ($tokens[0] ?? ''));
