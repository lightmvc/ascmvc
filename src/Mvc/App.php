<?php
/**
 * LightMVC/ASCMVC
 *
 * @package    LightMVC/ASCMVC
 * @author     Andrew Caya
 * @link       https://github.com/lightmvc/ascmvc
 * @version    2.0.0
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0.
 * @since      1.0.0
 */

namespace Ascmvc\Mvc;

use Ascmvc\AbstractApp;
use Ascmvc\AbstractController;
use Ascmvc\AbstractControllerManager;
use Ascmvc\AbstractRouter;
use Ascmvc\Middleware\MiddlewareFactory;
use Pimple\Container;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Exception\EmptyPipelineException;

use function Zend\Stratigility\path;

class App extends AbstractApp
{

    // @codeCoverageIgnoreStart
    protected function __construct()
    {
    }

    protected function __clone()
    {
    }
    // @codeCoverageIgnoreEnd

    public static function getInstance() : AbstractApp
    {
        if (!self::$appInstance) {
            self::$appInstance = new App();
        }

        return self::$appInstance;
    }

    public function boot() : array
    {
        if (PHP_SAPI !== 'cli') {
            $_SERVER['SERVER_SIGNATURE'] = isset($_SERVER['SERVER_SIGNATURE']) ? $_SERVER['SERVER_SIGNATURE'] : '80';

            $protocol = strpos($_SERVER['SERVER_SIGNATURE'], '443') !== false ? 'https://' : 'http://';

            $requestUriArray = explode('/', $_SERVER['PHP_SELF']);

            if (is_array($requestUriArray)) {
                $indexKey = array_search('index.php', $requestUriArray);

                array_splice($requestUriArray, $indexKey);

                $requestUri = implode('/', $requestUriArray);
            }

            $requestUrl = $protocol . $_SERVER['HTTP_HOST'] . $requestUri . '/';

            define('URLBASEADDR', $requestUrl);
        } else {
            define('URLBASEADDR', false);
        }


        $appFolder = basename(BASEDIR);

        $baseConfig = ['BASEDIR' => BASEDIR,
            'URLBASEADDR' => URLBASEADDR,
            'appFolder' => $appFolder,
        ];

        if (file_exists(BASEDIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php')) {
            require_once BASEDIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.local.php';
        } else {
            require_once BASEDIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        }

        return $baseConfig;
    }

    public function initialize(array &$baseConfig) : AbstractApp
    {
        $this->baseConfig = $baseConfig;

        if (!isset($this->request)) {
            $this->request = ServerRequestFactory::fromGlobals();
        }

        $serviceManager = new Container();
        $this->setServiceManager($serviceManager);

        $eventManager = AscmvcEventManagerFactory::create();
        $this->setEventManager($eventManager);

        $event = new AscmvcEvent(AscmvcEvent::EVENT_BOOTSTRAP);
        $event->setApplication($this);
        $this->setEvent($event);

        $router = new FastRouter($this->event);
        $this->setRouter($router);

        $viewObject = ViewObjectFactory::getInstance($this->baseConfig);
        $this->setViewObject($viewObject);

        if (isset($this->baseConfig['doctrine'])) {
            foreach ($this->baseConfig['doctrine'] as $connType => $connections) {
                foreach ($connections as $connName => $params) {
                    $serviceManager["$connName"] = $serviceManager->factory(function ($serviceManager) use ($connType, $connName, $params) {
                        $dbManager = Doctrine::getInstance($connType, $connName, $params);
                        return $dbManager;
                    });
                }
            }
        }

        if (isset($this->baseConfig['middleware'])) {
            $middlewarePipe = new MiddlewarePipe();

            $middlewareFactory = new MiddlewareFactory($serviceManager);

            foreach ($this->baseConfig['middleware'] as $path => $middleware) {
                $path = strpos($path, '/') !== false ? $path : '/';
                $middleware = $path !== '/'
                    ? path($path, $middlewareFactory->prepare($middleware))
                    : $middlewareFactory->prepare($middleware);
                $middlewarePipe->pipe($middleware);
            }

            $serviceManager['middlewarePipe'] = function ($serviceManager) use ($middlewarePipe) {
                return $middlewarePipe;
            };

            $this->eventManager->attach(AscmvcEvent::EVENT_BOOTSTRAP, function ($event) use ($serviceManager) {
                $middlewarePipe = $serviceManager['middlewarePipe'];
                try {
                    $response = $middlewarePipe->handle($this->request);
                } catch (EmptyPipelineException $e) {
                    return true;
                }

                return $response;
            }, 3);
        }

        return $this;
    }

    public function display(Response $response) : void
    {
        $statusCode = $response->getStatusCode();
        $protocolVersion = $this->request->getProtocolVersion();
        header("HTTP/$protocolVersion $statusCode");
        $headers = $response->getHeaders();

        foreach ($headers as $header => $value) {
            header("$header: $value[0]");
        }

        if (!empty($response->getBody())) {
            echo $response->getBody();
        }

        return;
    }

    public function render($controllerOutput) : Response
    {
        $response = new Response();

        if (is_array($controllerOutput)) {
            $viewObject = $this->viewObject;

            if ($viewObject instanceof \League\Plates\Engine) {
                echo $viewObject->render($controllerOutput['templatefile'], ['view' => $controllerOutput]);
            } elseif ($viewObject instanceof \Twig_Environment) {
                $twig = $viewObject->load($controllerOutput['templatefile'] . '.html.twig');
                echo $twig->render(['view' => $controllerOutput]);
            } elseif ($viewObject instanceof \Smarty) {
                $viewObject->assign('view', $controllerOutput);
                $viewObject->display($controllerOutput['templatefile'] . '.tpl');
            }

            $response->getBody()->write(ob_get_clean());
        } else {
            $response->getBody()->write($controllerOutput);
        }

        if (isset($controllerOutput['statuscode'])) {
            $response = $response->withStatus($controllerOutput['statuscode']);
        } else {
            $response = $response->withStatus(200);
        }

        return $response;
    }

    public function run() : void
    {
        $event = $this->event;

        $shortCircuit = function ($response) use ($event) {
            if ($response instanceof Response) {
                return true;
            } else {
                return false;
            }
        };

        $this->event->stopPropagation(false); // Clear before triggering
        $result = $this->eventManager->triggerEventUntil($shortCircuit, $this->event);

        if ($result->stopped()) {
            $response = $result->last();
            if ($response instanceof Response) {
                $this->response = $response;
                $this->event->setName(AscmvcEvent::EVENT_FINISH);
                $this->event->stopPropagation(false); // Clear before triggering
                $this->eventManager->triggerEvent($this->event);
                return;
            }
        }

        $this->event->setName(AscmvcEvent::EVENT_ROUTE);
        $this->event->stopPropagation(false); // Clear before triggering
        $result = $this->eventManager->triggerEvent($this->event);

        $this->event->setName(AscmvcEvent::EVENT_DISPATCH);
        $this->event->stopPropagation(false); // Clear before triggering
        $result = $this->eventManager->triggerEventUntil($shortCircuit, $this->event);

        $response = $result->last();

        if ($result->stopped()) {
            if ($response instanceof Response) {
                $this->response = $response;
                $this->event->setName(AscmvcEvent::EVENT_FINISH);
                $this->event->stopPropagation(false); // Clear before triggering
                $this->eventManager->triggerEvent($this->event);
                return;
            }
        } else {
            $this->controllerOutput = $response;
        }

        $this->event->setName(AscmvcEvent::EVENT_RENDER);
        $this->event->stopPropagation(false); // Clear before triggering
        $result = $this->eventManager->triggerEventUntil($shortCircuit, $this->event);

        $response = $result->last();

        $this->response = $response;

        $this->event->setName(AscmvcEvent::EVENT_FINISH);
        $this->event->stopPropagation(false); // Clear before triggering
        $this->eventManager->triggerEvent($this->event);

        return;
    }

    public function getBaseConfig() : array
    {
        return $this->baseConfig;
    }

    public function getBaseConfigForControllers() : array
    {
        $baseConfig = $this->getBaseConfig();
        unset($baseConfig['doctrine']);
        unset($baseConfig['routes']);
        unset($baseConfig['templates']);

        return $baseConfig;
    }

    public function appendBaseConfig($name, $array) : AbstractApp
    {
        $this->baseConfig[$name] = $array;

        return $this;
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    public function setRequest(Request $request) : Request
    {
        $this->request = $request;

        return $this->request;
    }

    public function getResponse() : Response
    {
        return $this->response;
    }

    public function setResponse(Response $response) : Response
    {
        $this->response = $response;

        return $this->response;
    }

    public function getServiceManager() : Container
    {
        return $this->serviceManager;
    }

    public function setServiceManager(Container &$serviceManager) : AbstractApp
    {
        $this->serviceManager = $serviceManager;

        return $this;
    }

    public function getEventManager() : AscmvcEventManager
    {
        return $this->eventManager;
    }

    public function setEventManager(AscmvcEventManager &$eventManager) : AbstractApp
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    public function getEvent() : AscmvcEvent
    {
        return $this->event;
    }

    public function setEvent(AscmvcEvent &$event) : AbstractApp
    {
        $this->event = $event;

        return $this;
    }

    public function getRouter() : AbstractRouter
    {
        return $this->router;
    }

    public function setRouter(AbstractRouter &$router) : AbstractApp
    {
        $this->router = $router;

        return $this;
    }

    public function getControllerManager() : AbstractControllerManager
    {
        return $this->controllerManager;
    }

    public function setControllerManager(AbstractControllerManager &$controllerManager) : AbstractApp
    {
        $this->controllerManager = $controllerManager;

        return $this;
    }

    public function getController() : AbstractController
    {
        return $this->controller;
    }

    public function setController(AbstractController &$controller) : AbstractApp
    {
        $this->controller = $controller;

        return $this;
    }

    public function getControllerOutput()
    {
        return $this->controllerOutput;
    }

    public function setControllerOutput($controllerOutput)
    {
        $this->controllerOutput = $controllerOutput;

        return $this;
    }

    public function getViewObject()
    {
        return $this->viewObject;
    }

    public function setViewObject(&$viewObject)
    {
        $this->viewObject = $viewObject;

        return $this;
    }
}
