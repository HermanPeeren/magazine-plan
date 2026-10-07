<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Sync;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * CursorStore on the plugin's own table, `#__magazinechecklist_cursor`.
 *
 * Its own table rather than the task's parameters: those belong to the form,
 * and the scheduler does not expect a routine to write them back.
 */
final class DatabaseCursorStore implements CursorStore
{
	private const TABLE = '#__magazinechecklist_cursor';

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function load(int $taskId): ?Cursor
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['last_event_id', 'etag']))
			->from($this->db->quoteName(self::TABLE))
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->bind(':taskId', $taskId, ParameterType::INTEGER);

		$row = $this->db->setQuery($query)->loadObject();

		if ($row === null) {
			return null;
		}

		return new Cursor((int) $row->last_event_id, $row->etag === null ? null : (string) $row->etag);
	}

	public function save(int $taskId, Cursor $cursor): void
	{
		$delete = $this->db->getQuery(true)
			->delete($this->db->quoteName(self::TABLE))
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->bind(':taskId', $taskId, ParameterType::INTEGER);

		$this->db->setQuery($delete)->execute();

		$row = (object) [
			'task_id'       => $taskId,
			'last_event_id' => $cursor->lastEventId,
			'etag'          => $cursor->etag,
			'modified'      => gmdate('Y-m-d H:i:s'),
		];

		$this->db->insertObject(self::TABLE, $row);
	}
}
