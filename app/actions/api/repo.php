<?php declare(strict_types=1);

/**
 * @route api/repo/([^/]+): org
 * @route api/repo/([^/]+)/([^/]+): org, name
 * @var string $org
 * @var string $name
 */

use App\Component\Search;

if ($name) {
	/** @var App\Model\Repo $repo */
	[$org, $repo] = result(Search::getOrgAndRepo($org, $name));
	return ok($repo ? $repo->toArray() : []);
}

return ok([]);
