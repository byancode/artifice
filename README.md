# Artifice Laravel

## Installation

You can install the package via composer:

``` bash
composer require byancode/artifice
```

### Register (for Laravel > 6.0)

Register the service provider in `config/app.php`

``` php
Byancode\Artifice\Provider\Service::class,
```

### Publish

Publish config file.

``` php
php artisan vendor:publish --provider="Byancode\Artifice\Provider\Service" --tag=blueprint-config
```

## Usage

``` bash 
php artisan artifice:build --force="observe, trait"
```
