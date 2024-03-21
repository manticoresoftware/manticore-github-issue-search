<?php declare(strict_types=1);

namespace App\Model;

// CREATE TABLE org (
// id bigint,
// public_repos integer,
// followers integer,
// following integer,
// updated_at timestamp,
// name string attribute,
// description text
// )
final class Org extends Model {
	public int $id;
	public string $name;
	public string $description;
	public int $public_repos;
	public int $followers;
	public int $following;
	public int $updated_at;
}
