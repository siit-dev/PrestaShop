<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Integration\Classes;

use Configuration;
use Db;
use Language;
use ObjectModel;
use PHPUnit\Framework\TestCase;
use Shop;

class ObjectModelTest extends TestCase
{
    private const DEFAULT_LANGUAGE_PLACEHOLDER = 'default_language';
    private const SECOND_LANGUAGE_PLACEHOLDER = 'second_language';

    private const DEFAULT_SHOP_PLACEHOLDER = 'default_shop';
    private const SECOND_SHOP_PLACEHOLDER = 'second_shop';

    /**
     * @var int
     */
    private $defaultLanguageId;

    /**
     * @var int
     */
    private $secondLanguageId;

    /**
     * @var int
     */
    private $defaultShopId;

    /**
     * @var int
     */
    private $secondShopId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installTestableObjectTables();
        $this->installLanguages();
        $this->installShops();
    }

    private function installTestableObjectTables(): void
    {
        $testableObjectSqlFile = dirname(__DIR__, 2) . '/Resources/sql/install_testable_object.sql';
        $sqlRequest = file_get_contents($testableObjectSqlFile);
        $sqlRequest = preg_replace('/PREFIX_/', _DB_PREFIX_, $sqlRequest);

        $dbCollation = Db::getInstance()->getValue('SELECT @@collation_database');
        $allowedCollations = ['utf8mb4_general_ci', 'utf8mb4_unicode_ci'];
        $collateReplacement = (empty($dbCollation) || !in_array($dbCollation, $allowedCollations)) ? '' : 'COLLATE ' . $dbCollation;
        $sqlRequest = preg_replace('/COLLATION/', $collateReplacement, $sqlRequest);

        $sqlRequest = preg_replace('/ENGINE_TYPE/', _MYSQL_ENGINE_, $sqlRequest);

        $db = Db::getInstance();
        $db->execute($sqlRequest);
    }

    private function installLanguages(): void
    {
        $this->defaultLanguageId = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->secondLanguageId = (int) Language::getIdByIso('fr');
        if ($this->secondLanguageId) {
            return;
        }

        $language = new Language();
        $language->name = 'fr';
        $language->iso_code = 'fr';
        $language->locale = 'fr-FR';
        $language->language_code = 'fr-FR';
        $language->add();
        $this->secondLanguageId = (int) $language->id;
    }

    private function installShops(): void
    {
        $this->defaultShopId = (int) Configuration::get('PS_SHOP_DEFAULT');
        $this->secondShopId = Shop::getIdByName('Shop 2');
        if ($this->secondShopId) {
            return;
        }

        $shop = new Shop();
        $shop->name = 'Shop 2';
        $shop->id_category = 1;
        $shop->id_shop_group = 1;
        $shop->domain = Configuration::get('PS_SHOP_DOMAIN');
        $shop->physical_uri = '/';
        $shop->add();
        $this->secondShopId = (int) $shop->id;
    }

    public function testAdd(): void
    {
        $quantity = 42;
        $localizedNames = [
            $this->defaultLanguageId => 'Default name',
            $this->secondLanguageId => 'Second name',
        ];

        $newObject = new TestableObjectModel();
        $newObject->quantity = $quantity;
        $newObject->enabled = true;
        $newObject->name = $localizedNames;

        $this->assertTrue((bool) $newObject->add());
        $this->assertNotNull($newObject->id);
        // Only the id field is filled, the identified primary key should also be updated
        // $this->assertNotNull($object->id_testable_object);
        $createdId = (int) $newObject->id;

        $multiLangObject = new TestableObjectModel($createdId);
        $this->assertEquals($createdId, $multiLangObject->id);
        $this->assertEquals($createdId, $multiLangObject->id_testable_object);
        $this->assertEquals($quantity, $multiLangObject->quantity);
        $this->assertTrue((bool) $multiLangObject->enabled);
        $this->assertEquals($localizedNames, $multiLangObject->name);

        $defaultLangObject = new TestableObjectModel($createdId, $this->defaultLanguageId);
        $this->assertEquals($localizedNames[$this->defaultLanguageId], $defaultLangObject->name);

        $secondLangObject = new TestableObjectModel($createdId, $this->secondLanguageId);
        $this->assertEquals($localizedNames[$this->secondLanguageId], $secondLangObject->name);
    }

    public function testUpdate(): void
    {
        $quantity = 42;
        $localizedNames = [
            $this->defaultLanguageId => 'Default name',
            $this->secondLanguageId => 'Second name',
        ];

        $newObject = new TestableObjectModel();
        $newObject->quantity = $quantity;
        $newObject->enabled = true;
        $newObject->name = $localizedNames;

        $this->assertTrue((bool) $newObject->add());
        $this->assertNotNull($newObject->id);
        $createdId = (int) $newObject->id;

        $newLocalizedNames = [
            $this->defaultLanguageId => 'New Default name',
            $this->secondLanguageId => 'New Second name',
        ];
        $newObject->enabled = false;
        $newObject->quantity = 51;
        $newObject->name = $newLocalizedNames;
        $this->assertTrue((bool) $newObject->update());

        $multiLangObject = new TestableObjectModel($createdId);
        $this->assertEquals($createdId, $multiLangObject->id);
        $this->assertEquals($createdId, $multiLangObject->id_testable_object);
        $this->assertEquals(51, $multiLangObject->quantity);
        $this->assertFalse((bool) $multiLangObject->enabled);
        $this->assertEquals($newLocalizedNames, $multiLangObject->name);

        $multiLangObject->quantity = $quantity;
        $multiLangObject->enabled = true;
        $multiLangObject->name = $localizedNames;
        $this->assertTrue((bool) $multiLangObject->update());

        $defaultLangObject = new TestableObjectModel($createdId, $this->defaultLanguageId);
        $this->assertEquals($localizedNames[$this->defaultLanguageId], $defaultLangObject->name);

        $secondLangObject = new TestableObjectModel($createdId, $this->secondLanguageId);
        $this->assertEquals($localizedNames[$this->secondLanguageId], $secondLangObject->name);
    }

    /**
     * @dataProvider getPartialUpdates
     *
     * @param array $initialProperties
     * @param array $updatedProperties
     * @param array $fieldsToUpdate
     * @param array $expectedProperties
     */
    public function testPartialUpdate(array $initialProperties, array $updatedProperties, array $fieldsToUpdate, array $expectedProperties): void
    {
        $newObject = new TestableObjectModel();
        $this->applyModifications($newObject, $initialProperties);
        $this->assertTrue((bool) $newObject->add());
        $this->assertNotNull($newObject->id);
        $createdId = (int) $newObject->id;

        $objectToUpdate = new TestableObjectModel($createdId);
        $this->applyModifications($objectToUpdate, $updatedProperties);
        if (isset($fieldsToUpdate['name'])) {
            $fieldsToUpdate['name'] = $this->convertLocalizedValue($fieldsToUpdate['name']);
        }
        $objectToUpdate->setFieldsToUpdate($fieldsToUpdate);
        $this->assertTrue((bool) $objectToUpdate->update());

        $updatedObject = new TestableObjectModel($createdId);
        $this->checkObjectFields($updatedObject, $expectedProperties);
    }

    public function getPartialUpdates(): iterable
    {
        $initQuantity = 42;
        $updatedQuantity = 51;
        $localizedNames = [
            self::DEFAULT_LANGUAGE_PLACEHOLDER => 'Default name',
            self::SECOND_LANGUAGE_PLACEHOLDER => 'Second name',
        ];
        $updatedLocalizedNames = [
            self::DEFAULT_LANGUAGE_PLACEHOLDER => 'Updated Default name',
            self::SECOND_LANGUAGE_PLACEHOLDER => 'Updated Second name',
        ];

        $initialValues = [
            'quantity' => $initQuantity,
            'enabled' => true,
            'name' => $localizedNames,
        ];

        yield [
            $initialValues,
            [
                'quantity' => $updatedQuantity,
                'enabled' => false,
                'name' => $updatedLocalizedNames,
            ],
            [
                'quantity' => true,
                'enabled' => true,
                'name' => [
                    self::DEFAULT_LANGUAGE_PLACEHOLDER => true,
                    self::SECOND_LANGUAGE_PLACEHOLDER => true,
                ],
            ],
            [
                'quantity' => $updatedQuantity,
                'enabled' => 0,
                'name' => $updatedLocalizedNames,
            ],
        ];

        // Modify multiple fields but only update quantity (classic value)
        yield [
            $initialValues,
            [
                'quantity' => $updatedQuantity,
                'enabled' => false,
                'name' => $updatedLocalizedNames,
            ],
            [
                'quantity' => true,
            ],
            [
                'quantity' => $updatedQuantity,
                'enabled' => 1,
                'name' => $localizedNames,
            ],
        ];

        // Modify multiple fields but only update enabled (multishop value)
        yield [
            $initialValues,
            [
                'quantity' => $updatedQuantity,
                'enabled' => false,
                'name' => $updatedLocalizedNames,
            ],
            [
                'enabled' => true,
            ],
            [
                'quantity' => $initQuantity,
                'enabled' => 0,
                'name' => $localizedNames,
            ],
        ];

        // Modify multiple fields but only update name (multilang value)
        yield [
            $initialValues,
            [
                'quantity' => $updatedQuantity,
                'enabled' => false,
                'name' => $updatedLocalizedNames,
            ],
            [
                'name' => [
                    self::DEFAULT_LANGUAGE_PLACEHOLDER => true,
                    self::SECOND_LANGUAGE_PLACEHOLDER => true,
                ],
            ],
            [
                'quantity' => $initQuantity,
                'enabled' => 1,
                'name' => $updatedLocalizedNames,
            ],
        ];

        // Modify multiple fields but only update one language for name (multilang value)
        yield [
            $initialValues,
            [
                'quantity' => $updatedQuantity,
                'enabled' => false,
                'name' => $updatedLocalizedNames,
            ],
            [
                'name' => [
                    self::SECOND_LANGUAGE_PLACEHOLDER => true,
                ],
            ],
            [
                'quantity' => $initQuantity,
                'enabled' => 1,
                'name' => [
                    self::DEFAULT_LANGUAGE_PLACEHOLDER => $localizedNames[self::DEFAULT_LANGUAGE_PLACEHOLDER],
                    self::SECOND_LANGUAGE_PLACEHOLDER => $updatedLocalizedNames[self::SECOND_LANGUAGE_PLACEHOLDER],
                ],
            ],
        ];
    }

    /**
     * @dataProvider getMultiShopValues
     *
     * @param array $initialProperties
     * @param array $initialShops
     * @param array $multiShopValues
     * @param array $expectedMultiShopValues
     */
    public function testMultiShopUpdate(array $initialProperties, array $initialShops, array $multiShopValues, array $expectedMultiShopValues): void
    {
        $newObject = new TestableObjectModel();
        $initialShopIds = [];
        if (in_array(static::DEFAULT_SHOP_PLACEHOLDER, $initialShops)) {
            $initialShopIds[] = $this->defaultShopId;
        }
        if (in_array(static::SECOND_SHOP_PLACEHOLDER, $initialShops)) {
            $initialShopIds[] = $this->secondShopId;
        }
        $newObject->id_shop_list = $initialShopIds;
        $this->applyModifications($newObject, $initialProperties);
        $this->assertTrue((bool) $newObject->add());
        $this->assertNotNull($newObject->id);
        $createdId = (int) $newObject->id;

        foreach ($multiShopValues as $shopId => $updateValues) {
            $shopId = $shopId === static::DEFAULT_SHOP_PLACEHOLDER ? $this->defaultShopId : $this->secondShopId;
            $objectToUpdate = new TestableObjectModel($createdId, null, $shopId);
            $objectToUpdate->id_shop_list = [$shopId];
            $this->applyModifications($objectToUpdate, $updateValues);
            $this->assertTrue((bool) $objectToUpdate->update());
        }

        foreach ($expectedMultiShopValues as $shopId => $expectedValues) {
            $shopId = $shopId === static::DEFAULT_SHOP_PLACEHOLDER ? $this->defaultShopId : $this->secondShopId;
            $updatedObject = new TestableObjectModel($createdId, null, $shopId);
            $this->checkObjectFields($updatedObject, $expectedValues);
        }
    }

    public function getMultiShopValues(): iterable
    {
        $initQuantity = 42;
        $localizedNames = [
            self::DEFAULT_LANGUAGE_PLACEHOLDER => 'Default name',
            self::SECOND_LANGUAGE_PLACEHOLDER => 'Second name',
        ];
        $updatedLocalizedNames = [
            self::DEFAULT_LANGUAGE_PLACEHOLDER => 'Updated Default name',
            self::SECOND_LANGUAGE_PLACEHOLDER => 'Updated Second name',
        ];

        $initialValues = [
            'quantity' => $initQuantity,
            'enabled' => true,
            'name' => $localizedNames,
        ];

        yield [
            $initialValues,
            [self::DEFAULT_SHOP_PLACEHOLDER, self::SECOND_SHOP_PLACEHOLDER],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => false,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => false,
                ],
            ],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => 0,
                    'name' => $localizedNames,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => 0,
                    'name' => $localizedNames,
                ],
            ],
        ];

        yield [
            $initialValues,
            [self::DEFAULT_SHOP_PLACEHOLDER, self::SECOND_SHOP_PLACEHOLDER],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'name' => $updatedLocalizedNames,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => false,
                ],
            ],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => 1,
                    'name' => $updatedLocalizedNames,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => 0,
                    'name' => $localizedNames,
                ],
            ],
        ];
    }

    /**
     * @dataProvider getPartialMultiShopValues
     *
     * @param array $initialProperties
     * @param array $initialShops
     * @param array $multiShopValues
     * @param array $multiShopFieldsToUpdate
     * @param array $expectedMultiShopValues
     */
    public function testPartialMultiShopUpdate(
        array $initialProperties,
        array $initialShops,
        array $multiShopValues,
        array $multiShopFieldsToUpdate,
        array $expectedMultiShopValues
    ): void {
        $newObject = new TestableObjectModel();
        $initialShopIds = [];
        if (in_array(static::DEFAULT_SHOP_PLACEHOLDER, $initialShops)) {
            $initialShopIds[] = $this->defaultShopId;
        }
        if (in_array(static::SECOND_SHOP_PLACEHOLDER, $initialShops)) {
            $initialShopIds[] = $this->secondShopId;
        }
        $newObject->id_shop_list = $initialShopIds;
        $this->applyModifications($newObject, $initialProperties);
        $this->assertTrue((bool) $newObject->add());
        $this->assertNotNull($newObject->id);
        $createdId = (int) $newObject->id;

        foreach ($multiShopValues as $shopId => $updateValues) {
            $fieldsToUpdate = $multiShopFieldsToUpdate[$shopId];
            $shopId = $shopId === static::DEFAULT_SHOP_PLACEHOLDER ? $this->defaultShopId : $this->secondShopId;
            $objectToUpdate = new TestableObjectModel($createdId, null, $shopId);
            $objectToUpdate->id_shop_list = [$shopId];
            $this->applyModifications($objectToUpdate, $updateValues);
            if (isset($fieldsToUpdate['name'])) {
                $fieldsToUpdate['name'] = $this->convertLocalizedValue($fieldsToUpdate['name']);
            }
            $objectToUpdate->setFieldsToUpdate($fieldsToUpdate);
            $this->assertTrue((bool) $objectToUpdate->update());
        }

        foreach ($expectedMultiShopValues as $shopId => $expectedValues) {
            $shopId = $shopId === static::DEFAULT_SHOP_PLACEHOLDER ? $this->defaultShopId : $this->secondShopId;
            $updatedObject = new TestableObjectModel($createdId, null, $shopId);
            $this->checkObjectFields($updatedObject, $expectedValues);
        }
    }

    public function getPartialMultiShopValues(): iterable
    {
        $initQuantity = 42;
        $localizedNames = [
            self::DEFAULT_LANGUAGE_PLACEHOLDER => 'Default name',
            self::SECOND_LANGUAGE_PLACEHOLDER => 'Second name',
        ];
        $updatedLocalizedNames = [
            self::DEFAULT_LANGUAGE_PLACEHOLDER => 'Updated Default name',
            self::SECOND_LANGUAGE_PLACEHOLDER => 'Updated Second name',
        ];

        $initialValues = [
            'quantity' => $initQuantity,
            'enabled' => true,
            'name' => $localizedNames,
        ];

        yield [
            $initialValues,
            [self::DEFAULT_SHOP_PLACEHOLDER, self::SECOND_SHOP_PLACEHOLDER],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => false,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => false,
                ],
            ],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => true,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => true,
                ],
            ],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => 0,
                    'name' => $localizedNames,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => 0,
                    'name' => $localizedNames,
                ],
            ],
        ];

        yield [
            $initialValues,
            [self::DEFAULT_SHOP_PLACEHOLDER, self::SECOND_SHOP_PLACEHOLDER],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'name' => $updatedLocalizedNames,
                    'enabled' => false,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'name' => $updatedLocalizedNames,
                    'enabled' => false,
                ],
            ],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'name' => [
                        self::DEFAULT_LANGUAGE_PLACEHOLDER => true,
                        self::SECOND_LANGUAGE_PLACEHOLDER => true,
                    ],
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => true,
                ],
            ],
            [
                self::DEFAULT_SHOP_PLACEHOLDER => [
                    'enabled' => 1,
                    'name' => $updatedLocalizedNames,
                ],
                self::SECOND_SHOP_PLACEHOLDER => [
                    'enabled' => 0,
                    'name' => $localizedNames,
                ],
            ],
        ];
    }

    /**
     * @param TestableObjectModel $object
     * @param array $expectedProperties
     */
    private function checkObjectFields(TestableObjectModel $object, array $expectedProperties): void
    {
        foreach ($expectedProperties as $field => $expectedValue) {
            if (is_array($expectedValue)) {
                $expectedValue = $this->convertLocalizedValue($expectedValue);
            }
            $this->assertEquals($expectedValue, $object->{$field});
        }
    }

    /**
     * @param TestableObjectModel $object
     * @param array $updatedProperties
     */
    private function applyModifications(TestableObjectModel $object, array $updatedProperties): void
    {
        foreach ($updatedProperties as $field => $value) {
            if (is_array($value)) {
                $object->{$field} = $this->convertLocalizedValue($value);
            } else {
                $object->{$field} = $value;
            }
        }
    }

    /**
     * @param array $value
     *
     * @return array
     */
    private function convertLocalizedValue(array $value): array
    {
        $localizedValue = [];
        if (isset($value[self::DEFAULT_LANGUAGE_PLACEHOLDER])) {
            $localizedValue[$this->defaultLanguageId] = $value[self::DEFAULT_LANGUAGE_PLACEHOLDER];
        }
        if (isset($value[self::SECOND_LANGUAGE_PLACEHOLDER])) {
            $localizedValue[$this->secondLanguageId] = $value[self::SECOND_LANGUAGE_PLACEHOLDER];
        }

        return $localizedValue;
    }
}

class TestableObjectModel extends ObjectModel
{
    /**
     * @var int
     */
    public $id_testable_object;

    /**
     * This field is multilang and multi shop
     *
     * @var string|string[]
     */
    public $name;

    /**
     * This field is global to all shops
     *
     * @var int
     */
    public $quantity;

    /**
     * This field is multishop
     *
     * @var bool
     */
    public $enabled;

    public static $definition = [
        'table' => 'testable_object',
        'primary' => 'id_testable_object',
        'multilang' => true,
        'multilang_shop' => true,
        'fields' => [
            // Classic fields
            'quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedFloat'],
            // Multi lang fields
            'name' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCatalogName', 'required' => false, 'size' => 128],
            // Shop fields
            'enabled' => ['type' => self::TYPE_BOOL, 'shop' => true, 'validate' => 'isBool'],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        Shop::addTableAssociation('testable_object', ['type' => 'shop']);
        Shop::addTableAssociation('testable_object_lang', ['type' => 'fk_shop']);
        parent::__construct($id, $id_lang, $id_shop);
    }
}
