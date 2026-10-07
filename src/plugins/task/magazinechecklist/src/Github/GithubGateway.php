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
 * The three things the sync asks of one GitHub repository.
 */
interface GithubGateway
{
	/**
	 * One page of issue events, newest first.
	 *
	 * @param   integer      $page  From 1.
	 * @param   string|null  $etag  The ETag of an earlier first page; when nothing
	 *                              changed since, the result is notModified.
	 *
	 * @throws  GithubException
	 */
	public function eventPage(int $page, ?string $etag = null): EventPage;

	/**
	 * Every open issue with this label, all pages, pull requests left out.
	 *
	 * @return  list<Issue>
	 *
	 * @throws  GithubException
	 */
	public function openIssuesWithLabel(string $label): array;

	/**
	 * @throws  GithubException
	 */
	public function updateIssueBody(int $number, string $body): void;
}
