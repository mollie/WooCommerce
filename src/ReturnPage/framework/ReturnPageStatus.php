<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Status enumeration
 */
class ReturnPageStatus
{
    public const SUCCESS = 'success';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const TIMEOUT = 'timeout';
    public const ERROR = 'error';

    /**
     * @var string
     */
    public $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function SUCCESS(): self
    {
        return new self(self::SUCCESS);
    }

    public static function PENDING(): self
    {
        return new self(self::PENDING);
    }

    public static function FAILED(): self
    {
        return new self(self::FAILED);
    }

    public static function CANCELLED(): self
    {
        return new self(self::CANCELLED);
    }

    public static function TIMEOUT(): self
    {
        return new self(self::TIMEOUT);
    }

    public static function ERROR(): self
    {
        return new self(self::ERROR);
    }

    /**
     * Get all valid status values
     *
     * @return array
     */
    public static function getValidValues(): array
    {
        return [
            self::SUCCESS,
            self::PENDING,
            self::FAILED,
            self::CANCELLED,
            self::TIMEOUT,
            self::ERROR,
        ];
    }

    /**
     * Create instance from string value
     *
     * @param string $value
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function from(string $value): self
    {
        if (!in_array($value, self::getValidValues(), true)) {
            throw new \InvalidArgumentException("Invalid status value: {$value}");
        }

        return new self($value);
    }

    /**
     * Check if this status equals another
     *
     * @param ReturnPageStatus $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get string representation
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
