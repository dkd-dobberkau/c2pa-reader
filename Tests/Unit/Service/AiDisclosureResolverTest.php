<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Service;

use Dkd\C2paReader\Domain\AiDisclosure;
use Dkd\C2paReader\Service\AiDisclosureResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AiDisclosureResolverTest extends UnitTestCase
{
    /** @param array<string, mixed> $properties */
    private function file(array $properties): FileInterface
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('hasProperty')->willReturnCallback(static fn(string $key): bool => array_key_exists($key, $properties));
        $file->method('getProperty')->willReturnCallback(static fn(string $key): mixed => $properties[$key] ?? null);

        return $file;
    }

    #[Test]
    public function noDisclosureWithoutAnyFlag(): void
    {
        self::assertNull((new AiDisclosureResolver())->resolve($this->file([
            'tx_c2pareader_ai' => 0,
            'tx_c2pareader_ai_manual' => 0,
        ])));
    }

    #[Test]
    public function noDisclosureWhenFieldsAreMissing(): void
    {
        self::assertNull((new AiDisclosureResolver())->resolve($this->file([])));
    }

    #[Test]
    public function credentialsFlagGivesCredentialsSourceWithAgents(): void
    {
        $disclosure = (new AiDisclosureResolver())->resolve($this->file([
            'tx_c2pareader_ai' => 1,
            'tx_c2pareader_agents' => 'OpenAI Media Service API, ChatGPT gpt-image',
        ]));

        self::assertNotNull($disclosure);
        self::assertSame(AiDisclosure::SOURCE_CREDENTIALS, $disclosure->source);
        self::assertTrue($disclosure->isFromCredentials());
        self::assertSame('OpenAI Media Service API, ChatGPT gpt-image', $disclosure->agents);
    }

    #[Test]
    public function editorFlagGivesEditorSourceWithoutAgents(): void
    {
        $disclosure = (new AiDisclosureResolver())->resolve($this->file([
            'tx_c2pareader_ai' => 0,
            'tx_c2pareader_ai_manual' => 1,
            'tx_c2pareader_agents' => 'should not be shown',
        ]));

        self::assertNotNull($disclosure);
        self::assertSame(AiDisclosure::SOURCE_EDITOR, $disclosure->source);
        self::assertFalse($disclosure->isFromCredentials());
        self::assertSame('', $disclosure->agents);
    }

    #[Test]
    public function credentialsTakePrecedenceOverEditorFlag(): void
    {
        $disclosure = (new AiDisclosureResolver())->resolve($this->file([
            'tx_c2pareader_ai' => 1,
            'tx_c2pareader_ai_manual' => 1,
        ]));

        self::assertSame(AiDisclosure::SOURCE_CREDENTIALS, $disclosure?->source);
    }

    #[Test]
    public function databaseStringValuesAreAccepted(): void
    {
        // Depending on the driver, values from the database arrive as strings.
        $disclosure = (new AiDisclosureResolver())->resolve($this->file(['tx_c2pareader_ai_manual' => '1']));

        self::assertSame(AiDisclosure::SOURCE_EDITOR, $disclosure?->source);
    }
}
