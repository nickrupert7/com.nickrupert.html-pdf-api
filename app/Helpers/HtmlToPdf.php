<?php

namespace App\Helpers;

use App\Exceptions\CouldNotCreatePdfException;
use App\Exceptions\CouldNotCreateSourceException;
use App\Exceptions\CouldNotFindOutputException;
use App\Exceptions\CouldNotFindSourceException;
use App\Exceptions\EngineNotFoundException;
use App\Exceptions\PdfCreationFailedException;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class HtmlToPdf
{
    protected static $engines = [
        '/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome',
        'chrome',
        'chromium-browser'
    ];

    protected static function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    protected static function getSourceFolder(): string
    {
        return storage_path('html_source');
    }

    protected static function getSourceFilePath(string $uuid): string
    {
        return storage_path("html_source/$uuid.html");
    }

    protected static function getOutputFolder(): string
    {
        return storage_path('pdf_output');
    }

    protected static function getOutputFilePath(string $uuid): string
    {
        return storage_path("pdf_output/$uuid.pdf");
    }

    /**
     * @param string $command
     * @return bool
     */
    protected static function engineExists(string $command): bool
    {
        $return = shell_exec(sprintf("which %s", $command));
        return !empty($return);
    }

    /**
     * @return string
     * @throws EngineNotFoundException
     */
    protected static function getEngine(): string
    {
        foreach (static::$engines as $program) {
            if (static::engineExists($program)) {
                return $program;
            }
        }

        throw new EngineNotFoundException();
    }

    protected static function getCss(string $width, string $height): string
    {
        return "<style>@media print{@page{margin: 0mm 0mm 0mm 0mm;size:$width $height;}}</style>";
    }

    /**
     * @param string $uuid
     * @param string $html
     * @param string $width
     * @param string $height
     * @return string
     * @throws CouldNotCreateSourceException
     */
    protected static function createSource(string $uuid, string $html, string $width, string $height): string
    {
        $sourceFolder = static::getSourceFolder();
        $sourceFilePath = static::getSourceFilePath($uuid);

        $css = static::getCss($width, $height);
        $html = "$css\n$html";

        if (!is_writable($sourceFolder) ||
            is_file($sourceFilePath) ||
            file_put_contents($sourceFilePath, $html) === false
        ) {
            throw new CouldNotCreateSourceException();
        }

        return $uuid;
    }

    /**
     * @param string $uuid
     * @throws CouldNotFindSourceException
     * @throws EngineNotFoundException
     * @throws CouldNotCreatePdfException
     * @throws PdfCreationFailedException
     */
    protected static function createPdf(string $uuid)
    {
        $sourceFilePath = static::getSourceFilePath($uuid);
        $outputFolder = static::getOutputFolder();
        $outputFilePath = static::getOutputFilePath($uuid);

        if (!is_file($sourceFilePath)) {
            echo $sourceFilePath;
            throw new CouldNotFindSourceException();
        }

        if (!is_writable($outputFolder) ||
            is_file($outputFilePath)
        ) {
            throw new CouldNotCreatePdfException();
        }

        $engine = static::getEngine();
        $command = sprintf(
            '%s --headless --disable-gpu --print-to-pdf="%s" %s',
            $engine,
            $outputFilePath,
            $sourceFilePath
        );
        exec($command, $output, $status);

        if ($status !== 0) {
            throw new PdfCreationFailedException();
        }
    }

    /**
     * @param string $uuid
     * @return string
     * @throws CouldNotFindOutputException
     */
    protected static function getPdfContents(string $uuid): string
    {
        $outputFilePath = static::getOutputFilePath($uuid);

        if (!file_exists($outputFilePath)) {
            throw new CouldNotFindOutputException();
        }

        return file_get_contents($outputFilePath);
    }

    /**
     * @param string $uuid
     */
    protected static function cleanup(string $uuid): void
    {
        $sourceFilePath = static::getSourceFilePath($uuid);
        $outputFilePath = static::getOutputFilePath($uuid);

        if (file_exists($sourceFilePath)) {
            unlink($sourceFilePath);
        }

        if (file_exists($outputFilePath)) {
            unlink($outputFilePath);
        }
    }

    /**
     * @param string $html
     * @param string $width
     * @param string $height
     * @return string|false
     */
    public static function convert(string $html, string $width = '8.5in', string $height = '11in'): bool
    {
        $uuid = static::generateUuid();
        $blob = false;

        try {
            static::createSource($uuid, $html, $width, $height);
            static::createPdf($uuid);
            $blob = static::getPdfContents($uuid);
        } catch (\Exception $exception) {
            Log::error($exception);
        } finally {
            static::cleanup($uuid);
        }

        file_put_contents('/Users/nick/Downloads/test.pdf', $blob);

        return $blob;
    }
}
