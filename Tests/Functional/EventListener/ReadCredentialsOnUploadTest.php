<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\EventListener;

use Dkd\C2paReader\Service\CredentialReader;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Vom Hinzufügen über die FAL-API (wie beim Backend-Upload) bis in sys_file_metadata.
 */
final class ReadCredentialsOnUploadTest extends FunctionalTestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/';

    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_file.csv');
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
    }

    private function storage(): ResourceStorage
    {
        return $this->get(StorageRepository::class)->findByUid(1);
    }

    private function add(string $fixture, string $name): File
    {
        // addFile() verschiebt die Quelle; eine Kopie übergeben.
        $tmp = $this->instancePath . '/typo3temp/' . uniqid('c2pa', true);
        copy(self::FIXTURES . $fixture, $tmp);

        return $this->storage()->addFile($tmp, $this->storage()->getRootLevelFolder(), $name, DuplicationBehavior::REPLACE);
    }

    /** @return array<string, mixed> */
    private function metadata(File $file): array
    {
        return $this->get(MetaDataRepository::class)->findByFileUid($file->getUid());
    }

    #[Test]
    public function addingAnAiImageStoresValidStatusAndAiFlag(): void
    {
        $metadata = $this->metadata($this->add('ai/chatgpt-gpt-image.png', 'ai.png'));

        self::assertSame(CredentialReader::STATUS_VALID, $metadata['tx_c2pareader_status']);
        self::assertSame(1, (int)$metadata['tx_c2pareader_ai']);
        self::assertSame('OpenAI Media Service API, ChatGPT gpt-image', $metadata['tx_c2pareader_agents']);
        self::assertGreaterThan(0, (int)$metadata['tx_c2pareader_checked']);
    }

    #[Test]
    public function addingAnImageWithoutManifestStoresNone(): void
    {
        $metadata = $this->metadata($this->add('c2pa-rs/no_manifest.jpg', 'plain.jpg'));

        self::assertSame(CredentialReader::STATUS_NONE, $metadata['tx_c2pareader_status']);
        self::assertSame(0, (int)$metadata['tx_c2pareader_ai']);
    }

    #[Test]
    public function replacingAFileChecksTheNewContent(): void
    {
        $file = $this->add('ai/chatgpt-gpt-image.png', 'replace-me.png');
        self::assertSame(CredentialReader::STATUS_VALID, $this->metadata($file)['tx_c2pareader_status']);

        $tmp = $this->instancePath . '/typo3temp/' . uniqid('c2pa', true);
        copy(self::FIXTURES . 'c2pa-rs/exp-test1.png', $tmp);
        $this->storage()->replaceFile($file, $tmp);

        $metadata = $this->metadata($file);
        self::assertSame(CredentialReader::STATUS_INVALID, $metadata['tx_c2pareader_status']);
        self::assertSame(0, (int)$metadata['tx_c2pareader_ai']);
    }
}
