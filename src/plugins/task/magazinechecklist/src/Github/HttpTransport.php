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
 * Sends one HTTP request.
 *
 * On a site that is Joomla's HTTP client, through JoomlaHttpTransport. In the
 * tests it is a fake that answers from a script, so the gateway's paging and
 * header handling are tested without a network.
 */
interface HttpTransport
{
	/**
	 * @param   array<string, string>  $headers
	 *
	 * @throws  \RuntimeException  When the request could not be sent at all.
	 */
	public function request(string $method, string $url, array $headers, ?string $body = null): HttpResult;
}
