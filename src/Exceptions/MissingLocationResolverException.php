<?php

namespace Pijler\UserDevices\Exceptions;

use LogicException;

class MissingLocationResolverException extends LogicException
{
    /**
     * Create a new exception instance.
     */
    public static function make(): self
    {
        return new self('The [user-devices.identify_by] option uses "location", which requires DeviceCreator::resolveLocationUsing().');
    }
}
