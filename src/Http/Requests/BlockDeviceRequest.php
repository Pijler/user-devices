<?php

namespace UserDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
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

        rescue(function () {
            $user = $this->getDevice()?->user;

            $sessionId = $this->getDevice()?->session_id;

            if (filled($user) && filled($sessionId)) {
                Session::getHandler()->destroy($sessionId);

                // Cycle remember token so the blocked device
                // cannot re-authenticate via remember me cookie
                tap($user, fn ($user) => $user->setRememberToken(Str::random(60)))->save();
            }
        });
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
