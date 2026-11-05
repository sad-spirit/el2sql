<?php

/*
 * This file is part of sad_spirit/el2sql:
 * Translator of Symfony's ExpressionLanguage to Postgres SQL dialect
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\el2sql\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\Connection;

/**
 * Contains helper methods for opening connections to the DB and executing SQL fixture files
 */
abstract class DatabaseBackedTestCase extends TestCase
{
    protected static ?Connection $connection = null;

    public static function setUpBeforeClass(): void
    {
        if ('' === CONNECTION_STRING) {
            self::markTestSkipped('Connection string is not configured');
        }
        self::$connection = new Connection(CONNECTION_STRING);
    }

    protected static function executeSqlFromFile(Connection $connection, string ...$files): void
    {
        $queries = [];
        foreach ($files as $file) {
            if (false === ($contents = @\file_get_contents(__DIR__ . '/assets/db/' . $file))) {
                self::fail('Failed to read fixture file ' . $file);
            }
            $queries = [...$queries, ...\preg_split('/;\s*/', $contents, -1, \PREG_SPLIT_NO_EMPTY) ?: []];
        }
        $connection->atomic(static function (Connection $connection) use ($queries): void {
            foreach ($queries as $query) {
                $connection->execute($query);
            }
        });
    }
}
