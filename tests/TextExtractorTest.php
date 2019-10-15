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

namespace BakameTest\Pdftotext;

use Bakame\Pdftotext\FileNotFound;
use Bakame\Pdftotext\FileNotSaved;
use Bakame\Pdftotext\Pdftotext;
use Bakame\Pdftotext\ProcessFailed;
use PHPUnit\Framework\TestCase;
use function unlink;

/**
 * @coversDefaultClass \Bakame\Pdftotext\Pdftotext
 */
class TextExtractorTest extends TestCase
{
    /**
     * @var string
     */
    protected $dummyPdf = __DIR__.'/data/dummy.pdf';

    /**
     * @var string
     */
    protected $dummyPdfText = 'This is a dummy PDF';

    /**
     * @var string
     */
    protected $binPath = '/usr/local/bin/pdftotext';

    /**
     * @covers ::__construct
     * @covers ::toString
     */
    public function testExtract(): void
    {
        $text = (new Pdftotext($this->binPath))->toString($this->dummyPdf);

        self::assertSame($this->dummyPdfText, $text);
    }

    /**
     * @covers ::__construct
     * @covers ::toString
     * @covers ::filterOptions
     */
    public function testExtractFilenameWithSpaces(): void
    {
        $pdfPath = __DIR__.'/data/dummy with spaces in its name.pdf';
        $text = (new Pdftotext($this->binPath))->toString($pdfPath);

        self::assertSame($this->dummyPdfText, $text);
    }

    /**
     * @covers ::toString
     */
    public function testExtractFilenameWithSingleQuotes(): void
    {
        $pdfPath = __DIR__.'/data/dummy\'s_file.pdf';
        $text = (new Pdftotext($this->binPath))->toString($pdfPath);

        self::assertSame($this->dummyPdfText, $text);
    }

    /**
     * @covers ::filterInputPath
     * @covers ::setDefaultOptions
     * @covers ::mergeOptions
     * @covers ::filterOptions
     */
    public function testExtractWithOptionsWithoutHyphen(): void
    {
        $text = (new Pdftotext($this->binPath, ['layout']))
            ->toString(__DIR__.'/data/scoreboard.pdf')
        ;

        self::assertStringContainsString('Charleroi 50      28     13 11 4', $text);
    }

    /**
     * @covers ::binaryPath
     * @covers ::filterInputPath
     * @covers ::mergeOptions
     * @covers ::toString
     */
    public function testExtractWithOptionsStartingWithHyphen(): void
    {
        $converter = new Pdftotext($this->binPath);
        self::assertSame($this->binPath, $converter->binaryPath());

        $text = $converter->toString(new \SplFileObject(__DIR__.'/data/scoreboard.pdf', 'r'), ['-layout']);

        self::assertStringContainsString('Charleroi 50      28     13 11 4', $text);
    }

    /**
     * @covers ::mergeOptions
     * @covers ::filterOutputPath
     * @covers ::toString
     * @covers ::toFile
     */
    public function testExtractWithTextSavedToFile(): void
    {
        (new Pdftotext($this->binPath, ['-layout']))->toFile(
            __DIR__.'/data/scoreboard.pdf',
            __DIR__.'/data/scoreboard.txt',
            ['-layout']
        );

        /** @var string $expectedData */
        $expectedData = file(__DIR__.'/data/scoreboard.txt');
        self::assertStringContainsString('Charleroi 50      28     13 11 4', implode(PHP_EOL, $expectedData));

        unlink(__DIR__.'/data/scoreboard.txt');
    }

    /**
     * @covers ::filterInputPath
     */
    public function testExtractThrowsExceptionIfThePDFFileIsNotFound(): void
    {
        $this->expectException(FileNotFound::class);
        (new Pdftotext($this->binPath))->toString('/no/pdf/here/dummy.pdf');
    }

    /**
     * @covers ::filterInputPath
     */
    public function testExtractThrowsExceptionIfTheSplFileInfoFileIsNotFound(): void
    {
        $this->expectException(FileNotFound::class);
        (new Pdftotext($this->binPath))->toString(new \SplFileInfo('/no/pdf/here/dummy.pdf'));
    }

    /**
     * @covers ::filterInputPath
     */
    public function testExtractThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        (new Pdftotext($this->binPath))->toString(['/no/pdf/here/dummy.pdf']);
    }

    /**
     * @covers ::toString
     */
    public function testExtractThrowsExceptionIfTheBinaryIsNotFound(): void
    {
        $this->expectException(ProcessFailed::class);
        (new Pdftotext('/there/is/no/place/like/home/pdftotext'))
            ->toString($this->dummyPdf);
    }

    /**
     * @covers ::toString
     */
    public function testExtractThrowsExceptionIfTheOptionsIsInvalid(): void
    {
        $this->expectException(ProcessFailed::class);
        (new Pdftotext($this->binPath, ['-foo']))->toString($this->dummyPdf);
    }

    /**
     * @covers ::filterOutputPath
     * @covers ::toString
     * @covers ::toFile
     */
    public function testExtractThrowsExceptionIfTheDestinationFileTypeIsNotSupported(): void
    {
        $this->expectException(\TypeError::class);
        (new Pdftotext($this->binPath, ['-foo']))->toFile($this->dummyPdf, []);
    }


    /**
     * @covers ::filterOutputPath
     * @covers ::toString
     * @covers ::toFile
     */
    public function testExtractThrowsExceptionIfTheDestinationFileIsNotWritable(): void
    {
        $this->expectException(FileNotSaved::class);
        (new Pdftotext($this->binPath, ['-layout']))->toFile($this->dummyPdf, new \SplFileObject($this->dummyPdf, 'r'));
    }

    /**
     * @covers ::setTimeout
     */
    public function testAddingTimeoutConditions(): void
    {
        $converter = new Pdftotext($this->binPath);
        $converter->setDefaultOptions(['-layout']);
        $converter->setTimeout(null);

        $text = $converter->toString(new \SplFileObject(__DIR__.'/data/scoreboard.pdf', 'r'));
        self::assertStringContainsString('Charleroi 50      28     13 11 4', $text);
    }

    /**
     * @covers ::setTimeout
     */
    public function testAddingTimoutConditionsFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Pdftotext($this->binPath))->setTimeout(-0.1);
    }
}
