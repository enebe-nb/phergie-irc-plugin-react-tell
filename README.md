# enebe-nb/phergie-irc-plugin-react-tell

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for send messages to users next time they are seen.

[![Build Status](https://travis-ci.org/enebe-nb/phergie-irc-plugin-react-tell.svg?branch=master)](https://travis-ci.org/enebe-nb/phergie-irc-plugin-react-tell)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "enebe-nb/phergie-irc-plugin-react-tell": "^1.0"
    }
}
```

See [Phergie documentation](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins) for more information on installing plugins.

## Configuration

```php
return array(
    'plugins' => array(
        new \EnebeNb\Phergie\Plugin\Tell\Plugin(array(
            // Send a \PDO object to use that database,
            // or leave unsetted to store the messages into an array.
            'database' => new \PDO($mydsn, $myusername, $mypassword),

            // change the default command text from 'tell' to anything
            'custom-commands' => 'mycustomcommand',
            // or pick any number of commands
            'custom-commands' => array('tell', 'ask', 'remind'),
            // also works with comma-delimited strings
            'custom-commands' => 'tell,ask,remind',
        )),

        // phergie/phergie-irc-plugin-react-command
        // is required to listen for commands
        new \Phergie\Irc\Plugin\React\Command\Plugin(),
    ),
);
```

See [phergie/phergie-irc-plugin-react-command](https://github.com/phergie/phergie-irc-plugin-react-command) for more information on Command Plugin.

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
