<?php declare(strict_types=1);

/**
 * @route api/autocomplete/([^/]+): org
 * @route api/autocomplete/([^/]+)/([^/]+): org, name
 * @var string $org
 * @var string $name
 * @var string $query
 * @var int $fuzziness
 * @var int $expansion_len
 * @var bool $append
 * @var bool $prepend
 * @var array $layouts
 */

use App\Component\Search;

/** @var App\Model\Repo $repo */
$org = result(Search::getOrg($org));
$config = compact('fuzziness', 'expansion_len', 'append', 'prepend', 'layouts');
return Search::autocomplete($org, $query, $config);
