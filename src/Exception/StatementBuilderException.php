<?php

declare(strict_types=1);

/**
 * Exceptions the statement builder throws.
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
 * @subpackage Exception
 */

namespace Pixie\Exception;

use Pixie\Exception;
use Pixie\JSON\JsonSelector;
use Pixie\Statement\StatementBuilder;

class StatementBuilderException extends Exception
{
    private $statementBuilder;

    public function __construct(StatementBuilder $statementBuilder, string $message, int $code = 0, Exception $previous = null)
    {
        $this->statementBuilder = $statementBuilder;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Access the statement builder that threw the exception.
     *
     * @return \Pixie\Statement\StatementBuilder
     */
    public function getStatementBuilder(): StatementBuilder
    {
        return $this->statementBuilder;
    }

    /**
     * Throws exceptions for missing table
     *
     * @param StatementBuilder $statementBuilder
     * @return StatementBuilderException
     */
    public static function noTableSelected(StatementBuilder $statementBuilder): StatementBuilderException
    {
        return new self($statementBuilder, 'No table selected.');
    }

    /**
     * Undocumented function
     *
     * @param \Pixie\Statement\StatementBuilder $statementBuilder
     * @param string $aggregateMethod
     * @param string|JsonSelector $column
     * @return StatementBuilderException
     */
    public static function columnNotSelectedForAggregate(StatementBuilder $statementBuilder, string $aggregateMethod, $column): StatementBuilderException
    {
        return new self(
            $statementBuilder,
            sprintf(
                'Failed %s query - the column %s hasn\'t been selected in the query.',
                $aggregateMethod,
                $column instanceof JsonSelector ? $column->getColumn() : $column
            )
        );
    }
}
