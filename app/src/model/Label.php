<?php declare(strict_types=1);

namespace App\Model;

// CREATE TABLE label (
// id bigint,
// description text,
// name string attribute,
// color string attribute
// )
final class Label extends Model {
	public int $id;
	public string $name;
	public ?string $description;
	public string $color;
}
