<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Doubles;

use Psr\Log\AbstractLogger;

/**
 * Keeps every log record instead of writing it, at every level.
 *
 * The production logger is a NullLogger unless the merchant switched debug on, so a test that
 * wants to prove "this never reaches the log" would otherwise pass for the wrong reason. Swapping
 * this in is the debug-on case: whatever the plugin would have written to disk ends up here.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private array $records = [];

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function log($level, $message, array $context = [])
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    public function records(?string $level = null): array
    {
        if ($level === null) {
            return $this->records;
        }

        return array_values(array_filter($this->records, static function (array $record) use ($level): bool {
            return $record['level'] === $level;
        }));
    }

    /**
     * Everything that was logged, messages and contexts, as one searchable string.
     */
    public function dump(): string
    {
        return implode("\n", array_map(static function (array $record): string {
            return $record['level'] . ' ' . $record['message'] . ' ' . (string) json_encode($record['context']);
        }, $this->records));
    }

    public function reset(): void
    {
        $this->records = [];
    }
}
