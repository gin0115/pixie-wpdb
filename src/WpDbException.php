<?php

declare(strict_types=1);

/**
 * Exception thrown when WPDB reports an error during query execution.
 *
 * Only thrown when the connection has been configured with the opt-in
 * Connection::THROW_ON_ERROR flag; by default WPDB errors continue to fail
 * silently to preserve backwards compatibility.
 */

namespace Pixie;

use Pixie\Exception;
use wpdb;

use function sprintf;

class WpDbException extends Exception
{
    /**
     * Builds an exception from the last error held by a WPDB instance.
     *
     * @param wpdb       $wpdb the WPDB instance whose last_error will be read
     * @param string|null $sql  the SQL statement that triggered the error, if known
     */
    public static function fromWpdb(wpdb $wpdb, ?string $sql = null): self
    {
        $message = '' !== $wpdb->last_error
            ? $wpdb->last_error
            : 'Unknown WPDB error';

        if (null !== $sql && '' !== $sql) {
            $message .= sprintf(' [SQL: %s]', $sql);
        }

        return new self($message);
    }
}
