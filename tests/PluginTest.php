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

class InvalidClass
{
}

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
    private function instantiatePlugin($config = [])
    {
        if (isset(self::$database)) {
            return new Plugin(array_merge([
                'database' => self::$database,
            ], $config));
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
                [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ]);

            // Set Session variables
            new Plugin([ 'database' => self::$database ]);

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
            $plugin = new Plugin([ 'database' => new InvalidClass() ]);
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
        if (isset(self::$database)) {
            self::$database->exec('DROP TABLE IF EXISTS "phergie-plugin-tell";');
            $this->instantiatePlugin([ 'create-database' => true ]);
            $this->assertNotFalse(self::$database->query(
                'SELECT 1 FROM "phergie-plugin-tell"'));
        }
    }

    /**
    * Data provider for testInvalidCommandParams().
    *
    * @return array
     */
    public function dataProviderInvalidCommandParams()
    {
        return [
            // None Params
            [ '' ],
            // Only nickname
            [ 'myrecipient' ],
        ];
    }

    /**
     * Tests invalid database object
     *
     * @param string $params Invalid params string
     * @dataProvider dataProviderInvalidCommandParams
     */
    public function testInvalidCommandParams($params)
    {
        $plugin = $this->instantiatePlugin();
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $commandEvent = $this->getMockCommandEvent('tell', $params, 'mynick');
        $plugin->handleCommand($commandEvent, $queue);
        Phake::verify($queue)->ircNotice($commandEvent->getNick(),
            'Can\'t identify nickname or message.');
    }

    /**
     * Data provider for testing valid messages
     *
     * @return array
     */
    public function dataProviderValidMessages()
    {
        return [
            // No configuration, no messages
            [
                [],
                [],
            ],
            // No configuration, messages with spaces
            [
                [],
                [
                    [
                        'sender' => 'mynick',
                        'content' => 'My long message is cool',
                    ],
                ],
            ],
            // No configuration, multiple messages
            [
                [],
                [
                    [
                        'sender' => 'mynick',
                        'content' => 'MessageOne',
                    ],
                    [
                        'sender' => 'hernick',
                        'content' => 'Message Two',
                    ],
                ],
            ],
        ];
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
            Phake::verify($queue)->ircNotice($message['sender'], 'Ok, I\'ll tell him/her.');
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
            Phake::verify($queue)->ircNotice($message['sender'], 'Ok, I\'ll tell him/her.');
        }

        $connection = $this->getMockConnection('mynickname');
        $userEvent = $this->getMockUserEvent('anotheruser', $connection);
        $plugin->deliverMessage($userEvent, $queue);

        Phake::verifyNoOtherInteractions($queue);
    }

    /**
     * Data provider for testMaximumMessages
     *
     * @return array
     */
    public function dataProviderMaximumMessages()
    {
        return [
            // Default value '10'
            [
                [],
                13,
                10,
            ],
            // Custom value
            [
                [ 'max-messages' => 5 ],
                7,
                5,
            ],
            // Disabled
            [
                [ 'max-messages' => false ],
                17,
                17,
            ],
        ];
    }

    /**
     * Tests maximum message params and responses
     *
     * @param array $config
     * @param integer $testing
     * @param integer $expected
     * @dataProvider dataProviderMaximumMessages
     */
    public function testMaximumMessages(array $config, $testing, $expected)
    {
        $plugin = $this->instantiatePlugin($config);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $commandEvent = $this->getMockCommandEvent('tell', 'myrecipient my message', 'mynick');
        for ($i = $testing; $i > 0; --$i) {
            $plugin->handleCommand($commandEvent, $queue);
        }

        Phake::verify($queue, Phake::times($testing - $expected))
            ->ircNotice('mynick', 'Sry, There\'s so many things to tell him/her.');

        $connection = $this->getMockConnection('mynickname');
        $userEvent = $this->getMockUserEvent('myrecipient', $connection);
        $plugin->deliverMessage($userEvent, $queue);

        Phake::verify($queue, Phake::times($expected))
            ->ircNotice('myrecipient', $this->stringContains('mynick: my message'));
    }

    /**
     * Data provider for testGetSubscribedEvents
     *
     * @return array
     */
    public function dataProviderGetSubscribedEvents()
    {
        return [
            // Empty configuration
            [
                [],
                [
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.tell' => 'handleCommand',
                    'command.tell.help' => 'helpCommand',
                ],
            ],
            // Single command configuration
            [
                [ 'custom-commands' =>'remind' ],
                [
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.remind' => 'handleCommand',
                    'command.remind.help' => 'helpCommand',
                ],
            ],
            // Array command configuration
            [
                [ 'custom-commands' => [ 'tell', 'remind' ] ],
                [
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.tell' => 'handleCommand',
                    'command.tell.help' => 'helpCommand',
                    'command.remind' => 'handleCommand',
                    'command.remind.help' => 'helpCommand',
                ],
            ],
            // Comma-delimited command configuration
            [
                [ 'custom-commands' => 'tell,remind' ],
                [
                    'irc.received.join' => 'deliverMessage',
                    'irc.received.privmsg' => 'deliverMessage',
                    'command.tell' => 'handleCommand',
                    'command.tell.help' => 'helpCommand',
                    'command.remind' => 'handleCommand',
                    'command.remind.help' => 'helpCommand',
                ],
            ],
        ];
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
        Phake::when($mock)->getCustomParams()->thenReturn(explode(' ', $paramString));
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
