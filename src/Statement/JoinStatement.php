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

class JoinStatement implements Statement, HasCriteria
{
    /**
     * The field which is being group by
     *
     * @var string|Raw|JsonSelector|\Closure(QueryBuilderHandler $query):void
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
     * Joiner
     *
     * @var string
     */
    protected $joiner;

    /**
     * Creates a Select Statement
     *
     * @param string|Raw|JsonSelector|\Closure(QueryBuilderHandler $query):void $field
     * @param string $operator
     * @param string|int|float|bool|string[]|int[]|float[]|bool[] $value
     * @param string $joiner
     */
    public function __construct($field, $operator = null, $value = null, string $joiner = 'AND')
    {
        // Verify valid field type.
        $this->verifyField($field);
        $this->field = $field;
        $this->operator = $operator ?? '=';
        $this->value = $value;
        $this->joiner = $joiner;
    }

    /** @inheritDoc */
    public function getCriteriaType(): string
    {
        return HasCriteria::HAVING_CRITERIA;
    }

    /** @inheritDoc */
    public function getType(): string
    {
        return Statement::HAVING;
    }

    /**
     * Verifies if the passed filed is of a valid type.
     *
     * @param mixed $field
     * @return void
     */
    protected function verifyField($field): void
    {
        if (
            !is_string($field)
            && ! is_a($field, Raw::class)
            && !is_a($field, JsonSelector::class)
            && !is_a($field, \Closure::class)
        ) {
            throw new TypeError("Only strings, Raw, JsonSelector and Closures may be used as fields in Where statements.");
        }
    }
    /**
     * Gets the field.
     *
     * @return string|\Closure(QueryBuilderHandler $query):void|Raw|JsonSelector
     */
    public function getField()
    {
        return $this->field;
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
     * @return string|int|float|bool|string[]|int[]|float[]|bool[]|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get joiner
     *
     * @return string
     */
    public function getJoiner(): string
    {
        return $this->joiner;
    }
}
