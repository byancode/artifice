<?php

namespace Byancode\Artifice\Provider;

use Blueprint\BlueprintServiceProvider;
use Byancode\Artifice\Commands\BuildCommand;
use Illuminate\Contracts\Support\DeferrableProvider;

class Service extends BlueprintServiceProvider implements DeferrableProvider
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

        $this->commands([
            BuildCommand::class,
        ]);
    }

}