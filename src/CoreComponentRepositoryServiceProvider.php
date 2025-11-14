<?php

namespace Ps4tek\CoreComponentRepository;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Ps4tek\CoreComponentRepository\Http\Middleware\CoreComponentGate;

class CoreComponentRepositoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->bound('router')) {
            /** @var Router $router */
            $router = $this->app['router'];

            $alias = config('core-component-repository.middleware.alias', 'core.component');
            $router->aliasMiddleware($alias, CoreComponentGate::class);

            foreach ((array) config('core-component-repository.middleware.groups', []) as $group) {
                $router->pushMiddlewareToGroup($group, CoreComponentGate::class);
            }
        }

        if (config('core-component-repository.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/license.php');
        }

        if (class_exists(Telescope::class)) {
            Telescope::filter(function (IncomingEntry $entry) {
                $content = $entry->content ?? [];
                $uri = $content['uri'] ?? $content['path'] ?? '';
                $command = $content['command'] ?? '';
                $message = $content['message'] ?? '';

                if (Str::contains($uri.$command.$message, ['core-component', '_core-component'])) {
                    return false;
                }

                return true;
            });
        }

        $this->app->booted(function () {
            CoreComponentRepository::initializeCache();
        });

        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'core-component-repository');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'core-component-repository');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('core-component-repository.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/core-component-repository'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/core-component-repository'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/core-component-repository'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'core-component-repository');

        // Register the main class to use with the facade
        $this->app->singleton('core-component-repository', function () {
            return new CoreComponentRepository;
        });
    }
}
