<?php
/**
 * @link http://github.com/enebe-nb/phergie-irc-plugin-react-tell for the canonical source repository
 * @license https://github.com/enebe-nb/phergie-irc-plugin-react-tell/master/LICENSE Simplified BSD License
 * @package EnebeNb\Phergie\Plugin\Tell
 */

namespace EnebeNb\Phergie\Plugin\Tell;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface;
use Phergie\Irc\Event\UserEventInterface;
use Phergie\Irc\Plugin\React\Command\CommandEventInterface;

use EnebeNb\Phergie\Plugin\Tell\Db;

/**
 * Plugin for send messages to users next time they are seen.
 *
 * @category Phergie
 * @package EnebeNb\Phergie\Plugin\Tell
 */
class Plugin extends AbstractPlugin
{
    /**
     * Array of command events to listen.
     *
     * @var array
     */
    protected $commandEvents = array(
            'command.tell' => 'handleCommand',
            'command.tell.help' => 'helpCommand',
        );

    /**
     * Database layer interface
     *
     * @var \EnebeNb\Phergie\Plugin\Tell\Db\WrapperInterface
     */
    protected $database;

    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     * database - optional, a database connection object. Default: null (memory)
     * Supported classes are: [\PDO]
     *
     * custom-commands - optional, either a comma-delimited string or array of
     * commands to register listeners. Default: 'tell'
     *
     * create-database - optional, call tables creation method on database.
     * Default: false
     *
     * max-messages - optional, maximum number of messages that can be stored
     * for each recipient. Default: 10
     *
     * [TODO] deliver on bot join
     * [TODO] message/date format
     *
     * [NOTE] There's many concepts to add yet.
     *
     * @param array $config
     * @throws \InvalidArgumentException if an unsupported database is passed.
     */
    public function __construct(array $config = array())
    {
        if (!isset($config['database']) || !$config['database']) {
            // Memory database
            $this->database = new Db\MemoryWrapper();

        } elseif (class_exists('PDO', false)    // Avoid autload class
                && $config['database'] instanceof \PDO) {
            // PDO database
            $this->database = new Db\PdoWrapper($config['database']);
            if(isset($config['create-database']) && $config['create-database']) {
                Db\PdoWrapper::create($config['database']);
            }

        } else {
            // Not Supported Error
            throw new \InvalidArgumentException('"'.get_class($config['database']).
                '" database class is not supported.');
        }

        if (isset($config['custom-commands'])) {
            $commands = is_string($config['custom-commands'])
                ? explode(',', $config['custom-commands'])
                : $config['custom-commands'];
            $this->commandEvents = array();
            foreach($commands as $command) {
                $this->commandEvents['command.'.$command] = 'handleCommand';
                $this->commandEvents['command.'.$command.'.help'] = 'helpCommand';
            }
        }

        if (isset($config['max-messages'])) {
            $this->database->setMaxMessages($config['max-messages']);
        }
    }

    /**
     * Indicates that the plugin monitors PART and KICK events.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array_merge(
            array(
                'irc.received.join' => 'deliverMessage',
                'irc.received.privmsg' => 'deliverMessage',
            ),
            $this->commandEvents
         );
    }

    /**
     * Listen for an user activity and send all stored messages.
     *
     * @param \Phergie\Irc\Event\UserEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function deliverMessage(UserEventInterface $event, EventQueueInterface $queue)
    {
        // [NOTE] test response speed and study a count check before try retrieve the messages
        if($event->getNick() != $event->getConnection()->getNickname()) {
            $messages = $this->database->retrieveMessages($event->getNick());
            foreach($messages as $row) {
                $message = sprintf('(%s) %s: %s',
                    (new \DateTime($row['timestamp']))->format('m/d h:ia'),
                    $row['sender'],
                    $row['message']);
                $queue->ircNotice($event->getNick(), $message);
            }
        }
    }

    /**
     * Handles command calls
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleCommand(CommandEventInterface $event, EventQueueInterface $queue)
    {
        $params = $event->getCustomParams();
        if (count($params) < 2) {
            $queue->ircNotice($event->getNick(), 'Can\'t identify nickname or message.');
            $this->helpMessages(array($queue, 'ircNotice'), $event->getNick(), $event->getCustomCommand());
        } else {
            $message = implode(' ', array_slice($params, 1));
            if ($this->database->postMessage($event->getNick(), $params[0], $message)) {
                $queue->ircNotice($event->getNick(), 'Ok, I\'ll tell him/her.');
            } else {
                $queue->ircNotice($event->getNick(), 'Sry, There\'s so many things to tell him/her.');
            }
        }
    }

    /**
     * Handles help command calls
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function helpCommand(CommandEventInterface $event, EventQueueInterface $queue)
    {
        $this->helpMessages(array($queue, 'irc'.$event->getCommand()), $event->getSource(), $event->getCustomParams()[0]);
    }

    /**
     * Reply with usage help messages
     *
     * @param callable $callback
     * @param string $target
     * @param string $command
     */
    private function helpMessages(callable $callback, $target, $command)
    {
        call_user_func($callback, array($target, 'Usage: '.$command.' <nickname> <message>'));
        call_user_func($callback, array($target, 'Stores a <message> to be send next time the <nickname> is seen.'));
    }
}
