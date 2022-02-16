<?php

declare(strict_types=1);

/**
 * Parser for casting Statements to SQL fragements
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @since 0.0.2
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @package Gin0115\Pixie
 * @subpackage Parser
 */

namespace Pixie\Parser;

use Pixie\Connection;
use Pixie\WpdbHandler;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\Statement\TableStatement;
use Pixie\Statement\SelectStatement;
use Pixie\JSON\JsonExpressionFactory;
use Pixie\Statement\OrderByStatement;

class StatementParser
{
    protected const TEMPLATE_AS = "%s AS %s";
    protected const TEMPLATE_ORDERBY = "ORDER BY %s";

    /**
     * @var Connection
     */
    protected $connection;

    /** @var Normalizer */
    protected $normalizer;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->normalizer = $this->createNormalizer($connection);
    }

    /**
     * Creates a full populated instance of the normalizer
     *
     * @param Connection $connection
     * @return Normalizer
     */
    private function createNormalizer($connection): Normalizer
    {
        // Create the table prefixer.
        $adapterConfig = $connection->getAdapterConfig();
        $prefix = isset($adapterConfig[Connection::PREFIX])
            ? $adapterConfig[Connection::PREFIX]
            : null;

        return new Normalizer(
            new WpdbHandler($connection),
            new TablePrefixer($prefix),
            new JsonSelectorHandler(),
            new JsonExpressionFactory($connection)
        );
    }

    /**
     * Normalizes and Parsers an array of SelectStatements.
     *
     * @param SelectStatement[]|mixed[] $select
     * @return string
     */
    public function parseSelect(array $select): string
    {
        // Remove any none SelectStatements
        $select = array_filter($select, function ($statement): bool {
            return is_a($statement, SelectStatement::class);
        });

        // Cast to string, with or without alias,
        $select = array_map(function (SelectStatement $value): string {
            $alias = $value->getAlias();
            $value = $this->normalizer->selectStatement($value);
            return  null === $alias
                    ? $value
                    : sprintf(self::TEMPLATE_AS, $value, $alias);
        }, $select);

        return join(', ', $select);
    }

    /**
     * Normalizes and Parsers an array of TableStatements
     *
     * @param TableStatement[] $tables
     * @return string
     */
    public function parseTable(array $tables): string
    {
        // Remove any none TableStatement
        $tables = array_filter($tables, function ($statement): bool {
            return is_a($statement, TableStatement::class);
        });

        $tables = array_map(function (TableStatement $table): string {
            return $this->normalizer->tableStatement($table);
        }, $tables);

        return join(', ', $tables);
    }

    /**
     * Normalizes and Parsers an array of OrderByStatements.
     *
     * @param OrderByStatement[]|mixed[] $orderBy
     * @return string
     */
    public function parseOrderBy(array $orderBy): string
    {
        // Remove any none OrderByStatements
        $orderBy = array_filter($orderBy, function ($statement): bool {
            return is_a($statement, OrderByStatement::class);
        });

        // Cast to string, with or without alias,
        $orderBy = array_map(function (OrderByStatement $value): string {
            return  sprintf(
                "%s %s",
                $this->normalizer->orderByStatement($value),
                $value->getDirection()
            );
        }, $orderBy);

        return 0 === count($orderBy)
            ? ''
            : sprintf(self::TEMPLATE_ORDERBY, join(', ', $orderBy));
    }
}
