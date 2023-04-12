# PHP Critical CSS

A PHP class that uses [sabberworm/php-css-parser](https://github.com/sabberworm/PHP-CSS-Parser) to parse a CSS file, extracting any selector blocks which contain a CSS `/* !critical */` comment - outputting them as separate files.

For an example of the CSS comment please see `test/test.css`.

## Installation

`composer require richmilns/php-critical-css`

## Usage

### Command Line Interface

`$ vendor/bin/critical-css path/to/my/file.css`

### PHP

```php
use Exception;
use CriticalCSS\Parser;

require_once 'vendor/autoload.php';

try {
    $critical = new Parser('path/to/my/file.css');
    $critical->parse()->output('compact');
    // saves two new files:
    // - path/to/my/file-critical.css
    // - path/to/my/file-non-critical.css
} catch(Exception $exception) {
    ray($exception->getMessage());
}
```
