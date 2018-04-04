<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\console\controllers;

use craft\helpers\Console as ConsoleHelper;
use vigetlabs\audit\Audit;

use yii\console\Controller;
use yii\console\ExitCode;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    // Public Methods
    // =========================================================================

    /**
     * Update Geolocation database
     *
     * @return mixed
     */
    public function actionUpdateDatabase()
    {
        ConsoleHelper::output('Updating Geolocation database');

        ConsoleHelper::startProgress(1, 6);

        $response = Audit::$plugin->geo->downloadDatabase();

        ConsoleHelper::startProgress(2, 6);

        if (isset($response['error'])) {
            ConsoleHelper::error($response['error']);

            ConsoleHelper::endProgress();

            return ExitCode::DATAERR;
        }

        ConsoleHelper::startProgress(3, 6);

        $response = Audit::$plugin->geo->unpackDatabase();

        ConsoleHelper::startProgress(4, 6);

        if (isset($response['error'])) {
            Console::error($response['error']);

            ConsoleHelper::endProgress();

            return ExitCode::DATAERR;
        }

        ConsoleHelper::startProgress(6, 6);

        ConsoleHelper::endProgress();

        ConsoleHelper::output('Finished updating database');

        return ExitCode::OK;
    }

    /**
     * Clean old log entries
     */
    public function actionPruneLogs()
    {
        $count = Audit::$plugin->auditService->pruneLogs();

        ConsoleHelper::output('Deleted ' . $count . ' entries');

        return ExitCode::OK;
    }
}
