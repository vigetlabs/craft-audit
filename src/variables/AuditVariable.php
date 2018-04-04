<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\variables;

use vigetlabs\audit\Audit;

use Craft;
use craft\base\Component;
use vigetlabs\audit\models\AuditModel;
use vigetlabs\audit\records\AuditRecord;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditVariable extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @param null $ipAddress
     *
     * @return mixed|null
     */
    public function getLocationInfoForIp($ipAddress = null)
    {
        return Audit::$plugin->geo->getLocationInfoForIp($ipAddress);
    }
}
