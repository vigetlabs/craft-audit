<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit;

use craft\events\ElementEvent;

use craft\events\RouteEvent;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\queue\Queue;
use craft\services\Routes;
use craft\web\twig\variables\CraftVariable;
use vigetlabs\audit\models\AuditModel;
use vigetlabs\audit\services\Audit_GeoService;
use vigetlabs\audit\services\AuditService;
use vigetlabs\audit\models\Settings;

use Craft;
use craft\console\Application as ConsoleApplication;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

use vigetlabs\audit\variables\AuditVariable;
use yii\base\Event;
use yii\queue\ExecEvent;
use yii\web\User;
use yii\web\UserEvent;

/**
 * Class Audit
 *
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 *
 * @property  AuditService     $auditService
 * @property  Audit_GeoService $geo
 * @method  Settings getSettings()
 */
class Audit extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Audit
     */
    public static $plugin;

    // Protected Methods
    // =========================================================================

    /**
     * Determine whether our table schema exists or not; this is needed because
     * migrations such as the install migration and base_install migration may
     * not have been run by the time our init() method has been called
     *
     * @return bool
     */
    protected function tableSchemaExists(): bool
    {
        return (Craft::$app->db->schema->getTableSchema('{{%audit_log}}') !== null);
    }

    // Public Methods
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.3';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'vigetlabs\audit\console\controllers';
        }

        /**
         * Install our event listeners. We do it only after we receive the event
         * EVENT_AFTER_LOAD_PLUGINS so that any pending db migrations can be run
         * before our event listeners kick in
         */
        // Handler: EVENT_AFTER_LOAD_PLUGINS
        if (!Craft::$app->getRequest()->isConsoleRequest && $this->tableSchemaExists()) {
            if ($this->getSettings()->enabled) {
                Event::on(
                    Plugins::class,
                    Plugins::EVENT_AFTER_LOAD_PLUGINS,
                    function() {
                        $this->initLogEvents();
                    });
            }

            Event::on(
                Plugins::class,
                Plugins::EVENT_AFTER_INSTALL_PLUGIN,
                function(PluginEvent $event) {
                    if ($event->plugin === $this) {
                        $settings = $this->getSettings();

                        if (empty($settings->updateAuthKey)) {
                            $settings->updateAuthKey = StringHelper::randomString(16);

                            Craft::$app->getPlugins()->savePluginSettings($this, $settings->toArray());
                        }
                    }
                }
            );
        }

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['audit/update-database'] = 'audit/geo/update-database';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('audit', AuditVariable::class);
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['audit']              = 'audit/default/index';
                $event->rules['audit/log/<id:\d+>'] = 'audit/default/details';
            }
        );

        Craft::info(
            Craft::t(
                'audit',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        $validDb = $this->geo->checkValidDb();

        return Craft::$app->view->renderTemplate(
            'audit/settings',
            [
                'settings' => $this->getSettings(),
                'validDb'  => $validDb,
            ]
        );
    }

    protected function initLogEvents()
    {
        // Elements
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $this->auditService->onSaveElement($event->element, $event->isNew);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->auditService->onDeleteElement($event->element);
            }
        );
    }
}
