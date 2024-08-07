<?php declare(strict_types=1);

namespace App\Model;

final class User extends Model {
	public int $id;
	public string $login;
	public string $avatar_url;

	/**
	 * @return string
	 */
	public function createTableSql(): string {
		return 'CREATE TABLE IF NOT EXISTS user (
			id bigint,
			login string attribute,
			avatar_url string attribute
			)';
	}
}
