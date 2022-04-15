<?php

declare(strict_types=1);

/**
 * Insert statement model.
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
use Pixie\Binding;
use Pixie\QueryBuilder\Raw;
use Pixie\Statement\Statement;

class InsertStatement implements Statement
{

    protected const IGNORE  = 'INSERT IGNORE';
    protected const REPLACE = 'REPLACE';

    /**
     * The data to inserted or updated.
     *
     * @var array<int, array<string, Binding|Raw|string|float|int|bool|null>>
     */
    protected $data = [];

    /**
     * Holds the insert type
     *
     * @var string
     */
    protected $type;

    public function __construct(array $data, string $type = 'INSERT')
    {
        $this->data = $this->normalizeData($data);
        $this->type = $type;
    }

    /**
     * Get the statement type
     *
     * @return string
     */
    public function getType(): string
    {
        return Statement::INSERT;
    }

    /**
     * Undocumented function
     *
     * @param mixed[] $data
     * @return array<int, array<string, Binding|Raw|string|float|int|bool|null>>
     */
    protected function normalizeData(array $data): array
    {
        // If single array.
        if (!is_array(current($data))) {
            $data = [$data];
        }

        return array_reduce($data, function (array $carry, $row): array {
            foreach ($row as $key => $value) {
                $this->verifyKey($key);
                $this->verifyValue($value);
            }
            $carry[] = $row;
            return $carry;
        }, []);
    }

    /**
     * Verifies if the passed filed is of a valid type.
     *
     * @param mixed $value
     * @return void
     */
    protected function verifyValue($value): void
    {
        if (
            !is_string($value)
            && !is_int($value)
            && !is_float($value)
            && !is_bool($value)
            && !is_null($value)
            && !is_a($value, Raw::class)
            && !is_a($value, Binding::class)
        ) {
            throw new TypeError(
                sprintf(
                    "Only Binding, Raw, string, float, int, bool, null accepted as values for insert, %s passed",
                    json_encode($value)
                )
            );
        }
    }

    /**
     * Verifies that only strings can be used as keys.
     *
     * @param [type] $key
     * @return void
     */
    protected function verifyKey($key): void
    {
        if (!is_string($key)) {
            throw new TypeError(
                sprintf(
                    "Only strings accepted as keys for insert, %s passed",
                    json_encode($key)
                )
            );
        }
    }
}
