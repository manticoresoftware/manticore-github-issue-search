<?php declare(strict_types=1);

namespace App\Lib;

use Manticore\Ext\Model;
use Result;

class TextEmbeddings {
	/**
	 * Get current instance of the client
	 * @return Model
	 */
	protected static function model(): Model {
		static $model;

		if (!$model) {
			$config = config('vectorsearch');
			$model = Model::create($config['model_id'], $config['model_rev'], (bool)$config['use_pth']);
		}

		return $model;
	}

	/**
	 * Get representation of embeddings for a given text
	 * @param string $text
	 * @return Result<array<float>>
	 */
	public static function get(string $text): Result {
		$embeddings = static::model()->predict($text);
		return ok($embeddings);
	}
}
