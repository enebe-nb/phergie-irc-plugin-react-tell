<?php
/**
 * @link http://github.com/enebe-nb/phergie-irc-plugin-react-tell for the canonical source repository
 * @license https://github.com/enebe-nb/phergie-irc-plugin-react-tell/master/LICENSE Simplified BSD License
 * @package EnebeNb\Phergie\Plugin\Tell
 */

namespace EnebeNb\Phergie\Plugin\Tell\Db;

/**
 * Handles database communication in EnebeNb\Phergie\Plugin\Tell Plugin
 * using a PDO database as storage method.
 *
 * @category Phergie
 * @package EnebeNb\Phergie\Plugin\Tell
 */
class PdoWrapper implements WrapperInterface
{
    /**
     * PDO database object
     *
     * @var \PDO
     */
    private $connection;

    /**
     * Maximum messages stored per user
     */
    private $maxMessages = 10;

    /**
     * Creates a wrapper to handle PDO connection for Tell Plugin
     *
     * @param \PDO $connection
     */
    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
        switch ($connection->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                $connection->exec('SET SESSION SQL_MODE=ANSI_QUOTES;');
                break;
            case 'sqlsrv':
                $connection->exec('SET QUOTED_IDENTIFIER ON;');
                break;
        }
    }

    /**
     * Creates the database structure.
     *
     * @return boolean true on success, false on failure
     */
    public static function create(\PDO $connection)
    {
        return $connection->exec(
            'CREATE TABLE IF NOT EXISTS "phergie-plugin-tell" (
                "timestamp" TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                "sender" VARCHAR(30) NOT NULL,
                "recipient" VARCHAR(30) NOT NULL,
                "message" VARCHAR(510) NOT NULL
            );'
        );
    }

    /**
     * Remove and returns messages for $recipient
     *
     * @param string $recipient
     * @return array messages
     */
    public function retrieveMessages($recipient)
    {
        $statement = $this->connection->prepare(
            'SELECT "timestamp", "sender", "message"
                FROM "phergie-plugin-tell"
                WHERE "recipient" = ?;'
        );

        if ($statement->execute([ $recipient ])) {
            $messages = $statement->fetchAll(\PDO::FETCH_ASSOC);

            $this->connection->prepare(
                'DELETE FROM "phergie-plugin-tell"
                    WHERE "recipient" = ?;'
            )->execute([ $recipient ]);

            return $messages;
        }

        return [];
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
        if ($this->maxMessages) {
            $statement = $this->connection->prepare(
                'SELECT COUNT(*) FROM "phergie-plugin-tell"
                    WHERE "recipient" = ?;'
            );
            $statement->execute([ $recipient ]);

            if ($statement->fetchColumn(0) >= $this->maxMessages) {
                return false;
            }
        }

        $this->connection->prepare(
            'INSERT INTO "phergie-plugin-tell"
                ("sender", "recipient", "message")
                VALUES (?, ?, ?);'
        )->execute([ $sender, $recipient, $message ]);

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
