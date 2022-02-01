<?php

declare(strict_types=1);

/**
 * Valid SQL Assertions
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Exceptions\ParserException;

trait SQLAssertionsTrait
{
    /**
     * Checks if the passed SQL Query is valid.
     *
     * @param string $sql
     * @param string|null $message Passing nothing will return a semi meaningful message.
     * @return void
     */
    public function assertValidSQL(string $sql, ?string $message = null)
    {
        $parser = new Parser($sql, false);

        if (! empty($parser->errors)) {
            // Get all unique errors
            $messages = array_unique(
                array_map(function (ParserException $e) {
                    return "--{$e->getMessage()}";
                }, $parser->errors)
            );

            // If we do not have a defined message, generate
            $message = $message ?? sprintf(
                'Query:: "%s" has %d errors %s%s',
                $sql,
                count($messages),
                PHP_EOL,
                join(\PHP_EOL, $messages)
            );

            $this->fail($message);
        } else {
            $this->assertTrue(true);
        }
    }
}
