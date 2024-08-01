<?php declare(strict_types=1);

namespace App\Model;

final class Label extends Model {
	public int $id;
	public string $name;
	public ?string $description;
	public string $color;

	/**
	 * @return string
	 */
	public function createTableSql(): string {
		return 'CREATE TABLE IF NOT EXISTS label (
			id bigint,
			description text,
			name string attribute,
			color string attribute
			)';
	}
}
