<?php declare(strict_types=1);

namespace App\Model;

// CREATE TABLE comment (
// id bigint,
// repo_id bigint,
// body text,
// issue_id bigint,
// user_id bigint,
// created_at timestamp,
// updated_at timestamp,
// reactions json
// ) html_strip='1' index_field_lengths='1' morphology='lemmatize_en' min_infix_len='2' expand_keywords='1'
final class Comment extends Model {
	public int $id;
	public int $repo_id;
	public int $issue_id;
	public int $user_id;
	/**
	 * @var array<string,int>
	 */
	public array $reactions;
	public string $body;
	public int $created_at;
	public int $updated_at;
}
