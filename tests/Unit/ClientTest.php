<?php

declare(strict_types=1);

namespace Omnifox\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Omnifox\Exception\AuthenticationException;
use Omnifox\Exception\ConflictException;
use Omnifox\Exception\InvalidArgumentException;
use Omnifox\Exception\NetworkException;
use Omnifox\Exception\NotFoundException;
use Omnifox\Exception\OmnifoxException;
use Omnifox\Exception\PermissionDeniedException;
use Omnifox\Exception\RateLimitException;
use Omnifox\Exception\ServerException;
use Omnifox\Exception\ValidationException;
use Omnifox\OmnifoxClient;

final class ClientTest extends TestCase
{
    public function testRequiresApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OmnifoxClient(['apiKey' => '  ']);
    }

    public function testAcceptsBareStringApiKey(): void
    {
        $c = new OmnifoxClient('abc');
        self::assertSame(OmnifoxClient::DEFAULT_BASE_URL, $c->getBaseUrl());
        self::assertNull($c->getWorkspaceId());
    }

    public function testSendsAuthHeadersAndUnwrapsEnvelope(): void
    {
        $c = $this->client([self::ok(['user' => ['id' => 28]])]);
        $me = $c->users->me();

        self::assertSame(['user' => ['id' => 28]], $me);
        $r = $this->request();
        self::assertSame('GET', $r->getMethod());
        self::assertSame('https://api.test/api/v1/auth/me', (string) $r->getUri());
        self::assertSame('Bearer test-token', $r->getHeaderLine('Authorization'));
        self::assertSame('application/json', $r->getHeaderLine('Accept'));
        self::assertStringStartsWith('omnifox-sdk-php/' . OmnifoxClient::VERSION, $r->getHeaderLine('User-Agent'));
        self::assertFalse($r->hasHeader('X-Workspace-Id'), 'the API ignores X-Workspace-Id');
    }

    public function testWorkspaceGoesInTheQueryNotAHeader(): void
    {
        $c = $this->client([self::ok([])], ['workspaceId' => 7]);
        $c->tags->listPage(['limit' => 5]);

        self::assertSame(['limit' => '5', 'workspace_id' => '7'], $this->query());
        self::assertFalse($this->request()->hasHeader('X-Workspace-Id'));
    }

    public function testCallerWorkspaceIdIsNotOverridden(): void
    {
        $c = $this->client([self::ok([])], ['workspaceId' => 7]);
        $c->tags->listPage(['workspace_id' => 9]);
        self::assertSame('9', $this->query()['workspace_id']);
    }

    public function testNoWorkspaceParamWithoutWorkspace(): void
    {
        $c = $this->client([self::ok([])]);
        $c->tags->listPage();
        self::assertArrayNotHasKey('workspace_id', $this->query());
    }

    /** @return iterable<string, array{callable(OmnifoxClient): mixed, string, string}> */
    public static function bodyWorkspaceEndpoints(): iterable
    {
        yield 'teams.create' => [fn (OmnifoxClient $c) => $c->teams->create(['name' => 'T']), 'POST', '/teams'];
        yield 'channels.create' => [fn (OmnifoxClient $c) => $c->channels->create(['type' => 'webchat']), 'POST', '/channels'];
        yield 'broadcasts.create' => [fn (OmnifoxClient $c) => $c->broadcasts->create(['name' => 'B']), 'POST', '/broadcasts'];
        yield 'users.setAgentStatus' => [fn (OmnifoxClient $c) => $c->users->setAgentStatus(3, 'busy'), 'PUT', '/agents/3/status'];
    }

    /** @dataProvider bodyWorkspaceEndpoints */
    public function testWorkspaceIsInjectedInBodyWhereRequired(callable $call, string $method, string $path): void
    {
        $c = $this->client([self::ok(['id' => 1])], ['workspaceId' => 4]);
        $call($c);

        self::assertSame($method, $this->request()->getMethod());
        self::assertSame('/api/v1' . $path, $this->request()->getUri()->getPath());
        self::assertSame(4, $this->jsonBody()['workspace_id']);
    }

    /** @dataProvider bodyWorkspaceEndpoints */
    public function testBodyWorkspaceEndpointsFailEarlyWithoutWorkspace(callable $call): void
    {
        $c = $this->client([]);
        $this->expectException(InvalidArgumentException::class);
        $call($c);
    }

    public function testOtherEndpointsDoNotGetWorkspaceInBody(): void
    {
        $c = $this->client([self::ok(['id' => 1])], ['workspaceId' => 4]);
        $c->tags->create(['name' => 'x']);
        self::assertSame(['name' => 'x'], $this->jsonBody());
    }

    public function testEmptyBodyIsEncodedAsObject(): void
    {
        $c = $this->client([self::ok(['id' => 1]), self::ok(['id' => 2])]);
        $c->workflows->trigger(12);
        $c->appointments->cancel(5);

        self::assertSame('{}', $this->body(0));
        self::assertSame('{}', $this->body(1));
        self::assertSame('{}', OmnifoxClient::encodeJson([]));
        self::assertSame('{"a":[]}', OmnifoxClient::encodeJson(['a' => []]));
        self::assertSame('{"a":{}}', OmnifoxClient::encodeJson(['a' => new \stdClass()]));
        self::assertSame('{"n":1.0,"s":"ñ/"}', OmnifoxClient::encodeJson(['n' => 1.0, 's' => 'ñ/']));
    }

    public function testNoBodyMeansNoContentType(): void
    {
        $c = $this->client([self::ok(null)]);
        $c->conversations->reopen(3);
        self::assertSame('', $this->body());
        self::assertFalse($this->request()->hasHeader('Content-Type'));
    }

    public function testQueryBooleansAndArrays(): void
    {
        $c = $this->client([self::ok([])]);
        $c->boards->list(['include_archived' => true, 'skip' => null, 'ids' => [1, 2], 'off' => false]);
        $q = $this->query();
        self::assertSame('1', $q['include_archived']);
        self::assertSame('0', $q['off']);
        self::assertSame(['1', '2'], $q['ids']);
        self::assertArrayNotHasKey('skip', $q);
    }

    public function testIdempotencyKeyAndExtraHeaders(): void
    {
        $c = $this->client([self::ok(['id' => 1]), self::ok(['id' => 2])]);
        $c->contacts->create(['display_name' => 'A'], ['idempotencyKey' => 'k-1', 'headers' => ['X-Trace' => 't']]);
        $c->contacts->create(['display_name' => 'B'], new \Omnifox\RequestOptions(idempotencyKey: 'k-2'));
        self::assertSame('k-1', $this->request(0)->getHeaderLine('Idempotency-Key'));
        self::assertSame('t', $this->request(0)->getHeaderLine('X-Trace'));
        self::assertSame('k-2', $this->request(1)->getHeaderLine('Idempotency-Key'));
    }

    public function testRawRequestEscapeHatch(): void
    {
        $c = $this->client([self::json(['items' => [1], 'pagination' => ['has_more' => false]]), self::ok(['x' => 1])]);
        self::assertSame(['items' => [1], 'pagination' => ['has_more' => false]], $c->request('GET', 'whatever'));
        self::assertSame(['x' => 1], $c->request('get', '/other'));
        self::assertSame('/api/v1/whatever', $this->request(0)->getUri()->getPath());
    }

    /** @return iterable<string, array{int, class-string}> */
    public static function statusMap(): iterable
    {
        yield '401' => [401, AuthenticationException::class];
        yield '403' => [403, PermissionDeniedException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, ValidationException::class];
    }

    /** @dataProvider statusMap */
    public function testTypedExceptions(int $status, string $class): void
    {
        $c = $this->client([self::json(['code' => $status * 100, 'message' => 'nope', 'details' => null], $status, ['X-Request-Id' => 'req-1'])]);
        try {
            $c->tags->get(1);
            self::fail('expected exception');
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertInstanceOf(OmnifoxException::class, $e);
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($status * 100, $e->getErrorCode());
            self::assertSame('nope', $e->getMessage());
            self::assertSame('req-1', $e->getRequestId());
        }
    }

    public function testValidationExceptionExposesFieldErrors(): void
    {
        $c = $this->client([self::json([
            'code' => 42200, 'message' => 'The display name field is required.',
            'details' => ['display_name' => ['The display name field is required.']],
        ], 422)]);
        try {
            $c->contacts->create([]);
            self::fail('expected exception');
        } catch (ValidationException $e) {
            self::assertSame(['display_name' => ['The display name field is required.']], $e->getFieldErrors());
        }
    }

    public function testLaravelStyleErrorBody(): void
    {
        $c = $this->client([self::json(['message' => 'Invalid', 'errors' => ['email' => ['bad']]], 422)]);
        try {
            $c->contacts->create(['display_name' => 'x']);
            self::fail('expected exception');
        } catch (ValidationException $e) {
            self::assertSame(42200, $e->getErrorCode());
            self::assertSame(['email' => ['bad']], $e->getFieldErrors());
        }
    }

    public function testRetriesOn503ThenSucceeds(): void
    {
        $c = $this->client([new Response(503), new Response(502), self::ok(['id' => 1])]);
        self::assertSame(['id' => 1], $c->tags->get(1));
        self::assertCount(3, $this->history);
        self::assertCount(2, $this->sleeps);
    }

    public function testRetryBodyIsResent(): void
    {
        $c = $this->client([new Response(500), self::ok(['id' => 1])]);
        $c->tags->create(['name' => 'x']);
        self::assertSame('{"name":"x"}', $this->body(1));
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $c = $this->client([new Response(500), new Response(500)], ['maxRetries' => 1]);
        $this->expectException(ServerException::class);
        $c->tags->get(1);
    }

    public function testRateLimitHonoursRetryAfter(): void
    {
        $c = $this->client([self::json(['code' => 42900, 'message' => 'slow down'], 429, ['Retry-After' => '2']), self::ok([])]);
        $c->tags->listPage();
        self::assertSame([2.0], $this->sleeps);
    }

    public function testRateLimitExceptionCarriesRetryAfter(): void
    {
        $c = $this->client([self::json(['message' => 'slow'], 429, ['Retry-After' => '7'])], ['maxRetries' => 0]);
        try {
            $c->tags->listPage();
            self::fail('expected exception');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->getRetryAfter());
        }
    }

    public function testDoesNotRetry4xx(): void
    {
        $c = $this->client([self::json(['message' => 'x'], 404)]);
        try {
            $c->tags->get(1);
        } catch (NotFoundException) {
        }
        self::assertCount(1, $this->history);
        self::assertSame([], $this->sleeps);
    }

    public function testNetworkErrorsAreRetriedThenWrapped(): void
    {
        $req = new Request('GET', 'https://api.test');
        $c = $this->client([new ConnectException('refused', $req), new ConnectException('refused', $req)], ['maxRetries' => 1]);
        try {
            $c->tags->get(1);
            self::fail('expected exception');
        } catch (NetworkException $e) {
            self::assertInstanceOf(OmnifoxException::class, $e);
        }
        self::assertCount(1, $this->sleeps);
    }

    public function testBinaryPdf(): void
    {
        $c = $this->client([new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="COT-0001.pdf"',
        ], "%PDF-1.7\x00\xff")], ['workspaceId' => 1]);
        $pdf = $c->quotes->pdf(9);

        self::assertSame("%PDF-1.7\x00\xff", $pdf->data);
        self::assertSame('application/pdf', $pdf->contentType);
        self::assertSame('COT-0001.pdf', $pdf->fileName);
        self::assertSame('/api/v1/quotes/9/pdf', $this->request()->getUri()->getPath());
        self::assertSame('1', $this->query()['workspace_id']);
    }

    public function testBinaryErrorIsTyped(): void
    {
        $c = $this->client([self::json(['message' => 'Quote not found'], 404)]);
        $this->expectException(NotFoundException::class);
        $c->quotes->pdf(9);
    }
}
