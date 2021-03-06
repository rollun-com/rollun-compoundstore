<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\compoundstore;

use rollun\datastore\DataStore\DbTable;
use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\TableGateway\TableManagerMysql as TableManager;
use Zend\Db\TableGateway\TableGateway;

/**
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
 * Table'sys_entities' must be exist. Use src\installer for create.
 *
 * @see http://www.cyberforum.ru/ms-access/thread1353090.html запрос
 */
class SysEntities extends DbTable
{

    const TYPE_ENTITY_LIST = TypeEntityList::TABLE_NAME;
    const FILED_ENTITY_TYPE = TypeEntityList::ID_FIELD;
    const TABLE_NAME = 'sys_entities';
    const ENTITY_PREFIX = 'entity_';
    const PROP_PREFIX = 'prop_';
    const ID_SUFFIX = '_id';

    public function prepareEntityCreate($entityName, $itemData, $rewriteIfExist)
    {
        $this->prepareEntityType($entityName);
        $identifier = $this->getIdentifier();
        //What is it array of arrays?
        if (isset($itemData[$identifier]) && $rewriteIfExist) {
            $this->delete($itemData[$identifier]);
        }
        $sysItem = [
            'add_date' => (new \DateTime())->format("Y-m-d"),
            'entity_type' => $entityName,
        ];
        if (isset($itemData[$identifier])) {
            $sysItem[$identifier] = $itemData[$identifier];
        }
        $sysItemInserted = $this->_create($sysItem);
        if (empty($sysItemInserted)) {
            throw new DataStoreException('Can not insert record for ' . $entityName . 'to sys_entities');
        }
        $itemData[$identifier] = $sysItemInserted[$identifier];
        return $itemData;
    }

    protected function prepareEntityType($entityName){
        $dbAdapter = $this->dbTable->getAdapter();
        $tableTypeEntityList = new TableGateway(static::TYPE_ENTITY_LIST,$dbAdapter);
        $dbTableTypeEntityList = new TypeEntityList($tableTypeEntityList);
        if(!$dbTableTypeEntityList->has($entityName)) {
            $dbTableTypeEntityList->create([static::FILED_ENTITY_TYPE => $entityName]);
        }
    }

    public static function getEntityName($tableName)
    {
        $entityName = substr($tableName, strlen(SysEntities::ENTITY_PREFIX));
        return $entityName;
    }

    public static function getEntityTableName($entityName)
    {
        $tableName = SysEntities::ENTITY_PREFIX . $entityName;
        return $tableName;
    }

    public static function getPropName($tableName)
    {
        $propName = substr($tableName, strlen(SysEntities::PROP_PREFIX));
        return $propName;
    }

    public static function getPropTableName($propName)
    {
        $tableName = SysEntities::PROP_PREFIX . $propName;
        return $tableName;
    }

    public function deleteAllInEntity($entityType)
    {
        $where = SysEntities::ENTITY_PREFIX . 'type = \'' . $entityType . '\'';
        $deletedItemsCount = $this->dbTable->delete($where);
        return $deletedItemsCount;
    }

    public static function getTableConfig()
    {
        return [
            SysEntities::TABLE_NAME => [
                'id' => [
                    TableManager::FIELD_TYPE => 'Integer',
                    TableManager::FIELD_PARAMS => [
                        'options' => ['autoincrement' => true]
                    ]
                ],
                'entity_type' => [
                    TableManager::FIELD_TYPE => 'Varchar',
                    TableManager::FOREIGN_KEY => [
                        'referenceTable' => static::TYPE_ENTITY_LIST,
                        'referenceColumn' => static::FILED_ENTITY_TYPE,
                        'onDeleteRule' => 'cascade',
                        'onUpdateRule' => null,
                        'name' => null
                    ],
                    TableManager::FIELD_PARAMS => [
                        'length' => 255,
                        'nullable' => false,
                    ],
                ],
                'add_date' => [
                    TableManager::FIELD_TYPE => 'Timestamp',
                ]
            ]
        ];
    }

}
