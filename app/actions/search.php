<?php declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable
/**
 * @route ([^/\.]+): org
 * @route ([^/\.]+)/([^/\.]+): org, repo
 * @var string $org
 * @var string $repo
 * @var string $query
 * @var array $filters
 * @var string $sort
 * @var string $search keyword-search-fuzzy-layouts
 * @var int $offset
 */

use App\Component\Search;
use App\Lib\TextEmbeddings;

$url = "https://github.com/{$org}/{$repo}";
[$org, $repo] = result(Search::fetchIssues($url));
$search_query = Search::sanitizeQuery($query);
if ($query !== $search_query) {
	$show_query = true;
}
$filters['org_id'] = $org->id;
if (!isset($filters['repos'])) {
	$filters['repos'] = $repo
	? [$repo->id]
	: array_map(
		fn($repo) => $repo['id'],
		result(Search::getRepos($org))
	);
}
$multiple_repos = sizeof($filters['repos']) > 1;
$filters = Search::prepareFilters($filters);
// Add vector search embeddings if we have query
if ($search_query) {
	if (str_starts_with($search, 'keyword-search')) {
		if (str_contains($search, 'fuzzy')) {
			$filters['use_fuzzy'] = true;
		}
		if (str_contains($search, 'layouts')) {
			$filters['use_layouts'] = true;
		}
	} else {
		$embeddings = result(TextEmbeddings::get($query));
		$filters['embeddings'] = $embeddings;
	}
	if ($search === 'semantic-search') {
		$filters['semantic_search_only'] = true;
	}
}
$list = result(Search::process($search_query, $filters, $sort, $offset));

$search_in = $filters['index'] ?? 'everywhere';
[
	$is_everywhere_active,
	$is_issues_active,
	$is_pull_requests_active,
	$is_comments_active,
] = match ($search_in) {
	'everywhere' => [true, false, false, false],
	'issues' => [false, true, false, false],
	'pull_requests' => [false, false, true, false],
	'comments' => [false, false, false, true],
};

$is_everywhere_active = (!$is_issues_active && !$is_comments_active && !$is_pull_requests_active);

if ($is_everywhere_active) {
	$is_comments_active = $is_issues_active = $is_pull_requests_active = false;
}

$is_open_active = ($filters['state'] ?? '') === 'open';
$is_closed_active = ($filters['state'] ?? '') === 'closed';
$is_any_active = !$is_open_active && !$is_closed_active;
$counters = [
	'total' => $list['count']['total'] . ($list['count']['total_more'] ? '+' : ''),
	'issues' => $list['count']['issue'] . ($list['count']['issue_more'] ? '+' : ''),
	'pull_requests' => $list['count']['pull_request'] . ($list['count']['pull_request_more'] ? '+' : ''),
	'comments' => $list['count']['comment'] . ($list['count']['comment_more'] ? '+' : ''),
	'open_issues' => $list['count']['open'],
	'closed_issues' => $list['count']['closed'],
	'any_issues' => $list['count']['any'],
];

$search_list = [
  [
	'value' => 'keyword-search-fuzzy-layouts',
	'name' => 'Fuzzy+Keyboard',
  ],
  [
	'value' => 'keyword-search-fuzzy',
	'name' => 'Fuzzy Search',
  ],
  [
	'value' => 'keyword-search',
	'name' => 'Keyword Search',
  ],
  [
	'value' => 'semantic-search',
	'name' => 'Semantic Search',
  ],
  [
	'value' => 'hybrid-search',
	'name' => 'Hybrid Search',
  ],
];
foreach ($search_list as &$item) {
	if ($item['value'] !== $search) {
		continue;
	}

	$item['selected'] = true;
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

$suffix = $repo ? "/{$repo->name}" : '';
$page_url = "/{$org->name}{$suffix}";
$getUrlFn = function (array $config) use ($page_url, $query, $filters, $sort) {
	return "{$page_url}?" . http_build_query(
		[
		'query' => $query,
		'filters' => array_merge($filters, $config),
		'sort' => $sort,
		]
	);
};

$filter_urls = [
	'everywhere' => $getUrlFn(['index' => 'everywhere']),
	'issues' => $getUrlFn(['index' => 'issues']),
	'pull_requests' => $getUrlFn(['index' => 'pull_requests']),
	'comments' => $getUrlFn(['index' => 'comments']),
	'open' => $getUrlFn(['state' => 'open']),
	'closed' => $getUrlFn(['state' => 'closed']),
];
$url = "{$page_url}?" . http_build_query(['query' => $query]);

// For template active filters
$form_vars = array_map(
	function ($key, $value) {
		if (!is_array($value)) {
			return [
			"is_{$key}" => true,
			'name' => "filters[$key]",
			'value' => $value,
			];
		}

		$result = [];
		foreach ($value as $subKey => $subValue) {
			$name = is_array($subValue) ? "filters[$key][$subKey][]" : "filters[$key][]";
			foreach ((array)$subValue as $val) {
				$result[] = [
				"is_{$key}" => true,
				'name' => $name,
				'value' => $val,
				];
			}
		}
		return $result;
	}, array_keys($filters), $filters
);

// Flatten the array since array_map can return nested arrays
$form_vars = array_merge([], ...$form_vars);

// Add sorting option
$form_vars[] = [
	'is_sort' => true,
	'name' => 'sort',
	'value' => $sort,
];
$form_vars[] = [
	'is_search' => true,
	'name' => 'search',
	'value' => $search,
];
$repos = result(Search::getRepos($org));
$repo_ids = array_map(fn($repo) => $repo->id, $repos);
$authors = result(Search::getAuthors($repo_ids, $query, $filters));
$assignees = result(Search::getAssignees($repo_ids, $query, $filters));
$labels = result(Search::getLabels($repo_ids, $query, $filters));
$comment_ranges = result(Search::getCommentRanges($repo_ids, $query, $filters));

$author_counters = result(Search::getCounterMap('author', $repo_ids, $query, $filters));
$assignee_counters = result(Search::getCounterMap('assignee', $repo_ids, $query, $filters));
$label_counters = result(Search::getCounterMap('label', $repo_ids, $query, $filters));
$comment_range_counters = result(Search::getCounterMap('comment_range', $repo_ids, $query, $filters));

// If we requested with navigation, return results only
if (Request::current()->getHeader('x-requested-with') === 'navigation') {
	return View::create('base/results');
}
$showcase = result(Search::getShowcaseRepos(10));
