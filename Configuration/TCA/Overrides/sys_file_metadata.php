<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$lll = 'LLL:EXT:c2pa_reader/Resources/Private/Language/locallang_db.xlf:';

ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', [
    'tx_c2pareader_status' => [
        'label' => $lll . 'status',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'readOnly' => true,
            'items' => [
                ['label' => $lll . 'status.none', 'value' => 'none'],
                ['label' => $lll . 'status.trusted', 'value' => 'trusted'],
                ['label' => $lll . 'status.valid', 'value' => 'valid'],
                ['label' => $lll . 'status.invalid', 'value' => 'invalid'],
                ['label' => $lll . 'status.unreadable', 'value' => 'unreadable'],
            ],
        ],
    ],
    'tx_c2pareader_ai' => [
        'label' => $lll . 'ai',
        'config' => [
            'type' => 'check',
            'readOnly' => true,
        ],
    ],
    'tx_c2pareader_agents' => [
        'label' => $lll . 'agents',
        'config' => [
            'type' => 'input',
            'readOnly' => true,
        ],
    ],
    // Maintained by editors, e.g. for AI images without a manifest (or with a removed one).
    'tx_c2pareader_ai_manual' => [
        'label' => $lll . 'ai_manual',
        'description' => $lll . 'ai_manual.description',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'tx_c2pareader_checked' => [
        'label' => $lll . 'checked',
        'config' => [
            'type' => 'datetime',
            'readOnly' => true,
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;' . $lll . 'tab, tx_c2pareader_status, tx_c2pareader_ai, tx_c2pareader_agents, tx_c2pareader_checked, tx_c2pareader_ai_manual',
);
