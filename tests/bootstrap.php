<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Support\Facades\Facade;

$container = new Illuminate\Container\Container;
$container['path'] = __DIR__;
$container['config'] = array('app.locale'=>'en');

$providers = array(
    'Illuminate\Events\EventServiceProvider',
    'Illuminate\Filesystem\FilesystemServiceProvider',
    'Illuminate\Translation\TranslationServiceProvider',
    'Illuminate\Validation\ValidationServiceProvider',
);

// Register all providers
$registered = array();
foreach ($providers as $provider) {
  $instance = new $provider($container);
  $instance->register();
  $registered[] = $instance;
}

// Then boot them
foreach ($registered as $instance) {
  $instance->boot();
}

Facade::setFacadeApplication($container);
