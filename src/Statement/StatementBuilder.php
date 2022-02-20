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

use Pixie\Statement\TableStatement;

class StatementBuilder
{
    /**
     * Holds all the statements
     *
     * @var array{
     *  select: SelectStatement[],
     *  table: TableStatement[],
     *  orderby: OrderByStatement[],
     *  groupby: GroupByStatement[],
     *  where: WhereStatement[],
     * }
     */
    protected $statements = [
        Statement::SELECT  => [],
        Statement::TABLE   => [],
        Statement::ORDER_BY => [],
        Statement::GROUP_BY => [],
        Statement::WHERE => [],
    ];

    /**
     * Denotes if a DISTINCT SELECT
     *
     * @var bool
     */
    protected $distinctSelect = false;

    /**
     * @var int|null
     */
    protected $limit = null;

    /**
     * @var int|null
     */
    protected $offset = null;

    /**
     * Get all the statements
     *
     * @return array{
     *  select: SelectStatement[],
     *  table: TableStatement[],
     *  orderby: OrderByStatement[],
     *  groupby: GroupByStatement[],
     *  where: WhereStatement[],
     *}
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

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

    /**
     * Adds a table statement to the collection.
     *
     * @param TableStatement $statement
     * @return self
     */
    public function addTable(TableStatement $statement): self
    {
        $this->statements[Statement::TABLE][] = $statement;
        return $this;
    }

    /**
     * Get all Table Statements
     *
     * @return TableStatement[]
     */
    public function getTable(): array
    {
        return $this->statements[Statement::TABLE];
    }

    /**
     * Table statements exist.
     *
     * @return bool
     */
    public function hasTable(): bool
    {
        return 0 < count($this->getTable());
    }

    /**
    * Adds a OrderBy statement to the collection.
    *
    * @param OrderByStatement $statement
    * @return self
    */
    public function addOrderBy(OrderByStatement $statement): self
    {
        $this->statements[Statement::ORDER_BY][] = $statement;
        return $this;
    }

    /**
     * Get all OrderBy Statements
     *
     * @return OrderByStatement[]
     */
    public function getOrderBy(): array
    {
        return $this->statements[Statement::ORDER_BY];
    }

    /**
     * OrderBy statements exist.
     *
     * @return bool
     */
    public function hasOrderBy(): bool
    {
        return 0 < count($this->getOrderBy());
    }

    /**
    * Adds a GroupBy statement to the collection.
    *
    * @param GroupByStatement $statement
    * @return self
    */
    public function addGroupBy(GroupByStatement $statement): self
    {
        $this->statements[Statement::GROUP_BY][] = $statement;
        return $this;
    }

    /**
     * Get all GroupBy Statements
     *
     * @return GroupByStatement[]
     */
    public function getGroupBy(): array
    {
        return $this->statements[Statement::GROUP_BY];
    }

    /**
     * GroupBy statements exist.
     *
     * @return bool
     */
    public function hasGroupBy(): bool
    {
        return 0 < count($this->getGroupBy());
    }

        /**
     * Adds a select statement to the collection.
     *
     * @param WhereStatement $statement
     * @return self
     */
    public function addWhere(WhereStatement $statement): self
    {
        $this->statements[Statement::WHERE][] = $statement;
        return $this;
    }

    /**
     * Get all WhereStatements
     *
     * @return WhereStatement[]
     */
    public function getWhere(): array
    {
        return $this->statements[Statement::WHERE];
    }

    /**
     * Where statements exist.
     *
     * @return bool
     */
    public function hasWhere(): bool
    {
        return 0 < count($this->getWhere());
    }

    /**
     * Set denotes if a DISTINCT SELECT
     *
     * @param bool $distinctSelect Denotes if a DISTINCT SELECT
     *
     * @return static
     */
    public function setDistinctSelect(bool $distinctSelect): self
    {
        $this->distinctSelect = $distinctSelect;

        return $this;
    }

    /**
     * Get denotes if a DISTINCT SELECT
     *
     * @return bool
     */
    public function getDistinctSelect(): bool
    {
        return $this->distinctSelect;
    }

    /**
     * Get the value of limit
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Set the value of limit
     *
     * @param int|null $limit
     * @return static
     */
    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Get the value of offset
     *
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * Set the value of offset
     *
     * @param int|null $offset
     * @return static
     */
    public function setOffset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
}
