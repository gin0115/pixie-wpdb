<?php

declare(strict_types=1);

/**
 * Handles WPDB interactions.
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
 */

namespace Pixie;

class WpdbHandler
{
    /**
     * WPDB instance
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * Should wpdb errors be thrown as exceptions
     *
     * @var bool
     */
    protected $throwErrors = false;

    public function __construct(Connection $connection)
    {
        $this->wpdb = $connection->getDbInstance();

        $this->throwErrors = array_key_exists(Connection::THROW_ERRORS, $connection->getAdapterConfig())
            ? true === (bool) $connection->getAdapterConfig()[Connection::THROW_ERRORS]
            : false;
    }

    /**
     * Uses WPDB::prepare() to interpolate the query passed.

     *
     * @param string $query  The sql query with parameter placeholders
     * @param mixed[]  $params The array of substitution parameters
     *
     * @return string The interpolated query
     *
     * @todo hook into catch on error.
     */
    public function interpolateQuery(string $query, array $params = []): string
    {
        // Only call this when we have valid params (avoids wpdb::prepare() incorrectly called error)
        $value = empty($params) ? $query : $this->wpdb->prepare($query, $params);
        return is_string($value) ? $value : '';
    }
}
