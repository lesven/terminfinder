<?php

use PHPUnit\Framework\TestCase;
use Terminfinder\Domain\ValueObject\LocalDate;

class LocalDateTest extends TestCase
{
    public function testValidDate()
    {
        $d = LocalDate::fromString('2026-01-19');
        $this->assertSame('2026-01-19', (string)$d);
    }

    public function testInvalidDateThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalDate::fromString('2026-02-30');
    }

    public function testIsValid()
    {
        $this->assertTrue(LocalDate::isValid('2026-01-01'));
        $this->assertFalse(LocalDate::isValid('not-a-date'));
        $this->assertFalse(LocalDate::isValid('2026-02-30'));
    }

    public function testEquals()
    {
        $a = LocalDate::fromString('2026-01-19');
        $b = LocalDate::fromString('2026-01-19');
        $c = LocalDate::fromString('2026-01-20');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
