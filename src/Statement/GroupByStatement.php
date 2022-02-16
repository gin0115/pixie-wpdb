<?php

declare(strict_types=1);

/**
 * Group By statement model.
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
use Pixie\Statement\Statement;

class GroupByStatement implements Statement
{
    /**
     * The field which is being group by
     *
     * @var string
     */
    protected $field;


    /**
     * Creates a Select Statement
     *
     * @param string $field
     */
    public function __construct(string $field)
    {
        // Verify valid field type.
        $this->verifyField($field);
        $this->field = $field;
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
        ) {
            throw new TypeError("Only string may be used as group by fields");
        }
    }
    /**
     * Gets the field.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }
}
