<?php

namespace UserDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use UserDevices\DeviceCreator;
use UserDevices\Models\UserDevice;

class BlockDeviceRequest extends FormRequest
{
    /**
     * The device instance for the given request.
     */
    protected ?UserDevice $device = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $device = $this->getDevice();

        if (blank($device)) {
            return false;
        }

        return hash_equals(sha1($device->getKey()), (string) $this->route('hash'));
    }

    /**
     * Fulfill the block device request.
     */
    public function fulfill(): void
    {
        $this->getDevice()?->block();
    }

    /**
     * Get the device instance for the request.
     */
    public function getDevice(): ?UserDevice
    {
        if (filled($this->device)) {
            return $this->device;
        }

        $modelClass = DeviceCreator::$userDeviceModel;

        return $this->device = resolve($modelClass)::find($this->route('id'));
    }
}
