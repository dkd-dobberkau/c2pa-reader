<?php

declare(strict_types=1);

namespace Dkd\C2paReader\ViewHelpers;

use Dkd\C2paReader\Service\AiDisclosureResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders an AI notice for a file if it counts as AI-generated; otherwise nothing.
 *
 *   {namespace c2pa=Dkd\C2paReader\ViewHelpers}   (registered globally, not needed)
 *   <c2pa:badge file="{file}" />
 *   <c2pa:badge file="{file}" id="ai-{file.uid}" />   → custom id, e.g. for aria-describedby on the <img>
 *   <c2pa:badge file="{file}" template="EXT:my_site/Resources/Private/Templates/C2paBadge.fluid.html" />
 *
 * The template receives {disclosure} (AiDisclosure) and {id}. All values from the
 * manifest are claims made by the file; the template outputs them escaped only.
 */
final class BadgeViewHelper extends AbstractViewHelper
{
    private const DEFAULT_TEMPLATE = 'EXT:c2pa_reader/Resources/Private/Templates/Badge.fluid.html';

    /** The rendered template escapes on its own; do not escape twice here. */
    protected $escapeOutput = false;

    public function __construct(
        private readonly AiDisclosureResolver $resolver,
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('file', FileInterface::class, 'File or FileReference', true);
        $this->registerArgument('id', 'string', 'id of the notice element (default: c2pa-badge-<uid>), e.g. for aria-describedby', false, '');
        $this->registerArgument('template', 'string', 'custom template (EXT: path)', false, self::DEFAULT_TEMPLATE);
    }

    public function render(): string
    {
        $disclosure = $this->resolver->resolve($this->arguments['file']);
        if ($disclosure === null) {
            return '';
        }

        $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;
        $view = $this->viewFactory->create(new ViewFactoryData(
            templatePathAndFilename: (string)$this->arguments['template'],
            request: $request,
        ));
        $view->assignMultiple([
            'disclosure' => $disclosure,
            // Default: c2pa-badge-<uid>; for a FileReference this is the uid of the reference.
            'id' => (string)$this->arguments['id'] ?: 'c2pa-badge-' . (int)$this->arguments['file']->getProperty('uid'),
        ]);

        return $view->render();
    }
}
