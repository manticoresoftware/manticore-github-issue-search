<?php declare(strict_types=1);

namespace App\Model;

final class Repo extends Model {
	public int $id;
	public int $org_id;
	public string $name;
	public int $issues;
	public int $pull_requests;
	public int $expected_issues;
	public bool $is_indexing;
	public int $comments;
	public int $updated_at;

	/**
	 * Get current percentage of the indexing progress
	 * @return float
	 */
	public function getIndexedPercentage(): float {
		return $this->expected_issues > 0
		? round((($this->issues + $this->pull_requests) / $this->expected_issues) * 100, 2)
		: 0;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql(): string {
		return 'CREATE TABLE IF NOT EXISTS repo (
			id bigint,
			org_id integer,
			issues integer,
			pull_requests integer,
			expected_issues integer,
			comments integer,
			is_indexing bool,
			updated_at timestamp,
			name string attribute
			) index_field_lengths=1';
	}
}
