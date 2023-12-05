<?php declare(strict_types=1);

/**
 * @route api/repo/([^/]+)/([^/]+): org, name
 * @var string $org
 * @var string $name
 */

use App\Component\Search;

/** @var App\Model\Repo $repo */
$repo = result(Search::getRepo($org, $name));
return ok($repo->toArray());
