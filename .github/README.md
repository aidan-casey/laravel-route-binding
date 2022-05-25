# Laravel Route Binding

This package is heavily influenced by the default [route binding](https://github.com/laravel/framework/blob/b8be411c27ae9f0ef822dab0c1e6c48beb3e06e1/src/Illuminate/Routing/ImplicitRouteBinding.php) available in Laravel. The primary difference being that this package extracts such functionality to a utility class that may be used throughout your application.

This package also resists utilizing the Laravel container to instantiate the object (though the Laravel container is used to resolve dependencies) so that this utility may be used in a Laravel service provider without causing a recursive dependency lookup.

# Install
To install, run:

```bash
composer install aidan-casey/laravel-route-binding
```

# Usage
To build a class with route parameters and return an instance of it, us the static `make` method. This method does accept an extra array of parameters if you wish to override any values.

```php
use AidanCasey\Laravel\RouteBinding\Binder;

// Binds route parameters to the construct of the referenced class.
$viewModel = Binder::make(IndexViewModel::class);

// Overrides the "user" parameter.
$viewModel = Binder::make(IndexViewModel::class, [
    'user' => 2,
]);
```

To bind route parameters to a specific method of a class, use the static `call` method. You may pass either an existing class instance or a class string to this method.

```php
use AidanCasey\Laravel\RouteBinding\Binder;

// Calls the "execute" method on "MyTestClass."
Binder::call(MyTestClass::class, 'execute');

// Overrides the "user" parameter.
Binder::call(MyTestClass::class, 'execute', [
    'user' => 2,
]);

// Calls the "execute" method on the existing instance.
$instance = new MyTestClass;
Binder::call($instance, 'execute');
```

# Performance
This package does make extensive use of reflection classes. It is recommended that you bind your result to the container so this only happens once. This can be done by using the `beforeResolving` method in a Laravel service provider. For example:

```php
use AidanCasey\Laravel\RouteBinding\Binder;
use Illuminate\Support\ServiceProvider;

class ServiceProvider extends ServiceProvider
{
    public function register(){
        $this->app->beforeResolving(MyClass::class, function ($class, $parameters, $app) {
            if ($app->has($class)) {
                return;
            }
            
            $app->bind($class, fn ($container) => Binder::make($class, $parameters));
        });
    }
}
```