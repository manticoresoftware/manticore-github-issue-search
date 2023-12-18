<?php declare(strict_types=1);

namespace App\Component;

use App\Lib\Github;
use App\Lib\Manticore;
use App\Lib\Queue;
use App\Model\Comment;
use App\Model\Issue;
use App\Model\Label;
use App\Model\Repo;
use App\Model\User;
use Cli;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use ReflectionClass;
use Result;
use Throwable;

use function ok;

final class Search {
	/**
	 * Get most popular repositories for showcase
	 * @param  int $limit
	 * @return Result
	 */
	public static function getRepos(int $limit = 10): Result {
		return Manticore::getRepos($limit);
	}

	/**
	 * Wrapper to get the repository
	 * @param  string $org
	 * @param  string $name
	 * @return Result<Repo>
	 */
	public static function getRepo(string $org, string $name): Result {
		return Manticore::findRepo($org, $name);
	}

	/**
	 * Pass the url to the github repo and fetch issue in th equeu
	 * @param  string $url
	 * @return Result<Repo>
	 */
	public static function fetchIssues(string $url): Result {
		if (str_starts_with($url, 'https://github.com/')) {
			$url = substr($url, 19);
		}
		[$org, $repo] = array_map(trim(...), explode('/', $url));
		$repoResult = static::getRepo($org, $repo);
		$issue_count = 0;
		if ($repoResult->err) {
			try {
				$info = Github::getRepo($org, $repo);
				if ($info['visibility'] !== 'public' || !$info['has_issues']) {
					return err('e_repo_not_indexable');
				}

				$issue_count = Github::getIssueCount($org, $repo);
			} catch (Throwable) {
				return err('e_repo_not_found');
			}

			$repoResult = Manticore::findOrCreateRepo(
				$org, $repo, [
				'expected_issues' => $issue_count,
				]
			);
			if ($repoResult->err) {
				return $repoResult;
			}
		}
		/** @var Repo $repo */
		$repo = result($repoResult);

		// Index only we have something to index in gap of 1 min
		if ((!$repo->is_indexing || $repo->updated_at === 0) && (time() - $repo->updated_at) >= 86400) {
			$repo->is_indexing = true;
			Manticore::add([$repo]);
			Queue::add('github-issue-fetch', $repo);
		}

		return ok($repo);
	}

	/**
	 * Get counters for the repo
	 * @param  Repo   $repo
	 * @param string $query
	 * @param array<string,mixed> $filters
	 * @return Result<array{total:int,issues:int,comments:int,pull_requests:int,open_issues:int,closed_issues:int}>
	 */
	public static function getRepoCounters(Repo $repo, string $query = '', array $filters = []): Result {
		/** @var array{open:int,closed:int} */
		$issueCounters = result(Manticore::getIssueCounters($repo->id, $query, $filters));
		return ok(
			[
			'total' => $repo->issues + $repo->pull_requests + $repo->comments,
			'issues' => $repo->issues,
			'pull_requests' => $repo->pull_requests,
			'comments' => $repo->comments,
			'open_issues' => $issueCounters['open'],
			'closed_issues' => $issueCounters['closed'],
			]
		);
	}

	/**
	 * This method helps us to load the issues from the repository
	 * and store it in manticore
	 * @param  Repo   $repo
	 * @return Result
	 */
	public static function index(Repo $repo): Result {
		$converter = new GithubFlavoredMarkdownConverter(
			[
			'html_input' => 'strip',
			'allow_unsafe_links' => true,
			]
		);
		// Refetch repo to make sure that we are not in race condition situations
		/** @var Repo $repo */
		$repo = result(static::getRepo($repo->org, $repo->name));
		$repo->expected_issues = Github::getIssueCount($repo->org, $repo->name);
		$since = $repo->updated_at;
		$comments = [];
		$users = [];
		/** @var string $since_date */
		while (true) {
			$since_date = gmdate('Y-m-d\TH:i:s\Z', $since + 1);
			$issue_count = 0;
			$pull_request_count = 0;
			$issues = Github::getIssues($repo->org, $repo->name, $since_date);
			Cli::print("Since: $since_date");
			Cli::print('Issues: ' . sizeof($issues));
			if (!$issues) {
				break;
			}
			$users = [];
			$labels = [];
			foreach ($issues as &$issue) {
				Cli::print("Issue #{$issue['id']} {$issue['title']}");
				// Unsupported type in manticore, so make it string
				$issue['closed_at'] = $issue['closed_at'] ? strtotime($issue['closed_at']) : 0;
				$issue['created_at'] = strtotime($issue['created_at']);
				$issue['updated_at'] = strtotime($issue['updated_at']);
				$issue['body'] = (string)$converter->convert((string)$issue['body']);
				$issue['assignee_id'] = $issue['assignee']['id'] ?? 0;
				$issue['assignee_ids'] = array_column($issue['assignees'], 'id');
				$issue['label_ids'] = array_column($issue['labels'], 'id');
				$issue['is_pull_request'] = isset($issue['pull_request']);
				if ($issue['is_pull_request']) {
					$pull_request_count += 1;
				} else {
					$issue_count += 1;
				}

				// TODO: temporarely solution cuz manticore has bug
				if (!$issue['assignee_ids']) {
					unset($issue['assignee_ids']);
				}
				if (!$issue['label_ids']) {
					unset($issue['label_ids']);
				}

				$users[] = $issue['user'];
				if (isset($issue['assignee'])) {
					$users[] = $issue['assignee'];
				}
				if ($issue['assignees']) {
					$users = array_merge($users, $issue['assignees']);
				}

				$labels = array_merge($labels, $issue['labels']);
				// Add common parameters
				$issue['repo_id'] = $repo->id;
				$issue['user_id'] = $issue['user']['id'];
				$comments = [];
				Cli::print("Comments: {$issue['comments']}");
				if ($issue['comments'] > 0) {
					$issueComments = Github::getIssueComments($repo->org, $repo->name, $issue['number']);
					foreach ($issueComments as &$comment) {
						$comment['repo_id'] = $repo->id;
						$comment['issue_id'] = $issue['id'];
						$comment['user_id'] = $comment['user']['id'];
						$comment['created_at'] = strtotime($comment['created_at']);
						$comment['updated_at'] = strtotime($comment['updated_at']);
						$comment['body'] = (string)$converter->convert((string)$comment['body']);
						$users[] = $comment['user'];
						unset($comment);
					}
					$comments = array_merge($comments, $issueComments);
				}
				$since = $issue['updated_at'];
				unset($issue);
			}

			// Deduplicate users
			$users = array_values(
				array_reduce(
					$users, function (array $carry, array $user) {
						if (!isset($carry[$user['id']])) {
							$carry[$user['id']] = $user;
						}
						return $carry;
					}, []
				)
			);

			// Deduplicate labels
			$labels = array_values(
				array_reduce(
					$labels, function (array $carry, array $label) {
						if (!isset($carry[$label['id']])) {
							$label['description'] = (string)$label['description'];
							$carry[$label['id']] = $label;
						}
						return $carry;
					}, []
				)
			);

			$since_date = gmdate('Y-m-d\TH:i:s\Z', $since);
			$repo->issues += $issue_count;
			$repo->pull_requests += $pull_request_count;
			$repo->comments += sizeof($comments);
			$repo->updated_at = $since;

			// Now add it to the storage
			$results = [
				Manticore::add(array_map(Issue::fromArray(...), $issues)),
				Manticore::add(array_map(Comment::fromArray(...), $comments)),
				Manticore::add(array_map(User::fromArray(...), $users)),
				Manticore::add(array_map(Label::fromArray(...), $labels)),
				Manticore::add([$repo]),
			];

			// Validate if we had errors or not
			foreach ($results as $result) {
				if ($result->err) {
					return $result;
				}
			}
		}

		// Update repo updated_at for future fetches
		Cli::print("Last since: $since_date");

		$repo->is_indexing = false;
		Manticore::add([$repo]);
		// We have deferred function that will update it all

		return ok();
	}

	/**
	 * Get the list of issues for requested repo
	 * @param Repo $repo
	 * @param string $query
	 * @param array<string,mixed> $filters
	 * @param string $sort
	 * @param int $offset
	 * @return Result<array<Issue>>
	 */
	public static function process(Repo $repo, string $query = '', array $filters = [], string $sort = 'best-match', int $offset = 0): Result {
		$filtered = [];
		foreach ([Issue::class, Comment::class] as $modelClass) {
			$reflectionClass = new ReflectionClass($modelClass);
			$ns = strtolower(basename(str_replace('\\', '/', $modelClass)));
			$properties = $reflectionClass->getProperties();
			$filtered[$ns] = [];
			foreach ($properties as $property) {
				$name = $property->getName();
				$type = $property->getType();
				if (!isset($filters[$name])) {
					continue;
				}

				if (!settype($filters[$name], $type->getName())) {
					continue;
				}

				$filtered[$ns][$name] = $filters[$name];
			}
		}
		if (isset($filters['state'])) {
			$filtered['state'] = $filters['state'];
		}

		// TODO: think about better implementation
		if (isset($filters['comment_ranges'])) {
			$filtered['comment_ranges'] = [];
			$range_ids = array_map('intval', $filters['comment_ranges']);
			$ranges = result(static::getCommentRanges($repo));
			foreach ($ranges as $range) {
				if (!in_array($range['id'], $range_ids)) {
					continue;
				}
				$row = [];
				if (isset($range['min'])) {
					$row['min'] = $range['min'];
				}

				if (isset($range['max'])) {
					$row['max'] = $range['max'];
				}
				$filtered['comment_ranges'][] = $row;
			}
		}

		$users = [];
		if (isset($filters['authors'])) {
			$users = array_filter(array_unique(array_map('intval', $filters['authors'])));
		}
		$filtered['issue'] = [];
		if (isset($filters['assignees'])) {
			$filtered['issue']['assignee_ids'] = array_filter(array_unique(array_map('intval', $filters['assignees'])));
		}
		if (isset($filters['labels'])) {
			$filtered['issue']['label_ids'] = array_filter(array_unique(array_map('intval', $filters['labels'])));
		}

		$search_in = $filters['index'] ?? 'everywhere';
		[$issues, $pull_requests, $comments] = match ($search_in) {
			'everywhere' => [true, true, $query ? true : false],
			'issues' => [true, false, false],
			'pull_requests' => [false, true, false],
			'comments' => [false, false, true],
		};

		$filtered['issues'] = $issues;
		$filtered['pull_requests'] = $pull_requests;
		$filtered['comments'] = $comments;
		$filtered['common'] = [
			'repo_id' => $repo->id,
		];

		if ($users) {
			$filtered['common']['user_id'] = $users;
		}

		return Manticore::search($query, $filtered, $sort, $offset);
	}

	/**
	 * Get authors for given repo to use filtering
	 * @param  Repo   $repo
	 * @return Result<array<User>>
	 */
	public static function getAuthors(Repo $repo): Result {
		return Manticore::getUsers($repo->id, 'user_id');
	}

	/**
	 * Get all assignees for the repo
	 * @param  Repo   $repo
	 * @return Result<array<User>>
	 */
	public static function getAssignees(Repo $repo): Result {
		return Manticore::getUsers($repo->id, 'assignee_id');
	}

	/**
	 * Get all labels for repo
	 * @param Repo $repo
	 * @return Result<array<Label>>
	 */
	public static function getLabels(Repo $repo): Result {
		return Manticore::getLabels($repo->id);
	}

	/**
	 * This method returns comment ranges used for the filter
	 * @param  Repo   $repo
	 * @return Result<array<mixed>>
	 */
	public static function getCommentRanges(Repo $repo): Result {
		return Manticore::getCommentRanges($repo->id, [3, 5, 10, 15, 20, 30, 50]);
	}
}
