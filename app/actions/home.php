<?php declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable

/**
 * @route home
 * @var string $url
 * @var string $query
 */

use App\Component\Search;
use App\Model\Repo;

$project = null;
if ($url) {
	/** @var Repo $repo */
	[$org, $repo] = result(Search::fetchIssues(rtrim($url, '/')));
	$suffix = $repo ? "{$repo->name}/" : '';
	return Response::redirect("/{$org->name}/$suffix");
}

$showcase = result(Search::getShowcaseRepos(6));
