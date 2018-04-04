<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://vigetlabs.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace vigetlabs\audit\controllers;

use craft\helpers\Template;
use craft\web\UrlManager;
use vigetlabs\audit\Audit;

use Craft;
use craft\web\Controller;
use vigetlabs\audit\models\AuditModel;
use vigetlabs\audit\records\AuditRecord;
use yii\data\Pagination;
use yii\widgets\LinkPager;

use JasonGrimes\Paginator;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $itemsPerPage = 20;
        $currentPage  = Craft::$app->getRequest()->getParam('page', 1);
        $urlPattern   = '/admin/audit?page=(:num)';
        $query        = AuditRecord::find()
                                   ->orderBy('dateCreated desc')
                                   ->with('user')
                                   ->where(['parentId' => null]);

        if ($titleSearch = Craft::$app->getRequest()->getParam('search')) {
            $query = $query->andWhere('title LIKE :val', [':val' => "%{$titleSearch}%"]);
        }

        $countQuery = clone $query;
        $totalItems = $countQuery->count();
        $paginator  = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);

        $records = $query
            ->offset(($currentPage - 1) * $itemsPerPage)
            ->limit($itemsPerPage)
            ->all();
        $models  = [];

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $this->renderTemplate('audit/index', [
            'logs'      => $models,
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param int|null $id
     *
     * @return mixed
     * @internal param array $variables
     *
     */
    public function actionDetails(int $id = null)
    {
        $service       = Audit::$plugin->auditService;
        $log           = $service->getEventById($id);
        $logsInSession = $service->getEventsBySessionId($log->sessionId);

        return $this->renderTemplate('audit/_view', [
            'settings'      => Audit::$plugin->getSettings(),
            'log'           => $log,
            'logsInSession' => $logsInSession,
        ]);
    }
}
