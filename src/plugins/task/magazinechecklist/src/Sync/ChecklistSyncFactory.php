<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Sync;

use Yepr\Plugin\Task\MagazineChecklist\Checklist\ChecklistEditor;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\TitleMatcher;
use Yepr\Plugin\Task\MagazineChecklist\Github\HttpGithubGateway;
use Yepr\Plugin\Task\MagazineChecklist\Github\HttpTransport;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the sync for one task run.
 *
 * The services that are the same for every run - HTTP, the cursor store, the
 * editor, the matcher - are injected here once, by the plugin's service
 * provider. What differs per task - which repository, which token, which
 * labels - comes from that task's parameters, so the gateway and the sync are
 * made per run, here and nowhere else.
 */
final class ChecklistSyncFactory
{
	public function __construct(
		private readonly HttpTransport $http,
		private readonly CursorStore $cursors,
		private readonly ChecklistEditor $editor,
		private readonly TitleMatcher $matcher
	) {
	}

	/**
	 * @param   object                          $params  The task's parameters, as the
	 *                                                   scheduler hands them over.
	 * @param   callable(string, string): void  $log     Receives a message and a priority.
	 *
	 * @throws  MissingConfiguration  Without an owner, a repository or a token.
	 */
	public function createForTask(object $params, callable $log): ChecklistSync
	{
		$owner      = $this->text($params, 'owner');
		$repository = $this->text($params, 'repository');
		$token      = $this->text($params, 'token');

		if ($owner === '' || $repository === '' || $token === '') {
			throw new MissingConfiguration('The task needs an owner, a repository and an access token.');
		}

		$gateway = new HttpGithubGateway($this->http, $owner, $repository, $token);

		return new ChecklistSync($gateway, $this->cursors, $this->settings($params), $log, $this->editor, $this->matcher);
	}

	private function settings(object $params): SyncSettings
	{
		$labels = array_values(array_filter(
			[$this->text($params, 'plan_label', 'issue plan'), $this->text($params, 'imagery_label', 'Imagery')],
			static fn (string $label): bool => $label !== ''
		));

		$heading = $this->text($params, 'heading', '### Table of contents');

		return new SyncSettings(
			$labels,
			$heading === '' ? '### Table of contents' : $heading,
			max(1, (int) ($params->max_pages ?? 10)),
			// On unless switched off: a task saved without the field writes nothing.
			(bool) ($params->dry_run ?? true)
		);
	}

	private function text(object $params, string $name, string $default = ''): string
	{
		$value = $params->{$name} ?? $default;

		return \is_scalar($value) ? trim((string) $value) : $default;
	}
}
