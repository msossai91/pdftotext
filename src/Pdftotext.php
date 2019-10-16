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

use Symfony\Component\Process\Process;
use function array_map;
use function array_merge;
use function array_reduce;
use function explode;
use function gettype;
use function is_readable;
use function is_string;
use function sprintf;
use function trim;

final class Pdftotext
{
    /**
     * Default process timeout in seconds.
     */
    public const DEFAULT_PROCESS_TIMEOUT = 60.0;

    /**
     * @var string
     */
    private $binPath;

    /**
     * @var array
     */
    private $defaultOptions = [];

    /**
     * @var float|null
     */
    private $timeout;

    /**
     * New instance.
     *
     * @param ?float $timeout
     */
    public function __construct(string $binPath, array $defaultOptions = [], ?float $timeout = self::DEFAULT_PROCESS_TIMEOUT)
    {
        $this->binPath = $binPath;
        $this->setDefaultOptions($defaultOptions);
        $this->setTimeout($timeout);
    }

    /**
     * Returns a new instance from a Unix based OS.
     *
     * @param string[] $defaultOptions
     * @param ?float   $timeout
     *
     * @throws FileNotFound
     */
    public static function fromUnix(array $defaultOptions = [], ?float $timeout = self::DEFAULT_PROCESS_TIMEOUT): self
    {
        $process = Process::fromShellCommandline('which pdftotext');
        $process->run();
        if ($process->isSuccessful()) {
            $binPath = trim($process->getOutput());

            return new self($binPath, $defaultOptions, $timeout);
        }

        throw new FileNotFound('The pdftotext executable could not be auto-detected.');
    }

    /**
     * Set pdftotext default options.
     */
    public function setDefaultOptions(array $defaultOptions): void
    {
        $this->defaultOptions = $this->filterOptions($defaultOptions);
    }

    /**
     * Sets the process timeout.
     *
     * @param ?float $timeout
     */
    public function setTimeout(?float $timeout): void
    {
        if (null === $timeout || 0.0 === $timeout) {
            $this->timeout = null;

            return;
        }

        if ($timeout < 0) {
            throw new \InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
        }

        $this->timeout = $timeout;
    }

    /**
     * Convert the PDF to raw text and save it to a destination file.
     *
     * @param \SplFileInfo|string   $pdfPath
     * @param \SplFileObject|string $destPath
     * @param string[]              $options
     */
    public function save($pdfPath, $destPath, array $options = []): int
    {
        $destFile = $this->filterOutputPath($destPath);
        $text = $this->extract($pdfPath, $options);
        $bytes = $destFile->fwrite($text);

        if (is_string($destPath)) {
            $destFile = null;
        }

        if ($bytes === null || 0 === $bytes) {
            throw new FileNotSaved('The Converted PDF could not be saved.');
        }

        return $bytes;
    }

    /**
     * Extracts the PDF to raw text using the binary and symfony process.
     *
     * @param \SplFileInfo|string $pdfPath
     * @param string[]            $options
     */
    public function extract($pdfPath, array $options = []): string
    {
        $path = $this->filterInputPath($pdfPath);
        $options = $this->mergeOptions($options);
        $process = new Process(array_merge([$this->binPath], $options, [$path, '-']));

        $process->setTimeout($this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailed($process);
        }

        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }

    /**
     * Filter pdftotext options.
     *
     * @param string[] $options
     *
     * @return string[]
     */
    private function filterOptions(array $options): array
    {
        $mapper = function (string $content): array {
            $content = trim($content);
            if ('-' !== ($content[0] ?? '')) {
                $content = '-'.$content;
            }
            return explode(' ', $content, 2);
        };

        $reducer = function (array $carry, array $option): array {
            return array_merge($carry, $option);
        };

        return array_reduce(array_map($mapper, $options), $reducer, []);
    }

    /**
     * pdftotext merge options.
     *
     * @param string[] $options
     *
     * @return string[]
     */
    private function mergeOptions(array $options): array
    {
        $options = $this->filterOptions($options);
        if ([] === $options) {
            return $this->defaultOptions;
        }

        if ([] === $this->defaultOptions) {
            return $options;
        }

        $mapper = function (string $option): array {
            return explode(' ', $option, 2) + [1 => null];
        };

        $defaultOptions = $this->defaultOptions;
        $splitOptions = array_map($mapper, $options);

        foreach (array_map($mapper, $defaultOptions) as $offset => [$optionName, $optionValue]) {
            foreach ($splitOptions as [$name, $value]) {
                if ($optionName === $name) {
                    unset($defaultOptions[$offset]);
                }
            }
        }

        return array_merge($defaultOptions, $options);
    }

    /**
     * @param \SplFileObject|string $path the path where to save the converted to text PDF content.
     */
    private function filterOutputPath($path): \SplFileObject
    {
        if ($path instanceof \SplFileObject) {
            return $path;
        }

        if (!is_string($path)) {
            throw new \TypeError(sprintf('the destination path must be a SplFileObject object or a string `%s` given', gettype($path)));
        }

        return new \SplFileObject($path, 'w+');
    }

    /**
     * @param \SplFileInfo|string $path the PDF document path
     *
     * @throws FileNotFound If the file can not be found
     * @throws \TypeError   If the path type is not supported
     */
    private function filterInputPath($path): string
    {
        if (is_string($path)) {
            if (is_readable($path)) {
                return $path;
            }

            throw new FileNotFound(sprintf('could not find or read pdf `%s`', $path));
        }

        if (!$path instanceof \SplFileInfo) {
            throw new \TypeError(sprintf('the PDF path must be a SplFileInfo object or a scalar `%s` given', gettype($path)));
        }

        if ($path->isReadable()) {
            return (string) $path->getRealPath();
        }

        throw new FileNotFound(sprintf('could not find or read pdf `%s`', $path->getPathname()));
    }
}
