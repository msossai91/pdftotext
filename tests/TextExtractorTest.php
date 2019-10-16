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
use const PHP_OS;

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

    public function setUp(): void
    {
        parent::setUp();

        if (0 !== stripos(PHP_OS, 'darwin')) {
            $this->binPath = '/usr/bin/pdftotext';
        }
    }

    /**
     * @covers ::__construct
     * @covers ::extract
     */
    public function testExtract(): void
    {
        $text = (new Pdftotext($this->binPath))->extract($this->dummyPdf);

        self::assertSame($this->dummyPdfText, $text);
    }

    /**
     * @covers ::fromUnix
     */
    public function testFromUnix(): void
    {
        $extractor = Pdftotext::fromUnix();
        $text = $extractor->extract($this->dummyPdf);

        self::assertSame($this->dummyPdfText, $text);
    }

    /**
     * @covers ::__construct
     * @covers ::extract
     * @covers ::filterOptions
     */
    public function testExtractFilenameWithSpaces(): void
    {
        $pdfPath = __DIR__.'/data/dummy with spaces in its name.pdf';
        $text = Pdftotext::fromUnix()->extract($pdfPath);

        self::assertSame($this->dummyPdfText, $text);
    }

    /**
     * @covers ::extract
     */
    public function testExtractFilenameWithSingleQuotes(): void
    {
        $pdfPath = __DIR__.'/data/dummy\'s_file.pdf';
        $text = Pdftotext::fromUnix()->extract($pdfPath);

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
            ->extract(__DIR__.'/data/scoreboard.pdf')
        ;

        self::assertStringContainsString('Charleroi 50      28     13 11 4', $text);
    }

    /**
     * @covers ::filterInputPath
     * @covers ::mergeOptions
     * @covers ::extract
     */
    public function testExtractWithOptionsStartingWithHyphen(): void
    {
        $converter = Pdftotext::fromUnix();
        $text = $converter->extract(new \SplFileObject(__DIR__.'/data/scoreboard.pdf', 'r'), ['-layout']);

        self::assertStringContainsString('Charleroi 50      28     13 11 4', $text);
    }

    /**
     * @covers ::mergeOptions
     * @covers ::filterOutputPath
     * @covers ::extract
     * @covers ::save
     */
    public function testExtractWithTextSavedToFile(): void
    {
        Pdftotext::fromUnix(['-layout'])->save(
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
        Pdftotext::fromUnix()->extract('/no/pdf/here/dummy.pdf');
    }

    /**
     * @covers ::filterInputPath
     */
    public function testExtractThrowsExceptionIfTheSplFileInfoFileIsNotFound(): void
    {
        $this->expectException(FileNotFound::class);
        Pdftotext::fromUnix()->extract(new \SplFileInfo('/no/pdf/here/dummy.pdf'));
    }

    /**
     * @covers ::filterInputPath
     */
    public function testExtractThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        Pdftotext::fromUnix()->extract(['/no/pdf/here/dummy.pdf']);
    }

    /**
     * @covers ::extract
     */
    public function testExtractThrowsExceptionIfTheBinaryIsNotFound(): void
    {
        $this->expectException(ProcessFailed::class);
        (new Pdftotext('/there/is/no/place/like/home/pdftotext'))
            ->extract($this->dummyPdf);
    }

    /**
     * @covers ::extract
     */
    public function testExtractThrowsExceptionIfTheOptionsIsInvalid(): void
    {
        $this->expectException(ProcessFailed::class);
        Pdftotext::fromUnix(['-foo'])->extract($this->dummyPdf);
    }

    /**
     * @covers ::filterOutputPath
     * @covers ::extract
     * @covers ::save
     */
    public function testExtractThrowsExceptionIfTheDestinationFileTypeIsNotSupported(): void
    {
        $this->expectException(\TypeError::class);
        Pdftotext::fromUnix(['-foo'])->save($this->dummyPdf, []);
    }


    /**
     * @covers ::filterOutputPath
     * @covers ::extract
     * @covers ::save
     */
    public function testExtractThrowsExceptionIfTheDestinationFileIsNotWritable(): void
    {
        $this->expectException(FileNotSaved::class);
        (new Pdftotext($this->binPath, ['-layout']))->save($this->dummyPdf, new \SplFileObject($this->dummyPdf, 'r'));
    }

    /**
     * @covers ::setTimeout
     */
    public function testAddingTimeoutConditions(): void
    {
        $converter = new Pdftotext($this->binPath);
        $converter->setDefaultOptions(['-layout']);
        $converter->setTimeout(null);

        $text = $converter->extract(new \SplFileObject(__DIR__.'/data/scoreboard.pdf', 'r'));
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
