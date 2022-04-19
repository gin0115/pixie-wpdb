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
use Pixie\Parser\Criteria;
use Pixie\Parser\Normalizer;
use Pixie\Statement\Statement;
use Pixie\Parser\CriteriaBuilder;
use Pixie\Statement\JoinStatement;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\Statement\TableStatement;
use Pixie\Statement\WhereStatement;
use Pixie\Statement\HavingStatement;
use Pixie\Statement\SelectStatement;
use Pixie\JSON\JsonExpressionFactory;
use Pixie\Statement\GroupByStatement;
use Pixie\Statement\OrderByStatement;
use Pixie\Statement\StatementBuilder;

class StatementParser
{
    protected const TEMPLATE_AS = "%s AS %s";
    protected const TEMPLATE_ORDER_BY = "ORDER BY %s";
    protected const TEMPLATE_GROUP_BY = "GROUP BY %s";
    protected const TEMPLATE_LIMIT = "LIMIT %d";
    protected const TEMPLATE_OFFSET = "OFFSET %d";
    protected const TEMPLATE_JOIN = "%s JOIN %s ON %s";

    /**
     * @var Connection
     */
    protected $connection;

    /** @var Normalizer */
    protected $normalizer;

    public function __construct(Connection $connection, Normalizer $normalizer)
    {
        $this->connection = $connection;
        $this->normalizer = $normalizer;
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

    public function table(StatementBuilder $builder, bool $single = false): string
    {
        if (!$builder->has(Statement::TABLE)) {
            return '';
        }

        return true === $single
            ? $this->parseTable([$builder->getTable()[0]])
            : $this->parseTable($builder->getTable());
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

        $tables = array_map([$this->normalizer,'tableStatement'], $tables);
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
            : sprintf(self::TEMPLATE_ORDER_BY, join(', ', $orderBy));
    }

    /**
     * Normalizes and Parsers an array of GroupByStatements.
     *
     * @param GroupByStatement[]|mixed[] $orderBy
     * @return string
     */
    public function parseGroupBy(array $orderBy): string
    {
        // Remove any none GroupByStatements
        $orderBy = array_filter($orderBy, function ($statement): bool {
            return is_a($statement, GroupByStatement::class);
        });

        // Get the array of columns.
        $orderBy = array_map(function (GroupByStatement $value): string {
            return  $value->getField();
        }, $orderBy);

        return 0 === count($orderBy)
            ? ''
            : sprintf(self::TEMPLATE_GROUP_BY, join(', ', $orderBy));
    }

    /**
     * Parses a limit statement based on the passed value not being null.
     *
     * @param int|null $limit
     * @return string
     */
    public function parseLimit(?int $limit): string
    {
        return is_int($limit)
            ? \sprintf(self::TEMPLATE_LIMIT, $limit)
            : '';
    }

    /**
     * Parses a offset statement based on the passed value not being null.
     *
     * @param int|null $offset
     * @return string
     */
    public function parseOffset(?int $offset): string
    {
        return is_int($offset)
            ? \sprintf(self::TEMPLATE_OFFSET, $offset)
            : '';
    }

    /**
     * Parses an array of where statements into a Criteria model
     *
     * @param WhereStatement[]|mixed[] $where
     * @return Criteria
     */
    public function parseWhere(array $where): Criteria
    {
        // Remove any none GroupByStatements
        $where = array_filter($where, function ($statement): bool {
            return is_a($statement, WhereStatement::class);
        });
        $criteriaWhere = new CriteriaBuilder($this->connection);
        $criteriaWhere->fromStatements($where);
        return $criteriaWhere->getCriteria();
    }

    /**
     * Parses an array of where statements into a Criteria model
     *
     * @param HavingStatement[]|mixed[] $having
     * @return Criteria
     */
    public function parseHaving(array $having): Criteria
    {
        // Remove any none GroupByStatements
        $having = array_filter($having, function ($statement): bool {
            return is_a($statement, HavingStatement::class);
        });
        $criteriaHaving = new CriteriaBuilder($this->connection);
        $criteriaHaving->fromStatements($having);
        return $criteriaHaving->getCriteria();
    }

    /**
     * Parses an array of Join statements
     *
     * @param JoinStatement[]|mixed[] $join
     * @return string
     */
    public function parseJoin(array $join): string
    {
        // @var JoinStatement[] $join
        $join = array_filter($join, function ($statement): bool {
            return is_a($statement, JoinStatement::class);
        });

        // Cast to string, with or without alias,
        $joins = array_map(function (JoinStatement $statement): string {
            // dump($statement->getTable());
            // Extract the table and possible alias.
            $alias = $statement->getTable()['value'];
            $table = $this->normalizer->normalizeTable($statement->getTable()['key']);

            // If not already a closure, cast to.
            $key = $statement->getField() instanceof \Closure
                ? $statement->getField()
                : JoinBuilder::createClosure(
                    $this->normalizer->normalizeField($statement->getField()) ?? '', /** @phpstan-ignore-line Cant be closure filtered above*/
                    $statement->getOperator(),
                    $this->normalizer->normalizeField($statement->getValue())
                );

            // Populate the join builder
            $builder = new JoinBuilder($this->connection);
            $key($builder);

            return sprintf(
                self::TEMPLATE_JOIN,
                strtoupper($statement->getJoinType()),
                0 === $alias ? $table : sprintf(self::TEMPLATE_AS, $table, $alias),
                $builder->getQuery('criteriaOnly', false)->getSql()
            );
        }, $join);

        return join(' ', $joins);
    }

    public function parseInsert(StatementBuilder $builder): string
    {
        $statement = $builder->getInsert();
        // dump($statement);
        return '';
    }
}
