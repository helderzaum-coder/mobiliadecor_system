<?php

namespace Tests\Unit;

use App\Jobs\SyncEstoqueBlingJob;
use App\Services\Bling\BlingClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SyncEstoqueBlingJobTest extends TestCase
{
    public function test_saida_usa_endpoint_de_movimentacao_e_tipo_saida(): void
    {
        $client = $this->fakeClient();
        $job = new SyncEstoqueBlingJob('SKU-1', 2, 'Saida (-2)', 'S');

        $this->assertSame(
            ['success' => true, 'http_code' => 201, 'body' => []],
            $this->invokePrivate($job, 'movimentarEstoque', [$client, 123, 456])
        );
        $this->assertSame('/estoques/movimentacoes', $client->calls[0]['path']);
        $this->assertSame('S', $client->calls[0]['body']['tipo']);
        $this->assertArrayNotHasKey('operacao', $client->calls[0]['body']);
    }

    public function test_balanco_continua_usando_operacao_b_no_endpoint_legado(): void
    {
        $client = $this->fakeClient();
        $job = new SyncEstoqueBlingJob('SKU-1', 8, 'Balanco (=8)', 'B');

        $this->assertSame(
            ['success' => true, 'http_code' => 201, 'body' => []],
            $this->invokePrivate($job, 'balancoEstoque', [$client, 123, 456])
        );
        $this->assertSame('/estoques', $client->calls[0]['path']);
        $this->assertSame('B', $client->calls[0]['body']['operacao']);
        $this->assertSame(8, $client->calls[0]['body']['quantidade']);
    }

    private function fakeClient(): BlingClient
    {
        return new class extends BlingClient {
            public array $calls = [];

            public function __construct() {}

            public function post(string $path, array $query = [], array $body = []): array
            {
                $this->calls[] = compact('path', 'query', 'body');

                return ['success' => true, 'http_code' => 201, 'body' => []];
            }
        };
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
