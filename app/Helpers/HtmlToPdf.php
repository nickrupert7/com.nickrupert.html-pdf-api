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
            if (self::engineExists($program)) {
                return $program;
            }
        }

        throw new EngineNotFoundException();
    }

    /**
     * @param string $html
     * @param string $uuid
     * @return string
     * @throws CouldNotCreateSourceException
     */
    protected static function createSource(string $html, string $uuid): string
    {
        $sourceFolder = self::getSourceFolder();
        $sourceFilePath = self::getSourceFilePath($uuid);

        //TODO: Inject CSS

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
        $sourceFilePath = self::getSourceFilePath($uuid);
        $outputFolder = self::getOutputFolder();
        $outputFilePath = self::getOutputFilePath($uuid);

        if (!is_file($sourceFilePath)) {
            echo $sourceFilePath;
            throw new CouldNotFindSourceException();
        }

        if (!is_writable($outputFolder) ||
            is_file($outputFilePath)
        ) {
            throw new CouldNotCreatePdfException();
        }

        $engine = self::getEngine();
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
        $outputFilePath = self::getOutputFilePath($uuid);

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
        $sourceFilePath = self::getSourceFilePath($uuid);
        $outputFilePath = self::getOutputFilePath($uuid);

        if (file_exists($sourceFilePath)) {
            unlink($sourceFilePath);
        }

        if (file_exists($outputFilePath)) {
            unlink($outputFilePath);
        }
    }

    /**
     * @param string $html
     * @return string|false
     */
    public static function convert(string $html): bool
    {
        $uuid = self::generateUuid();
        $blob = false;

        try {
            self::createSource($html, $uuid);
            self::createPdf($uuid);
            $blob = self::getPdfContents($uuid);
        } catch (\Exception $exception) {
            Log::error($exception);
        } finally {
            self::cleanup($uuid);
        }

        return $blob;
    }
}
