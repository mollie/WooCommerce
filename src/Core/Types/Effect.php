<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

use InvalidArgumentException;

/**
 * One change a decision wants made to an order. Effects are values: the pure core returns them,
 * and only the effect interpreter applies them.
 */
final class Effect
{
    public const SET_META = 'set_meta';
    public const DELETE_META = 'delete_meta';
    public const SET_TRANSACTION_ID = 'set_transaction_id';
    public const SET_PAYMENT_METHOD = 'set_payment_method';
    public const SET_STATUS = 'set_status';
    public const ADD_NOTE = 'add_note';
    public const SET_ADDRESS = 'set_address';
    public const SET_CREATED_VIA = 'set_created_via';

    private const ADDRESS_TYPES = ['billing', 'shipping'];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private string $type,
        private array $data
    ) {
    }

    public static function setMeta(string $key, string $value): self
    {
        return new self(self::SET_META, ['key' => $key, 'value' => $value]);
    }

    public static function deleteMeta(string $key): self
    {
        return new self(self::DELETE_META, ['key' => $key]);
    }

    public static function setTransactionId(string $transactionId): self
    {
        return new self(self::SET_TRANSACTION_ID, ['transactionId' => $transactionId]);
    }

    public static function setPaymentMethod(string $gatewayId): self
    {
        return new self(self::SET_PAYMENT_METHOD, ['gatewayId' => $gatewayId]);
    }

    /**
     * Which flow created the order, as WooCommerce's created_via.
     */
    public static function setCreatedVia(string $createdVia): self
    {
        return new self(self::SET_CREATED_VIA, ['createdVia' => $createdVia]);
    }

    public static function setStatus(string $status): self
    {
        return new self(self::SET_STATUS, ['status' => $status]);
    }

    /**
     * @param array<string, string> $params Values interpolated into the message the interpreter renders.
     */
    public static function addNote(string $messageKey, array $params = []): self
    {
        return new self(self::ADD_NOTE, ['messageKey' => $messageKey, 'params' => $params]);
    }

    /**
     * @param string $addressType 'billing' or 'shipping'.
     * @param array<string, string> $fields In WooCommerce field names: first_name, address_1, postcode, …
     */
    public static function setAddress(string $addressType, array $fields): self
    {
        if (!in_array($addressType, self::ADDRESS_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown address type "%s".', $addressType));
        }

        return new self(self::SET_ADDRESS, ['addressType' => $addressType, 'fields' => $fields]);
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
