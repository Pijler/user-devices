<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Pijler\UserDevices\Models\UserDevice as BaseUserDevice;

class UserDevice extends BaseUserDevice
{
    use HasFactory;
}
