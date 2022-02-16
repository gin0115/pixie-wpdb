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

use Pixie\WpdbHandler;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Parser\TablePrefixer;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\Statement\TableStatement;
use Pixie\Statement\SelectStatement;
use Pixie\JSON\JsonExpressionFactory;
use Pixie\Statement\OrderByStatement;

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
        $table = $statement->getTable();
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
}
