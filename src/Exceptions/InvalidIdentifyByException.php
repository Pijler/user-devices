<?php

namespace Pijler\UserDevices\Exceptions;

use InvalidArgumentException;
use Pijler\UserDevices\DTO\DeviceContext;

class InvalidIdentifyByException extends InvalidArgumentException
{
    /**
     * Create a new exception instance.
     */
    public static function make(): self
    {
        $attributes = implode(', ', DeviceContext::IDENTIFIABLE_ATTRIBUTES);

        return new self(sprintf('The [user-devices.identify_by] option must be "cookie" or a list of: %s.', $attributes));
    }
}
