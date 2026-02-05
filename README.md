# 📱 Laravel User Devices

This package provides support for managing user devices in Laravel. Track login devices, detect new logins, send notifications, and allow users to block suspicious devices—useful for security monitoring, session management, and multi-device applications.

### 🧩 Features

- ✅ **Device tracking**: Automatic tracking of IP address and user agent
- ✅ **New login detection**: Identifies first-time logins from new devices
- ✅ **Email notifications**: Sends alerts when a new device logs in
- ✅ **Block device**: Signed links to block suspicious devices
- ✅ **Integrated middleware**: Protect routes from blocked devices
- ✅ **Model trait**: Simple Eloquent integration
- ✅ **Flexible configuration**: Custom models and callbacks

### 📦 Installation

You can install the package via Composer:

```bash
composer require pijler/user-devices
```

### 🗄️ Publishing Migrations

Publish the package migrations:

```bash
php artisan vendor:publish --tag=user-devices-migrations
```

Run the migrations:

```bash
php artisan migrate
```

### ⚙️ Configuration

#### Basic Configuration

The package works out-of-the-box, but you can customize the behavior:

```php
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use UserDevices\DeviceCreator;
use UserDevices\Notifications\NewLoginDeviceNotification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Use custom user model
        DeviceCreator::useUserModel(CustomUser::class);

        // Use custom user device model
        DeviceCreator::useUserDeviceModel(CustomUserDevice::class);

        // Customize user agent generation
        DeviceCreator::userAgentUsing(fn ($userAgent) => substr($userAgent, 0, 255));

        // Control when to send new login notifications (e.g. disable in local/staging)
        DeviceCreator::shouldSendNotificationUsing(function ($user, $device) {
            return ! app()->environment('local');
        });

        // Customize the notification email
        NewLoginDeviceNotification::toMailUsing(function ($notifiable, $device) {
            $expire = Config::get('auth.verification.expire', 60);

            $blockUrl = URL::temporarySignedRoute(
                name: 'user-devices.block',
                expiration: Carbon::now()->addMinutes($expire),
                parameters: [
                    'id' => $device->getKey(),
                    'hash' => sha1($device->getKey()),
                ],
            );

            return (new MailMessage)
                ->subject('New device detected')
                ->line('We detected a new login to your account.')
                ->action('Block device', $blockUrl);
        });

        // Customize the block device URL
        NewLoginDeviceNotification::createBlockUrlUsing(function ($device) {
            return URL::temporarySignedRoute(
                name: 'your-custom-route-name',
                expiration: Carbon::now()->addMinutes(120),
                parameters: [
                    'id' => $device->getKey(),
                    'hash' => sha1($device->getKey()),
                ],
            );
        });
    }
}
```

The block link expiration uses `auth.verification.expire` (default 60 minutes), same as Laravel's email verification.

### 🧠 Usage

#### 1. Using the HasUserDevices Trait

Add the `HasUserDevices` trait to your User model. Your User model must also use the `Notifiable` trait for notifications:

```php
use Illuminate\Notifications\Notifiable;
use UserDevices\Traits\HasUserDevices;

class User extends Authenticatable
{
    use HasUserDevices;
    use Notifiable;
}
```

#### 2. Saving User Devices

The package automatically saves user devices when the `Authenticated` event is fired (on every authenticated request). No manual setup required—just add the `HasUserDevices` trait to your User model.

To skip saving the device entirely for a request (e.g. in middleware or controller before authentication, or before impersonation such as `onceUsingId`):

```php
use UserDevices\DeviceCreator;

// Call before the user is authenticated for this request
DeviceCreator::ignoreListener();
```

To ignore only the new login notification (device is still saved, but no email is sent):

```php
use UserDevices\DeviceCreator;

// Call before the user is authenticated for this request
DeviceCreator::ignoreNotification();
```

To control notifications globally (e.g. disable in local/staging, or custom logic per user/device):

```php
use UserDevices\DeviceCreator;

// Disable notifications entirely
DeviceCreator::shouldSendNotificationUsing(fn () => false);

// Custom logic (e.g. skip for admins)
DeviceCreator::shouldSendNotificationUsing(fn ($user, $device) => ! $user->isAdmin());

// Only send in production
DeviceCreator::shouldSendNotificationUsing(fn ($user, $device) => app()->environment('production'));
```

This will:

- Create or update the device record (IP + user agent)
- Update last activity timestamp
- Send a notification email on **first login** from that device (unless `ignoreListener()` was called, or `ignoreNotification()` was called, or `shouldSendNotificationUsing()` returns false)

#### 3. Block Device Route

When a user receives the new login notification email, they can click a link to block the device. Register a route that handles this request. The route must be **signed** and named `user-devices.block`:

```php
use UserDevices\Http\Requests\BlockDeviceRequest;

Route::get('/devices/block/{id}/{hash}', function (BlockDeviceRequest $request) {
    $request->fulfill();

    return redirect()->route('home')->with('message', 'Device blocked successfully.');
})->middleware(['signed', 'throttle:6,1'])->name('user-devices.block');
```

You can use any path you prefer (e.g. `/user-devices/block/{id}/{hash}`) as long as the route is named `user-devices.block` and includes the `{id}` and `{hash}` parameters.

#### 4. Using the Middleware

The package includes middleware to block requests from devices the user has blocked:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.device'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

When a blocked device tries to access a protected route, the middleware returns `423 Locked`.

#### 5. Working with the UserDevice Model

```php
use UserDevices\Models\UserDevice;

// Get user's devices
$devices = $user->userDevices;

// Block a device
$device = UserDevice::find($id);
$device->block();

// Unblock a device
$device->unblock();

// Block by ID (static)
UserDevice::markAsBlocked($id);

// Unblock by ID (static)
UserDevice::markAsUnblocked($id);
```

#### 6. Sending Notifications Manually

```php
$user->sendNewLoginDeviceNotification($device);
```

### 🧩 API Reference

#### DeviceCreator

```php
// Configuration
DeviceCreator::useUserModel(string $model): void
DeviceCreator::useUserDeviceModel(string $model): void
DeviceCreator::userAgentUsing(Closure $callback): void
DeviceCreator::shouldSendNotificationUsing(Closure $callback): void  // (user, device) => bool

// Request context (call before authentication)
DeviceCreator::ignoreListener(): void     // Skip saving the device for the current request
DeviceCreator::ignoreNotification(): void // Skip the new login notification for the current request
```

#### UserDevice Model

```php
// Relationships
$device->user(): BelongsTo

// Actions
$device->block(): void
$device->unblock(): void

// Static methods
UserDevice::markAsBlocked(mixed $id): void
UserDevice::markAsUnblocked(mixed $id): void
```

#### HasUserDevices Trait

```php
// Methods available on model
$model->userDevices(): HasMany
$model->sendNewLoginDeviceNotification(UserDevice $device): void
```

#### NewLoginDeviceNotification

```php
// Configuration
NewLoginDeviceNotification::toMailUsing(Closure $callback): void
NewLoginDeviceNotification::createBlockUrlUsing(Closure $callback): void
```

#### BlockDeviceRequest

```php
// Methods
$request->fulfill(): void
$request->getDevice(): ?UserDevice
```

### 📝 License

Open-source under the [MIT license](LICENSE).

## 🚀 Thanks!
