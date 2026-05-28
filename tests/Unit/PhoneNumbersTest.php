<?php

namespace Tests\Unit;

use App\Support\PhoneNumbers;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneNumbersTest extends TestCase
{
    public function test_normalize_tanzania_with_leading_zero(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('0712345678'));
    }

    public function test_normalize_tanzania_with_plus_prefix(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('+255712345678'));
    }

    public function test_normalize_tanzania_with_full_prefix(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('255712345678'));
    }

    public function test_normalize_tanzania_with_nine_digits(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('712345678'));
    }

    public function test_normalize_tanzania_with_double_zero_prefix(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('00255712345678'));
    }

    public function test_normalize_tanzania_with_2550_prefix(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('2550712345678'));
    }

    public function test_normalize_tanzania_with_spaces_and_dashes(): void
    {
        $this->assertSame('255712345678', PhoneNumbers::normalizeTanzania('+255 712-345-678'));
    }

    public function test_normalize_tanzania_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhoneNumbers::normalizeTanzania('');
    }

    public function test_normalize_tanzania_rejects_invalid_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhoneNumbers::normalizeTanzania('12345');
    }

    public function test_normalize_tanzania_rejects_null(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhoneNumbers::normalizeTanzania(null);
    }
}
