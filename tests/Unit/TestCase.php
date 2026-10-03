<?php

declare(strict_types=1);

namespace Omnifox\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Omnifox\OmnifoxClient;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var list<array{request: RequestInterface}> */
    protected array $history = [];
    protected MockHandler $mock;
    /** @var list<float> */
    protected array $sleeps = [];

    /**
     * @param list<Response|\Throwable> $responses
     * @param array<string, mixed>      $options
     */
    protected function client(array $responses, array $options = []): OmnifoxClient
    {
        $this->history = [];
        $this->sleeps = [];
        $this->mock = new MockHandler($responses);
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new OmnifoxClient($options + [
            'apiKey' => 'test-token',
            'baseUrl' => 'https://api.test/api/v1',
            'httpClient' => new Client(['handler' => $stack]),
            'sleeper' => function (float $s): void {
                $this->sleeps[] = $s;
            },
        ]);
    }

    /** @param mixed $body */
    protected static function json(mixed $body, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body));
    }

    protected static function ok(mixed $data): Response
    {
        return self::json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    protected function request(int $i = 0): RequestInterface
    {
        return $this->history[$i]['request'];
    }

    /** @return array<string, mixed> */
    protected function query(int $i = 0): array
    {
        parse_str($this->request($i)->getUri()->getQuery(), $q);

        return $q;
    }

    protected function body(int $i = 0): string
    {
        return (string) $this->request($i)->getBody();
    }

    /** @return mixed */
    protected function jsonBody(int $i = 0): mixed
    {
        return json_decode($this->body($i), true);
    }
}
