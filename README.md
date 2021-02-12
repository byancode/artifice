# Artifice Laravel

## Installation

You can install the package via composer:

``` bash
composer require byancode/artifice
```

### Register (for Laravel > 6.0)

Register the service provider in `config/app.php`

``` php
Byancode\Artifice\Providers\ArtificeProvider::class,
```

## Usage

``` bash 
php artisan artifice:build --force="observe, trait"
```
