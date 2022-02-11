<?php

namespace Pixie;

use wpdb;
use Exception;
use Viocon\Container;
use Pixie\AliasFacade;
use Pixie\EventHandler;
use Pixie\QueryBuilder\QueryBuilderHandler;

class Connection
{
    /** Config keys */
    public const CLONE_WPDB        = 'clone_wpdb';
    public const PREFIX            = 'prefix';
    public const SHOW_ERRORS       = 'show_errors';
    public const USE_WPDB_PREFIX   = 'use_wpdb_prefix';

    /**
     * @var string
     */
    protected $adapter;

    /**
     * @var array<string, mixed>
     */
    protected $adapterConfig;

    /**
     * @var wpdb
     */
    protected $dbInstance;

    /**
     * @var Connection|null
     */
    protected static $storedConnection;

    /**
     * @var EventHandler
     */
    protected $eventHandler;

    /**
     * @param wpdb                 $wpdb
     * @param array<string, mixed>  $adapterConfig
     * @param string|null           $alias
     */
    public function __construct(
        wpdb $wpdb,
        array $adapterConfig = [],
        ?string $alias = null
    ) {
        $this->setAdapterConfig($adapterConfig);
        $this->dbInstance = $this->configureWpdb($wpdb);

        $this->eventHandler = new EventHandler();
        if ($alias) {
            $this->createAlias($alias);
        }

        // Preserve the first database connection with a static property
        if (!static::$storedConnection) {
            static::$storedConnection = $this;
        }
    }

    /**
     * Configures the instance of WPDB based on adaptor config values.
     *
     * @param \wpdb $wpdb
     * @return \wpdb
     */
    protected function configureWpdb(wpdb $wpdb): wpdb
    {
        // Maybe clone instance.
        if (
            array_key_exists(self::CLONE_WPDB, $this->adapterConfig)
            && true === $this->adapterConfig[self::CLONE_WPDB]
        ) {
            $wpdb = clone $wpdb;
        }

        // Maybe set the prefix to WPDB's.
        if (
            array_key_exists(self::USE_WPDB_PREFIX, $this->adapterConfig)
            && 0 < \mb_strlen($this->adapterConfig[self::USE_WPDB_PREFIX])
        ) {
            $this->adapterConfig[self::PREFIX] = $wpdb->prefix;
        }

        // Maybe configure errors
        if (array_key_exists(self::SHOW_ERRORS, $this->adapterConfig)) {
            // Based in its value.
            if (true === (bool) $this->adapterConfig[self::SHOW_ERRORS]) {
                $wpdb->show_errors(true);
                $wpdb->suppress_errors(false);
            } else {
                $wpdb->show_errors(false);
                $wpdb->suppress_errors(true);
            }
        }

        return $wpdb;
    }

    /**
     * Create an easily accessible query builder alias
     *
     * @param string $alias
     */
    public function createAlias(string $alias): void
    {
        class_alias(AliasFacade::class, $alias);
        $builder = new QueryBuilderHandler($this);
        AliasFacade::setQueryBuilderInstance($builder);
    }

    /**
     * Returns an instance of Query Builder
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return new QueryBuilderHandler($this);
    }

    /**
     * @param wpdb $wpdb
     *
     * @return $this
     */
    public function setDbInstance(wpdb $wpdb)
    {
        $this->dbInstance = $wpdb;

        return $this;
    }

    /**
     * @return wpdb
     */
    public function getDbInstance()
    {
        return $this->dbInstance;
    }

    /**
     * @param array<string, mixed> $adapterConfig
     *
     * @return $this
     */
    public function setAdapterConfig(array $adapterConfig)
    {
        $this->adapterConfig = $adapterConfig;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdapterConfig()
    {
        return $this->adapterConfig;
    }

    /**
     * @return EventHandler
     */
    public function getEventHandler()
    {
        return $this->eventHandler;
    }

    /**
     * Returns the initial instance created.
     *
     * @return Connection
     *
     * @throws Exception If connection not already established
     */
    public static function getStoredConnection()
    {
        if (null === static::$storedConnection) {
            throw new Exception('No initial instance of Connection created');
        }

        return static::$storedConnection;
    }
}
