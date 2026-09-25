<?php

declare (strict_types=1);
namespace Mollie;

// Data for the Express Component that differs by wallet, surface or mode.
//
// A table, not code: nothing here does I/O at load. Later specs add what they need (the session
// reuse margin, the anonymous work budget, the abandon grace) next to the rows that use them.
/**
 * @var array{
 *     wallets: array<string, array{gatewayId: string, mollieMethod: string, needsHttps: bool}>,
 *     surfaces: array<int, string>,
 *     allowedModes: array<int, string>,
 * } $express
 */
$express = [
    // Filled in with the blocks-express-button spec, when the first wallet button exists.
    'wallets' => [],
    'surfaces' => ['checkout'],
    // Sessions may have no test mode (REQ-H4 is unanswered), so live only until it is answered.
    'allowedModes' => ['live'],
];
return $express;
