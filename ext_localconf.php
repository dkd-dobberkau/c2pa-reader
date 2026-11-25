<?php

declare(strict_types=1);

use Dkd\C2paReader\Hooks\ProtectCredentialFields;

defined('TYPO3') or die();

// Lock the C2PA fields against writes through DataHandler (readOnly in TCA only affects the display).
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['c2pa_reader']
    = ProtectCredentialFields::class;

// <c2pa:badge> in all Fluid templates without a namespace declaration.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['c2pa'][] = 'Dkd\\C2paReader\\ViewHelpers';
