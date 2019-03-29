<?php
/**
 * LightMVC/ASCMVC
 *
 * @package    LightMVC/ASCMVC
 * @author     Andrew Caya
 * @link       https://github.com/lightmvc/ascmvc
 * @version    2.1.0
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0.
 * @since      2.1.0
 *
 * @see       https://github.com/itxiao6/session for the canonical source repository
 * @copyright Copyright (c) 2018  戒尺 包描述
 * @license   https://opensource.org/licenses/MIT
 */

namespace Ascmvc\Session;

/**
 * Class SessionManager
 * 
 * @package Ascmvc\Session
 */
class SessionManager
{
    /**
     * Contains the session Config object.
     * 
     * @var Config|null 
     */
    protected $config = null;

    /**
     * Contains the session Http object.
     * 
     * @var Http|null 
     */
    protected $http = null;

    /**
     * Contains the Session object.
     * 
     * @var Session|null 
     */
    protected $session = null;
    
    /**
     * Contains an instance of the Cache Driver object.
     *
     * @var \Doctrine\Common\Cache\Cache|null
     */
    protected $driver = null;

    /**
     * Swoole Session interface
     *
     * @var null
     */
    protected static $session_swoole_interface = null;

    /**
     * Contains the SessionManager instance.
     *
     * @var null
     */
    protected static $sessionManager = null;

    /**
     * Contains the Swoole Request object.
     *
     * @var \swoole_http_request
     */
    protected $request;

    /**
     * Contains the Swoole Response object.
     *
     * @var \swoole_http_response
     */
    protected $response;

    /**
     * SessionManager constructor.
     *
     * @param \swoole_http_request|null $request
     * @param \swoole_http_response|null $response
     * @param Config|null $config
     */
    protected function __construct(\swoole_http_request $request = null, \swoole_http_response $response = null, Config $config = null)
    {
        if (isset($config)) {
            $this->config = $config;
        } else {
            $this->config = new Config();
        }

        $this->request = $request;

        $this->response = $response;
    }

    /**
     * Starts the session.
     *
     * @return $this
     * @throws \Exception
     */
    public function start()
    {
        if($this->request != null && $this->response != null) {
            if($this->request instanceof \swoole_http_request
                && $this->response instanceof \swoole_http_response
            ) {
                $this->http = (new Swoole($this->request, $this->response));
            } else {
                throw new \Exception('Request or Response invalid');
            }
        } else {
            $this->http = (new Http($this->config));
        }

        $this->session = new Session($this);

        return $this;
    }

    /**
     * Gets the SessionManager interface.
     *
     * @return SessionManager|null
     * @throws \Exception
     */
    public static function getSessionManager()
    {

        if(!self::$sessionManager){
            self::$sessionManager = new static();
        }
        return self::$sessionManager;
    }

    /**
     * Returns the Config instance.
     *
     * @return Config|null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the Config instance.
     *
     * @param Config $config
     * @return $this
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the Http instance.
     *
     * @return Http|null
     */
    public function getHttp()
    {
        return $this->http;
    }

    /**
     * Gets the Session instance.
     *
     * @return Session|null
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Gets the storage driver instance.
     *
     * @return mixed
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Sets the storage driver instance.
     * @param \Doctrine\Common\Cache\Cache $driver
     * @return $this
     */
    public function setDriver(\Doctrine\Common\Cache\Cache $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Gets the Swoole Session interface.
     *
     * @param \swoole_http_request|null $request
     * @param \swoole_http_response|null $response
     * @param Config|null $config
     * @return SessionManager|null
     * @throws \Exception
     */
    public static function getSwooleSessionInterface(\swoole_http_request $request = null, \swoole_http_response $response = null, Config $config = null)
    {
        if(!self::$session_swoole_interface) {
            self::$session_swoole_interface = new static($request, $response, $config);
        }

        return self::$session_swoole_interface;
    }
}