<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BadgeViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    /** @param array<string, mixed> $properties */
    private function render(array $properties, string $arguments = ''): string
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('hasProperty')->willReturnCallback(static fn(string $key): bool => array_key_exists($key, $properties));
        $file->method('getProperty')->willReturnCallback(static fn(string $key): mixed => $properties[$key] ?? null);

        $template = tempnam($this->instancePath . '/typo3temp', 'badge') . '.fluid.html';
        // No namespace declaration: this also checks the global registration of "c2pa".
        file_put_contents($template, '<c2pa:badge file="{file}"' . $arguments . ' />');

        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(templatePathAndFilename: $template));
        $view->assign('file', $file);

        return trim(preg_replace('/\s+/', ' ', $view->render()));
    }

    #[Test]
    public function rendersNothingWithoutAiFlags(): void
    {
        self::assertSame('', $this->render(['uid' => 7, 'tx_c2pareader_ai' => 0, 'tx_c2pareader_ai_manual' => 0]));
    }

    #[Test]
    public function rendersCredentialsBadgeWithAgentsAndDefaultId(): void
    {
        $html = $this->render(['uid' => 7, 'tx_c2pareader_ai' => 1, 'tx_c2pareader_agents' => 'ChatGPT gpt-image']);

        self::assertStringContainsString('class="c2pa-badge c2pa-badge--credentials" id="c2pa-badge-7"', $html);
        self::assertStringContainsString('AI-generated', $html);
        self::assertStringContainsString('according to Content Credentials', $html);
        self::assertStringContainsString('(ChatGPT gpt-image)', $html);
        self::assertStringNotContainsString('trust list', $html);
    }

    #[Test]
    public function trustedSignerIsMentioned(): void
    {
        $html = $this->render(['uid' => 7, 'tx_c2pareader_ai' => 1, 'tx_c2pareader_status' => 'trusted']);

        self::assertStringContainsString('<span class="c2pa-badge__trust">signer on the C2PA trust list</span>', $html);
    }

    #[Test]
    public function rendersEditorBadgeWithoutAgents(): void
    {
        $html = $this->render(['uid' => 7, 'tx_c2pareader_ai_manual' => 1, 'tx_c2pareader_agents' => 'hidden']);

        self::assertStringContainsString('c2pa-badge--editor', $html);
        self::assertStringContainsString('according to the editors', $html);
        self::assertStringNotContainsString('hidden', $html);
    }

    #[Test]
    public function customIdIsUsed(): void
    {
        $html = $this->render(['uid' => 7, 'tx_c2pareader_ai' => 1], ' id="ai-note"');

        self::assertStringContainsString('id="ai-note"', $html);
    }

    #[Test]
    public function agentsFromTheManifestAreEscaped(): void
    {
        $html = $this->render(['uid' => 7, 'tx_c2pareader_ai' => 1, 'tx_c2pareader_agents' => '<script>alert(1)</script>" onmouseover="x']);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('" onmouseover', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;&quot; onmouseover=&quot;x', $html);
    }
}
