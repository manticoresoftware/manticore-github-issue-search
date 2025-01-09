<?php declare(strict_types=1);

/**
 * @route api/search/([^/]+): org
 * @route api/search/([^/]+)/([^/]+): org, repo
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

$filters['org_id'] = $org->id;
if (!isset($filters['repos'])) {
	$filters['repos'] = $repo
	? [$repo->id]
	: array_map(
		fn($repo) => $repo['id'],
		result(Search::getRepos($org))
	);
}
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

	if ($search === 'keyword-search') {
		$filters['keyword_search_only'] = true;
	}

	if ($search === 'semantic-search') {
		$filters['semantic_search_only'] = true;
	}
}
return Search::process($search_query, $filters, $sort, $offset);
