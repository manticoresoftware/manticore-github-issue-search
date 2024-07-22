<?php declare(strict_types=1);

namespace App\Model;

final class Comment extends Model {
	public int $id;
	public int $org_id;
	public int $repo_id;
	public int $issue_id;
	public int $user_id;
	/**
	 * @var array<string,int>
	 */
	public array $reactions;
	/**
	 * @var array<float>
	 */
	public array $embeddings;
	public string $body;
	public int $created_at;
	public int $updated_at;

	/**
	 * Get current table name for org
	 * @return string
	 */
	public function getTableName(): string {
		return "comment_{$this->org_id}";
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql(): string {
		$table = $this->getTableName();
		return "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint,
			body text,
			org_id bigint,
			repo_id bigint,
			issue_id bigint,
			user_id bigint,
			created_at timestamp,
			updated_at timestamp,
			reactions json,
			embeddings float_vector knn_type='hnsw' knn_dims='384' hnsw_similarity='COSINE'
			) min_infix_len='2' index_exact_words='1' html_strip='1' index_field_lengths='1' morphology='lemmatize_en' expand_keywords='1'";
	}
}
