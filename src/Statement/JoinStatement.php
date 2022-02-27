<?php

declare(strict_types=1);

/**
 * Join statement model.
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
 * @subpackage QueryBuilder\Statement
 */

namespace Pixie\Statement;

use Closure;
use TypeError;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Statement\Statement;
use Pixie\Statement\HasCriteria;
use Pixie\QueryBuilder\JoinBuilder;

class JoinStatement implements Statement
{
    /**
     * The table which is being group by
     *
     * @var string|Raw|array<string|int, string|Raw>
     */
    protected $table;

    /**
     * The field which is being group by
     *
     * @var string|Raw|JsonSelector|\Closure(JoinBuilder $joinBuilder):void
     */
    protected $field;

    /**
     * The operator
     *
     * @var string
     */
    protected $operator;

    /**
     * Value for expression
     *
     * @var string|int|float|bool|string[]|int[]|float[]|bool[]|null
     */
    protected $value;

    /**
     * Type of join
     *
     * @var string
     */
    protected $joinType;


    /**
     * Creates a Select Statement
     *
     * @param string|Raw|array<string|int, string|Raw|string[]> $table
     * @param string|Raw|JsonSelector|\Closure(JoinBuilder $joinBuilder):void $field
     * @param string $operator
     * @param string|int|float|bool|string[]|int[]|float[]|bool[] $value
     * @param string $joinType
     */
    public function __construct(
        $table,
        $field,
        $operator = null,
        $value = null,
        string $joinType = 'INNER'
    ) {
        // Verify valid table type.
        $this->verifyTable($table);
        $this->table = $table;
        $this->field = $field;
        $this->operator = $operator ?? '=';
        $this->value = $value;
        $this->joinType = $joinType;
    }


    /** @inheritDoc */
    public function getType(): string
    {
        return Statement::JOIN;
    }

    /**
     * Verifies if the passed filed is of a valid type.
     *
     * @param mixed $table
     * @return void
     */
    protected function verifyTable($table): void
    {
        if (
            !is_string($table)
            && !is_array($table)
            && ! is_a($table, Raw::class)
        ) {
            throw new TypeError("Only strings and Raw may be used as tables in Where statements.");
        }
    }
    /**
     * Gets the table.
     *
     * @return string|Raw|array<string|int, string|Raw>
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the operator
     *
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Get value for expression
     *
     * @return string|Raw|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get field
     *
     * @return string|\Closure(JoinBuilder $query):void|Raw|JsonSelector
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Get the join type.
     *
     * @return string
     */
    public function getJoinType(): string
    {
        return $this->joinType;
    }
}
