<?php
/**
 * LightMVC/ASCMVC
 *
 * @package    LightMVC/ASCMVC
 * @author     Andrew Caya
 * @link       https://github.com/lightmvc/ascmvc
 * @version    4.0.0
 * @license    Apache License, Version 2.0, see above
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 * @since      3.0.0
 */

namespace AscmvcTest;

use Ascmvc\EventSourcing\AggregateImmutableValueObject;
use Ascmvc\EventSourcing\Event\AggregateEvent;
use Ascmvc\EventSourcing\EventDispatcher;
use Ascmvc\EventSourcing\EventLogger;
use Ascmvc\Mvc\App;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class EventLoggerTest extends TestCase
{
    public function testCreateEventLoggerInstance()
    {
        if (!defined('BASEDIR')) {
            define('BASEDIR', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        if (!defined('BASEDIR2')) {
            define('BASEDIR2', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        $serverRequestFactoryMock = \Mockery::mock('alias:' . ServerRequestFactory::class);
        $serverRequestFactoryMock
            ->shouldReceive('fromGlobals')
            ->once();

        $baseConfig['BASEDIR'] = BASEDIR2;

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

        $baseConfig['view'] = [];

        $baseConfig['events'] = [
            // PSR-14 compliant Event Bus.
            'psr14_event_dispatcher' => \Ascmvc\EventSourcing\EventDispatcher::class,
            // Different read and write connections allow for simplified (!) CQRS. :)
            'read_conn_name' => 'em1',
            'write_conn_name' => 'em1',
        ];

        $baseConfig['eventlog'] = [
            'enabled' => true,
            'doctrine' => [
                'log_conn_name' => 'em1',
                'entity_name' => \Application\Models\Entity\EventLog::class,
            ],
            // Leave empty to log everything, including the kitchen sink. :)
            // If you you start whitelisting events, it will blacklist everything else by default.
            'log_event_type' => [
                'whitelist' => [
                    \Ascmvc\EventSourcing\Event\WriteAggregateCompletedEvent::class,
                ],
                'blacklist' => [
                    //\Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
            ],
        ];

        $baseConfig['doctrine']['ORM']['em1'] = [
            'driver'   => 'pdo_mysql',
            'host'     => 'localhost',
            'user'     => 'USERNAME',
            'password' => 'PASSWORD',
            'dbname'   => 'DATABASE',
        ];

        $app = App::getInstance();

        // Deliberately not calling the app's boot() method
        $app->initialize($baseConfig);

        $listeners = $app->getEventManager()->getSharedManager()->getListeners([EventLogger::class], 'test');

        $this->assertSame(EventLogger::class, get_class($listeners[-1][0][0]));
    }

    public function testLogWhitelistEvents()
    {
        if (!defined('BASEDIR')) {
            define('BASEDIR', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        if (!defined('BASEDIR2')) {
            define('BASEDIR2', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        $serverRequestFactoryMock = \Mockery::mock('alias:' . ServerRequestFactory::class);
        $serverRequestFactoryMock
            ->shouldReceive('fromGlobals')
            ->once();

        $baseConfig['BASEDIR'] = BASEDIR2;

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

        $baseConfig['view'] = [];

        $baseConfig['events'] = [
            // PSR-14 compliant Event Bus.
            'psr14_event_dispatcher' => \Ascmvc\EventSourcing\EventDispatcher::class,
            // Different read and write connections allow for simplified (!) CQRS. :)
            'read_conn_name' => 'em1',
            'write_conn_name' => 'em1',
        ];

        $baseConfig['eventlog'] = [
            'enabled' => true,
            'doctrine' => [
                'log_conn_name' => 'em1',
                'entity_name' => \Application\Models\Entity\EventLog::class,
            ],
            // Leave empty to log everything, including the kitchen sink. :)
            // If you you start whitelisting events, it will blacklist everything else by default.
            'log_event_type' => [
                'whitelist' => [
                    \Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
                'blacklist' => [
                    //\Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
            ],
        ];

        $baseConfig['doctrine']['ORM']['em1'] = [
            'driver'   => 'pdo_mysql',
            'host'     => 'localhost',
            'user'     => 'USERNAME',
            'password' => 'PASSWORD',
            'dbname'   => 'DATABASE',
        ];

        $app = App::getInstance();

        // Deliberately not calling the app's boot() method
        $app->initialize($baseConfig);

        $sharedEventManager = $app->getEventManager()->getSharedManager();

        $eventDispatcher = new EventDispatcher($app, $sharedEventManager);

        $eventDispatcher->setIdentifiers([EventLogger::class]);

        $aggregateValueObject = new AggregateImmutableValueObject(['testkey' => 'testvalue']);

        $aggregateEvent = new AggregateEvent($aggregateValueObject, 'testRootAggregate', 'testName');

        try {
            $eventDispatcher->dispatch($aggregateEvent);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->assertSame('Commit Deferred Failed', $message);
    }

    public function testLogBlacklistEvents()
    {
        if (!defined('BASEDIR')) {
            define('BASEDIR', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        if (!defined('BASEDIR2')) {
            define('BASEDIR2', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        $serverRequestFactoryMock = \Mockery::mock('alias:' . ServerRequestFactory::class);
        $serverRequestFactoryMock
            ->shouldReceive('fromGlobals')
            ->once();

        $baseConfig['BASEDIR'] = BASEDIR2;

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

        $baseConfig['view'] = [];

        $baseConfig['events'] = [
            // PSR-14 compliant Event Bus.
            'psr14_event_dispatcher' => \Ascmvc\EventSourcing\EventDispatcher::class,
            // Different read and write connections allow for simplified (!) CQRS. :)
            'read_conn_name' => 'em1',
            'write_conn_name' => 'em1',
        ];

        $baseConfig['eventlog'] = [
            'enabled' => true,
            'doctrine' => [
                'log_conn_name' => 'em1',
                'entity_name' => \Application\Models\Entity\EventLog::class,
            ],
            // Leave empty to log everything, including the kitchen sink. :)
            // If you you start whitelisting events, it will blacklist everything else by default.
            'log_event_type' => [
                'whitelist' => [
                    //\Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
                'blacklist' => [
                    \Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
            ],
        ];

        $baseConfig['doctrine']['ORM']['em1'] = [
            'driver'   => 'pdo_mysql',
            'host'     => 'localhost',
            'user'     => 'USERNAME',
            'password' => 'PASSWORD',
            'dbname'   => 'DATABASE',
        ];

        $app = App::getInstance();

        // Deliberately not calling the app's boot() method
        $app->initialize($baseConfig);

        $sharedEventManager = $app->getEventManager()->getSharedManager();

        $eventDispatcher = new EventDispatcher($app, $sharedEventManager);

        $eventDispatcher->setIdentifiers([EventLogger::class]);

        $aggregateValueObject = new AggregateImmutableValueObject(['testkey' => 'testvalue']);

        $aggregateEvent = new AggregateEvent($aggregateValueObject, 'testRootAggregate', 'testName');

        $eventDispatcher->dispatch($aggregateEvent);

        $listeners = $app->getEventManager()->getSharedManager()->getListeners([EventLogger::class], 'test');

        $this->assertSame(EventLogger::class, get_class($listeners[-1][0][0]));
    }

    public function testLogAllEventsByDefault()
    {
        if (!defined('BASEDIR')) {
            define('BASEDIR', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        if (!defined('BASEDIR2')) {
            define('BASEDIR2', dirname(dirname(__FILE__))
                . DIRECTORY_SEPARATOR
                . 'app');
        }

        $serverRequestFactoryMock = \Mockery::mock('alias:' . ServerRequestFactory::class);
        $serverRequestFactoryMock
            ->shouldReceive('fromGlobals')
            ->once();

        $baseConfig['BASEDIR'] = BASEDIR2;

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

        $baseConfig['view'] = [];

        $baseConfig['events'] = [
            // PSR-14 compliant Event Bus.
            'psr14_event_dispatcher' => \Ascmvc\EventSourcing\EventDispatcher::class,
            // Different read and write connections allow for simplified (!) CQRS. :)
            'read_conn_name' => 'em1',
            'write_conn_name' => 'em1',
        ];

        $baseConfig['eventlog'] = [
            'enabled' => true,
            'doctrine' => [
                'log_conn_name' => 'em1',
                'entity_name' => \Application\Models\Entity\EventLog::class,
            ],
            // Leave empty to log everything, including the kitchen sink. :)
            // If you you start whitelisting events, it will blacklist everything else by default.
            'log_event_type' => [
                'whitelist' => [
                    //\Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
                'blacklist' => [
                    //\Ascmvc\EventSourcing\Event\AggregateEvent::class,
                ],
            ],
        ];

        $baseConfig['doctrine']['ORM']['em1'] = [
            'driver'   => 'pdo_mysql',
            'host'     => 'localhost',
            'user'     => 'USERNAME',
            'password' => 'PASSWORD',
            'dbname'   => 'DATABASE',
        ];

        $app = App::getInstance();

        // Deliberately not calling the app's boot() method
        $app->initialize($baseConfig);

        $sharedEventManager = $app->getEventManager()->getSharedManager();

        $eventDispatcher = new EventDispatcher($app, $sharedEventManager);

        $eventDispatcher->setIdentifiers([EventLogger::class]);

        $aggregateValueObject = new AggregateImmutableValueObject(['testkey' => 'testvalue']);

        $aggregateEvent = new AggregateEvent($aggregateValueObject, 'testRootAggregate', 'testName');

        try {
            $eventDispatcher->dispatch($aggregateEvent);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->assertSame('Commit Deferred Failed', $message);
    }
}
