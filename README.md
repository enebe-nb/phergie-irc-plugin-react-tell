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

[TODO] Document configuration

```php
return array(
    'plugins' => array(
    ),
);
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
