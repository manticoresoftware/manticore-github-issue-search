<?php declare(strict_types=1);

namespace App\Model;

// CREATE TABLE repo (
// id bigint,
// issues integer,
// expected_issues integer,
// comments integer,
// is_indexing bool,
// updated_at timestamp,
// org string attribute,
// name string attribute
// )
final class Repo extends Model {
	public int $id;
	public string $org;
	public string $name;
	public int $issues;
	public int $expected_issues;
	public bool $is_indexing;
	public int $comments;
	public int $updated_at;

	/**
	 * @param array<string,mixed> $args
	 * @return void
	 */
	public function __construct(array $args) {
		parent::__construct($args);
		$this->id = crc32("{$this->org}:{$this->name}");
	}

	/**
	 * Get url for the current project
	 * @return string
	 */
	public function getUrl(): string {
		return "https://github.com/{$this->getProject()}";
	}

	/**
	 * Get current percentage of the indexing progress
	 * @return float
	 */
	public function getIndexedPercentage(): float {
		return $this->expected_issues > 0
		? round(($this->issues / $this->expected_issues) * 100, 2)
		: 0;
	}

	/**
	 * Get the project as org/repo for this
	 * @return string
	 */
	public function getProject(): string {
		return "{$this->org}/{$this->name}";
	}
}
