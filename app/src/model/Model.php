<?php declare(strict_types=1);

namespace App\Model;

use ArrayAccess;
use Exception;

abstract class Model implements ArrayAccess {
	/**
	 * @param array<string,mixed> $args
	 * @return void
	 */
	public function __construct(array $args) {
		foreach ($args as $arg => $value) {
			$this->$arg = $value;
		}
	}

	/**
	 * Create the object from the arrauy
	 * @param  array  $data
	 * @return static
	 */
	public static function fromArray(array $data): static {
		return new static(
			array_filter(
				$data,
				fn($v) => property_exists(static::class, $v),
				ARRAY_FILTER_USE_KEY
			)
		);
	}

	/**
	 * Convert current instance of the model to the array
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		$array = (array)$this;
		$methods = get_class_methods($this);
		foreach ($methods as $method) {
		  // Check if the method name starts with 'get'
			if (strpos($method, 'get') !== 0) {
				continue;
			}

			$propertyName = lcfirst(substr($method, 3));
			$snakeCasePropertyName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $propertyName));
			$array[$snakeCasePropertyName] = $this->$method();
		}

		return $array;
	}

	/**
	 * @param  mixed  $offset
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet(mixed $offset, mixed $value): void {
		throw new Exception('Properties cannot be set via array access.');
	}

	/**
	 * @param  mixed  $offset
	 * @return bool
	 */
	public function offsetExists(mixed $offset): bool {
		return property_exists($this, $offset);
	}

	/**
	 * @param  mixed  $offset
	 * @return void
	 */
	public function offsetUnset(mixed $offset): void {
		throw new Exception('Properties cannot be unset via array access.');
	}

	/**
	 * @param  mixed  $offset
	 * @return mixed
	 */
	public function offsetGet(mixed $offset): mixed {
		// Return the property with the same name, or null if it doesn't exist
		if (!$this->offsetExists($offset)) {
			throw new Exception("Property {$offset} does not exist");
		}
		return $this->$offset;
	}
}
