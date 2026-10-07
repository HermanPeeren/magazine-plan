<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use Yepr\Plugin\Task\MagazineChecklist\Github\HttpGithubGateway;
use Yepr\Plugin\Task\MagazineChecklist\Github\JoomlaHttpTransport;
use Yepr\Plugin\Task\MagazineChecklist\Sync\ChecklistSync;
use Yepr\Plugin\Task\MagazineChecklist\Sync\DatabaseCursorStore;
use Yepr\Plugin\Task\MagazineChecklist\Sync\SyncSettings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Offers the task type "Magazine: sync checklists" to the Task Scheduler.
 *
 * Everything the task does is in ChecklistSync; this class only turns the
 * task's parameters into one and runs it. The owner, repository and token are
 * parameters of each task rather than of the plugin, so one site can sync a
 * test fork and the real repository side by side.
 */
final class MagazineChecklist extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait;

	/**
	 * @var array<string, array<string, string>>
	 */
	protected const TASKS_MAP = [
		'magazinechecklist.sync' => [
			'langConstPrefix' => 'PLG_TASK_MAGAZINECHECKLIST_SYNC',
			'form'            => 'sync',
			'method'          => 'sync',
		],
	];

	/**
	 * @var boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The routine.
	 *
	 * @return  integer  A TaskStatus exit code.
	 */
	protected function sync(ExecuteTaskEvent $event): int
	{
		$params     = $event->getArgument('params');
		$owner      = trim((string) ($params->owner ?? ''));
		$repository = trim((string) ($params->repository ?? ''));
		$token      = trim((string) ($params->token ?? ''));

		if ($owner === '' || $repository === '' || $token === '') {
			$this->logTask(Text::_('PLG_TASK_MAGAZINECHECKLIST_LOG_NOT_CONFIGURED'), 'error');

			return TaskStatus::KNOCKOUT;
		}

		$labels = array_values(array_filter([
			trim((string) ($params->plan_label ?? 'issue plan')),
			trim((string) ($params->imagery_label ?? 'Imagery')),
		], static fn (string $label): bool => $label !== ''));

		$settings = new SyncSettings(
			$labels,
			(string) ($params->heading ?? '### Table of contents'),
			max(1, (int) ($params->max_pages ?? 10)),
			(bool) ($params->dry_run ?? true)
		);

		$gateway = new HttpGithubGateway(
			new JoomlaHttpTransport((new HttpFactory())->getHttp()),
			$owner,
			$repository,
			$token
		);

		$sync = new ChecklistSync(
			$gateway,
			new DatabaseCursorStore($this->getDatabase()),
			$settings,
			function (string $message, string $priority): void {
				$this->logTask($message, $priority);
			}
		);

		try {
			$sync->run($event->getTaskId());
		} catch (\RuntimeException $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_MAGAZINECHECKLIST_LOG_FAILED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}

		return TaskStatus::OK;
	}
}
