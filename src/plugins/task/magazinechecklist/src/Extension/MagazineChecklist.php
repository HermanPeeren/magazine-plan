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
use Joomla\Event\SubscriberInterface;
use Yepr\Plugin\Task\MagazineChecklist\Sync\ChecklistSyncFactory;
use Yepr\Plugin\Task\MagazineChecklist\Sync\MissingConfiguration;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Offers the task type "Magazine: sync checklists" to the Task Scheduler.
 *
 * Everything the task does is in ChecklistSync. This class hands the task's
 * parameters to the injected factory, runs what it gets back and turns the
 * outcome into an exit code. The owner, repository and token are parameters of
 * each task rather than of the plugin, so one site can sync a test fork and the
 * real repository side by side.
 */
final class MagazineChecklist extends CMSPlugin implements SubscriberInterface
{
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
	 * @param   array<string, mixed>  $config  The plugin's row, from PluginHelper::getPlugin().
	 */
	public function __construct(array $config, private readonly ChecklistSyncFactory $syncFactory)
	{
		parent::__construct($config);
	}

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
		$params = $event->getArgument('params');

		try {
			$sync = $this->syncFactory->createForTask(
				\is_object($params) ? $params : (object) [],
				function (string $message, string $priority): void {
					$this->logTask($message, $priority);
				}
			);
		} catch (MissingConfiguration) {
			$this->logTask(Text::_('PLG_TASK_MAGAZINECHECKLIST_LOG_NOT_CONFIGURED'), 'error');

			return TaskStatus::KNOCKOUT;
		}

		try {
			$sync->run($event->getTaskId());
		} catch (\RuntimeException $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_MAGAZINECHECKLIST_LOG_FAILED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}

		return TaskStatus::OK;
	}
}
