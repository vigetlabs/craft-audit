<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\models;

use vigetlabs\audit\Audit;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * How many days to keep log entries around
     */
    public $pruneDays = 30;

    /**
     * Enabled status
     */
    public $enabled = true;

    /**
     * Enabled status
     */
    public $enabledGeolocation = true;

    /**
     * Update authentication key
     */
    public $updateAuthKey = '';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['enabled', 'boolean'],
            ['enabledGeolocation', 'boolean'],
            ['pruneDays', 'integer'],
        ]);
    }
}
