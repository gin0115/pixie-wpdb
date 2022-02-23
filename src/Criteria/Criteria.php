<?php

declare(strict_types=1);

/**
 * Model of a criteria
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
 * @subpackage Criteria
 */

namespace Pixie\Criteria;

class Criteria
{
    /**
     * The SQL statement
     *
     * @var string
     */
    protected $statement;

    /**
     * The bindings for the statement
     *
     * @var array<int, string|int|float|bool|null>
     */
    protected $bindings;

    /**
     * @param string $statement
     * @param array<int, string|int|float|bool|null> $bindings
     */
    public function __construct(string $statement, array $bindings)
    {
        $this->statement = $statement;
        $this->bindings = $bindings;
    }

    /**
     * Get the SQL statement
     *
     * @return string
     */
    public function getStatement(): string
    {
        return $this->statement;
    }

    /**
     * Get the bindings
     *
     * @return array<int, string|int|float|bool|null>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
