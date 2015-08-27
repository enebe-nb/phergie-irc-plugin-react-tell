<?php
/**
 * @link http://github.com/enebe-nb/phergie-irc-plugin-react-tell for the canonical source repository
 * @license https://github.com/enebe-nb/phergie-irc-plugin-react-tell/master/LICENSE Simplified BSD License
 * @package EnebeNb\Phergie\Plugin\Tell
 */

namespace EnebeNb\Phergie\Plugin\Tell\Db;

use EnebeNb\Phergie\Plugin\Tell\Db\WrapperInterface;

/**
 * Handles database communication in EnebeNb\Phergie\Plugin\Tell Plugin
 * using memory as storage method.
 *
 * @category Phergie
 * @package EnebeNb\Phergie\Plugin\Tell
 */
class MemoryWrapper implements WrapperInterface
{
    /**
     * Array object to store messages
     *
     * @var array
     */
    private $database = array();

    /**
     * Maximum messages stored per user
     */
    private $maxMessages = 10;

    /**
     * Remove and returns messages for $recipient
     *
     * @param string $recipient
     * @return array messages
     */
    public function retrieveMessages($recipient)
    {
        if (!isset($this->database[$recipient])) {
            return array();
        }

        $messages = $this->database[$recipient];
        unset($this->database[$recipient]);

        return $messages;
    }

    /**
     * Post a message from $sender to $recipient
     *
     * @param string $sender
     * @param string $recipient
     * @param string $message
     * @return boolean true on success, false on failure
     */
    public function postMessage($sender, $recipient, $message)
    {
        if (!isset($this->database[$recipient])) {
            $this->database[$recipient] = array();
        } elseif ($this->maxMessages
            && count($this->database[$recipient]) >= $this->maxMessages) {
            return false;
        }

        $this->database[$recipient][] = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'sender' => $sender,
            'message' => $message,
        );

        return true;
    }

    /**
     * Sets maximum messages stored per user
     *
     * @param integer $maxMessages
     */
    public function setMaxMessages($maxMessages)
    {
        $this->maxMessages = $maxMessages;
    }

    /**
     * Gets maximum messages stored per user
     *
     * @return integer
     */
    public function getMaxMessages()
    {
        return $this->maxMessages;
    }
}
