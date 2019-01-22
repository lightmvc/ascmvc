<?php
/**
 * LightMVC/ASCMVC
 *
 * @package    LightMVC/ASCMVC
 * @author     Andrew Caya
 * @link       https://github.com/lightmvc/ascmvc
 * @version    2.0.2
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0.
 * @since      2.0.0
 */

namespace AscmvcTest;

use Ascmvc\Mvc\ViewObjectFactory;
use League\Plates\Engine;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ViewObjectFactoryTest extends TestCase
{
    public function testGetPlatesInstanceWithDevelopmentEnvironment()
    {
        $baseConfig['BASEDIR'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

        $baseConfig['templateManager'] = 'Plates';
        $baseConfig['templates']['templateDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates';
        $baseConfig['templates']['compileDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates_c';
        $baseConfig['templates']['configDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'config';

        $baseConfig['env'] = 'development';

        $viewObject = ViewObjectFactory::getInstance($baseConfig);

        $this->assertInstanceOf(Engine::class, $viewObject);
    }

    public function testGetTwigInstanceWithDevelopmentEnvironment()
    {
        $baseConfig['BASEDIR'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

        $baseConfig['templateManager'] = 'Twig';
        $baseConfig['templates']['templateDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates';
        $baseConfig['templates']['compileDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates_c';
        $baseConfig['templates']['configDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'config';

        $baseConfig['env'] = 'development';

        $viewObject = ViewObjectFactory::getInstance($baseConfig);

        $this->assertInstanceOf(\Twig_Environment::class, $viewObject);
    }

    public function testGetSmartyInstanceWithDevelopmentEnvironment()
    {
        $baseConfig['BASEDIR'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

        $baseConfig['templateManager'] = 'Smarty';
        $baseConfig['templates']['templateDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates';
        $baseConfig['templates']['compileDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates_c';
        $baseConfig['templates']['configDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'config';

        $baseConfig['env'] = 'development';

        $viewObject = ViewObjectFactory::getInstance($baseConfig);

        $this->assertInstanceOf(\Smarty::class, $viewObject);
    }

    public function testGetPlatesInstanceWithProductionEnvironment()
    {
        $baseConfig['BASEDIR'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

        $baseConfig['templateManager'] = 'Plates';
        $baseConfig['templates']['templateDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates';
        $baseConfig['templates']['compileDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates_c';
        $baseConfig['templates']['configDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'config';
        $baseConfig['templates']['cacheDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'cache';

        $baseConfig['env'] = 'production';

        $viewObject = ViewObjectFactory::getInstance($baseConfig);

        $this->assertInstanceOf(Engine::class, $viewObject);
    }

    public function testGetTwigInstanceWithProductionEnvironment()
    {
        $baseConfig['BASEDIR'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

        $baseConfig['templateManager'] = 'Twig';
        $baseConfig['templates']['templateDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates';
        $baseConfig['templates']['compileDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates_c';
        $baseConfig['templates']['configDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'config';
        $baseConfig['templates']['cacheDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'cache';

        $baseConfig['env'] = 'production';

        $viewObject = ViewObjectFactory::getInstance($baseConfig);

        $this->assertInstanceOf(\Twig_Environment::class, $viewObject);
    }

    public function testGetSmartyInstanceWithProductionEnvironment()
    {
        $baseConfig['BASEDIR'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

        $baseConfig['templateManager'] = 'Smarty';
        $baseConfig['templates']['templateDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates';
        $baseConfig['templates']['compileDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'templates_c';
        $baseConfig['templates']['configDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'config';
        $baseConfig['templates']['cacheDir'] =
            $baseConfig['BASEDIR']
            . DIRECTORY_SEPARATOR
            . 'cache';

        $baseConfig['env'] = 'production';

        $viewObject = ViewObjectFactory::getInstance($baseConfig);

        $this->assertInstanceOf(\Smarty::class, $viewObject);
    }
}
