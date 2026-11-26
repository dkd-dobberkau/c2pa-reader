<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The Scheduler task "Execute console commands" offers only schedulable commands.
 */
final class SchedulableCommandsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    #[Test]
    public function commandsCanBeScheduled(): void
    {
        $schedulable = array_keys($this->get(CommandRegistry::class)->getSchedulableCommandsConfiguration());

        self::assertContains('c2pa:trust:update', $schedulable);
        self::assertContains('c2pa:scan', $schedulable);
    }
}
