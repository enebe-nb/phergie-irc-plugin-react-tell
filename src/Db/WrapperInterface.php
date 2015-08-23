<?php
/**
 * @link http://github.com/enebe-nb/phergie-irc-plugin-react-tell for the canonical source repository
 * @license https://github.com/enebe-nb/phergie-irc-plugin-react-tell/master/LICENSE Simplified BSD License
 * @package EnebeNb\Phergie\Plugin\Tell
 */

namespace EnebeNb\Phergie\Plugin\Tell\Db;

/**
 * Interface for handling database communication in
 * EnebeNb\Phergie\Plugin\Tell Plugin
 *
 * @category Phergie
 * @package EnebeNb\Phergie\Plugin\Tell
 */
interface WrapperInterface
{
    /**
     * Remove and returns messages for $recipient
     *
     * @param string $recipient
     * @return array messages
     */
    public function retrieveMessages($recipient);

    /**
     * Post a message from $sender to $recipient
     *
     * @param string $sender
     * @param string $recipient
     * @param string $message
     * @return boolean true on success, false on failure
     */
    public function postMessage($sender, $recipient, $message);
}
