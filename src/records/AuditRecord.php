<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\records;

use craft\records\User;
use craft\records\Element;
use vigetlabs\audit\Audit;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 *
 * @property integer      $id
 * @property integer      $siteId
 * @property integer|null $userId
 * @property integer|null $parentId
 * @property integer|null $elementId
 * @property string|null  $elementType
 * @property string|null  $title
 * @property string       $event
 * @property \DateTime    $dateCreated
 * @property \DateTime    $dateUpdated
 * @property string       $ip
 * @property string       $userAgent
 * @property string|null  $snapshot
 * @property string|null  $sessionId
 */
class AuditRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%audit_log}}';
    }

    /**
     * Returns the log entry’s user.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * Returns the log entry's element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'elementId']);
    }

    /**
     * Returns the log entry's parent.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getParent(): ActiveQueryInterface
    {
        return $this->hasOne(AuditRecord::class, ['id' => 'parentId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(AuditRecord::class, ['parentId' => 'id']);
    }
}
