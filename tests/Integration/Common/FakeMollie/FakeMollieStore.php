<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\FakeMollie;

/**
 * Where the fake Mollie keeps its state.
 *
 * PHPUnit uses InMemoryStore: one process, state dies with the test. The Playwright environment
 * uses OptionStore, because there every call — the plugin creating a session, the browser stub
 * completing it, the test reading what was sent — is a separate HTTP request.
 */
interface FakeMollieStore
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array;

    /**
     * @param array<string, mixed> $state
     */
    public function save(array $state): void;
}
