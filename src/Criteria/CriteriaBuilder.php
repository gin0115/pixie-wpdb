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
 * @subpackage Criteria
 */

namespace Pixie\Criteria;

use Pixie\Binding;
use Pixie\Connection;
use Pixie\WpdbHandler;
use Pixie\QueryBuilder\Raw;
use Pixie\Parser\Normalizer;
use Pixie\Parser\TablePrefixer;
use Pixie\Statement\HasCriteria;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\JSON\JsonExpressionFactory;

class CriteriaBuilder
{
    /** BETWEEN TEMPLATE {1: Joiner, 2: Type, 3: Field, 4: Operation, 5: Val1, 6: Val2} */
    protected const TEMPLATE_BETWEEN = "%s%s%s %s %s AND %s";

    /** IN TEMPLATE {1: Joiner, 2: Type, 3: Field, 4: Operation, 5: Vals (as comma separated array)} */
    protected const TEMPLATE_IN = "%s%s%s %s (%s)";

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
    protected $useBindings;

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
     * @param array<int, string>
     * @return void
     */
    public function pushFragments(array $criteriaFragments): void
    {
        $this->criteriaFragments = array_merge($this->criteriaFragments, $criteriaFragments);
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
            $this->bindings
        );
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

            default:
                $criteria = new Criteria('MOCK', []);
                break;
        }

        // Push the current criteria to the collections.
        $this->pushBindings($criteria->getBindings());
        $this->pushFragments([$criteria->getStatement()]);
    }

    /**
     * Processes a nested query
     * @param HasCriteria $statement The statement
     * @return Criteria
     */
    protected function processNestedQuery(HasCriteria $statement): Criteria
    {
        return new Criteria('MOCK', []);
    }

    public function processWithMultipleValues(HasCriteria $statement): Criteria
    {
        $values = array_map([$this->normalizer, 'normalizeValue'], (array)$statement->getValue());

        $isBetween = strpos($statement->getOperator(), 'BETWEEN') !== false
            && 2 === count($values);

        // Loop through values and build collection of placeholders and bindings
        $placeHolder = [];
        $bindings = [];

        foreach ($values as $value) {
            if ($value instanceof Raw) {
                $placeHolder[] = $this->normalizer->parseRaw($value);
            } elseif ($value instanceof Binding) {
                $placeHolder[] = $value->getType();
                $bindings[] = $value->getValue();
            }
        }

        $statement = true === $isBetween
            ? sprintf(
                self::TEMPLATE_BETWEEN,
                $this->firstFragment() ? '' : \strtoupper($statement->getJoiner()) . ' ',
                ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()) . ' ',
                $this->normalizer->getTablePrefixer()->field($statement->getField()),
                strtoupper($statement->getOperator()),
                $placeHolder[0],
                $placeHolder[1],
            )
            : sprintf(
                self::TEMPLATE_IN,
                $this->firstFragment() ? '' : \strtoupper($statement->getJoiner()) . ' ',
                ! $this->firstFragment() ? '' : strtoupper($statement->getCriteriaType()) . ' ',
                $this->normalizer->getTablePrefixer()->field($statement->getField()),
                strtoupper($statement->getOperator()),
                join(', ', $placeHolder),
            );

        return new Criteria(
            $statement,
            $bindings
        );

        // dump($values); TEMPLATE_IN
    }
}
