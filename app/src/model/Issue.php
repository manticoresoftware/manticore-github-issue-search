<?php declare(strict_types=1);

namespace App\Model;

final class Issue extends Model {
	public int $id;
	public int $org_id;
	public int $repo_id;
	public int $user_id;
	public bool $is_pull_request;
	/**
	 * @var array<int>
	 */
	public array $label_ids;
	public int $number;
	public int $comments;
	public string $title;
	public ?string $body;
	public ?int $assignee_id;
	/**
	 * @var array<int>
	 */
	public array $assignee_ids;
	/**
	 * @var array<string,int>
	 */
	public array $reactions;
	/**
	 * @var array<float>
	 */
	public array $embeddings;

	// public int $assignee;
	// public array $assignees;
	public int $created_at;

	public int $updated_at;
	public ?int $closed_at;

	/**
	 * Get bool flag for is closed
	 * @return bool
	 */
	public function getIsClosed(): bool {
		return $this->closed_at > 0;
	}

	/**
	 * Get table name for this issue
	 * @return string
	 */
	public function tableName(): string {
		return "issue_{$this->org_id}";
	}

	/**
	 * @return string
	 */
	public function createTableSql(): string {
		$table = $this->tableName();
		return "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint,
			title text,
			body text,
			is_pull_request bool,
			number integer,
			comments integer,
			org_id bigint,
			repo_id bigint,
			user_id bigint,
			label_ids multi64,
			assignee_id bigint,
			created_at timestamp,
			updated_at timestamp,
			closed_at timestamp,
			reactions json,
			assignee_ids multi64,
			embeddings float_vector knn_type='hnsw' knn_dims='384' hnsw_similarity='COSINE'
			) min_infix_len='2' index_exact_words='1' html_strip='1' index_field_lengths='1' morphology='lemmatize_en' expand_keywords='1'";
	}
}
