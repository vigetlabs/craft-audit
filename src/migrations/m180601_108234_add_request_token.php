<?php
namespace vigetlabs\audit\migrations;
use Craft;
use craft\db\Migration;
/**
 * m180601_108234_add_request_token migration.
 */
class m180601_108234_add_request_token extends Migration
{
    // Public Properties
    // =========================================================================
    /**
     * @var string The database driver to use
     */
    public $driver;
    protected $tableName = '{{%audit_log}}';
    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->modifyTable();
    }
    /**
     * @return bool
     */
    protected function modifyTable()
    {
        $columnCreated = true;
        $this->addColumn($this->tableName, 'requestToken', $this->text());
        return $columnCreated;
    }
    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180601_108234_add_request_token cannot be reverted.\n";
        return false;
    }
}
