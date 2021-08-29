#  psr18-adapter/guzzle

You may find couple adapters that adapt [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) to PSR-18.
This is a reverse of that. It adapts PSR-18 client to `\GuzzleHttp\ClientInterface`. 

That's needed in case some of your libraries still depend on Guzzle, but you moved away. 

For example, I'm using PSR-18 client having plugins and profiler integration and wish to have 
consistent experience and reuse same stack, instead of having to search for guzzle middleware 
alternatives and so on just because these libraries still didn't move on.

## Install

Via [Composer](https://getcomposer.org/doc/00-intro.md)

```bash
composer require psr18-adapter/guzzle
```
## Usage

```php
new \Psr18Adapter\Guzzle\GuzzlePsr18Client($psr18Client, $psr7RequestFactory);
```

## Licensing

MIT license. Please see [License File](LICENSE) for more information.
