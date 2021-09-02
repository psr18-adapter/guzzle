<?php

declare(strict_types=1);

namespace Psr18Adapter\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzlePsr18Client implements ClientInterface
{
    /** @var PsrClientInterface */
    private $client;
    /** @var RequestFactoryInterface */
    private $requestFactory;

    public function __construct(
        PsrClientInterface $client,
        RequestFactoryInterface $requestFactory
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->client->sendRequest($this->applyGuzzleOptions($request, $options));
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return new FulfilledPromise($this->client->sendRequest($this->applyGuzzleOptions($request, $options)));
    }

    public function request($method, $uri, array $options = []): ResponseInterface
    {
        return $this->send($this->requestFactory->createRequest($method, $uri), $options);
    }

    public function requestAsync($method, $uri, array $options = []): PromiseInterface
    {
        return $this->sendAsync($this->requestFactory->createRequest($method, $uri), $options);
    }

    public function getConfig($option = null)
    {
        return $option ? null : [];
    }

    /**
     * @param array<string, mixed> $options
     * @throws \JsonException
     */
    private function applyGuzzleOptions(RequestInterface $request, array $options): RequestInterface
    {
        // See \GuzzleHttp\Client::prepareDefaults
        foreach ($options['headers'] ?? [] as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        // See \GuzzleHttp\Client::sendAsync
        if (isset($config['base_uri'])) {
            $request = $request->withUri(
                UriResolver::resolve(Utils::uriFor($config['base_uri']), $request->getUri()),
                $request->hasHeader('Host')
            );
        }

        // For remaining, see \GuzzleHttp\Client::applyOptions
        $modify = [
            'set_headers' => [],
        ];

        if (isset($options['headers'])) {
            $modify['set_headers'] = $options['headers'];
            unset($options['headers']);
        }

        if (isset($options['form_params'])) {
            if (isset($options['multipart'])) {
                throw new InvalidArgumentException('You cannot use '
                    . 'form_params and multipart at the same time. Use the '
                    . 'form_params option if you want to send application/'
                    . 'x-www-form-urlencoded requests, and the multipart '
                    . 'option to send multipart/form-data requests.');
            }
            $options['body'] = \http_build_query($options['form_params']);
            unset($options['form_params']);
            // Ensure that we don't have the header in different case and set the new value.
            $options['_conditional'] = Utils::caselessRemove(['Content-Type'], $options['_conditional'] ?? []);
            $options['_conditional']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if (isset($options['multipart'])) {
            $options['body'] = new MultipartStream($options['multipart']);
            unset($options['multipart']);
        }

        if (isset($options['json'])) {
            $options['body'] = json_encode($options['json'], JSON_THROW_ON_ERROR);
            unset($options['json']);
            // Ensure that we don't have the header in different case and set the new value.
            $options['_conditional'] = Utils::caselessRemove(['Content-Type'], $options['_conditional'] ?? []);
            $options['_conditional']['Content-Type'] = 'application/json';
        }

        if (!empty($options['decode_content'])
            && $options['decode_content'] !== true
        ) {
            // Ensure that we don't have the header in different case and set the new value.
            $options['_conditional'] = Utils::caselessRemove(['Accept-Encoding'], $options['_conditional'] ?? []);
            $modify['set_headers']['Accept-Encoding'] = $options['decode_content'];
        }

        if (isset($options['body'])) {
            $modify['body'] = Utils::streamFor($options['body']);
            unset($options['body']);
        }

        if (!empty($options['auth']) && \is_array($options['auth'])) {
            $value = $options['auth'];
            $type = isset($value[2]) ? \strtolower($value[2]) : 'basic';
            if ($type === 'basic') {
                // Ensure that we don't have the header in different case and set the new value.
                $modify['set_headers'] = Utils::caselessRemove(['Authorization'], $modify['set_headers']);
                $modify['set_headers']['Authorization'] = 'Basic ' . \base64_encode("$value[0]:$value[1]");
            }
        }

        if (isset($options['query'])) {
            $value = $options['query'];
            if (\is_array($value)) {
                $value = \http_build_query($value, '', '&', \PHP_QUERY_RFC3986);
            }
            if (!\is_string($value)) {
                throw new InvalidArgumentException('query must be a string or array');
            }
            $modify['query'] = $value;
            unset($options['query']);
        }

        $request = Utils::modifyRequest($request, $modify);
        if ($request->getBody() instanceof MultipartStream) {
            // Use a multipart/form-data POST if a Content-Type is not set.
            // Ensure that we don't have the header in different case and set the new value.
            $options['_conditional'] = Utils::caselessRemove(['Content-Type'], $options['_conditional'] ?? []);
            $options['_conditional']['Content-Type'] = 'multipart/form-data; boundary='
                . $request->getBody()->getBoundary();
        }

        // Merge in conditional headers if they are not present.
        if (isset($options['_conditional'])) {
            // Build up the changes so it's in a single clone of the message.
            $modify = [];
            foreach ($options['_conditional'] as $k => $v) {
                if (!$request->hasHeader($k)) {
                    $modify['set_headers'][$k] = $v;
                }
            }
            $request = Utils::modifyRequest($request, $modify);
            // Don't pass this internal value along to middleware/handlers.
            unset($options['_conditional']);
        }

        return $request;
    }
}