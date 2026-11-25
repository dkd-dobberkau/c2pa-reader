<?php

declare(strict_types=1);

use Dkd\C2paReader\Hooks\ProtectCredentialFields;

defined('TYPO3') or die();

// C2PA-Felder gegen Schreiben per DataHandler sperren (readOnly im TCA wirkt nur in der Anzeige).
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['c2pa_reader']
    = ProtectCredentialFields::class;
