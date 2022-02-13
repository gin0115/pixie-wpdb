<?php

declare(strict_types=1);

/**
 * Normalizer for Tables, Fields and JSON expressions.
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
 * @subpackage Parser
 */

namespace Pixie\Parser;

use Pixie\Connection;
use Pixie\HasConnection;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Parser\TablePrefixer;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\Statement\SelectStatement;
use Pixie\JSON\JsonExpressionFactory;

class Normalizer
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Handler for JSON selectors
     *
     * @var JsonSelectorHandler
     */
    protected $jsonSelectors;

    /**
     * JSON expression factory
     *
     * @var JsonExpressionFactory
     */
    protected $jsonExpressions;

    /**
     * Access to the table prefixer.
     *
     * @var TablePrefixer
     */
    protected $tablePrefixer;

    public function __construct(Connection $connection, TablePrefixer $tablePrefixer)
    {
        $this->connection = $connection;
        $this->tablePrefixer = $tablePrefixer;
        $this->jsonSelectors = new JsonSelectorHandler();
        $this->jsonExpressions = new JsonExpressionFactory($connection);

        // Create table prefixer.
    }

    /**
     * Access to the connection.
     *
     * @return \Pixie\Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     *
     *
     * @param \Pixie\Statement\SelectStatement $statement
     * @return string
     */
    public function selectStatement(SelectStatement $statement): string
    {
        $field = $statement->getField();
        switch (true) {
            // Is JSON Arrow Selector.
            case is_string($field) && $this->jsonSelectors->isJsonSelector($field):
                // Cast as JsonSelector
                $field = $this->jsonSelectors->toJsonSelector($field);
                // Get & Return SQL Expression as RAW
                return $this->jsonExpressions->extractAndUnquote(
                    $this->tablePrefixer->field($field->getColumn()),
                    $field->getNodes()
                )->getValue();

            // If JSON selector
            case is_a($field, JsonSelector::class):
                // Get & Return SQL Expression as RAW
                return $this->jsonExpressions->extractAndUnquote(
                    $this->tablePrefixer->field($field->getColumn()),
                    $field->getNodes()
                )->getValue();

            // RAW
            case is_a($field, Raw::class):
                // Return the extrapolated Raw expression.
                return ! $field->hasBindings()
                    ? $this->tablePrefixer->field($field->getValue())
                    : sprintf($field->getValue(), ...$field->getBindings());

            // Assume fallback as string.
            default:
                return $this->tablePrefixer->field($field);
        }
    }
}
