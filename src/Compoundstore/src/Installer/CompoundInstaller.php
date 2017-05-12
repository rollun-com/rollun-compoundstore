<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\compoundstore\Installer;

use Composer\IO\IOInterface;
use Interop\Container\ContainerInterface;
use rollun\datastore\Middleware\DataStoreMiddlewareInstaller;
use rollun\installer\Install\InstallerAbstract;
use rollun\datastore\DataStore\DbTable;
use rollun\datastore\TableGateway\DbSql\MultiInsertSql;
use rollun\utils\DbInstaller;
use Zend\Db\Adapter\AdapterInterface;
use rollun\datastore\TableGateway\TableManagerMysql as TableManager;
use rollun\compoundstore\SysEntities;
use rollun\compoundstore\Example\StoreCatalog;
use rollun\compoundstore\Factory\CompoundAbstractFactory;
use Zend\Db\TableGateway\TableGateway;

/**
 * Installer class
 *
 * @category   Zaboy
 * @package    zaboy
 */
class CompoundInstaller extends InstallerAbstract
{

    /**
     *
     * @var AdapterInterface
     */
    private $dbAdapter;

    /**
     *
     *
     * Add to config:
     * <code>
     *    'services' => [
     *        'aliases' => [
     *            compoundAbstractFactory::DB_SERVICE_NAME => getenv('APP_ENV') === 'prod' ? 'dbOnProduction' : 'local-db',
     *        ],
     *        'abstract_factories' => [
     *            compoundAbstractFactory::class,
     *        ]
     *    ],
     * </code>
     *
     */
    public function isInstall()
    {
        $config = $this->container->get('config');
        return (
            isset($config['services']['abstract_factories']) &&
            in_array(CompoundAbstractFactory::class, $config['services']['abstract_factories']) &&
            $this->container->has(CompoundAbstractFactory::DB_SERVICE_NAME)
            );
    }

    public function uninstall()
    {
        $this->dbAdapter = $this->container->get(CompoundAbstractFactory::DB_SERVICE_NAME);
        if (isset($this->dbAdapter)) {
            if (constant('APP_ENV') === 'dev') {
                $tableManager = new TableManager($this->dbAdapter);
                $tableManager->deleteTable(StoreCatalog::PROP_LINKED_URL_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::PROP_PRODUCT_CATEGORY_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::PROP_TAG_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::MAIN_SPECIFIC_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::MAINICON_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::PRODUCT_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::CATEGORY_TABLE_NAME);
                $tableManager->deleteTable(StoreCatalog::TAG_TABLE_NAME);
                $tableManager->deleteTable(SysEntities::TABLE_NAME);
            } else {
                $this->consoleIO->write('constant("APP_ENV") !== "dev" It has did nothing');
            }
        }
    }

    public function install()
    {
        $this->dbAdapter = $this->container->get('db');
        if (isset($this->dbAdapter)) {
            if (constant('APP_ENV') === 'dev') {
                //develop only
                $tablesConfigDevelop = [
                    TableManager::KEY_TABLES_CONFIGS => array_merge(
                        SysEntities::getTableConfigProdaction(),
                        StoreCatalog::$develop_tables_config
                    )
                ];
                $tableManager = new TableManager($this->dbAdapter, $tablesConfigDevelop);

                $tableManager->rewriteTable(SysEntities::TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::PRODUCT_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::TAG_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::MAINICON_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::MAIN_SPECIFIC_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::CATEGORY_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::PROP_LINKED_URL_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::PROP_PRODUCT_CATEGORY_TABLE_NAME);
                $tableManager->rewriteTable(StoreCatalog::PROP_TAG_TABLE_NAME);
                $this->addData();
            } else {
                $tablesConfigProdaction = [
                    TableManager::KEY_TABLES_CONFIGS => SysEntities::getTableConfigProdaction()
                ];
                $tableManager = new TableManager($this->dbAdapter, $tablesConfigProdaction);

                $tableManager->createTable(SysEntities::TABLE_NAME);
            }
            return [
                'dependencies' => [
                    'aliases' => [
                        CompoundAbstractFactory::DB_SERVICE_NAME => 'db',
                    ],
                    'abstract_factories' => [
                        CompoundAbstractFactory::class,
                    ]
                ],
            ];
        }
        return [];
    }

    public function addData()
    {
        if (isset($this->dbAdapter)) {
            $data = array_merge(
                StoreCatalog::$sys_entities,
                StoreCatalog::$entity_product,
                StoreCatalog::$entity_category,
                StoreCatalog::$entity_tag,
                StoreCatalog::$entity_mainicon,
                StoreCatalog::$entity_main_specific,
                StoreCatalog::$prop_tag,
                StoreCatalog::$prop_product_category,
                StoreCatalog::$prop_linked_url
            );

            foreach ($data as $key => $value) {
                $sql = new MultiInsertSql($this->dbAdapter, $key);
                $tableGateway = new TableGateway($key, $this->dbAdapter, null, null, $sql);
                $dataStore = new DbTable($tableGateway);
                echo "create $key" . PHP_EOL;
                $dataStore->create($value, true);
            }
        }
    }

    /**
     * Return string with description of installable functional.
     * @param string $lang ; set select language for description getted.
     * @return string
     */
    public function getDescription($lang = "en")
    {
        switch ($lang) {
            case "ru":
                $description = "Предоставляет compound ранилище.";
                break;
            default:
                $description = "Does not exist.";
        }
        return $description;
    }

    public function getDependencyInstallers()
    {
        return [
            DbInstaller::class,
            DataStoreMiddlewareInstaller::class,
        ];
    }
}
