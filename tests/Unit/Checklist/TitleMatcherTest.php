<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Unit\Checklist;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\TitleMatcher;

final class TitleMatcherTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function titles(): array
    {
        return [
            'the milestone in the title'         => ['Issue plan November 2026', 'November 2026', true],
            'case does not matter'               => ['Imagery november 2026', 'November 2026', true],
            'between punctuation'                => ['[November 2026] Issue plan', 'November 2026', true],
            'another month'                      => ['Issue plan December 2026', 'November 2026', false],
            'part of a longer number'            => ['Issue plan 20261', '2026', false],
            'part of a word'                     => ['Mayhem in the plan', 'May', false],
            'an empty milestone matches nothing' => ['Issue plan', '   ', false],
            'regex characters are taken as text' => ['Plan (1.0)', '(1.0)', true],
        ];
    }

    #[DataProvider('titles')]
    public function testMatches(string $issueTitle, string $milestoneTitle, bool $expected): void
    {
        self::assertSame($expected, (new TitleMatcher())->matches($issueTitle, $milestoneTitle));
    }
}
