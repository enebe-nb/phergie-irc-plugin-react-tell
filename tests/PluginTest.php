<?php
/**
 * @link http://github.com/enebe-nb/phergie-irc-plugin-react-tell for the canonical source repository
 * @license https://github.com/enebe-nb/phergie-irc-plugin-react-tell/master/LICENSE Simplified BSD License
 * @package EnebeNb\Phergie\Plugin\Tell
 */

namespace EnebeNb\Phergie\Tests\Plugin\Tell;

use Phake;
use EnebeNb\Phergie\Plugin\Tell\Plugin;
use EnebeNb\Phergie\Plugin\Tell\Db;

class InvalidClass {}

/**
 * Tests for the Plugin class.
 *
 * @category Phergie
 * @package EnebeNb\Phergie\Plugin\AutoRejoin
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{
    private static $database;

    /**
     * (internal) Instantiate the Plugin object using current
     * envoirment database.
     */
    private function instantiatePlugin($config)
    {
        if (isset(self::$database)) {
            return new Plugin(array_merge(array(
                'database' =>  self::$database,
            ), $config) );
        } else {
            return new Plugin($config);
        }
    }

    /**
     * Create a database connection before all tests
     */
    public static function setUpBeforeClass()
    {
        if (isset($GLOBALS['dburi'])) {
            self::$database = new \PDO(
                $GLOBALS['dburi'],
                isset($GLOBALS['dbuser']) ? $GLOBALS['dbuser'] : null,
                isset($GLOBALS['dbpass']) ? $GLOBALS['dbpass'] : null,
                array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));

            // Set Session variables
            new Plugin(array('database' => self::$database));

            Db\PdoWrapper::create(self::$database);
        }
    }

    /**
     * Cleans the table before running each test
     */
    protected function setUp()
    {
        if (isset(self::$database)) {
            self::$database->exec('DELETE FROM "phergie-plugin-tell";');
        }
    }

    /**************************** TESTS ****************************/

    /**
     * Tests invalid database object
     */
    public function testInvalidDatabase()
    {
        try {
            $plugin = new Plugin(array('database' => new InvalidClass()));
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('"EnebeNb\Phergie\Tests\Plugin\Tell\InvalidClass" '.
                'database class is not supported.', $e->getMessage());
        }
    }

    /**
     * Tests if the database is correctly created
     */
    public function testCreateDatabase()
    {
        if(isset(self::$database)) {
            self::$database->exec('DROP TABLE IF EXISTS "phergie-plugin-tell";');
            $this->instantiatePlugin(array('create-database' => true));
            $this->assertNotFalse(self::$database->query(
                'SELECT 1 FROM "phergie-plugin-tell"') );
        }
    }

    /**
    * Data provider for testInvalidCommandParams().
    *
    * @return array
     */
    public function dataProviderInvalidCommandParams()
    {
        return array(
            // None Params
            array(''),
            // Only nickname
            array('myrecipient'),
        );
    }

    /**
     * Tests invalid database object
     *
     * @param string $params Invalid params string
     * @dataProvider dataProviderInvalidCommandParams
     */
    public function testInvalidCommandParams($params)
    {
        $plugin = Phake::partialMock('EnebeNb\Phergie\Plugin\Tell\Plugin');
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $commandEvent = $this->getMockCommandEvent('tell', $params, 'mynick');
        $plugin->handleCommand($commandEvent, $queue);
        Phake::verify($plugin)->helpCommand($commandEvent, $queue,
            'Can\'t identify nickname or message.');
    }

    /**
     * Data provider for testing valid messages
     *
     * @return array
     */
    public function dataProviderValidMessages()
    {
        return array(
            // No configuration, no messages
            array(
                array(),
                array(),
            ),
            // No configuration, messages with spaces
            array(
                array(),
                array(
                    array(
                        'sender' => 'mynick',
                        'content' => 'My long message is cool'
                    )
                ),
            ),
            // No configuration, multiple messages
            array(
                array(),
                array(
                    array(
                        'sender' => 'mynick',
                        'content' => 'MessageOne'
                    ),
                    array(
                        'sender' => 'hernick',
                        'content' => 'Message Two'
                    ),
                ),
            ),
        );
    }

    /**
     * Tests store and retrieve of messages.
     *
     * @param array $config Plugin configuration
     * @param array $messages messages array to store and retrieve
     * @dataProvider dataProviderValidMessages
     */
    public function testStoreRetrieveMessages($config, $messages)
    {
        $plugin = $this->instantiatePlugin($config);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        foreach ($messages as $message) {
            $commandEvent = $this->getMockCommandEvent('tell',
                'myrecipient '.$message['content'], $message['sender']);
            $plugin->handleCommand($commandEvent, $queue);
        }

        $connection = $this->getMockConnection('mynickname');
        $userEvent = $this->getMockUserEvent('myrecipient', $connection);
        $plugin->deliverMessage($userEvent, $queue);

        foreach ($messages as $message) {
            Phake::verify($queue)->ircNotice('myrecipient',
                $this->stringContains($message['sender'].': '.$message['content']));
        }
        Phake::verifyNoOtherInteractions($queue);
    }

    /**
     * Tests ignore other users events.
     *
     * @param array $config Plugin configuration
     * @param array $messages messages array to store and retrieve
     * @dataProvider dataProviderValidMessages
     */
    public function testDontDeliverOnOtherUser($config, $messages)
    {
        $plugin = $this->instantiatePlugin($config);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        foreach ($messages as $message) {
            $commandEvent = $this->getMockCommandEvent('tell',
                'myrecipient '.$message['content'], $message['sender']);
            $plugin->handleCommand($commandEvent, $queue);
        }

        $connection = $this->getMockConnection('mynickname');
        $userEvent = $this->getMockUserEvent('anotheruser', $connection);
        $plugin->deliverMessage($userEvent, $queue);

        Phake::verifyNoInteraction($queue);
    }

    /**
     * Data provider for testGetSubscribedEvents
     *
     * @return array
     */
    public function dataProviderGetSubscribedEvents()
    {
        return array(
            // Empty configuration
            array(
                array(),
                array(
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.tell' => 'handleCommand',
                    'command.tell.help' => 'helpCommand',
                ),
            ),
            // Single command configuration
            array(
                array('custom-commands' =>'remind'),
                array(
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.remind' => 'handleCommand',
                    'command.remind.help' => 'helpCommand',
                ),
            ),
            // Array command configuration
            array(
                array('custom-commands' => array('tell', 'remind')),
                array(
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.tell' => 'handleCommand',
                    'command.tell.help' => 'helpCommand',
                    'command.remind' => 'handleCommand',
                    'command.remind.help' => 'helpCommand',
                ),
            ),
            // Comma-delimited command configuration
            array(
                array('custom-commands' => 'tell,remind'),
                array(
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.tell' => 'handleCommand',
                    'command.tell.help' => 'helpCommand',
                    'command.remind' => 'handleCommand',
                    'command.remind.help' => 'helpCommand',
                ),
            ),
        );
    }

    /**
     * Tests that getSubscribedEvents() returns the correct event listeners.
     *
     * @param array $config
     * @param array $events
     * @dataProvider dataProviderGetSubscribedEvents
     */
    public function testGetSubscribedEvents(array $config, array $events)
    {
        $plugin = new Plugin($config);
        $this->assertEquals($events, $plugin->getSubscribedEvents());
    }

    /**************************** MOCKS ****************************/

    /**
     * Returns a mock user event.
     *
     * @return \Phergie\Irc\Event\UserEventInterface
     */
    protected function getMockUserEvent($nickname, $connection)
    {
        $mock = Phake::mock('\Phergie\Irc\Event\UserEventInterface');
        Phake::when($mock)->getNick()->thenReturn($nickname);
        Phake::when($mock)->getConnection()->thenReturn($connection);
        return $mock;
    }

    /**
     * Returns a mock command event.
     *
     * @return \Phergie\Irc\Plugin\React\Command\CommandEventInterface
     */
    protected function getMockCommandEvent($command, $paramString, $nickname)
    {
        $mock = Phake::mock('\Phergie\Irc\Plugin\React\Command\CommandEventInterface');
        Phake::when($mock)->getNick()->thenReturn($nickname);
        Phake::when($mock)->getCommand()->thenReturn('Privmsg');
        Phake::when($mock)->getCustomCommand()->thenReturn($command);
        Phake::when($mock)->getCustomParams()->thenReturn(explode(' ',$paramString));
        return $mock;
    }

    /**
     * Returns a mock connection.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnection($nickname)
    {
        $mock = Phake::mock('\Phergie\Irc\ConnectionInterface');
        Phake::when($mock)->getNickname()->thenReturn($nickname);
        return $mock;
    }
}
