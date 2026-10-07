<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Github;

use Joomla\Http\Http;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HttpTransport on Joomla's own HTTP client, so the site's proxy settings and
 * certificate bundle apply.
 */
final class JoomlaHttpTransport implements HttpTransport
{
	public function __construct(private readonly Http $http, private readonly int $timeout = 30)
	{
	}

	public function request(string $method, string $url, array $headers, ?string $body = null): HttpResult
	{
		$response = match ($method) {
			'GET'   => $this->http->get($url, $headers, $this->timeout),
			'PATCH' => $this->http->patch($url, $body ?? '', $headers, $this->timeout),
			default => throw new \InvalidArgumentException('Unsupported HTTP method ' . $method),
		};

		$flatHeaders = [];

		foreach ($response->getHeaders() as $name => $values) {
			$flatHeaders[strtolower((string) $name)] = implode(', ', $values);
		}

		return new HttpResult($response->getStatusCode(), $flatHeaders, (string) $response->getBody());
	}
}
