<?php

declare(strict_types=1);

/**
 * Spielt die Testbilder aus Tests/Fixtures/* über die FAL-API in fileadmin/c2pa-test/ ein.
 * Über die FAL-API, damit AfterFileAddedEvent feuert wie bei einem Backend-Upload.
 * Vorhandene Dateien werden ersetzt (AfterFileReplacedEvent), also neu geprüft.
 *
 * Nur für lokale Entwicklungsinstanzen.
 *
 *   ddev exec php /var/www/packages/c2pa_reader/Build/Scripts/import-fixtures.php [/pfad/zu/vendor/autoload.php]
 */

use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\BootService;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$autoload = $argv[1] ?? '/var/www/html/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Autoloader not found: $autoload\n");
    exit(1);
}

$classLoader = require $autoload;
SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init($classLoader)->get(BootService::class);
Bootstrap::initializeBackendUser(CommandLineUserAuthentication::class);
$GLOBALS['BE_USER']->authenticate();

$storage = GeneralUtility::makeInstance(StorageRepository::class)->getDefaultStorage();
$folderName = 'c2pa-test';
$folder = $storage->hasFolder($folderName) ? $storage->getFolder($folderName) : $storage->createFolder($folderName);

foreach (glob(dirname(__DIR__, 2) . '/Tests/Fixtures/*/*.{jpg,png}', GLOB_BRACE) as $path) {
    // addFile() verschiebt die Quelle, daher eine temporäre Kopie übergeben.
    $tmp = tempnam(sys_get_temp_dir(), 'c2pa');
    copy($path, $tmp);
    $file = $storage->addFile($tmp, $folder, basename($path), DuplicationBehavior::REPLACE);
    printf("%d\t%s\n", $file->getUid(), $file->getIdentifier());
}
