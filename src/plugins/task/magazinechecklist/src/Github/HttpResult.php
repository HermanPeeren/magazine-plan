<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Github;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One HTTP response, reduced to what the gateway reads.
 */
final class HttpResult
{
	/**
	 * @param   array<string, string>  $headers  Keyed by lower-case header name.
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $headers,
		public readonly string $body
	) {
	}

	public function header(string $name): ?string
	{
		return $this->headers[strtolower($name)] ?? null;
	}
}
