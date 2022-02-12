<?php

declare(strict_types=1);

/**
 * Select statement model.
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

use TypeError;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Statement\Statement;

class SelectStatement implements Statement
{
    /**
     * The field which is being selected
     *
     * @var string|Raw|JsonSelector
     */
    protected $field;

    /**
     * The alias for the selected field
     *
     * @var string|null
     */
    protected $alias = null;

    /**
     * Creates a Select Statement
     *
     * @param string|Raw|JsonSelector $field
     * @param string|null             $alias
     */
    public function __construct($field, ?string $alias = null)
    {
        // Verify valid field type.
        $this->verifyField($field);
        $this->field = $field;
        $this->alias = $alias;
    }

    /** @inheritDoc */
    public function getType(): string
    {
        return Statement::SELECT;
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
        ) {
            throw new TypeError("Only string, Raw and JsonSelectors may be used as select fields");
        }
    }

    /**
     * Checks if the passed field needs to be interpolated.
     *
     * @return bool TRUE if Raw or JsonSelector, FALSE if string.
     */
    public function fieldRequiresInterpolation(): bool
    {
        return is_a($this->field, Raw::class) || is_a($this->field, JsonSelector::class);
    }

    /**
     * Allows the passing in of a closure to interpolate the statement.
     *
     * @psalm-immutable
     * @param \Closure(string|Raw|JsonSelector $field): string $callback
     * @return SelectStatement
     */
    public function interpolateField(\Closure $callback): SelectStatement
    {
        $field = $callback($this->field);
        return new self($field, $this->alias);
    }

    /**
     * Gets the field.
     *
     * @return string|Raw|JsonSelector
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Checks if we have a valid (is string, not empty) alias.
     *
     * @return bool
     */
    public function hasAlias(): bool
    {
        return is_string($this->alias) && 0 !== \mb_strlen($this->alias);
    }

    /**
     * Gets the alias
     *
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->hasAlias() ? $this->alias : null;
    }
}
