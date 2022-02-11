# Pixie WPDB Query Builder for WordPress

[![GitHub issues](https://img.shields.io/github/release/gin0115/pixie-wpdb)](https://github.com/gin0115/pixie-wpdb/releases)
![](https://github.com/gin0115/pixie-wpdb/workflows/GitHub_CI/badge.svg " ")
[![codecov](https://codecov.io/gh/gin0115/pixie-wpdb/branch/master/graph/badge.svg?token=4yEceIaSFP)](https://codecov.io/gh/gin0115/pixie-wpdb)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gin0115/pixie-wpdb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gin0115/pixie-wpdb/?branch=master)
[![GitHub issues](https://img.shields.io/github/issues/gin0115/pixie-wpdb)](https://github.com/gin0115/pixie-wpdb/issues)
[![Open Source Love](https://badges.frapsoft.com/os/mit/mit.svg?v=102)]()

An expressive, query builder for WordPRess it can also be referred as a Database Abstraction Layer. Pixie WPDB supports WPDB ONLY and it takes care of query sanitization, table prefixing and many other things with a unified API.

> **Pixie WPDB** is an adaption of `pixie` originally written by [usmanhalalit](https://github.com/usmanhalalit). [Pixie](https://github.com/usmanhalalit/pixie) is no longer under active development.

# Features
* [Fluent API](https://github.com/gin0115/pixie-wpdb/wiki/Query%20Methods)
* [Nested Queries](https://github.com/gin0115/pixie-wpdb/wiki/Sub%20&%20Nested%20Queries)
* [Multiple Connections](https://github.com/gin0115/pixie-wpdb/wiki/Home#setup-connection)
* [Sub Queries](https://github.com/gin0115/pixie-wpdb/wiki/Sub%20&%20Nested%20Queries)
* [JSON Support](https://github.com/gin0115/pixie-wpdb/wiki/Json%20Methods)
* [Model Hydration](https://github.com/gin0115/pixie-wpdb/wiki/Result%20Hydration)
* [Custom Alias Facade](https://github.com/gin0115/pixie-wpdb/wiki/Home#connection-alias)
* [Raw SQL Expressions](https://github.com/gin0115/pixie-wpdb/wiki/Bindings%20&%20Raw%20Expressions)
* [Value Type Binding](https://github.com/gin0115/pixie-wpdb/wiki/Bindings%20&%20Raw%20Expressions)
* [Transaction Support](https://github.com/gin0115/pixie-wpdb/wiki/Transactions)
* [Query Events](https://github.com/gin0115/pixie-wpdb/wiki/Query%20Events)

```php
$thing = QB::table('someTable')->where('something','=', 'something else')->first();
```

# Install

## Perquisites

* WordPress 5.7+ (tested upto 5.9)
* PHP 7.1+ (includes support for PHP8)
* MySql 5.7+ or MariaDB 10.2+
* Composer (optional)

## Using Composer

The easiest way to include Pixie in your project is to use [composer](http://getcomposer.org/doc/00-intro.md#installation-nix). 

```bash
composer require gin0115/pixie-wpdb
```

## Static Loader

If you are planning to just inlcude Pixie direct in your plugin, you can extract the `src` directory and add this to your `functions.php` or similar.

```php 
require_once '/path/to/src/loader.php'; 

```
> Each class is checked if already loaded, to avoid conflicts if used on multiple plugins.

# Setup Connection

If you are only planning on having a single connection, you will only need to configure the connection once. 

```php
# Basic setup

// Access the global WPDB or a custom instance for additional tables.
global $wpdb;

// Configure the builder and/or internal WPDB instance
$connection_config = [Connection::PREFIX => 'gin0115_'];

// Give a *single* Alias
$builder_alias = 'Gin0115\\DB';

new Connection( $wpdb, $connection_config, $builder_alias );
```

This would then give access to an instance of the QueryBuilder using this connection, via the alias defined `Gin0115\DB`

```php
$foos = Gin0115\DB::table('foo')->where('column', 'red')->get();
```

> Generated & executed query :: "SELECT * FROM gin0115_foo WHERE column = 'red'; "

## Connection Config

It is possible to configure the connection used by your instance of the query builder.

Values

| Key      | Constant | Value | Description |  
| ----------- | ----------- |----------- |----------- |
| prefix      | Connection:: PREFIX       | STRING | Custom table prefix (will ignore WPDB prefix)|
| use_wpdb_prefix   | Connection:: USE_WPDB_PREFIX        | BOOL | If true will use WPDB prefix and ignore custom prefix
| clone_wpdb      | Connection:: CLONE_WPDB       | BOOL | If true, will clone WPDB to not use reference to the instance (usually the $GLOBAL)|
| show_errors | Connection:: SHOW_ERRORS | BOOL | If set to true will configure WPDB to show/hide errors |
 

```php
$config = [
    Connection::PREFIX          => 'acme_',
    Connection::USE_WPDB_PREFIX => true,
    Connection::CLONE_WPDB      => true,
    Connection::SHOW_ERRORS     => false,
];
```

> [More details on the config](https://github.com/gin0115/pixie-wpdb/wiki#connection-config)

## Connection Alias

When you create a connection:

```PHP
new Connection($wpdb, $config, 'MyAlias');
```

`MyAlias` is the name for the class alias you want to use (like `MyAlias::table(...)` ), you can use whatever name (with Namespace also, `MyNamespace\\MyClass` ) you like or you may skip it if you don't need an alias. Alias gives you the ability to easily access the QueryBuilder class across your application.

# Usage

Once a connection is created, the builder can be accessed either directly using the Alias Facade or by creating an instance.

## Static Usage

The easiest way to use Pixie is to use the alias facade provided. This allows you to access a builder instance anywhere, much like WPDB. 

```php
// Create the connection early on.
$connection = new Connection($wpdb, $config, 'Alias');

// Insert some data to bar.
Alias::table('bar')->insert(['column'=>'value']);
```

## None Static Usage

When not using an alias you can instantiate the QueryBuilder handler separately, helpful for Dependency Injection and Testing.

```PHP
// Create connection and builder instance.
$connection = new Connection($wpdb, $config);
$qb = new QueryBuilderHandler($connection);

$query = $qb->table('my_table')->where('name', '=', 'Sana');
$results = $query->get();
```

`$connection` here is optional, if not given it will always associate itself to the first connection, but it can be useful when you have multiple database connections.

# Credits

This package began as a fork of [Pixie](https://github.com/usmanhalalit/pixie) originally written by [usmanhalalit](https://github.com/usmanhalalit)
A few features have been inspired by the [Pecee-pixie](https://github.com/skipperbent/pecee-pixie/) fork and continuation, especially the extended aggregate methods.


## Changelog
* 0.0.2 - 
    * Improvements to the `updateOrInsert()` method
    * Introduced `when()` to query builder
    * Expanded test cover for WP5.5 to WP5.9 
    * Removed container dependency.
* 0.0.1 - Various external and interal changes made to the initial code written by [Muhammad Usman](http://usman.it/)
___
If you find any typo then please edit and send a pull request.

&copy; 2022 [Glynn Quelch](https://www.github.com/gin0115). Licensed under MIT license.
