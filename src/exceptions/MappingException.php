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

namespace sad_spirit\el2sql\exceptions;

use sad_spirit\el2sql\Exception;

/**
 * Thrown when mapping a class to a variable name fails (usually due to invalid attributes)
 */
class MappingException extends \RuntimeException implements Exception
{
}
