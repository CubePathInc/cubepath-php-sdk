<?php

namespace Cubepath;

/**
 * How a baremetal server is named in a request: its number or its uuid.
 *
 * The public "id" of a server is a number and the API also returns a "uuid". Every
 * /baremetal/{ref} route accepts either one. The value is never cast to int: a uuid that
 * starts with a digit would otherwise turn into another server.
 *
 * Only for baremetal servers. VPS and network helpers keep their own integer rules.
 */
final class BaremetalRef
{
    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';
    private const NUMBER_PATTERN = '/\A[0-9]+\z/';

    private function __construct()
    {
    }

    /**
     * Validate a server reference and return it ready for a URL.
     *
     * @param mixed $ref A number (int or digits only) or a uuid (any case)
     * @return string The number as text, or the uuid in lower case
     * @throws \InvalidArgumentException when it is neither a number nor a uuid
     */
    public static function normalize($ref): string
    {
        if (is_int($ref) && $ref >= 0) {
            return (string) $ref;
        }
        if (is_string($ref)) {
            if (preg_match(self::NUMBER_PATTERN, $ref) === 1) {
                return $ref;
            }
            if (preg_match(self::UUID_PATTERN, $ref) === 1) {
                return strtolower($ref);
            }
        }
        throw new \InvalidArgumentException(
            'Baremetal reference must be a number or a uuid (8-4-4-4-12 hex), got ' . self::describe($ref)
        );
    }

    /**
     * Whether a server object from the API (/projects/ or GET /baremetal/) is the one named by $ref.
     * Matches "id" as text (a number or a numeric string both work) or "uuid" when present.
     *
     * @param array  $server
     * @param string $ref    A value returned by normalize()
     */
    public static function matches(array $server, string $ref): bool
    {
        if (preg_match(self::UUID_PATTERN, $ref) === 1) {
            return isset($server['uuid']) && is_string($server['uuid']) && strtolower($server['uuid']) === $ref;
        }
        if (!isset($server['id']) || !(is_int($server['id']) || is_string($server['id']))) {
            return false;
        }
        return self::stripZeros((string) $server['id']) === self::stripZeros($ref);
    }

    private static function stripZeros(string $number): string
    {
        return ltrim($number, '0');
    }

    private static function describe($value): string
    {
        if (is_string($value)) {
            return '"' . (strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value) . '"';
        }
        return gettype($value);
    }
}
