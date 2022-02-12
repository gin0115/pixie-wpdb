<?php

declare(strict_types=1);

/**
 * Table statement model.
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
use Pixie\Statement\Statement;

class TableStatement implements Statement
{
    /**
     * The table which is being selected
     *
     * @var string|Raw
     */
    protected $table;

    /**
     * The alias for the selected table
     *
     * @var string|null
     */
    protected $alias = null;

    /**
     * Creates a Select Statement
     *
     * @param string|Raw $table
     * @param string|null             $alias
     */
    public function __construct($table, ?string $alias = null)
    {
        // Verify valid table type.
        $this->verifyTable($table);
        $this->table = $table;
        $this->alias = $alias;
    }

    /** @inheritDoc */
    public function getType(): string
    {
        return Statement::TABLE;
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
            && ! is_a($table, Raw::class)
        ) {
            throw new TypeError("Only string and Raw may be used as tables");
        }
    }

    /**
     * Checks if the passed table needs to be interpolated.
     *
     * @return bool TRUE if Raw, FALSE if string.
     */
    public function tableRequiresInterpolation(): bool
    {
        return is_a($this->table, Raw::class);
    }

    /**
     * Allows the passing in of a closure to interpolate the statement.
     *
     * @psalm-immutable
     * @param \Closure(string|Raw $table): string $callback
     * @return TableStatement
     */
    public function interpolateField(\Closure $callback): TableStatement
    {
        $table = $callback($this->table);
        return new self($table, $this->alias);
    }

    /**
     * Gets the table.
     *
     * @return string|Raw
     */
    public function getTable()
    {
        return $this->table;
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
