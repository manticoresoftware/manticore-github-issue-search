<?php declare(strict_types=1);

/**
 * @route api/autocomplete/([^/]+): org
 * @route api/autocomplete/([^/]+)/([^/]+): org, name
 * @var string $org
 * @var string $name
 * @var string $query
 * @var array $config
 */

use App\Component\Search;

/** @var App\Model\Repo $repo */
$org = result(Search::getOrg($org));
return Search::autocomplete($org, $query, $config);
