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
     * Message to be send when a command is accepted.
     *
     * @var string
     */
    protected $successMessage = 'Ok. I\'ll tell him.';

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
     * success-message - optional, message to be send after storing a message.
     * Default: 'Ok. I\'ll tell him.'
     *
     * [TODO] deliver on bot join
     * [TODO] max notes (avoid spam)
     * [TODO] message/date format
     * [TODO] failure message
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

        if (isset($config['success-message'])) {
            $this->successMessage = $config['success-message'];
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
     * Listen for command calls and manager theirs parameters.
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleCommand(CommandEventInterface $event, EventQueueInterface $queue)
    {
        $params = $event->getCustomParams();
        if (count($params) < 2) {
            $this->helpCommand($event, $queue, 'Can\'t identify nickname or message.' );
        } else {
            $this->database->postMessage($event->getNick(), $params[0],
                implode(' ', array_slice($params, 1)));
            $queue->ircNotice($event->getNick(), $this->successMessage);
        }
    }

    /**
     * Respond to a help Command
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEventInterface $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     * @param string $errorMessage
     */
    public function helpCommand(CommandEventInterface $event, EventQueueInterface $queue, $errorMessage = null)
    {
        $method = 'irc'.$event->getCommand();
        $target = $event->getSource();
        if ($errorMessage != null) {
            $queue->$method($target, $errorMessage);
            $command = $event->getCustomCommand();
        } else {
            $command = $event->getCustomParams()[0];
        }
        $queue->$method($target, 'Usage: '.$command.' <nickname> <message>');
        $queue->$method($target, 'Stores a message to be send next time the <nickname> is seen.');
    }
}
