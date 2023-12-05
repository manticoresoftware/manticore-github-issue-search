<?php declare(strict_types=1);

namespace App\Model;

// CREATE TABLE user (
// id bigint,
// login string attribute,
// avatar_url string attribute
// )
final class User extends Model {
	public int $id;
	public string $login;
	public string $avatar_url;
}
