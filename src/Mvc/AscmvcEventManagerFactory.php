<?php
/**
 * LightMVC/ASCMVC
 *
 * @package    LightMVC/ASCMVC
 * @author     Andrew Caya
 * @link       https://github.com/lightmvc/ascmvc
 * @version    2.0.0
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0.
 * @since      2.0.0
 */

namespace Ascmvc\Mvc;

//use Zend\EventManager\SharedEventManager;

class AscmvcEventManagerFactory
{

    public static function create() : AscmvcEventManager
    {
        //$shared = new SharedEventManager();
        return new AscmvcEventManager();
    }
}
