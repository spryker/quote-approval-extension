<?php

use SprykerFeature\Zed\Transfer\Business\Model\Generator\ClassCollectionManager;
use SprykerFeature\Zed\Transfer\Business\Model\Generator\ClassGenerator;
use Zend\Config\Config;
use Zend\Config\Factory;

class FullProcessTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $xmlTree;

    /**
     * @var ClassCollectionManager
     */
    protected $manager;

    /**
     * @var array
     */
    protected $generatedClasses = [];

    public function setUp()
    {
        $this->manager = new ClassCollectionManager();

        $file = dirname(__DIR__) . '/data/template.xml';

        $definitions = Factory::fromFile($file, true)->toArray();

        foreach ($definitions as $item) {
            if ( is_array($item) ) {
                foreach ($item as $itemComponent) {
                    $this->manager->setClassDefinition($itemComponent);
                }
            } else {
                $this->manager->setClassDefinition($item);
            }

        }

        $definitions = $this->manager->getCollections();

        $generator = new ClassGenerator();
        $generator->setNamespace('Generated\Shared\Transfer');
        $generator->setTargetFolder(dirname(__DIR__) . '/target/');
        foreach ($definitions as $classDefinition) {
            $this->generatedClasses[] = [
                'definition' => $classDefinition,
                'code' => $generator->generateClass($classDefinition),
            ];
        }
    }


    public function test_class_name()
    {
        $this->assertSame('AlfaTransfer', $this->generatedClasses[0]['definition']->getClassName());
    }

    public function test_get_interfaces()
    {
        $this->assertEquals(3, count($this->generatedClasses[0]['definition']->getInterfaces()));
    }

    /**
     * @dataProvider codeSampleValidationProvider
     * @param $codeSample
     */
    public function test_properties_type_array($codeSample)
    {
        $this->assertContains($codeSample, $this->generatedClasses[0]['code']);
    }

    public function codeSampleValidationProvider()
    {
        return [
            ['namespace Generated\Shared\Transfer;'],
            ['$properties = array();'],
            ['@var array $properties'],
            ['@return array $properties'],
            ['@var Customer $customer'],
            ['@return Customer $customer'],
            ['$this->properties[] = $properties;'],
            ['setCustomer(Customer $customer)'],
            ['public function setPublished(boolean $published)'],
            ['class AlfaTransfer extends AbstractTransfer implements FirstInterface, SecondInterface, ThirdInterface'],
            ['use Spryker\Demo\FirstInterface'],
            ['use Spryker\Demo\SecondInterface'],
            ['use Spryker\Demo\ThirdInterface'],
            ['use CartItem'],
            ['use Customer'],
            ['$this->addModifiedProperty(\'published\');'],
            ['$this->addModifiedProperty(\'customer\');'],
            ['$this->addModifiedProperty(\'properties\');'],
            ['$this->addModifiedProperty(\'cartItems\');'],
        ];
    }
}
