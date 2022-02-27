<?php

declare(strict_types=1);

/**
 * Normalizer for Tables, Fields and JSON expressions.
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
use Pixie\WpdbHandler;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Parser\TablePrefixer;
use Pixie\Statement\JoinStatement;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\Statement\TableStatement;
use Pixie\Statement\SelectStatement;
use Pixie\JSON\JsonExpressionFactory;
use Pixie\Statement\OrderByStatement;
use Pixie\QueryBuilder\JsonQueryBuilder;

class Normalizer
{
    /**
     * @var WpdbHandler
     * @since 0.2.0
     */
    protected $wpdbHandler;

    /**
     * Handler for JSON selectors
     *
     * @var JsonSelectorHandler
     * @since 0.2.0
     */
    protected $jsonSelectors;

    /**
     * JSON expression factory
     *
     * @var JsonExpressionFactory
     * @since 0.2.0
     */
    protected $jsonExpressions;

    /**
     * Access to the table prefixer.
     *
     * @var TablePrefixer
     * @since 0.2.0
     */
    protected $tablePrefixer;

    public function __construct(
        WpdbHandler $wpdbHandler,
        TablePrefixer $tablePrefixer,
        JsonSelectorHandler $jsonSelectors,
        JsonExpressionFactory $jsonExpressions
    ) {
        $this->wpdbHandler = $wpdbHandler;
        $this->tablePrefixer = $tablePrefixer;
        $this->jsonSelectors = $jsonSelectors;
        $this->jsonExpressions = $jsonExpressions;
    }

    /**
     * Normalize all select statements into strings
     *
     * Accepts string or (string) JSON Arrow Selectors,
     * JsonSelector Objects and Raw Objects as fields.
     *
     * @param \Pixie\Statement\SelectStatement $statement
     * @return string
     * @since 0.2.0
     */
    public function selectStatement(SelectStatement $statement): string
    {
        $field = $statement->getField();
        switch (true) {
            // Is JSON Arrow Selector.
            case is_string($field) && $this->jsonSelectors->isJsonSelector($field):
                return $this->normalizeJsonArrowSelector($field);

            // If JSON selector
            case is_a($field, JsonSelector::class):
                return $this->normalizeJsonSelector($field);

            // RAW
            case is_a($field, Raw::class):
                return $this->normalizeRaw($field);

            // Assume fallback as string.
            default:
                return $this->tablePrefixer->field($field);
        }
    }

    /**
     * Normalize all orderBy statements into strings
     *
     * Accepts string or (string) JSON Arrow Selectors,
     * JsonSelector Objects and Raw Objects as fields.
     *
     * @param \Pixie\Statement\OrderByStatement $statement
     * @return string
     * @since 0.2.0
     */
    public function orderByStatement(OrderByStatement $statement): string
    {
        $field = $statement->getField();
        switch (true) {
            // Is JSON Arrow Selector.
            case is_string($field) && $this->jsonSelectors->isJsonSelector($field):
                return $this->normalizeJsonArrowSelector($field);

            // If JSON selector
            case is_a($field, JsonSelector::class):
                return $this->normalizeJsonSelector($field);

            // RAW
            case is_a($field, Raw::class):
                return $this->normalizeRaw($field);

            // Assume fallback as string.
            default:
                return $this->tablePrefixer->field($field);
        }
    }

    /**
     * Normalize all table states into strings
     *
     * Accepts either string or RAW expression.
     *
     * @param \Pixie\Statement\TableStatement $statement
     * @return string
     */
    public function tableStatement(TableStatement $statement): string
    {
        return $this->normalizeTable($statement->getTable());
    }

    /**
     * Casts a table (string or Raw) including table prefixing.
     *
     * @var string|Raw $table
     * @return string
     */
    public function normalizeTable($table): string
    {
        return is_a($table, Raw::class)
            ? $this->normalizeRaw($table)
            : $this->tablePrefixer->table($table) ?? $table;
    }

    /**
     * Interpolates a raw expression
     *
     * @param \Pixie\QueryBuilder\Raw $raw
     * @return string
     */
    private function normalizeRaw(Raw $raw): string
    {
        return $this->wpdbHandler->interpolateQuery(
            $raw->getValue(),
            $raw->getBindings()
        );
    }

    /**
     * Extract from JSON Arrow selector to string representation.
     *
     * @param string $selector
     * @param bool $isField If set to true with apply table prefix as a field (false for table)
     * @return string
     */
    private function normalizeJsonArrowSelector(string $selector, bool $isField = true): string
    {
        $selector = $this->jsonSelectors->toJsonSelector($selector);
        return $this->normalizeJsonSelector($selector);
    }

    /**
     * Extract from JSON Selector to string representation
     *
     * @param \Pixie\JSON\JsonSelector $selector
     * @param bool $isField If set to true with apply table prefix as a field (false for table)
     * @return string
     */
    private function normalizeJsonSelector(JsonSelector $selector, bool $isField = true): string
    {
        $column = $isField === \true
            ? $this->tablePrefixer->field($selector->getColumn())
            : $this->tablePrefixer->table($selector->getColumn()) ?? $selector->getColumn();

        return $this->jsonExpressions->extractAndUnquote($column, $selector->getNodes())
            ->getValue();
    }

    /**
     * Normalizes a values to either a bindings or raw statement.
     *
     * @param Raw|Binding|JsonSelector|string|float|int|bool|null $value
     * @return Raw|Binding
     */
    public function normalizeValue($value)
    {
        switch (true) {
            case $value instanceof Binding && Binding::RAW === $value->getType():
                /** @var Raw */
                $value = $value->getValue();
                break;

            case is_string($value) && $this->jsonSelectors->isJsonSelector($value):
                $value = Raw::val($this->normalizeJsonArrowSelector($value, false));
                break;

            case is_string($value):
                $value = Binding::asString($value);
                break;

            case is_int($value):
            case is_bool($value):
                $value = Binding::asInt($value);
                break;

            case is_float($value):
                $value = Binding::asFloat($value);
                break;

            case $value instanceof Binding || $value instanceof Raw:
                $value = $value;
                break;

            case $value instanceof JsonSelector:
                $value = Raw::val($this->normalizeJsonSelector($value, false));
                break;

            case is_null($value):
                $value = Raw::val('NULL');
                break;



            default:
                throw new Exception(\sprintf("Unexpected type :: %s", json_encode($value)), 1);
        }

        return $value;
    }

    /**
     * Normalizes a values to either a bindings or raw statement.
     *
     * @param Raw|\Closure|JsonSelector|string|null $value
     * @return Raw|string|\Closure|null
     */
    public function normalizeField($value)
    {
        switch (true) {
            case is_string($value) && $this->jsonSelectors->isJsonSelector($value):
                $value = Raw::val($this->normalizeJsonArrowSelector($value, true));
                break;
            case $value instanceof JsonSelector:
                $value = Raw::val($this->normalizeJsonSelector($value, true));
                break;

            case is_null($value):
            case $value instanceof \Closure:
            case $value instanceof Raw:
            case is_string($value):
                $value = $value;
                break;

            default:
                throw new Exception(\sprintf("Unexpected type :: %s", json_encode($value)), 1);
        }

        return $value;
    }

    /**
     * Attempts to parse a raw query, if bindings are defined then they will be bound first.
     *
     * @param Raw $raw
     * @requires string
     */
    public function parseRaw(Raw $raw): string
    {
        $bindings = $raw->getBindings();
        return 0 === count($bindings)
            ? (string) $raw
            : $this->wpdbHandler->interpolateQuery($raw->getValue(), $bindings);
    }

    /**
     * Returns a closure for parsing potential raw statements.
     */
    public function parseRawCallback(): \Closure
    {
        /**
         * @template M
         * @param M|Raw $datum
         * @return M
         */
        return function ($datum) {
            return $datum instanceof Raw ? $this->parseRaw($datum) : $datum;
        };
    }

    /**
     * Parses a valid type
     *
     * @param string|Raw|\Closure|null $value
     * @return string
     */
    public function normalizeForSQL($value): string
    {
        if ($value instanceof Raw) {
            $value = $this->parseRaw($value);
        }
        if ($value instanceof \Closure || is_null($value)) {
            throw new Exception(\sprintf("Field must be a valid type, %s supplied", json_encode($value)), 1);
        }
        return $value;
    }

    /**
     * Get access to the table prefixer.
     *
     * @return TablePrefixer
     */
    public function getTablePrefixer(): TablePrefixer
    {
        return $this->tablePrefixer;
    }

    /**
     * Removes the operator (AND|OR) from a statement.
     * @param string $statement
     * @return string
     */
    public function removeInitialOperator(string $statement): string
    {
        return (string) (preg_replace(['#(?:AND|OR)#is', '/\s+/'], ' ', $statement, 1) ?: '');
    }
}
