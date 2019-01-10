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

/**
 * Class App
 *
 * The MVC application class.
 *
 */
class App extends AbstractApp
{

    // @codeCoverageIgnoreStart
    /**
     * App constructor.
     */
    protected function __construct()
    {
    }

    /**
     * App clone method.
     */
    protected function __clone()
    {
    }
    // @codeCoverageIgnoreEnd

    /**
     * Gets a Singleton instance of the App class.
     *
     * @return AbstractApp
     */
    public static function getInstance() : AbstractApp
    {
        if (!self::$appInstance) {
            self::$appInstance = new App();
        }

        return self::$appInstance;
    }

    /**
     * Boots the application by preparing its configuration.
     *
     * @return array
     */
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

    /**
     * Initializes all of the application's objects based on received configuration.
     *
     * @param array $baseConfig
     *
     * @return AbstractApp
     *
     * @throws \Exception
     */
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

    /**
     * Emits the response to the server's buffers.
     *
     * @param Response $response
     *
     * @return null
     */
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

    /**
     * Parses the templates and the controller's output.
     *
     * @param array|string|Response $controllerOutput
     *
     * @return Response
     *
     * @throws \SmartyException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
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

    /**
     * The application's main runtime method. It executes the Application's bootstrap events.
     */
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

    /**
     * Gets the application's base configuration.
     *
     * @param void
     *
     * @return array
     */
    public function getBaseConfig() : array
    {
        return $this->baseConfig;
    }

    /**
     * Gets what is useful to the controllers from the application's base configuration.
     *
     * @param void
     *
     * @return array
     */
    public function getBaseConfigForControllers() : array
    {
        $baseConfig = $this->getBaseConfig();
        unset($baseConfig['doctrine']);
        unset($baseConfig['routes']);
        unset($baseConfig['templates']);

        return $baseConfig;
    }

    /**
     * Adds an element to the application's base configuration.
     *
     * @param string $name
     * @param array $array
     *
     * @return AbstractApp
     */
    public function appendBaseConfig($name, $array) : AbstractApp
    {
        $this->baseConfig[$name] = $array;

        return $this;
    }

    /**
     * Gets the Request object.
     *
     * @return Request
     */
    public function getRequest() : Request
    {
        return $this->request;
    }

    /**
     * Sets the Request object.
     *
     * @param Request
     *
     * @return Request
     */
    public function setRequest(Request $request) : Request
    {
        $this->request = $request;

        return $this->request;
    }

    /**
     * Gets the Response object.
     *
     * @return Response
     */
    public function getResponse() : Response
    {
        return $this->response;
    }

    /**
     * Sets the Response object.
     *
     * @param Response
     *
     * @return Response
     */
    public function setResponse(Response $response) : Response
    {
        $this->response = $response;

        return $this->response;
    }

    /**
     * Gets the Pimple\Container object.
     *
     * @return \Pimple\Container
     */
    public function getServiceManager() : Container
    {
        return $this->serviceManager;
    }

    /**
     * Sets the Pimple\Container object.
     *
     * @param \Pimple\Container
     *
     * @return AbstractApp
     */
    public function setServiceManager(Container &$serviceManager) : AbstractApp
    {
        $this->serviceManager = $serviceManager;

        return $this;
    }

    /**
     * Gets the AscmvcEventManager object.
     *
     * @return AscmvcEventManager
     */
    public function getEventManager() : AscmvcEventManager
    {
        return $this->eventManager;
    }

    /**
     * Sets the AscmvcEventManager object.
     *
     * @param AscmvcEventManager
     *
     * @return AbstractApp
     */
    public function setEventManager(AscmvcEventManager &$eventManager) : AbstractApp
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    /**
     * Gets the AscmvcEvent object.
     *
     * @return AscmvcEvent
     */
    public function getEvent() : AscmvcEvent
    {
        return $this->event;
    }

    /**
     * Sets the AscmvcEvent object.
     *
     * @param AscmvcEvent
     *
     * @return AbstractApp
     */
    public function setEvent(AscmvcEvent &$event) : AbstractApp
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Gets the AbstractRouter object.
     *
     * @return AbstractRouter
     */
    public function getRouter() : AbstractRouter
    {
        return $this->router;
    }

    /**
     * Sets the AbstractRouter object.
     *
     * @param AbstractRouter
     *
     * @return AbstractApp
     */
    public function setRouter(AbstractRouter &$router) : AbstractApp
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Gets the AbstractControllerManager object.
     *
     * @return AbstractControllerManager
     */
    public function getControllerManager() : AbstractControllerManager
    {
        return $this->controllerManager;
    }

    /**
     * Sets the AbstractControllerManager object.
     *
     * @param AbstractControllerManager
     *
     * @return AbstractApp
     */
    public function setControllerManager(AbstractControllerManager &$controllerManager) : AbstractApp
    {
        $this->controllerManager = $controllerManager;

        return $this;
    }

    /**
     * Gets the AbstractController object.
     *
     * @return AbstractController|null
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Sets the AbstractController object.
     *
     * @param AbstractController
     *
     * @return AbstractApp
     */
    public function setController(AbstractController &$controller) : AbstractApp
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * Gets the Controller's output.
     *
     * @return Response|array|string|null
     */
    public function getControllerOutput()
    {
        return $this->controllerOutput;
    }

    /**
     * Sets the Controller's output.
     *
     * @param array $controllerOutput
     *
     * @return AbstractApp
     */
    public function setControllerOutput($controllerOutput)
    {
        $this->controllerOutput = $controllerOutput;

        return $this;
    }

    /**
     * Gets the Template Manager object.
     *
     * @return \League\Plates\Engine|\Smarty|\Twig_Environment
     */
    public function getViewObject()
    {
        return $this->viewObject;
    }

    /**
     * Sets the Template Manager object.
     *
     * @param \League\Plates\Engine|\Smarty|\Twig_Environment
     *
     * @return AbstractApp
     */
    public function setViewObject(&$viewObject)
    {
        $this->viewObject = $viewObject;

        return $this;
    }
}
