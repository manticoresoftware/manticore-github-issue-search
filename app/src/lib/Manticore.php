<?php declare(strict_types=1);

namespace App\Lib;

use App\Model\Comment;
use App\Model\Issue;
use App\Model\Repo;
use App\Model\User;
use Manticoresearch\Client;
use Manticoresearch\ResultHit;
use Manticoresearch\Search;
use ReflectionClass;
use Result;
use Throwable;

class Manticore {
	const PERSISTENCE_FACTOR = 0.8;

	const HIGHLIGHT_CONFIG = [
		// 'html_strip_mode' => 'strip',
		'pre_tags' => '<span>',
		'post_tags' => '</span>',
	];

	/**
	 * Get current instance of the client
	 * @return Client
	 */
	protected static function client(): Client {
		static $client;

		if (!$client) {
			$config = ['host' => 'manticore','port' => 9308];
			$client = new Client($config);
		}

		return $client;
	}

	/**
	 * Add any type of model to the index on permanent store
	 * @param array<Issue|User|Comment> $list
	 * @return Result
	 */
	public static function add(array $list): Result {
		if (!$list) {
			return ok();
		}

		try {
			$reflection = new ReflectionClass($list[0]);
			$client = static::client();
			$index = $client->index(strtolower($reflection->getShortName()));
			$docs = array_map(fn ($v) => (array)$v, $list);
			$index->replaceDocuments($docs);
			return ok();
		} catch (Throwable $t) {
			return err('e_add_issue_failed');
		}
	}

	/**
	 * Get repo by org and name
	 * @param string $org
	 * @param string $name
	 * @return Result<Repo>
	 */
	public static function findRepo(string $org, string $name): Result {
		$client = static::client();
		$index = $client->index('repo');
		$result = $index->search('')->filter('org', $org)->filter('name', $name)->get();
		foreach ($result as $doc) {
			return ok(new Repo($doc->getData()));
		}

		return err('e_repo_not_found');
	}

	/**
	 * Find repo id by organization and name
	 * @param  string $org
	 * @param  string $name
	 * @param array<string,mixed> $data
	 * @return Result<int>
	 */
	public static function findOrCreateRepo(string $org, string $name, array $data = []): Result {
		$repoResult = static::findRepo($org, $name);
		if (!$repoResult->err) {
			return $repoResult;
		}

		$data = array_replace(
			[
			'org' => $org,
			'name' => $name,
			'is_indexing' => true,
			'expected_issues' => 0,
			'issues' => 0,
			'comments' => 0,
			'updated_at' => 0,
			], $data
		);
		$repo = new Repo($data);
		$result = static::add([$repo]);
		if ($result->err) {
			return $result;
		}
		return ok($repo);
	}

	/**
	 * Perform the multimodel search and return combined list
	 * @param  string $query
	 * @param array<string,mixed> $filters
	 * @param string $sort One of: best-match, most-commented,
	 *  least-commented, newest, oldest, recently-updated,
	 *  least-recently-updated, most-reacted,
	 *  most-positive-reacted, most-negative-reacted
	 * @param int $offset
	 * @return Result<array{time:int,list:array<Issue>}>
	 */
	public static function search(string $query = '', array $filters = [], string $sort = 'best-match', int $offset = 0): Result {
		$client = static::client();
		$issueIndex = $client->index('issue');
		$commentIndex = $client->index('comment');
		$userIndex = $client->index('user');
		$labelIndex = $client->index('label');
		$searchIssues = $filters['issues'] ?? false;
		$searchComments = $filters['comments'] ?? false;
		$issueFilters = array_merge($filters['common'] ?? [], $filters['issue'] ?? []);
		$commentFilters = array_merge($filters['common'] ?? [], $filters['comment'] ?? []);
		$time = 0;

		$issueCount = 0;
		$issueRelation = 'eq';
		$items = [];
		if ($searchIssues) {
			$search = $issueIndex
				->search($query)
				->offset($offset)
				->highlight(
					['title', 'body'],
					static::HIGHLIGHT_CONFIG
				);
			;
			// Special state filter
			if (isset($filters['state'])) {
				$fn = match ($filters['state']) {
					'open' => 'filter',
					'closed' => 'notFilter',
				};
				$search->$fn('closed_at', 0);
			}
			if (isset($filters['comment_ranges'])) {
				$condition = Search::FILTER_AND;
				foreach ($filters['comment_ranges'] as $range) {
					if (isset($range['min'])) {
						$search->filter('comments', 'gt', $range['min'], $condition);
						$condition = Search::FILTER_OR;
					}

					if (!isset($range['max'])) {
						continue;
					}

					$search->filter('comments', 'lte', $range['max'], $condition);
					$condition = Search::FILTER_OR;
				}
			}

			foreach ($issueFilters as $key => $value) {
				$search->filter($key, is_array($value) ? 'in' : 'equals', $value);
			}

			// Apply sorting
			static::applySorting($search, $sort);
			$docs = $search->get();
			$time += (int)($docs->getResponse()->getTime() * 1000);
			// Confused ?
			$issueRelation = $docs->getResponse()->getResponse()['hits']['total_relation'] ?? 'eq';
			$issueCount = $docs->getTotal();
			foreach ($docs as $n => $doc) {
				$row = ['id' => (int)$doc->getId(), ...$doc->getData()];
				$row['highlight'] = static::highlight($doc, strip_tags($row['body']));
				$user = $userIndex->getDocumentById($row['user_id'])?->getData();
				$row['user'] = $user;
				// TODO: migrate to by ids
				$labels = [];
				if ($row['label_ids']) {
					foreach ($row['label_ids'] as $label_id) {
						$label = $labelIndex->getDocumentById($label_id)?->getData();
						if (!$label) {
							continue;
						}
						$labels[] = $label;
					}
				}
				$row['labels'] = $labels;
				$user = $labelIndex->getDocumentById($row['user_id'])?->getData();
				$row['is_closed'] = $row['closed_at'] > 0;
				$row['is_open'] = !$row['is_closed'];
				$rbpScore = pow(static::PERSISTENCE_FACTOR, $n) * $doc->getScore();
				$items[] = ['score' => $rbpScore, 'issue' => $row, 'comment' => []];
			}
		}

		$commentCount = 0;
		$commentRelation = 'eq';
		if ($searchComments) {
			$search = $commentIndex
				->search($query)
				->offset($offset)
				->highlight(
					['body'],
					static::HIGHLIGHT_CONFIG
				);
			foreach ($commentFilters as $key => $value) {
				$search->filter($key, is_array($value) ? 'in' : 'eq', $value);
			}

			// We can sort comments by all but comments
			if ($sort !== 'most-commented' && $sort !== 'least-commented') {
				static::applySorting($search, $sort);
			}

			$docs = $search->get();
			$time += (int)($docs->getResponse()->getTime() * 1000);
			$commentRelation = $docs->getResponse()->getResponse()['hits']['total_relation'] ?? 'eq';
			$commentCount = $docs->getTotal();
			foreach ($docs as $n => $doc) {
				$row = ['id' => (int)$doc->getId(), ...$doc->getData()];
				$row['highlight'] = static::highlight($doc, strip_tags($row['body']));
				$user = $userIndex->getDocumentById($row['user_id'])?->getData();
				$row['user'] = $user;

				$issue = $issueIndex->getDocumentById($row['issue_id'])?->getData();
				if ($issue) {
					$user = $userIndex->getDocumentById($issue['user_id'])->getData();
					$issue['user'] = $user;
				}
				$rbpScore = pow(static::PERSISTENCE_FACTOR, $n) * $doc->getScore();
				$items[] = ['score' => $rbpScore, 'issue' => $issue, 'comment' => $row];
			}
		}

		// Sort by score
		usort(
			$items, function ($a, $b) {
				return $b['score'] <=> $a['score'];
			}
		);

		return ok(
			[
			'time' => $time,
			'items' => $items,
			'count' => [
				'total' => $issueCount + $commentCount,
				'total_more' => $issueRelation !== 'eq' || $commentRelation !== 'eq',
				'issue' => $issueCount,
				'issue_more' => $issueRelation !== 'eq',
				'comment' => $commentCount,
				'comment_more' => $commentRelation !== 'eq',
			],
			]
		);
	}

	/**
	 * Get counters for issues in given repository
	 * @param  int    $repoId
	 * @return Result<array{open:int,closed:int}>
	 */
	public static function getIssueCounters(int $repoId): Result {
		$client = static::client();
		$index = $client->index('issue');
		$search = $index->search('');
		$facets = $search
			->limit(0)
			->filter('repo_id', $repoId)
			->expression('open', 'if(closed_at=0,1,0)')
			->facet('open', 'counters', 2)
			->get()
			->getFacets();

		$counters = [
			'open' => 0,
			'closed' => 0,
		];
		foreach ($facets['counters']['buckets'] as $bucket) {
			$counters[match ($bucket['key']) {
				1 => 'open',
				0 => 'closed',
			}] = $bucket['doc_count'];
		}

		return ok($counters);
	}

	/**
	 * Fetch unique users by given field from the issues
	 * @param  int         $repoId
	 * @param  string      $field
	 * @param  int $max
	 * @return Result<array<User>>
	 */
	public static function getUsers(int $repoId, string $field, int $max = 1000): Result {
		$client = static::client();
		$index = $client->index('issue');
		$search = $index->search('');
		$facets = $search
			->limit(0)
			->filter('repo_id', $repoId)
			->facet($field, 'users', $max)
			->get()
			->getFacets();
		$userIds = array_filter(array_column($facets['users']['buckets'], 'key'));

		$index = $client->index('user');
		// TODO: migrate to getDocumentByIds but it does not work now
		$docs = [];
		foreach ($userIds as $userId) {
			$user = $index->getDocumentById($userId)?->getData();
			// Missing assignees
			if (!$user) {
				continue;
			}
			$docs[] = [
				'id' => $userId,
				...$user,
			];
		}

		return ok($docs);
	}

	/**
	 * @param  int $limit
	 * @return Result<array<Repo>>
	 */
	public static function getRepos(int $limit = 1000): Result {
		$client = static::client();
		$index = $client->index('repo');
		$search = $index->search('');
		$docs = $search
			->limit($limit)
			->sort('issues', 'desc')
			->get();
		$result = [];
		foreach ($docs as $doc) {
			$result[] = new Repo([
				'id' => (int)$doc->getId(),
				...$doc->getData(),
			]);
		}

		return ok($result);
	}

	/**
	 * Fetch unique labels by given field from the issues
	 * @param  int         $repoId
	 * @param  int $max
	 * @return Result<array<User>>
	 */
	public static function getLabels(int $repoId, int $max = 1000): Result {
		$client = static::client();
		$index = $client->index('issue');
		$search = $index->search('');
		$facets = $search
			->limit(0) # TODO: fix it after manticore will fix bug
			->filter('repo_id', $repoId)
			->facet('label_ids', 'labels', $max)
			->get()
			->getFacets();
		$labelIds = array_filter(array_column($facets['labels']['buckets'], 'key'));
		$index = $client->index('label');
		// TODO: migrate to getDocumentByIds but it does not work now
		$docs = [];
		foreach ($labelIds as $labelId) {
			$label = $index->getDocumentById($labelId)?->getData();
			$docs[] = [
				'id' => $labelId,
				...$label,
			];
		}

		return ok($docs);
	}

	/**
	 * Get comment ranges for the repo with counts for each
	 * @param  int    $repoId
	 * @param  array<int>  $values List of values to use for aggregation
	 * @return Result<array<mixed>>
	 */
	public static function getCommentRanges(int $repoId, array $values): Result {
		$client = static::client();
		$index = $client->index('issue');
		$search = $index->search('');
		$range = implode(',', $values);
		$facets = $search
			->limit(0)
			->filter('repo_id', $repoId)
			->expression('range', "INTERVAL(comments, $range)")
			->facet('range', 'counters', sizeof($values) + 1)
			->get()
			->getFacets();
		uasort($facets['counters']['buckets'], fn ($a, $b) => $a['key'] < $b['key'] ? -1 : 1);
		$docs = [];
		$n = 1;
		foreach ($facets['counters']['buckets'] as $bucket) {
			$doc = [
				'id' => $n++,
				'count' => $bucket['doc_count'],
			];

			if ($bucket['key'] > 0) {
				$doc['min'] = ($values[$bucket['key'] - 1] + 1);
			}

			if (isset($values[$bucket['key']])) {
				$doc['max'] = $values[$bucket['key']];
			}

			$doc['name'] = match (true) {
				!isset($doc['min']) => "< {$doc['max']}",
				!isset($doc['max']) => "> {$doc['min']}",
				default => "{$doc['min']} – {$doc['max']}",
			};

			$docs[] = $doc;
		}
		return ok($docs);
	}

	/**
	 * Generate higlihght with begin-end cut and using default value
	 * @param  ResultHit $doc
	 * @param  string    $body
	 * @return string
	 */
	protected static function highlight(ResultHit $doc, string $body): string {
		$highlights = array_filter(array_map(trim(...), $doc->getHighlight()['body']));
		if (!$highlights) {
			$highlights = [$body];
		}
		return implode(' ', $highlights);
		// $lastI = sizeof($highlights) - 1;
		// $result = '';
		// foreach ($highlights as $i => $highlight) {
		// 	if (substr($highlight, 0, 5) !== substr($body, 0, 5)) {
		// 		$result .= "…{$highlight}";
		// 		continue;
		// 	}

		// 	if ($i === $lastI && substr($highlight, -5, 5) !== substr($body, -5, 5)) {
		// 		$result .= "{$highlight}…";
		// 		continue;
		// 	}

		// 	$result .= "$highlight";
		// }

		// return $result;
	}

	/**
	 * Apply sorting to the active serach instance
	 * @param  Search $search
	 * @param  string $sort
	 * @return void
	 */
	protected static function applySorting(Search $search, string $sort): void {
		$sorting = match ($sort) {
			'most-commented' => ['comments', 'desc'],
			'least-commented' => ['comments', 'asc'],
			'newest' => ['created_at', 'desc'],
			'oldest' => ['created_at', 'asc'],
			'recently-updated' => ['updated_at', 'desc'],
			'least-recently-updated' => ['updated_at', 'asc'],
			'most-reacted' => ['reactions.total_count', 'desc'],
			'most-positive-reacted' => ['positive_reactions', 'desc'],
			'most-negative-reacted' => ['negative_reactions', 'desc'],
			default => [], // best-match
		};
		if (!$sorting) {
			return;
		}

		if ($sort === 'most-positive-reacted') {
			$search->expression(
				'positive_reactions',
				'integer(reactions.`+1`) + integer(reactions.hooray) + integer(reactions.heart) + integer(reactions.rocket)'
			);
		} elseif ($sort === 'most-negative-reacted') {
			$search->expression(
				'negative_reactions',
				'integer(reactions.`-1`) + integer(reactions.laugh) + integer(reactions.confused)'
			);
		}
		$search->sort(...$sorting);
	}
}
