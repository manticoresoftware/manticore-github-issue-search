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

static::setExceptionHandler(
	Throwable::class, static function (Throwable $t) {
		[$code, $text] = match ($t->getMessage()) {
			'e_org_not_found' => [404, "We cant't find organization."],
			'e_repo_not_public' => [403, 'You try to add a repository that is not public.'],
			'e_repo_no_issues' => [403, "You try to add a repository that doesn't have issues."],
			'e_repo_not_found' => [404, "We can't find the GitHub repository you're looking for."],
			'e_github_token_limit_exceed' => [500, 'Your GitHub token limit has been exceeded. Please update it or wait.'],
			default => [500, 'Something went wrong'],
		};


		$View = View::create('error')
		->assign('BUNDLE_HASH', random_int(10000, 99999))
		->assign('text', $text)
		->render();
		return Response::current()
		->status($code)
		->header('Content-type', 'text/html;charset=utf8')
		->send((string)$View);
	}
);
