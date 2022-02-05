# Pixie WPDB Query Builder for WordPress


![alt text](https://img.shields.io/badge/Current_Version-0.0.1-yellow.svg?style=flat " ") 
[![Open Source Love](https://badges.frapsoft.com/os/mit/mit.svg?v=102)]()
![](https://github.com/gin0115/pixie-wpdb/workflows/GitHub_CI/badge.svg " ")
[![codecov](https://codecov.io/gh/gin0115/pixie-wpdb/branch/master/graph/badge.svg?token=4yEceIaSFP)](https://codecov.io/gh/gin0115/pixie-wpdb)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gin0115/pixie-wpdb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gin0115/pixie-wpdb/?branch=master)


A lightweight, expressive, query builder for WordPRess it can also be referred as a Database Abstraction Layer. Pixie WPDB supports WPDB ONLY and it takes care of query sanitization, table prefixing and many other things with a unified API.

> **Pixie WPDB** is an adaption of `pixie` originally written by [usmanhalalit](https://github.com/usmanhalalit). [Pixie is not longer under active development ](https://github.com/usmanhalalit/pixie)

## Requirements
 - PHP 7.1+
 - MySql 5.7+ or MariaDB 10.2+

> Tested all combinations of PHP 7.1, 7.2, 7.3, 7.4, 8.0, 8.1 with MySql 5.7 & MariaBD 10.2, 10.3, 10.4, 10.5, 10.6, 10.7


It has some advanced features like:

 - Query Events
 - Nested Criteria
 - Sub Queries
 - Multiple Database Connections.

Additional features added to this version of Pixie
 - JSON Support (Select, Where)
 - Aggregation methods (Min, Max, Average & Sum)
 - Custom Model Hydration
 - Date based Where (Month, Day, Year & Date)

The syntax is quite similar to Laravel's query builder.

## Example
```PHP
// Make sure you have Composer's autoload file included
require 'vendor/autoload.php';

// Create a connection, once only.
$config = [
    Connection::PREFIX => 'cb_', // Table prefix, optional
];

// Get the current (gloabl) WPDB instance, or create a custom one 
global $wpdb;

// Give this instance its own custom class alias (for calling statically);
$alias = 'QB';

new \Pixie\Connection($wpdb, $config, $alias);
```

**Simple Query:**

The query below returns the row where id = 3, null if no rows.
```PHP
$row = QB::table('my_table')->find(3);
```

**Full Queries:**

```PHP
$query = QB::table('my_table')->where('name', '=', 'Sana');

// Get result
$query->get();
```

**Query Events:**

After the code below, every time a select query occurs on `users` table, it will add this where criteria, so banned users don't get access.

```PHP
QB::registerEvent('before-select', 'users', function($qb)
{
    $qb->where('status', '!=', 'banned');
});
```


There are many advanced options which are documented below. Sold? Let's install.

## Installation

Pixie uses [Composer](http://getcomposer.org/doc/00-intro.md#installation-nix) to make things easy.

To install run `composer require gin0115/pixie-wpdb`

Library on [Packagist](https://packagist.org/packages/gin0115/pixie-wpdb).

## Full Usage API
For the full usage docs, please see the wiki.

## Changelog
* 0.0.1 - Various external and interal changes made to the initial code written by [Muhammad Usman](http://usman.it/)
___
If you find any typo then please edit and send a pull request.

&copy; 2022 [Glynn Quelch](https://www.github.com/gin0115). Licensed under MIT license.
