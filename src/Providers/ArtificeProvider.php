<?php

namespace Byancode\Artifice\Providers;

use Blueprint\BlueprintServiceProvider;
use Byancode\Artifice\Commands\BuildCommand;
use Illuminate\Contracts\Support\DeferrableProvider;

class ArtificeProvider extends BlueprintServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
        $this->app->bind('command.artifice.build', function ($app) {
            return new BuildCommand($app['files']);
        });

        $this->commands([
            BuildCommand::class,
        ]);
    }

}