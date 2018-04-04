<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\services;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Plugin;
use craft\base\PluginInterface;
use craft\events\RouteEvent;
use craft\helpers\Template;
use craft\queue\jobs\ResaveElements;
use DateTime;
use vigetlabs\audit\Audit;

use Craft;
use craft\base\Component;
use vigetlabs\audit\events\SnapshotEvent;
use vigetlabs\audit\helpers\Route;
use vigetlabs\audit\models\AuditModel;
use vigetlabs\audit\records\AuditRecord;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditService extends Component
{
    // Public Methods
    // =========================================================================

    const EVENT_TRIGGER  = 'eventTrigger';
    const EVENT_SNAPSHOT = 'snapshot';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
    }

    /**
     * @param ElementInterface $element
     *
     * @return array|null
     */
    public function getEventsForElement(ElementInterface $element)
    {
        $elementId   = $element->getId();
        $elementType = get_class($element);

        return $this->getEventsByAttributes(['elementId' => $elementId, 'elementType' => $elementType]);
    }

    /**
     * @param null $id
     *
     * @return null|AuditModel
     */
    public function getEventById($id = null)
    {
        $models = null;
        $record = AuditRecord::findOne($id);

        if (!$record) {
            return null;
        }

        return AuditModel::createFromRecord($record);
    }

    /**
     * @param null $handle
     *
     * @return array|null
     */
    public function getEventsByHandle($handle = null)
    {
        return $this->getEventsByAttributes(['eventHandle' => $handle]);
    }

    /**
     * @param null $id
     *
     * @return array|null
     */
    public function getEventsBySessionId($id = null)
    {
        if (!$id) {
            return null;
        }

        return $this->getEventsByAttributes(['sessionId' => $id, 'parentId' => null]);
    }

    /**
     * @param array $attributes
     *
     * @return array|null
     */
    public function getEventsByAttributes($attributes = [])
    {
        $models  = null;
        $records = AuditRecord::findAll($attributes);

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $models;
    }

    public function getEventCountByParentId($parentId = null)
    {
        return AuditRecord::find()
                          ->where(['parentId' => $parentId])
                          ->count();
    }

    /**
     * @param ElementInterface $element
     * @param bool             $isNew
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element, $isNew = false)
    {
        try {
            if (get_class($element) == 'craft\commerce\elements\Product') {
                if ($this->recentlyAudited($element)) {
                    return false;
                }

                $model              = $this->_getStandardModel();
                $model->event       = $isNew ? AuditModel::EVENT_CREATED_ELEMENT : AuditModel::EVENT_SAVED_ELEMENT;
                $model->elementId   = $element->getId();
                $model->elementType = get_class($element);

                $model->title = $element->title;

                $model->snapshot = Craft::$app->view->renderTemplate('audit/product', [
                    'product' => $element
                ]);

                $parentId = $this->getParentId($model->elementType);

                if (!empty($parentId)) {
                    $model->parentId = $parentId;
                }

                return $this->_saveRecord($model);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @param ElementInterface $element
     *
     * @return bool
     */
    public function onDeleteElement(ElementInterface $element)
    {
        try {
            if (get_class($element) == 'craft\commerce\elements\Product') {
                if ($this->recentlyAudited($element)) {
                    return false;
                }

                $model              = $this->_getStandardModel();
                $model->event       = AuditModel::EVENT_DELETED_ELEMENT;
                $model->elementType = get_class($element);
                $snapshot           = [
                    'elementId'   => $element->getId(),
                    'elementType' => get_class($element),
                ];

                if ($element->hasTitles()) {
                    $model->title      = $element->title;
                    $snapshot['title'] = $element->title;
                }

                $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

                return $this->_saveRecord($model);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @param AuditModel $auditModel
     * @param            $snapshot
     *
     * @return array
     */
    protected function afterSnapshot(AuditModel $auditModel, $snapshot)
    {
        $event = new SnapshotEvent([
            'audit'    => $auditModel,
            'snapshot' => $snapshot,
        ]);

        $this->trigger(self::EVENT_SNAPSHOT, $event);

        return $event->snapshot;
    }

    /**
     * @return AuditModel
     */
    private function _getStandardModel()
    {
        $app           = Craft::$app;
        $request       = $app->getRequest();
        $model         = new AuditModel();
        $model->siteId = $app->getSites()->currentSite->id;

        if (!$request->isConsoleRequest) {
            $session          = $app->getSession();
            $model->sessionId = $session->getId();
            $model->ip        = $request->getUserIP();
            $model->userAgent = $request->getUserAgent();

            if ($identity = $app->getUser()->getIdentity()) {
                $model->userId = $identity->id;

                $model->snapshot = [
                    'userId' => $model->userId,
                ];
            }
        }

        return $model;
    }

    /**
     * @param AuditModel $model
     * @param bool       $unique
     *
     * @return bool
     */
    public function _saveRecord(AuditModel &$model, $unique = true)
    {
        try {
            if ($model->id) {
                $record = AuditRecord::findOne($model->id);
            }
            else {
                $record = new AuditRecord();
            }

            $record->event       = $model->event;
            $record->title       = $model->title;
            $record->parentId    = $model->parentId;
            $record->userId      = $model->userId;
            $record->elementId   = $model->elementId;
            $record->elementType = $model->elementType;
            $record->ip          = $model->ip;
            $record->userAgent   = $model->userAgent;
            $record->siteId      = $model->siteId;
            $record->snapshot    = serialize($model->snapshot);
            $record->sessionId   = $model->sessionId;

            if (!$record->save()) {
                Craft::error(
                    Craft::t('audit', 'An error occured when saving audit log record: {error}',
                        [
                            'error' => print_r($record->getErrors(), true),
                        ]),
                    'audit');
            }

            $model->id = $record->id;

            return true;
        } catch (Exception $e) {
            Craft::error(
                Craft::t('audit', 'An error occured when saving audit log record: {error}',
                    [
                        'error' => $e->getMessage(),
                    ]),
                'audit');

            return false;
        }
    }

    /**
     * @return int|string
     */
    public function pruneLogs()
    {
        $pruneDays = Audit::$plugin->getSettings()->pruneDays ?? 30;
        $date      = (new DateTime())->modify('-' . $pruneDays . ' days')->format('Y-m-d H:i:s');
        $query     = AuditRecord::find()->where('dateCreated <= :pruneDate', [':pruneDate' => $date]);
        $count     = $query->count();

        // Delete
        AuditRecord::deleteAll('dateCreated <= :pruneDate', [':pruneDate' => $date]);

        return $count;
    }

    /**
     * @param string $elementType
     *
     * @return mixed
     */
    public function getParentId($elementType = '')
    {
        $cache    = Craft::$app->getCache();
        $parentId = $cache->get($this->getParentIdKey($elementType));

        return $parentId;
    }

    public function getParentIdKey($elementType = '')
    {
        return AuditModel::FLASH_RESAVE_ID . ':' . $elementType;
    }

    private function recentlyAudited($element) {
        $existing = AuditRecord::findAll(['elementId' => $element->id]);
        $now      = time();

        foreach ($existing as $record) {
            $created = strtotime($record->dateCreated);
            $diff = $now - $created;
            if ($now - $created < 10) {
                return true;
            }
        }

        return false;
    }
}
