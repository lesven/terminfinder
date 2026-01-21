<?php

namespace Terminfinder\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

final class LocalDate
{
    private DateTimeImmutable $date;

    private function __construct(DateTimeImmutable $date)
    {
        // keep as internal representation
        $this->date = $date;
    }

    public static function fromString(string $value): self
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!($d && $d->format('Y-m-d') === $value)) {
            throw new InvalidArgumentException("Invalid date format: {$value}");
        }

        return new self($d);
    }

    public static function isValid(string $value): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return (bool)($d && $d->format('Y-m-d') === $value);
    }

    public function __toString(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function equals(self $other): bool
    {
        return $this->date == $other->date;
    }

    public function toDateTimeImmutable(): DateTimeImmutable
    {
        return $this->date;
    }
}
