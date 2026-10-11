# Installation

This document explains how to install and start using the KusikusiCMS Models package with Laravel 13.

## Requirements
- Laravel 13
- PHP compatible with your Laravel version
- Composer

## Install

When published to Packagist:
```
composer require kusikusicms/models
```

Package discovery will register the service providers.

## Publish configuration (optional)
```
php artisan vendor:publish --tag=kusikusicms-config
```
This creates `config/kusikusicms/models.php`.

## Run migrations
The package auto-loads its migrations. Run:
```
php artisan migrate
```

## Verify installation
- `php artisan about` should list: "KusikusiCMS core models package".
- `config('kusikusicms.models.default_language')` should return `en` by default.
