<?php

declare(strict_types=1);

/**
 * Table Prefixer
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

use Closure;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;

class TablePrefixer
{
    /**
     * @var string|null
     */
    protected $tablePrefix;


    public function __construct(?string $tablePrefix)
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Applies the prefix if it applies to a field.
     *
     * tableName.columnName would be prefixed.
     * columnName would not be prefixed.
     *
     * Raw, JsonSelector and Closure values skipped, these should prefixed when interpolated.
     *
     * @phpstan-template T of string|string[]|Raw|Raw[]|JsonSelector|JsonSelector[]|Closure|Closure[]
     * @phpstan-param T $value
     * @phpstan-return T
     */
    public function field($value)
    {
        return $this->addTablePrefix($value, true);
    }

    /**
     * Applies the prefix to a table name.
     *
     * Raw, JsonSelector and Closure values skipped, these should prefixed when interpolated.
     *
     * @phpstan-template T of string|string[]|Raw|Raw[]|JsonSelector|JsonSelector[]|Closure|Closure[]
     * @phpstan-param T $tableName
     * @phpstan-return T|null
     */
    public function table($tableName)
    {
        return $this->addTablePrefix($tableName, false);
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @phpstan-template T of string|string[]|Raw|Raw[]|JsonSelector|JsonSelector[]|Closure|Closure[]
     * @phpstan-param T     $values
     * @param bool $tableFieldMix If we have mixes of field and table names with a "."
     *
     * @phpstan-return T
     */
    public function addTablePrefix($values, bool $tableFieldMix = true)
    {
        if (is_null($this->tablePrefix)) {
            return $values;
        }

        // $value will be an array and we will add prefix to all table names

        // If supplied value is not an array then make it one
        $single = false;
        if (!is_array($values)) {
            $values = [$values];
            // We had single value, so should return a single value
            $single = true;
        }

        $return = [];

        foreach ($values as $key => $value) {
            // It's a raw query, just add it to our return array and continue next
            if ($value instanceof Raw || $value instanceof Closure || $value instanceof JsonSelector) {
                $return[$key] = $value;
                continue;
            }

            // If key is not integer, it is likely a alias mapping,
            // so we need to change prefix target
            $target = &$value;
            if (!is_int($key)) {
                $target = &$key;
            }

            // Do prefix if the target is an expression or function.
            if (
                !$tableFieldMix
                || (
                    is_string($target) // Must be a string
                    && strpos($target, $this->tablePrefix) !== 0 // Inst already added.
                    && (bool) preg_match('/^[A-Za-z0-9_.]+$/', $target) // Can only contain letters, numbers, underscore and full stops
                    && 1 === \substr_count($target, '.') // Contains a single full stop ONLY.
                )
            ) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }
        // If we had single value then we should return a single value (end value of the array)
        return true === $single ? array_values($return)[0] : $return;  // @phpstan-ignore-line
    }
}
