<?php

declare(strict_types=1);

/**
 * Builds an criteria of a where/join sql statement
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

use Pixie\Binding;
use Pixie\Exception;
use Pixie\Connection;
use Pixie\WpdbHandler;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Parser\Normalizer;
use Pixie\Parser\TablePrefixer;
use Pixie\Statement\HasCriteria;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\JSON\JsonExpressionFactory;
use Pixie\QueryBuilder\NestedCriteria;

class CriteriaBuilder
{
    /** BETWEEN TEMPLATE {1: Joiner, 2: Type, 3: Field, 4: Operation, 5: Val1, 6: Val2} */
    protected const TEMPLATE_BETWEEN = "%s%s%s %s %s AND %s";

    /** IN TEMPLATE {1: Joiner, 2: Type, 3: Field, 4: Operation, 5: Vals (as comma separated array)} */
    protected const TEMPLATE_IN = "%s%s%s %s (%s)";

    /** SIMPLE TEMPLATE {1: Joiner, 2: Type, 3: Field, 4: Operation, 5: Val} */
    protected const TEMPLATE_SIMPLE = "%s%s%s %s %s";

    /** EXPRESSION TEMPLATE {1: Joiner, 2: Type, 3: Expression} */
    protected const TEMPLATE_EXPRESSION = "%s%s %s";

    /** NESTED TEMPLATE {1: Joiner, 2: Type, 3: Expression} */
    protected const TEMPLATE_NESTED = "%s%s(%s)";

    /**
     * Hold access to the connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Holds all composed fragments of the criteria
     *
     * @var string[]
     */
    protected $criteriaFragments = [];

    /**
     * Binding values for the criteria.
     *
     * @var array<int, string|int|float|bool|null>
     */
    protected $bindings = [];

    /**
     * Does this criteria use bindings.
     *
     * @var bool
     */
    protected $useBindings = true;

    /**
     * WPDB Access
     *
     * @var WpdbHandler
     */
    protected $wpdbHandler;

    /** @var Normalizer */
    protected $normalizer;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->wpdbHandler = new WpdbHandler($connection);
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
     * Returns a new instance of it self
     *
     * @return self
     */
    public function new(): self
    {
        return new self($this->connection);
    }

    /**
     * Pushes a set of bindings to the existing collection
     *
     * @param array<int, string|int|float|bool|null> $bindings
     * @return void
     */
    public function pushBindings(array $bindings): void
    {
        $this->bindings = array_merge($this->bindings, $bindings);
    }

    /**
     * Pushes a set of criteria fragments to the existing collection
     *
     * @param string[] $criteriaFragments
     * @return void
     */
    public function pushFragments(array $criteriaFragments): void
    {
        $this->criteriaFragments = array_merge($this->criteriaFragments, $criteriaFragments);
    }

    /**
    * Set does this criteria use bindings.
    *
    * @param bool $useBindings  Does this criteria use bindings.
    * @return self
    */
    public function useBindings(bool $useBindings = true)
    {
        $this->useBindings = $useBindings;
        return $this;
    }

    /**
     * Checks if fragments are empty and would be first
     *
     * @return bool
     */
    protected function firstFragment(): bool
    {
        return 0 === count($this->criteriaFragments);
    }

    /**
     * Builds the criteria based on an array of statements.
     *
     * @param HasCriteria[] $statements
     * @return self
     */
    public function fromStatements(array $statements): self
    {
        foreach ($statements as $statement) {
            $this->processStatement($statement);
        }
        return $this;
    }

    /**
     * Return the current criteria
     *
     * @return Criteria
     */
    public function getCriteria(): Criteria
    {
        return new Criteria(
            join(' ', $this->criteriaFragments),
            array_filter($this->bindings, function ($binding): bool {
                return false === is_null($binding);
            })
        );
    }

    /**
     * Parses a simple (string or Raw) field
     *
     * @param Raw|\Closure|JsonSelector|string|null $field
     * @return string
     * @throws Exception If none string or Raw passed.
     */
    public function parseBasicField($field): string
    {
        /** @phpstan-var string|Raw|\Closure $field */
        $field = $this->normalizer->normalizeField($field);
        $field = $this->normalizer->normalizeForSQL($field);
        /** @phpstan-var string $field */
        return $this->normalizer->getTablePrefixer()->field($field);
    }

    /**
     * Processes a single statement.
     *
     * @param \Pixie\Statement\HasCriteria $statement
     * @return void
     */
    protected function processStatement(HasCriteria $statement): void
    {
        // Based on the statement, build the criteria.
        switch (true) {
            // NESTED STATEMENT.
            case is_a($statement->getField(), \Closure::class)
            && null === $statement->getValue():
                $criteria = $this->processNestedQuery($statement);
                break;

            // BETWEEN or IN criteria.
            case is_array($statement->getValue()):
                $criteria = $this->processWithMultipleValues($statement);
                break;

            // Where field is raw and value is null (whereNull)
            case is_a($statement->getField(), Raw::class)
                && null === $statement->getValue():
                $criteria = $this->processRawExpression($statement);
                break;

            case is_a($statement->getField(), Raw::class):
            case is_a($statement->getField(), JsonSelector::class):
                $criteria = $this->processObjectField($statement);
                break;

            case is_object($statement->getValue())
            && is_a($statement->getValue(), Raw::class):
                $criteria = $this->processSimpleCriteria($statement);
                break;




            default:
                // $criteria = new Criteria('MOCK', []);
                $criteria = $this->processSimpleCriteria($statement);
                break;
        }

        // Push bindings, unless specified not to.
        if (true === $this->useBindings) {
            $this->pushBindings($criteria->getBindings());
        }
        $this->pushFragments([$criteria->getStatement()]);
    }

    /**
     * Processes a nested query
     * @param HasCriteria $statement The statement
     * @return Criteria
     */
    protected function processNestedQuery(HasCriteria $statement): Criteria
    {
        // Ensure only raw can be used as Field.
        if (! $statement->getField() instanceof \Closure) {
            throw new Exception(sprintf("Nested queries can only be used with a closure as the field., %s passed", json_encode($statement)), 1);
        }

        $nestedCriteria = new NestedCriteria($this->connection);

        // Call the closure with our new nestedCriteria object
        $statement->getField()($nestedCriteria);
        $queryObject = $nestedCriteria->getQuery('criteriaOnly', true);

        $sql = \sprintf(
            self::TEMPLATE_NESTED,
            $this->firstFragment() ? '' : \strtoupper($statement->getJoiner()) . ' ',
            ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()),
            $queryObject->getSql()
        );

        return new Criteria(
            $sql,
            $queryObject->getBindings()
        );
    }

    /**
     * Process criteria with an array of values
     * @param HasCriteria $statement Where or Having statement
     * @return Criteria
     */
    protected function processWithMultipleValues(HasCriteria $statement): Criteria
    {
        $values = array_map(
            [$this->normalizer, 'normalizeValue'],
            is_array($statement->getValue())
                ? $statement->getValue()
                : [$statement->getValue()
            ]
        );

        // Loop through values and build collection of placeholders and bindings
        $placeHolder = [];
        $bindings = [];

        foreach ($values as $value) {
            if ($value instanceof Raw) {
                $placeHolder[] = $this->normalizer->parseRaw($value);
            } elseif ($value instanceof Binding) {
                // Set as placeholder if we are using bindings
                $placeHolder[] = true === $this->useBindings
                    ? $value->getType()
                    : $value->getValue();
                $bindings[] = $value->getValue();
            }
        }

        // Parse any Raw in Bindings.
        $bindings = array_map($this->normalizer->parseRawCallback(), $bindings);

        // If we have a valid BETWEEN statement,
        // use TEMPLATE_BETWEEN else TEMPLATE_IN
        $statement = strpos($statement->getOperator(), 'BETWEEN') !== false
            && 2 === count($values)
            ? sprintf(
                self::TEMPLATE_BETWEEN,
                $this->firstFragment() ? '' : \strtoupper($statement->getJoiner()) . ' ',
                ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()) . ' ',
                $this->parseBasicField($statement->getField()),
                strtoupper($statement->getOperator()),
                $placeHolder[0],
                $placeHolder[1],
            )
            : sprintf(
                self::TEMPLATE_IN,
                $this->firstFragment() ? '' : \strtoupper($statement->getJoiner()) . ' ',
                ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()) . ' ',
                $this->parseBasicField($statement->getField()),
                strtoupper($statement->getOperator()),
                join(', ', $placeHolder),
            );

        return new Criteria($statement, $bindings);
    }

    /**
     * Process a simple statement
     * @param HasCriteria $statement
     * @return Criteria
     * @throws Exception
     */
    protected function processSimpleCriteria(HasCriteria $statement): Criteria
    {
        // Only allow single values to be processed as simple.
        $value = $statement->getValue();
        if (is_array($value)) {
            throw new Exception(sprintf("Simple criteria must only have a single value, %s passed", json_encode($value)), 1);
        }
        $value = $this->normalizer->normalizeValue($value);

        // Set the placeholder and binding based on type
        if ($value instanceof Raw) {
            $placeHolder = $this->normalizer->parseRaw($value);
            $bindings = [];
        } else {
            // Set as placeholder if we are using bindings
            $placeHolder = true === $this->useBindings
                ? $value->getType()
                : $value->getValue();
            $bindings = [$value->getValue()];
        }

        $sql = sprintf(
            self::TEMPLATE_SIMPLE,
            ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()) . ' ',
            \strtoupper($statement->getJoiner()) . ' ',
            $this->parseBasicField($statement->getField()),
            strtoupper($statement->getOperator()),
            $placeHolder
        );

        // If this is the first fragment, remove the operator
        if ($this->firstFragment()) {
            $sql = $this->normalizer->removeInitialOperator($sql);
        }

        return new Criteria(
            $sql,
            array_map($this->normalizer->parseRawCallback(), $bindings)
        );
    }

    /**
     * Process a statement where the field is a Raw expression
     * Gets forwarded through processSimpleCriteria()
     * @param HasCriteria $statement
     * @return Criteria
     * @throws Exception
     */
    protected function processObjectField(HasCriteria $statement): Criteria
    {
        // Normalize the field and parse if raw.
        $field = $this->normalizer->normalizeField($statement->getField());
        $field = $field instanceof Raw
            ? $this->normalizer->parseRaw($field)
            : $field;

        /** @var HasCriteria */
        $statementType = get_class($statement);
        $statement = new $statementType(
            $field,
            $statement->getOperator(),
            $statement->getValue(),
            $statement->getJoiner()
        );
        return $this->processSimpleCriteria($statement);
    }

    /**
     * Process a statement where all is held as an expression in field only.
     * @param HasCriteria $statement
     * @return Criteria
     */
    protected function processRawExpression(HasCriteria $statement): Criteria
    {
        // Ensure only raw can be used as Field.
        if (! $statement->getField() instanceof Raw) {
            throw new Exception(sprintf("Only Raw expressions can be processed, %s passed", json_encode($statement)), 1);
        }
        $field = $this->normalizer->parseRaw($statement->getField());
        $field = $this->normalizer->getTablePrefixer()->field($field);

        $sql = sprintf(
            self::TEMPLATE_EXPRESSION,
            $this->firstFragment() ? '' : \strtoupper($statement->getJoiner()),
            ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()),
            $field
        );

        return new Criteria($sql, []);
    }
}
