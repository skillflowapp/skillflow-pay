<?php

namespace Tests\Unit;

use App\Services\MalipoPayClient;
use PHPUnit\Framework\TestCase;

class MalipoPayClientTest extends TestCase
{
    private MalipoPayClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new MalipoPayClient;
    }

    public function test_extract_reference_from_top_level(): void
    {
        $data = ['reference' => 'ref-123'];
        $this->assertSame('ref-123', $this->client->extractReference($data));
    }

    public function test_extract_reference_from_nested_data(): void
    {
        $data = ['data' => ['reference' => 'ref-456']];
        $this->assertSame('ref-456', $this->client->extractReference($data));
    }

    public function test_extract_reference_from_payment_object(): void
    {
        $data = ['payment' => ['reference' => 'ref-789']];
        $this->assertSame('ref-789', $this->client->extractReference($data));
    }

    public function test_extract_reference_returns_null_when_missing(): void
    {
        $this->assertNull($this->client->extractReference([]));
    }

    public function test_extract_status_from_top_level(): void
    {
        $data = ['status' => 'completed'];
        $this->assertSame('completed', $this->client->extractStatus($data));
    }

    public function test_extract_status_from_nested_data(): void
    {
        $data = ['data' => ['status' => 'pending']];
        $this->assertSame('pending', $this->client->extractStatus($data));
    }

    public function test_extract_external_reference(): void
    {
        $data = ['external_reference' => 'ext-1'];
        $this->assertSame('ext-1', $this->client->extractExternalReference($data));
    }

    public function test_extract_link(): void
    {
        $data = ['data' => ['link' => 'https://pay.example.com/123']];
        $this->assertSame('https://pay.example.com/123', $this->client->extractLink($data));
    }

    public function test_extract_amount(): void
    {
        $data = ['amount' => 5000];
        $this->assertSame(5000, $this->client->extractAmount($data));
    }

    public function test_extract_amount_from_nested(): void
    {
        $data = ['data' => ['amount' => '10000']];
        $this->assertSame(10000, $this->client->extractAmount($data));
    }

    public function test_extract_amount_returns_null_for_invalid(): void
    {
        $this->assertNull($this->client->extractAmount(['amount' => 'not-a-number']));
    }

    public function test_extract_currency(): void
    {
        $data = ['currency' => 'TZS'];
        $this->assertSame('TZS', $this->client->extractCurrency($data));
    }
}
