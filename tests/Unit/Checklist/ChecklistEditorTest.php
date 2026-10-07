<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Unit\Checklist;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\ChecklistEditor;

final class ChecklistEditorTest extends TestCase
{
    private const HEADING = '### Table of contents';

    private ChecklistEditor $editor;

    protected function setUp(): void
    {
        $this->editor = new ChecklistEditor();
    }

    public function testAddsAtTheTopOfTheListUnderTheHeading(): void
    {
        $body = "Intro\n\n### Table of contents\n\n- [ ] #10\n- [x] #11\n\n### Notes\nSee #99";

        self::assertSame(
            "Intro\n\n### Table of contents\n\n- [ ] #12\n- [ ] #10\n- [x] #11\n\n### Notes\nSee #99",
            $this->editor->add($body, 12, self::HEADING)
        );
    }

    public function testAddsUnderAHeadingWithNothingBelowIt(): void
    {
        self::assertSame(
            "### Table of contents\n- [ ] #5",
            $this->editor->add('### Table of contents', 5, self::HEADING)
        );
    }

    public function testLeavesTheBodyAloneWhenTheEntryIsThere(): void
    {
        $body = "### Table of contents\n- [x] #12 The article";

        self::assertSame($body, $this->editor->add($body, 12, self::HEADING));
    }

    public function testANumberThatStartsTheSameIsAnotherIssue(): void
    {
        // The old workflow saw "#123" and concluded #12 was already listed.
        $body = "### Table of contents\n- [ ] #123";

        self::assertSame(
            "### Table of contents\n- [ ] #12\n- [ ] #123",
            $this->editor->add($body, 12, self::HEADING)
        );
    }

    public function testAMentionInRunningTextIsNotAnEntry(): void
    {
        $body = "Follows up on #12.\n\n### Table of contents\n";

        self::assertSame(
            "Follows up on #12.\n\n### Table of contents\n- [ ] #12\n",
            $this->editor->add($body, 12, self::HEADING)
        );
    }

    public function testWithoutAListYetTheEntryGoesStraightUnderTheHeading(): void
    {
        self::assertSame(
            "### Table of contents\n- [ ] #12\n\n### Notes\nText",
            $this->editor->add("### Table of contents\n\n### Notes\nText", 12, self::HEADING)
        );
    }

    public function testKeepsWindowsLineEndings(): void
    {
        $body = "### Table of contents\r\n\r\n- [ ] #1\r\n";

        self::assertSame(
            "### Table of contents\r\n\r\n- [ ] #2\r\n- [ ] #1\r\n",
            $this->editor->add($body, 2, self::HEADING)
        );
    }

    public function testFindsTheHeadingDespiteSurroundingWhitespace(): void
    {
        self::assertSame(
            "  ### Table of contents  \n- [ ] #3",
            $this->editor->add('  ### Table of contents  ', 3, self::HEADING)
        );
    }

    public function testWithoutTheHeadingThereIsNothingToAddTo(): void
    {
        self::assertNull($this->editor->add("### Contents\n- [ ] #1", 2, self::HEADING));
    }

    public function testRemovesOnlyTheEntryLine(): void
    {
        // The old workflow also collapsed every blank line in the body.
        $body = "Intro\n\n### Table of contents\n\n- [ ] #10\n- [x] #12 Done\n- [ ] #123\n\n### Notes\n\nText";

        self::assertSame(
            "Intro\n\n### Table of contents\n\n- [ ] #10\n- [ ] #123\n\n### Notes\n\nText",
            $this->editor->remove($body, 12)
        );
    }

    public function testRemovesTheLastLineWithoutALineEnding(): void
    {
        self::assertSame("### Table of contents\n- [ ] #1\n", $this->editor->remove("### Table of contents\n- [ ] #1\n- [ ] #2", 2));
    }

    public function testRemovesWithWindowsLineEndings(): void
    {
        self::assertSame(
            "### Table of contents\r\n- [ ] #1\r\n",
            $this->editor->remove("### Table of contents\r\n- [X] #2\r\n- [ ] #1\r\n", 2)
        );
    }

    public function testRemovingWhatIsNotThereChangesNothing(): void
    {
        $body = "### Table of contents\n- [ ] #123\nSee #12";

        self::assertSame($body, $this->editor->remove($body, 12));
    }

    public function testRecognisesAsteriskBullets(): void
    {
        self::assertTrue($this->editor->hasEntry('* [ ] #7', 7));
        self::assertSame('', $this->editor->remove('* [ ] #7', 7));
    }
}
