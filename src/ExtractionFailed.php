<?php

/**
 * Bakame.PDF (http://github.com/bakame-php/pdftotext)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bakame\Pdftotext;

use Symfony\Component\Process\Exception\ProcessFailedException;

class ExtractionFailed extends ProcessFailedException implements Exception
{
}
