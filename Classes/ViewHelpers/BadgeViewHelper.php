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
 * Gibt einen KI-Hinweis für eine Datei aus, wenn sie als KI-generiert gilt; sonst nichts.
 *
 *   {namespace c2pa=Dkd\C2paReader\ViewHelpers}   (global registriert, nicht nötig)
 *   <c2pa:badge file="{file}" />
 *   <c2pa:badge file="{file}" id="ai-{file.uid}" />   → eigene id, z. B. für aria-describedby am <img>
 *   <c2pa:badge file="{file}" template="EXT:my_site/Resources/Private/Templates/C2paBadge.fluid.html" />
 *
 * Das Template bekommt {disclosure} (AiDisclosure) und {id}. Alle Werte aus dem
 * Manifest sind Angaben aus der Datei; das Template gibt sie nur escaped aus.
 */
final class BadgeViewHelper extends AbstractViewHelper
{
    private const DEFAULT_TEMPLATE = 'EXT:c2pa_reader/Resources/Private/Templates/Badge.fluid.html';

    /** Das gerenderte Template escaped selbst; hier nicht doppelt escapen. */
    protected $escapeOutput = false;

    public function __construct(
        private readonly AiDisclosureResolver $resolver,
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('file', FileInterface::class, 'File oder FileReference', true);
        $this->registerArgument('id', 'string', 'id des Hinweis-Elements (Standard: c2pa-badge-<uid>), z. B. für aria-describedby', false, '');
        $this->registerArgument('template', 'string', 'eigenes Template (EXT:-Pfad)', false, self::DEFAULT_TEMPLATE);
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
            // Standard: c2pa-badge-<uid>; bei einer FileReference ist das die uid der Referenz.
            'id' => (string)$this->arguments['id'] ?: 'c2pa-badge-' . $this->arguments['file']->getUid(),
        ]);

        return $view->render();
    }
}
