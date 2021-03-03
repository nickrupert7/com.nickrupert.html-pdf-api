<?php

namespace App\Helpers;

use App\Exceptions\CouldNotCreatePdfException;
use App\Exceptions\CouldNotCreateSourceException;
use App\Exceptions\CouldNotFindOutputException;
use App\Exceptions\CouldNotFindSourceException;
use App\Exceptions\EngineNotFoundException;
use App\Exceptions\PdfCreationFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;

class HtmlToPdf
{
    //region Base
    /**
     * Compatible Chromium utilities.
     * @var string[]
     */
    protected const ENGINES = [
        'chromium-browser',
        'chrome',
        '/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome'
    ];

    /**
     * Name of working engine utility.
     * @var string
     */
    protected $engine;

    /**
     * Unique identifier for this builder.
     * @var string
     */
    protected $uuid;

    /**
     * HTML source
     * @var string
     */
    protected $html;

    /**
     * Use custom document sizing.
     * @var bool
     */
    protected $useCustomSize = false;

    /**
     * Standard document size.
     * @var string
     */
    protected $size = 'letter';

    /**
     * Orientation of standard document size.
     * @var string
     */
    protected $portrait = true;

    /**
     * Custom document width.
     * @var string
     */
    protected $width;

    /**
     * Custom document height.
     * @var string
     */
    protected $height;

    /**
     * HtmlToPdf constructor.
     * @param string $html
     * @throws EngineNotFoundException
     */
    public function __construct(string $html)
    {
        $this->html = $html;
        $this->engine = static::getEngine();
        $this->uuid = Uuid::uuid4()->toString();
    }
    //endregion

    //region Basic Helpers
    /**
     * Get path where HTML source files are stored.
     * @return string
     */
    protected function getSourceFolder(): string
    {
        return storage_path('html_source');
    }

    /**
     * Get path of HTML source file.
     * @return string
     */
    protected function getSourceFilePath(): string
    {
        return storage_path("html_source/{$this->uuid}.html");
    }

    /**
     * Get path where PDF output files are stored.
     * @return string
     */
    protected function getOutputFolder(): string
    {
        return storage_path('pdf_output');
    }

    /**
     * Get path of PDF output file.
     * @return string
     */
    protected function getOutputFilePath(): string
    {
        return storage_path("pdf_output/{$this->uuid}.pdf");
    }

    /**
     * Determine whether the specified engine is installed on the current host.
     * @param string $engine
     * @return bool
     */
    protected static function engineExists(string $engine): bool
    {
        $return = shell_exec(sprintf("which %s", $engine));
        return !empty($return);
    }

    /**
     * Get the first engine in the list that is installed on this host.
     * @return string
     * @throws EngineNotFoundException
     */
    protected static function getEngine(): string
    {
        foreach (static::ENGINES as $engine) {
            if (static::engineExists($engine)) {
                return $engine;
            }
        }

        throw new EngineNotFoundException();
    }
    //endregion

    //region Builder Helpers
    /**
     * Get the CSS style block to be injected at the beginning of the HTML source to hide browser artifacts.
     * @return string
     */
    protected function getCss(): string
    {
        if ($this->useCustomSize) {
            $size = "{$this->width} {$this->height}";
        } else {
            $orientation = $this->portrait ? 'portrait' : 'landscape';
            $size = "{$this->size} {$orientation}";
        }

        return "<style>@media print{@page{margin:0;size:$size;}}</style>";
    }

    /**
     * Create the source HTML file.
     * @throws CouldNotCreateSourceException
     */
    protected function createSource(): void
    {
        $sourceFolder = $this->getSourceFolder();
        $sourceFilePath = $this->getSourceFilePath();

        $css = $this->getCss();
        $html = "$css\n{$this->html}";

        if (!is_writable($sourceFolder) ||
            is_file($sourceFilePath) ||
            file_put_contents($sourceFilePath, $html) === false
        ) {
            throw new CouldNotCreateSourceException();
        }
    }

    /**
     * Create the PDF from the HTML source file.
     * @throws CouldNotFindSourceException
     * @throws CouldNotCreatePdfException
     * @throws PdfCreationFailedException
     */
    protected  function createPdf()
    {
        $sourceFilePath = $this->getSourceFilePath();
        $outputFolder = $this->getOutputFolder();
        $outputFilePath = $this->getOutputFilePath();

        if (!is_file($sourceFilePath)) {
            echo $sourceFilePath;
            throw new CouldNotFindSourceException();
        }

        if (!is_writable($outputFolder) ||
            is_file($outputFilePath)
        ) {
            throw new CouldNotCreatePdfException();
        }

        $command = sprintf(
            '%s --headless --disable-gpu --print-to-pdf="%s" %s',
            $this->engine,
            $outputFilePath,
            $sourceFilePath
        );
        exec($command, $output, $status);

        if ($status !== 0) {
            throw new PdfCreationFailedException();
        }
    }

    /**
     * Get the contents of the created PDF file.
     * @return string
     * @throws CouldNotFindOutputException
     */
    protected function getPdfContents(): string
    {
        $outputFilePath = static::getOutputFilePath();

        if (!file_exists($outputFilePath)) {
            throw new CouldNotFindOutputException();
        }

        return file_get_contents($outputFilePath);
    }

    /**
     * Remove HTML source and PDF output files.
     */
    protected function cleanup(): void
    {
        $sourceFilePath = static::getSourceFilePath();
        $outputFilePath = static::getOutputFilePath();

        if (file_exists($sourceFilePath)) {
            unlink($sourceFilePath);
        }

        if (file_exists($outputFilePath)) {
            unlink($outputFilePath);
        }
    }
    //endregion

    //region Builders
    /**
     * Set a standard document size.
     * @param string $size
     * @return $this
     */
    public function size(string $size): HtmlToPdf
    {
        Validator::validate([
            'size' => $size
        ], [
            'size' => ['required', Rule::in(['A5', 'A4', 'A3', 'B5', 'B4', 'JIS-B5', 'JIS-B4', 'letter', 'legal', 'ledger'])]
        ], [
            'size.in' => 'Size must be an acceptable value for CSS page-size. See https://developer.mozilla.org/en-US/docs/Web/CSS/@page/size#values'
        ]);

        $this->useCustomSize = false;
        $this->size = $size;

        return $this;
    }

    /**
     * Set the document orientation.
     * @param string $orientation
     * @return $this
     */
    public function orientation(string $orientation): HtmlToPdf
    {
        Validator::validate([
            'orientation' => $orientation
        ], [
            'orientation' => ['required', Rule::in('portrait', 'landscape')]
        ], [
            'orientation.in' => 'Orientation must be "portrait" or "landscape"'
        ]);

        $orientation == 'landscape' ?  $this->landscape() : $this->portrait();

        return $this;
    }

    /**
     * Set the document orientation to portrait.
     * @return $this
     */
    public function portrait(): HtmlToPdf
    {
        $this->portrait = true;

        return $this;
    }

    /**
     * Set the document orientation to landscape.
     * @return $this
     */
    public function landscape(): HtmlToPdf
    {
        $this->portrait = false;

        return $this;
    }

    /**
     * Set a custom document dimensions.
     * @param string $width
     * @param string $height
     * @return $this
     */
    public function dimensions(string $width, string $height): HtmlToPdf
    {
        $format = '/^(\d+\.)?\d+((cm)|(mm)|(Q)|(in)|(pc)|(pt)|(px))$/i';

        Validator::validate([
            'width' => $width,
            'height' => $height
        ], [
            'width' => ['required', "regex:$format"], //Use array to prevent errors with pipe character |
            'height' => ['required', "regex:$format"] //Use array to prevent errors with pipe character |
        ], [
            'width.regex' => 'Width must be an acceptable value for CSS absolute length. See https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Values_and_Units#dimensions',
            'height.regex' => 'Height must be an acceptable value for CSS absolute length. See https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Values_and_Units#dimensions'
        ]);

        $this->useCustomSize = true;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Initiate start conversion and return PDF contents.
     * @return string|false
     */
    public function convert()
    {
        try {
            static::createSource();
            static::createPdf();
            $blob = static::getPdfContents();
        } catch (\Exception $exception) {
            Log::error($exception);
            $blob = false;
        } finally {
            static::cleanup();
        }

        return $blob;
    }
    //endregion
}
