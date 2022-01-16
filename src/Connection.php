<?php

namespace Pixie;

use Exception;
use Pixie\AliasFacade;
use Pixie\EventHandler;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Viocon\Container;
use wpdb;

class Connection
{
    /**
     * @var Container
     */
    protected $container;

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
     * @param Container|null        $container
     */
    public function __construct(
        wpdb $wpdb,
        array $adapterConfig = [],
        ?string $alias = null,
        ?Container $container = null
    ) {
        $this->dbInstance = $wpdb;
        $this->setAdapterConfig($adapterConfig);

        $this->container    = $container ?? new Container();
        $this->eventHandler = $this->container->build(EventHandler::class);

        if ($alias) {
            $this->createAlias($alias);
        }

        // Preserve the first database connection with a static property
        if (!static::$storedConnection) {
            static::$storedConnection = $this;
        }
    }

    /**
     * Create an easily accessible query builder alias
     *
     * @param string $alias
     */
    public function createAlias(string $alias): void
    {
        class_alias(AliasFacade::class, $alias);
        $builder = $this->container->build(QueryBuilderHandler::class, [$this]);
        AliasFacade::setQueryBuilderInstance($builder);
    }

    /**
     * Returns an instance of Query Builder
     */
    public function getQueryBuilder(): QueryBuilderHandler
    {
        return $this->container->build(QueryBuilderHandler::class, [$this]);
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
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
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
