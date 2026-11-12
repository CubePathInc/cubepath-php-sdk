<?php

namespace Cubepath;

/**
 * How a baremetal server is named in a request: its uuid.
 *
 * The "id" of a server is its uuid, and "uuid" carries the same value. Every
 * /baremetal/{uuid} route takes it, and so do the fields that name a server inside other
 * objects (route next hop, BGP peer, load balancer target, alert target, floating IP
 * assignment). A server number is not a reference any more.
 *
 * Only for baremetal servers. VPS and network helpers keep their own integer rules.
 */
final class BaremetalRef
{
    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';

    private function __construct()
    {
    }

    /**
     * Validate a server reference and return it ready for a URL.
     *
     * @param mixed $ref The server uuid, in any case
     * @return string The uuid in lower case
     * @throws \InvalidArgumentException when it is not a uuid (a server number included)
     */
    public static function normalize($ref): string
    {
        if (is_string($ref) && preg_match(self::UUID_PATTERN, $ref) === 1) {
            return strtolower($ref);
        }
        throw new \InvalidArgumentException(
            'Baremetal reference must be a uuid (8-4-4-4-12 hex), got ' . self::describe($ref)
        );
    }

    /**
     * Validate the target of a route, BGP peer, load balancer target or alert when its type is
     * "baremetal" (the uuid, returned in lower case). Targets of other types are returned as given.
     *
     * @param mixed $type
     * @param mixed $target
     * @return mixed
     */
    public static function target($type, $target)
    {
        return $type === 'baremetal' ? self::normalize($target) : $target;
    }

    /**
     * Whether a server object from the API (/projects/ or GET /baremetal/) is the one named by $ref.
     *
     * @param array  $server
     * @param string $ref    A value returned by normalize()
     */
    public static function matches(array $server, string $ref): bool
    {
        return isset($server['id']) && is_string($server['id']) && strtolower($server['id']) === $ref;
    }

    private static function describe($value): string
    {
        if (is_string($value)) {
            return '"' . (strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value) . '"';
        }
        return gettype($value);
    }
}
