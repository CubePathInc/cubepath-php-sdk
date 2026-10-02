<?php

namespace Cubepath;

/**
 * Verification of Object Storage event webhook deliveries.
 */
class Webhooks
{
    /**
     * Verify an Object Storage event webhook delivery.
     *
     * $secret is the signing secret of the destination, $timestamp the CubePath-Timestamp header
     * (unix seconds), $body the raw request body (verify before decoding it) and $header the
     * CubePath-Signature header: one or more "v1=<hex>" values (several during a secret rotation),
     * each the HMAC-SHA256 of timestamp + "." + body. Deliveries whose timestamp is further than
     * $tolerance seconds (default 300) from now are rejected; 0 skips that check.
     *
     * @throws StorageEventSignatureException when the delivery is not valid
     */
    public static function verifyStorageEventSignature(
        string $secret,
        string $timestamp,
        string $body,
        string $header,
        int $tolerance = 300,
        ?int $now = null
    ): void {
        $ts = trim($timestamp);
        if (!ctype_digit($ts)) {
            throw new StorageEventSignatureException('Invalid timestamp');
        }
        $now = $now ?? time();
        if ($tolerance > 0 && abs($now - (int) $ts) > $tolerance) {
            throw new StorageEventSignatureException('Timestamp outside the tolerance');
        }
        $expected = hash_hmac('sha256', $ts . '.' . $body, $secret);
        foreach (preg_split('/[,\s]+/', $header, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (strncmp($part, 'v1=', 3) !== 0) {
                continue;
            }
            $value = strtolower(substr($part, 3));
            if (preg_match('/^[0-9a-f]{64}$/', $value) && hash_equals($expected, $value)) {
                return;
            }
        }
        throw new StorageEventSignatureException('Storage event signature mismatch');
    }
}
