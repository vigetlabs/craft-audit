<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\assetbundles\Audit;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@vigetlabs/audit/assetbundles/audit/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Audit.js',
        ];

        $this->css = [
            'css/Audit.css',
        ];

        parent::init();
    }
}
