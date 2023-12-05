<?php declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable
/**
 * @route ([^/\.]+)/([^/\.]+): org, repo
 * @var string $org
 * @var string $repo
 * @var string $query
 * @var array $filters
 * @var string $sort
 * @var int $offset
 */

use App\Component\Search;
use App\Model\Repo;

/** @var array{count:array{total:int,issue:int,total_more:bool,issue_more:bool,comment_more:bool,comment:int}} $list */
$repo = result(Search::fetchIssues("https://github.com/{$org}/{$repo}"));
/** @var Repo $repo */
$project = $repo->getProject();
$url = $repo->getUrl();
$counters = result(Search::getRepoCounters($repo));
$list = result(Search::process($repo, $query, $filters, $sort, $offset));

$is_issues_active = $filters['issues'] ?? false;
$is_comments_active = $filters['comments'] ?? false;
$is_pull_requests_active = $filters['pull_requests'] ?? false;
$is_everywhere_active =
	(!$is_issues_active && !$is_comments_active && !$is_pull_requests_active)
		||
	($filters['issues'] && $filters['comments'] && $filters['pull_requests']);
if ($is_everywhere_active) {
	$is_comments_active = $is_issues_active = $is_pull_requests_active = false;
}

$is_open_active = ($filters['state'] ?? '') === 'open';
$is_closed_active = ($filters['state'] ?? '') === 'closed';
if ($query) {
	$counters = array_merge(
		$counters, [
		'total' => $list['count']['total'] . ($list['count']['total_more'] ? '+' : ''),
		'issues' => $list['count']['issue'] . ($list['count']['issue_more'] ? '+' : ''),
		'pull_requests' => $list['count']['pull_request'] . ($list['count']['pull_request_more'] ? '+' : ''),
		'comments' => $list['count']['comment'] . ($list['count']['comment_more'] ? '+' : ''),
		]
	);
}

// Sorting list with some dynamic logic
$sort_list = [
  [
	'value' => 'best-match',
	'name' => 'Best match',
  ],
  [
	'value' => 'most-commented',
	'name' => 'Most commented',
  ],
  [
	'value' => 'least-commented',
	'name' => 'Least commented',
  ],
  [
	'value' => 'newest',
	'name' => 'Newest',
  ],
  [
	'value' => 'oldest',
	'name' => 'Oldest',
  ],
  [
	'value' => 'recently-updated',
	'name' => 'Recently updated',
  ],
  [
	'value' => 'least-recently-updated',
	'name' => 'Least recently updated',
  ],
  [
	'value' => 'most-reacted',
	'name' => 'Most reacted',
  ],
  [
	'value' => 'most-positive-reacted',
	'name' => 'Most positive reacted',
  ],
  [
	'value' => 'most-negative-reacted',
	'name' => 'Most negative reacted',
  ],
];
foreach ($sort_list as &$item) {
	if ($item['value'] !== $sort) {
		continue;
	}

	$item['selected'] = true;
}

$getUrlFn = function (array $config) use ($repo, $query, $filters, $sort) {
	return "/{$repo->org}/{$repo->name}?" . http_build_query(
		[
		'query' => $query,
		'filters' => array_merge($filters, $config),
		'sort' => $sort,
		]
	);
};

$filter_urls = [
	'everywhere' => $getUrlFn(['issues' => 1, 'pull_requests' => 1, 'comments' => 1]),
	'issues' => $getUrlFn(['issues' => 1, 'pull_requests' => 0, 'comments' => 0]),
	'pull_requests' => $getUrlFn(['issues' => 0, 'pull_requests' => 1, 'comments' => 0]),
	'comments' => $getUrlFn(['issues' => 0, 'pull_requests' => 0, 'comments' => 1]),
	'open' => $getUrlFn(['state' => 'open']),
	'closed' => $getUrlFn(['state' => 'closed']),
];
$url = "/{$repo->org}/{$repo->name}?" . http_build_query(['query' => $query]);

// For template active filters
$form_vars = [];
foreach ($filters as $k => $v) {
	if (is_array($v)) {
		foreach ($v as $mk => $mv) {
			$form_vars[] = [
				"is_{$k}" => true,
				'name' => "filters[$k][]",
				'value' => $mv,
			];
		}
	} else {
		$form_vars[] = [
			"is_{$k}" => true,
			'name' => "filters[$k]",
			'value' => $v,
		];
	}
}

$form_vars[] = [
	'is_sort' => true,
	'name' => 'sort',
	'value' => $sort,
];

$has_filters = sizeof($filters) > 0 || ($sort && $sort !== 'best-match');
$authors = result(Search::getAuthors($repo));
$assignees = result(Search::getAssignees($repo));
$labels = result(Search::getLabels($repo));
$comment_ranges = result(Search::getCommentRanges($repo));
// If we requested with navigation, return results only
if (Request::current()->getHeader('x-requested-with') === 'navigation') {
	return View::create('base/results');
}
$showcase = result(Search::getRepos(10));
