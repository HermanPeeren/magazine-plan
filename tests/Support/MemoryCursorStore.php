<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Support;

use Yepr\Plugin\Task\MagazineChecklist\Sync\Cursor;
use Yepr\Plugin\Task\MagazineChecklist\Sync\CursorStore;

final class MemoryCursorStore implements CursorStore
{
    /** @var array<int, Cursor> */
    public array $cursors = [];

    public function load(int $taskId): ?Cursor
    {
        return $this->cursors[$taskId] ?? null;
    }

    public function save(int $taskId, Cursor $cursor): void
    {
        $this->cursors[$taskId] = $cursor;
    }
}
