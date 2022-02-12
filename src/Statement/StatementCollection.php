<?php

declare(strict_types=1);

/**
 * Collection for holding and accessing all statements.
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
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @package Gin0115\Pixie
 * @subpackage QueryBuilder\Statement
 */

namespace Pixie\Statement;

class StatementCollection
{
    /**
     * Holds all the statements
     * @var array{select:SelectStatement[]}
     */
    protected $statements = [
        Statement::SELECT => [],
        Statement::TABLE => [],
    ];

    /**
     * Adds a select statement to the collection.
     *
     * @param SelectStatement $statement
     * @return self
     */
    public function addSelect(SelectStatement $statement): self
    {
        $this->statements[Statement::SELECT][] = $statement;
        return $this;
    }

    /**
     * Get all SelectStatements
     *
     * @return SelectStatement[]
     */
    public function getSelect(): array
    {
        return $this->statements[Statement::SELECT];
    }

    /**
     * Select statements exist.
     *
     * @return bool
     */
    public function hasSelect(): bool
    {
        return 0 < count($this->getSelect());
    }
}
