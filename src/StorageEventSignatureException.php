<?php

namespace Cubepath;

/**
 * Thrown by Webhooks::verifyStorageEventSignature() when a delivery must not be trusted.
 */
class StorageEventSignatureException extends \RuntimeException
{
}
