<?php

declare(strict_types=1);

/**
 * Interface for statements that are used for building criteria.
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

use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\QueryBuilder\QueryBuilderHandler;

interface HasCriteria
{
    public const WHERE_CRITERIA = 'WHERE';
    public const JOIN_CRITERIA = 'JOIN';
    public const HAVING_CRITERIA = 'HAVING';

    /**
     * Returns the type of criteria (JOIN, WHERE, HAVING)
     *
     * @return string
     */
    public function getCriteriaType(): string;

    /**
     * Gets the field.
     *
     * @return string|\Closure(QueryBuilderHandler $query):void|Raw|JsonSelector
     */
    public function getField();

    /**
     * Get the operator
     *
     * @return string
     */
    public function getOperator(): string;

    /**
     * Get value for expression
     *
     * @return Raw|string|int|float|bool|Raw[]|string[]|int[]|float[]|bool[]|null
     */
    public function getValue();

    /**
     * Get joiner
     *
     * @return string
     */
    public function getJoiner(): string;
}
