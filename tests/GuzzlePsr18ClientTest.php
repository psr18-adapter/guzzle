<?php

declare(strict_types=1);

namespace Psr18Adapter\Guzzle\Tests;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr18Adapter\Guzzle\GuzzlePsr18Client;

class GuzzlePsr18ClientTest extends TestCase
{
    /** @var GuzzlePsr18Client */
    private $client;
    /** @var Client */
    private $decoratedClient;

    public function setUp(): void
    {
        $this->client = new GuzzlePsr18Client($this->decoratedClient = new Client(), new Psr17Factory());
        $this->decoratedClient->addResponse(new Response(200, ['baz' => ['quix', 'last']], '{}'));
    }

    public function testJsonRequest(): void
    {
        $response = $this->client->request(
            'post',
            '/oauth/token',
            [
                'json' => ['foo' => 'bar'],
                'headers' => [
                    'User-Agent' => 'testing/1.0',
                    'X-Foo'      => ['Bar', 'Baz'],
                ],
            ],
        );

        self::assertSame('{}', $response->getBody()->__toString());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['baz' => ['quix', 'last']], $response->getHeaders());

        $request = $this->decoratedClient->getLastRequest();

        $uri = $request->getUri();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/oauth/token', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame(
            [
                'Content-Type' => ['application/json'],
                'User-Agent' => ['testing/1.0'],
                'X-Foo' => ['Bar', 'Baz'],
            ],
            $request->getHeaders()
        );
        self::assertSame('{"foo":"bar"}', $request->getBody()->__toString());
    }

    public function testQueryAndFormParamsRequest(): void
    {
        $response = $this->client->request(
            'post',
            '/post',
            [
                'form_params' => ['foo' => 'bar', 'baz' => ['hi', 'there!']],
                'query' => ['bar' => 'baz'],
                'headers' => [
                    'User-Agent' => 'testing/1.0',
                    'X-Foo'      => ['Bar', 'Baz'],
                ],
            ],
        );

        self::assertSame('{}', $response->getBody()->__toString());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['baz' => ['quix', 'last']], $response->getHeaders());

        $request = $this->decoratedClient->getLastRequest();

        $uri = $request->getUri();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/post', $uri->getPath());
        self::assertSame('bar=baz', $uri->getQuery());
        self::assertSame(
            [
                'Content-Type' => ['application/x-www-form-urlencoded'],
                'User-Agent' => ['testing/1.0'],
                'X-Foo' => ['Bar', 'Baz'],
            ],
            $request->getHeaders()
        );
        self::assertSame('foo=bar&baz%5B0%5D=hi&baz%5B1%5D=there%21', $request->getBody()->__toString());
    }
}
