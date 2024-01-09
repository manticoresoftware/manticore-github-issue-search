<?php declare(strict_types=1);

namespace App\Model;

// create table notification (
// id bigint,
// repo_id bigint,
// email string,
// is_sent bool,
// created_at timestamp,
// updated_at timestamp
// )
final class Notification extends Model {
	public int $id;
	public int $repo_id;
	public string $email;
	public bool $is_sent;
	public int $created_at;
	public int $updated_at;
}
