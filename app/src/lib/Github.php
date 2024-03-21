<?php declare(strict_types=1);

namespace App\Lib;

use Github\Client;

class Github {

	/**
	 * Get current instance of the client
	 * @return Client
	 */
	protected static function client(): Client {
		static $client;

		if (!$client) {
			$client = new Client();
			$token = getenv('GITHUB_TOKEN', true);
			if ($token) {
				$client->authenticate($token, authMethod: Client::AUTH_ACCESS_TOKEN);
			}
		}

		return $client;
	}

	/**
	 * Get repo information
	 * @param string $org
	 * @return array{has_issues:bool,open_issues:int,visibility:string}
	 */
	public static function getOrg(
		string $org
	): array {
		/** @var \Github\Api\Organization */
		$api = static::client()->api('organization');
		return $api->show($org);
	}

	/**
	 * Get repo information
	 * @param string $org
	 * @param string $repo
	 * @return array{has_issues:bool,open_issues:int,visibility:string}
	 */
	public static function getRepo(
		string $org,
		string $repo
	): array {
		/** @var \Github\Api\Repo */
		$api = static::client()->api('repo');
		return $api->show($org, $repo);
	}

	/**
	 * Get all issues from the organizatio nrepository
	 * @param string $org
	 * @param string $repo
	 * @param string $since
	 * @param int $limit
	 * @return array<array{html_url:string,number:int,title:string,body:?string,labels:array<string>,assignee:?array{login:string,avatar_url:string,html_url:string},assignees:array<array{login:string,avatar_url:string,html_url:string}>,comments:int,reactions:array<string,int>,created_at:string,updated_at:string,closed_at:?string,user:array{login:string,avatar_url:string,html_url:string}}
	 */
	public static function getIssues(
		string $org,
		string $repo,
		string $since = '2000-01-01T00:00:00Z',
		int $limit = 100
	): array {
		/** @var \Github\Api\Issue */
		$api = static::client()->api('issue');
		return $api->all(
			$org, $repo, [
			'since' => $since,
			'direction' => 'asc',
			'sort' => 'updated',
			'state' => 'all',
			'per_page' => $limit,
			]
		);
	}

	/**
	 * Get the total count for issues to maintain progress of loading
	 * @param  string $org
	 * @param  string $repo
	 * @return int
	 */
	public static function getIssueCount(string $org, string $repo): int {
		$info = static::client()->search()->issues("repo:{$org}/{$repo}");
		return $info['total_count'] ?? 0;
	}

	/**
	 * Get comments for the issue by it's number from the given repository
	 * @param  string $org
	 * @param  string $repo
	 * @param  int    $number
	 * @param  string $since
	 * @param int $limit
	 * @return array<array{id:int,html_url:string,body:string,created_at:string,updated_at:string,author_association:string,user:array{login:string,avatar_url:string,html_url:string},reactions:array<string,int>}>
	 */
	public static function getIssueComments(
		string $org,
		string $repo,
		int $number,
		string $since = '2000-01-01T00:00:00Z',
		int $limit = 100
	): array {
		/** @var \Github\Api\Issue */
		$api = static::client()->api('issue');
		return $api->comments()->all(
			$org, $repo, $number, [
			'since' => $since,
			'direction' => 'asc',
			'sort' => 'updated',
			'state' => 'all',
			'per_page' => $limit,
			]
		);
	}
}
