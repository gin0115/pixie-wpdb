<?php

declare(strict_types=1);

/**
 * Unit tests for the AliasFacade
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\AliasFacade;
use Gin0115\WPUnit_Helpers\Objects;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestAliasFacade extends WP_UnitTestCase
{

    /** @testdox It should be possible to use the AliasFacade to call query builder methods using static methods and have the builder instance created for the first call. */
    public function testInstanceOfQueryBuilderShouldBeCreatedIfNotAlreadyDefined(): void
    {
        $connection = new Connection(new Logable_WPDB());
        $facade = new AliasFacade();

        $facade::raw('select * from foo');
        $protectedBuilder = Objects::get_property($facade, 'queryBuilderInstance');

        $this->assertInstanceOf(QueryBuilderHandler::class, $protectedBuilder);
        $this->assertSame($connection, $protectedBuilder->getConnection());
    }
}
