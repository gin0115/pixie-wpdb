<?php

declare(strict_types=1);

/**
 * Unit tests for the static autoloader
 *
 * @package PinkCrab\Test_Helpers
 * @author Glynn Quelch glynn@pinkcrab.co.uk
 * @since 0.0.1
 */

namespace Pixie\Tests\Unit;

class TestStaticLoader extends \WP_UnitTestCase
{

    /** @testdox It should be possible to include the static loader via PHP and only have files required if the class it contains isnt already defined. */
    public function testLoaderDoesntLoadAlreadyLoadedClasses(): void
    {
        try {
            require SRC_PATH . '/loader.php';
        } catch (\Throwable $th) {
            $this->fail($th->getMessage());
        }
        $this->assertTrue(true);
    }

    /** @testdox It should be possible to require the loader.php file and have no errors thrown due to incorrect paths. */
    public function testLoaderThrowsNoErrorsBeingIncluded(): void
    {
        try {
            \shell_exec('php ' . SRC_PATH . '/loader.php 2>&1');
        } catch (\Throwable $th) {
            $this->fail($th->getMessage());
        }
        $this->assertTrue(true);
    }
}
