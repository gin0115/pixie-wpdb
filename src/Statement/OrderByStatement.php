<?php

declare(strict_types=1);

/**
 * Order By statement model.
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

class OrderByStatement implements Statement
{
    /**
     * The field which is being order by
     *
     * @var string|Raw|JsonSelector
     */
    protected $field;

    /**
     * The direction for the order by field
     *
     * @var string|null
     */
    protected $direction = null;

    /**
     * Creates a Select Statement
     *
     * @param string|Raw|JsonSelector $field
     * @param string|null             $direction
     */
    public function __construct($field, ?string $direction = null)
    {
        // Verify valid field type.
        $this->verifyField($field);
        $this->field = $field;
        $this->direction = $direction;
    }

    /** @inheritDoc */
    public function getType(): string
    {
        return Statement::ORDER_BY;
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
            throw new TypeError("Only string, Raw and JsonSelectors may be used as orderBy fields");
        }
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
     * Checks if we have a defined direction
     *
     * @return bool
     */
    public function hasDirection(): bool
    {
        return is_string($this->direction)
        && in_array(strtoupper($this->direction), ['ASC', 'DESC']);
    }

    /**
     * Gets the direction
     *
     * @return string|null
     */
    public function getDirection(): ?string
    {
        return $this->hasDirection()
            ? \strtoupper($this->direction ?? '')
            : null;
    }
}
