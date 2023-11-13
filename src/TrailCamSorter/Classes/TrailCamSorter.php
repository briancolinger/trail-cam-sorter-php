<?php

namespace Briancolinger\TrailCamSorterPhp\TrailCamSorter\Classes;

use Briancolinger\TrailCamSorterPhp\TrailCamSorter\Enums\IgnoredFiles;
use Briancolinger\TrailCamSorterPhp\TrailCamSorter\Enums\MediaFormat;
use DateTime;
use Exception;
use FilesystemIterator;
use GdImage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use stdClass;

class TrailCamSorter
{
    /**
     * @var int
     */
    protected const FRAME_LIMIT = 100;

    /**
     * @var int
     */
    protected const FRAME_SKIP = 10;

    /**
     * @var int
     */
    protected const FRAME_RATE = 30;

    /**
     * @var string
     */
    protected string $inputDir;

    /**
     * @var string
     */
    protected string $outputDir;

    /**
     * @var ?string
     */
    protected ?string $outputFile = null;

    /**
     * @var bool
     */
    protected bool $debug = false;

    /**
     * @var string
     */
    protected string $debugDir;

    /**
     * @var bool
     */
    protected bool $dryRun = true;

    /**
     * @var int
     */
    protected int $limit = 0;

    /**
     * @var int
     */
    protected int $remainingLimit;

    /**
     * @var int
     */
    protected int $filesProcessed = 0;

    /**
     * @var array
     */
    protected array $inputFiles = [];

    /**
     * @var SplFileInfo
     */
    protected SplFileInfo $inputFile;

    /**
     * @var TrailCamData
     */
    protected TrailCamData $trailCamData;

    /**
     * @var int
     */
    protected int $inputFilesTotal = 0;

    /**
     * @var DateTime
     */
    protected DateTime $timerStart;

    /**
     * @var string
     */
    protected string $ffmpegPath;

    /**
     * @var string
     */
    protected string $tesseractPath;

    /**
     * @var string
     */
    protected string $tessdataPath;

    /**
     * @var array
     */
    protected array $cameraNameCorrections = [];

    /**
     * @var string
     */
    protected string $cameraNameCorrectionsFile;

    /**
     * TrailCamSorter constructor.
     */
    public function __construct()
    {
    }

    /**
     * Initialize the script.
     *
     * @return void
     * @throws Exception
     */
    private function init(): void
    {
        // Start the timer.
        $this->setTimerStart(new DateTime());

        // Set the CLI options for the script.
        $this->loadOptions();

        // Check if the input directory exists.
        $this->checkInputDir();

        // Load the ffmpeg path.
        $this->loadFfmpegPath();

        // Load the tesseract path.
        $this->loadTesseractPath();

        // Load the tessdata path.
        $this->loadTessdataPath();

        // Create the output directory if it doesn't exist.
        $this->createOutputDir();

        // Create the debug directory if debug mode is enabled.
        $this->createDebugDir();

        // Initialize the limit counter.
        $this->setRemainingLimit($this->getLimit());

        // Load the camera name corrections.
        $this->loadCameraNameCorrections();
    }

    /**
     * Process files in the input directory.
     *
     * @return bool
     * @throws Exception
     */
    public function processFiles(): bool
    {
        // Initialize the script.
        $this->init();

        // Delete IgnoredFiles::DS_STORE and IgnoredFiles::THUMBS_DB.
        $this->deleteIgnoredFiles($this->getInputDir());

        // Load the input files sorted alphabetically.
        $this->loadInputFiles($this->getInputDir());

        foreach ($this->getInputFiles() as $inputFile) {
            // Set the input file.
            $this->setInputFile($inputFile);

            // Skip if the file is an ignored file.
            if ($this->isIgnoredFile()) {
                continue;
            }

            // Check if the file has an allowed media format extension.
            if (!$this->isAllowedMediaFormat()) {
                continue;
            }

            // Run the do process file loop.
            if (!$this->doProcessFile()) {
                return false;
            }

            // Print the status.
            $this->printStatus();

            // Delete empty directories.
            $this->deleteEmptyDirs(dirname($this->getInputFile()->getPath()));

            // Increment the files processed counter.
            $this->incrementFilesProcessed();

            // Check if the processing limit has been reached.
            if ($this->hasReachedLimit()) {
                break;
            }
        }

        // Delete empty directories.
        $this->deleteEmptyDirs($this->getInputDir());

        return true;
    }

    /**
     * Run the do process file loop.
     *
     * @return bool
     */
    private function doProcessFile(): bool
    {
        // Loop through the frames.
        for ($frameNum = 1; $frameNum <= self::FRAME_LIMIT; $frameNum += self::FRAME_SKIP) {
            try {
                // Process the file.
                $this->setTrailCamData($this->processFrame($frameNum));

                // Set the output file.
                $this->initOutputFile();

                // Rename the file.
                $this->renameFile($this->getInputFile()->getPathname(), $this->getOutputFile());

                // Break out of the loop.
                break;
            } catch (Exception $e) {
                // Skip to the next frame if an exception is thrown.
                echo $e->getMessage() . PHP_EOL;
                continue;
            }
        }

        return true;
    }

    /**
     * Process a frame from the video file.
     *
     * @param int $frameNum
     *
     * @return TrailCamData
     * @throws Exception
     */
    private function processFrame(int $frameNum): TrailCamData
    {
        // Get a frame from the video file.
        $frameImage = $this->loadFrame($frameNum);

        // Get the bounding boxes.
        $boundingBoxes = $this->createBoundingBoxes($frameImage);

        // Create a labeled image with cropped bounding boxes.
        $labeledImage = $this->createJoinedImage($boundingBoxes, $frameImage);

        // Run OCR on the labeled image.
        $ocrText = $this->runOCR($labeledImage);
        if ($ocrText === null) {
            throw new Exception('OCR failed.');
        }

        // Parse the OCR text.
        return $this->parseOcrText($ocrText);
    }

    /**
     * @param string $inputDir
     *
     * @return void
     */
    private function deleteIgnoredFiles(string $inputDir): void
    {
        foreach (glob($inputDir . '/*/' . IgnoredFiles::DS_STORE->value) as $file) {
            unlink($file);
        }

        foreach (glob($inputDir . '/*/' . IgnoredFiles::THUMBS_DB->value) as $file) {
            unlink($file);
        }
    }

    /**
     * Delete empty directories recursively.
     *
     * @param string $dir
     *
     * @return void
     * @throws Exception
     */
    private function deleteEmptyDirs(string $dir): void
    {
        if ($this->isDryRun()) {
            return;
        }

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            ) as $path => $info
        ) {
            if ($info->isDir() && count(scandir($path)) === 2) {
                if (!rmdir($path)) {
                    throw new Exception('Failed to delete empty directory: ' . $path);
                }
            }
        }
    }

    /**
     * Print the status.
     *
     * @return void
     */
    private function printStatus(): void
    {
        // Output buffer.
        $output = [];

        $filesProcessed = number_format($this->getFilesProcessed() + 1);
        $filesTotal     = number_format($this->getInputFilesTotal());
        $percent        = number_format(($this->getFilesProcessed() + 1) / $this->getInputFilesTotal() * 100, 2) . '%';

        $output[] = sprintf('Progress: %s of %s (%s)', $filesProcessed, $filesTotal, $percent);
        $output[] = 'Input File: ' . $this->getInputFile();
        $output[] = 'Output File: ' . $this->getOutputFile();
        $output[] = 'Timestamp: ' . $this->getTrailCamData()->getTimestamp()->format('Y-m-d H:i:s');
        $output[] = 'Camera Name: ' . $this->getTrailCamData()->getCameraName();
        $output[] = sprintf('Elapsed Time: %s', (new DateTime())->diff($this->getTimerStart())->format('%H:%I:%S'));

        echo implode(PHP_EOL, $output) . PHP_EOL . PHP_EOL;
    }

    /**
     * Rename the file.
     *
     * @param string $from
     * @param string $to
     *
     * @return void
     * @throws Exception
     */
    private function renameFile(string $from, string $to): void
    {
        if ($this->isDryRun()) {
            return;
        }

        // Create the directory for the renamed file if it doesn't exist.
        if (!is_dir(dirname($to))) {
            if (!mkdir(dirname($to), 0755, true)) {
                throw new Exception('Failed to create directory for renamed file.');
            }
        }

        // Rename the file.
        if (!rename($from, $to)) {
            throw new Exception('Failed to rename file.');
        }
    }

    /**
     * Set the CLI options for the script.
     *
     * @return void
     * @throws Exception
     */
    private function loadOptions(): void
    {
        // Define option definitions with names, data types, and descriptions.
        $parser = new CommandLineParser([
            [
                'name'        => 'input',
                'type'        => 'string',
                'default'     => '.',
                'description' => 'The input directory containing video files.'
            ],
            [
                'name'        => 'output',
                'type'        => 'string',
                'default'     => '.',
                'description' => 'The output directory for sorted video files.'
            ],
            [
                'name'        => 'dry-run',
                'type'        => 'bool',
                'default'     => true,
                'description' => 'Enable dry run mode.'
            ],
            [
                'name'        => 'limit',
                'type'        => 'int',
                'default'     => 0,
                'description' => 'Set the processing limit.'
            ],
            [
                'name'        => 'debug',
                'type'        => 'bool',
                'default'     => false,
                'description' => 'Enable debug mode.'
            ],
            [
                'name'        => 'corrections',
                'type'        => 'string',
                'default'     => 'camera-name-corrections.json',
                'description' => 'Camera name corrections file.'
            ],
            [
                'name'        => 'tessdata',
                'type'        => 'string',
                'default'     => '/usr/local/share/tessdata/',
                'description' => 'tessdata directory path.'
            ],
        ]);

        // Parse the command line arguments.
        $this->setInputDir(rtrim($parser->getOption('input'), '/'));
        $this->setOutputDir(rtrim($parser->getOption('output'), '/'));
        $this->setDryRun($parser->getOption('dry-run'));
        $this->setLimit($parser->getOption('limit'));
        $this->setDebug($parser->getOption('debug'));
        $this->setCameraNameCorrectionsFile($parser->getOption('corrections'));
        $this->setTessdataPath(rtrim($parser->getOption('tessdata'), '/'));

        if (empty($this->getInputDir())) {
            throw new Exception('Input directory is required.');
        }

        if (empty($this->getOutputDir())) {
            throw new Exception('Output directory is required.');
        }
    }

    /**
     * Load the input files sorted alphabetically.
     *
     * @param string $inputDir
     *
     * @return void
     */
    private function loadInputFiles(string $inputDir): void
    {
        $filePaths = [];
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($inputDir, FilesystemIterator::SKIP_DOTS)
            ) as $file
        ) {
            // Skip if the item is not a file.
            if (!$file->isFile()) {
                continue;
            }

            /**
             * @var SplFileInfo $file
             */
            $filePaths[] = $file;
        }

        // Sort the file paths naturally.
        natsort($filePaths);

        // Set the input files.
        $this->setInputFiles($filePaths);
        $this->setInputFilesTotal(count($filePaths));
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadCameraNameCorrections(): void
    {
        if (empty($this->getCameraNameCorrectionsFile())) {
            return;
        }

        $corrections = json_decode(file_get_contents($this->getCameraNameCorrectionsFile()), true);
        if ($corrections === null) {
            throw new Exception('Failed to load camera name corrections.');
        }

        $this->setCameraNameCorrections($corrections);
    }

    /**
     * Load a frame from a video file.
     *
     * @param int $frameNum
     *
     * @return GdImage
     * @throws Exception
     */
    private function loadFrame(int $frameNum): GdImage
    {
        // Calculate the video timestamp based on frame number and frame rate.
        $timestamp = intval($frameNum / self::FRAME_RATE);

        // Format the timestamp as 'HH:MM:SS' (hours:minutes:seconds).
        $formattedTimestamp = sprintf(
            '%02d:%02d:%02d',
            floor($timestamp / 3600),
            floor(($timestamp % 3600) / 60),
            floor($timestamp % 60)
        );

        // Construct the ffmpeg command to read a single frame into memory.
        $ffmpegCommand = [
            $this->getFfmpegPath(),
            '-loglevel',
            '-8', // Suppress warnings and info messages
            '-i',
            $this->getInputFile()->getPathName(), // Input file
            '-ss',
            $formattedTimestamp, // Seek to the calculated timestamp
            '-vframes',
            $frameNum, // Capture 1 frame
            '-f',
            'image2pipe', // Output format
            '-vcodec',
            'mjpeg', // MJPEG codec
            '-', // Output to stdout
        ];

        // Execute the ffmpeg command and capture the output.
        $frameData = shell_exec(implode(' ', array_map('escapeshellarg', $ffmpegCommand)));
        if ($frameData === null) {
            throw new Exception('Failed to execute ffmpeg command.');
        }

        // Get the image resource from frame data.
        $frameImage = imagecreatefromstring($frameData);
        if (!($frameImage instanceof GdImage)) {
            throw new Exception('Failed to create image from frame data.');
        }

        $this->writeDebugImage($frameImage, $frameNum . '-frame');

        return $frameImage;
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadFfmpegPath(): void
    {
        $path = trim(shell_exec('which ffmpeg'));
        if (!$path) {
            throw new Exception('Failed to load ffmpeg path.');
        }

        $this->setFfmpegPath($path);
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadTesseractPath(): void
    {
        $path = trim(shell_exec('which tesseract'));
        if (!$path) {
            throw new Exception('Failed to load tesseract path.');
        }

        $this->setTesseractPath($path);
    }

    /**
     * @return void
     * @throws Exception
     */
    private function loadTessdataPath(): void
    {
        if (!is_dir($this->getTessdataPath())) {
            throw new Exception('tessdata directory does not exist.');
        }
    }

    /**
     * @return void
     */
    private function initOutputFile(): void
    {
        // Set the output file in the format:
        // 'output_dir/camera_name/YYYY-MM-DD/camera_name-YYYY-MM-DD-HH-MM-SS.ext'
        $outputFile = sprintf(
            '%s/%s/%s/%s-%s.%s',
            $this->getOutputDir(),
            $this->getTrailCamData()->getCameraName(),
            $this->getTrailCamData()->getTimestamp()->format('Y-m-d'),
            $this->getTrailCamData()->getCameraName(),
            $this->getTrailCamData()->getTimestamp()->format('Y-m-d-H-i-s'),
            strtolower($this->getInputFile()->getExtension())
        );

        // Append a number to the output file if it already exists.
        $i = 1;
        while (file_exists($outputFile)) {
            // 'output_dir/camera_name/YYYY-MM-DD/camera_name-YYYY-MM-DD-HH-MM-SS-1.ext'
            $outputFile = sprintf(
                '%s/%s/%s/%s-%s-%d.%s',
                $this->getOutputDir(),
                $this->getTrailCamData()->getCameraName(),
                $this->getTrailCamData()->getTimestamp()->format('Y-m-d'),
                $this->getTrailCamData()->getCameraName(),
                $this->getTrailCamData()->getTimestamp()->format('Y-m-d-H-i-s'),
                $i,
                strtolower($this->getInputFile()->getExtension())
            );
            $i++;
        }

        $this->setOutputFile($outputFile);
    }

    /**
     * Check if the processing limit has been reached.
     *
     * @return bool
     */
    private function hasReachedLimit(): bool
    {
        // Decrement the remaining limit if it's not unlimited.
        if ($this->getLimit() > 0) {
            $this->setRemainingLimit($this->getRemainingLimit() - 1);
            if ($this->getRemainingLimit() === 0) {
                // Reached the processing limit.
                return true;
            }
        }

        return false;
    }

    /**
     * @return void
     */
    private function incrementFilesProcessed(): void
    {
        $this->setFilesProcessed($this->getFilesProcessed() + 1);
    }

    /**
     * Calculate pixel coordinates for each label and return a list of bounding boxes.
     *
     * @param GdImage $image
     *
     * @return array
     * @throws Exception
     */
    private function createBoundingBoxes(GdImage $image): array
    {
        // Create an array to store the bounding boxes.
        $boundingBoxes = [];

        // Get image dimensions.
        $width  = imagesx($image);
        $height = imagesy($image);
        if (($width === false) || ($height === false)) {
            throw new Exception('Failed to get image dimensions.');
        }

        // Loop through the label coordinates and create bounding boxes for each label.
        foreach (LabelCoordinates::cases() as $label => $coords) {
            // Calculate the pixel values for the bounding box.
            list($x, $y, $w, $h) = $coords;

            // Create the bounding box array.
            $boundingBox = [
                'Label' => $label,
                'Rect'  => [
                    'Left'   => intval(($x - $w / 2) * $width),
                    'Top'    => intval(($y - $h / 2) * $height),
                    'Right'  => intval(($x + $w / 2) * $width),
                    'Bottom' => intval(($y + $h / 2) * $height),
                ],
            ];

            // Add the bounding box to the list.
            $boundingBoxes[] = $boundingBox;
        }

        return $boundingBoxes;
    }

    /**
     * Create a joined image with cropped bounding boxes.
     *
     * @param array   $boundingBoxes
     * @param GdImage $frame
     *
     * @return GdImage|bool
     * @throws Exception
     */
    private function createJoinedImage(array $boundingBoxes, GdImage $frame): GdImage | bool
    {
        // Loop through each bounding box and crop out the image.
        $croppedImages = [];
        foreach ($boundingBoxes as $box) {
            // Crop out the bounding box.
            $cropped = imagecrop($frame, [
                'x'      => $box['Rect']['Left'],
                'y'      => $box['Rect']['Top'],
                'width'  => $box['Rect']['Right'] - $box['Rect']['Left'],
                'height' => $box['Rect']['Bottom'] - $box['Rect']['Top'],
            ]);

            // Check if cropping was successful.
            if (!($cropped instanceof GdImage)) {
                throw new Exception('Failed to crop image.');
            }

            // Convert the image to grayscale.
            if (!imagefilter($cropped, IMG_FILTER_GRAYSCALE)) {
                throw new Exception('Failed to convert image to grayscale.');
            }

            // Create a blank template image.
            $templateWidth  = 520;
            $templateHeight = 60;
            $template       = imagecreatetruecolor($templateWidth, $templateHeight);
            if (!($template instanceof GdImage)) {
                throw new Exception('Failed to create template.');
            }

            // Overlay the cropped image onto the template.
            if (
                !imagecopyresampled(
                    $template,
                    $cropped,
                    $templateWidth - imagesx($cropped),
                    0,
                    0,
                    0,
                    imagesx($cropped),
                    imagesy($cropped),
                    imagesx($cropped),
                    imagesy($cropped)
                )
            ) {
                throw new Exception('Failed to copy cropped image to template.');
            }

            $croppedImages[] = $template;
        }

        // Initialize joined to null.
        $joined = null;

        // Concatenate the cropped images.
        foreach ($croppedImages as $croppedImage) {
            if ($joined === null) {
                // If joined is empty, assign it to the first cropped image.
                $joined = $croppedImage;
            } else {
                // Otherwise, concatenate the cropped image to the bottom of joined.
                $joined = $this->vConcat($joined, $croppedImage);
            }
        }

        if (!($joined instanceof GdImage)) {
            throw new Exception('Failed create to joined image.');
        }

        $this->writeDebugImage($joined, 'joined');

        return $joined;
    }

    /**
     * Vertically concatenate two images.
     *
     * @param GdImage $image1
     * @param GdImage $image2
     *
     * @return resource|false
     * @throws Exception
     */
    private function vConcat(GdImage $image1, GdImage $image2): GdImage
    {
        $height1 = imagesy($image1);
        $height2 = imagesy($image2);
        $width1  = imagesx($image1);
        $width2  = imagesx($image2);

        if ($width1 != $width2) {
            throw new Exception('Images must have the same width.');
        }

        $result = imagecreatetruecolor($width1, $height1 + $height2);

        imagecopy($result, $image1, 0, 0, 0, 0, $width1, $height1);
        imagecopy($result, $image2, 0, $height1, 0, 0, $width2, $height2);

        // Convert the image to grayscale.
        if (!imagefilter($result, IMG_FILTER_GRAYSCALE)) {
            throw new Exception('Failed to convert image to grayscale.');
        }

        return $result;
    }

    /**
     * Check if the input directory exists.
     *
     * @return void
     * @throws Exception
     */
    private function checkInputDir(): void
    {
        if (!is_dir($this->getInputDir())) {
            throw new Exception('Input directory does not exist.');
        }
    }

    /**
     * Create the output directory if it doesn't exist.
     *
     * @return void
     * @throws Exception
     */
    private function createOutputDir(): void
    {
        // Skip if debug mode is enabled.
        if ($this->isDebug()) {
            return;
        }

        // Create the output directory if it doesn't exist.
        if (!is_dir($this->getOutputDir())) {
            if (!mkdir($this->getOutputDir(), 0755, true)) {
                throw new Exception('Failed to create output directory.');
            }
        }
    }

    /**
     * Create the output directory if it doesn't exist.
     *
     * @return void
     * @throws Exception
     */
    private function createDebugDir(): void
    {
        // Skip if debug mode is not enabled.
        if (!$this->isDebug()) {
            return;
        }

        $this->setDebugDir($this->getOutputDir() . '/debug');

        // Create the output directory if it doesn't exist.
        if (!is_dir($this->getDebugDir())) {
            if (!mkdir($this->getDebugDir(), 0755, true)) {
                throw new Exception('Failed to create debug directory.');
            }
        }
    }

    /**
     * Check if the file is in the ignored files list.
     *
     * @return bool
     */
    private function isIgnoredFile(): bool
    {
        // Ignore files and directories specified in IgnoredFiles enum.
        if (in_array($this->getInputFile()->getBasename(), array_column(IgnoredFiles::cases(), 'value'))) {
            return true;
        }

        return false;
    }

    /**
     * Check if the file has an allowed media format extension.
     *
     * @return bool
     */
    private function isAllowedMediaFormat(): bool
    {
        // Get the file extension.
        $extension = strtolower($this->getInputFile()->getExtension());

        // Check if the file has an allowed media format extension.
        if (!in_array($extension, array_column(MediaFormat::cases(), 'value'))) {
            return false;
        }

        return true;
    }

    /**
     * Write a debug image to the debug directory.
     *
     * @param GdImage $image
     * @param string  $labelTag
     *
     * @return void
     * @throws Exception
     */
    private function writeDebugImage(GdImage $image, string $labelTag): void
    {
        // Skip if debug mode is not enabled.
        if (!$this->isDebug()) {
            return;
        }

        if (
            !imagepng(
                $image,
                $this->getDebugDir() . '/' . sprintf('%s-%s.png', $this->getInputFile()->getBasename(), $labelTag)
            )
        ) {
            throw new Exception('Failed to write debug image.');
        }
    }

    /**
     * Run OCR on an image.
     *
     * @param GdImage $image
     *
     * @return string|null
     * @throws Exception
     */
    private function runOCR(GdImage $image): ?string
    {
        // Create a temporary file to store the image data in the temp directory
        $tempImageFile = tempnam(sys_get_temp_dir(), $this->getInputFile()->getBasename() . '-ocr-image-') . '.png';

        // Write the image data to the temporary file.
        if (!imagepng($image, $tempImageFile)) {
            throw new Exception('Failed to write temporary image file.');
        }

        // Define the Tesseract command with tessdata directory path.
        $tesseractCommand = sprintf(
            '%s %s - -l eng --tessdata-dir %s',
            $this->getTesseractPath(),
            escapeshellarg($tempImageFile),
            escapeshellarg($this->getTessdataPath())
        );

        // Execute Tesseract and capture the output.
        $output = trim(shell_exec($tesseractCommand));

        // Delete the temporary image file.
        unlink($tempImageFile);

        if ($this->isDebug()) {
            echo '<OCR>' . PHP_EOL . $output . PHP_EOL . '</OCR>' . PHP_EOL;
        }

        return $output;
    }

    /**
     * Parse the OCR text.
     *
     * @param string $ocrText
     *
     * @return TrailCamData
     * @throws Exception
     */
    private function parseOcrText(string $ocrText): TrailCamData
    {
        $parsed = new stdClass();

        // Split the OCR text into lines.
        $lines = explode("\n", $ocrText);
        if (count($lines) < 2) {
            throw new Exception('Invalid OCR text.');
        }

        $parsed->timestamp   = trim($lines[0]);
        $parsed->camera_name = trim($lines[1]);

        if ($this->isDebug()) {
            echo '<PARSED>' . PHP_EOL . var_export($parsed, true) . PHP_EOL . '</PARSED>' . PHP_EOL;
        }

        // Check if the timestamp and camera_name properties exist.
        if (empty($parsed->timestamp) || empty($parsed->camera_name)) {
            throw new Exception('Failed to parse OCR text.');
        }

        // Validate the timestamp.
        $parsed->timestamp = $this->validateTimestamp($parsed->timestamp);

        // Validate the camera_name.
        $parsed->camera_name = $this->validateCameraName($parsed->camera_name);

        return new TrailCamData($parsed->timestamp, $parsed->camera_name);
    }

    /**
     * Validate the timestamp.
     *
     * @param string $timestamp
     *
     * @return DateTime
     * @throws Exception
     */
    private function validateTimestamp(string $timestamp): DateTime
    {
        // Convert timestamp to a DateTime object.
        try {
            $timestamp = new DateTime($timestamp);
        } catch (Exception $e) {
            throw new Exception('Failed to convert timestamp text to DateTime. Error: ' . $e->getMessage());
        }

        $now = new DateTime();
        if ($timestamp < $now->modify('-41 years')) {
            throw new Exception('Timestamp is out of range.');
        }

        return $timestamp;
    }

    /**
     * Validate the camera_name.
     *
     * @param string $cameraName
     *
     * @return string
     */
    private function validateCameraName(string $cameraName): string
    {
        $cameraName = strtoupper($cameraName);
        // Remove non-alphanumeric characters (except '.' and spaces).
        $cameraName = preg_replace('/[^a-zA-Z0-9. ]/', '', $cameraName);

        return $this->getCameraNameCorrections()[$cameraName] ?? $cameraName;
    }

    /**
     * @return string
     */
    public function getInputDir(): string
    {
        return $this->inputDir;
    }

    /**
     * @param string $inputDir
     *
     * @return TrailCamSorter
     */
    public function setInputDir(string $inputDir): TrailCamSorter
    {
        $this->inputDir = $inputDir;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    /**
     * @param string $outputDir
     *
     * @return TrailCamSorter
     */
    public function setOutputDir(string $outputDir): TrailCamSorter
    {
        $this->outputDir = $outputDir;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOutputFile(): ?string
    {
        return $this->outputFile;
    }

    /**
     * @param string|null $outputFile
     *
     * @return TrailCamSorter
     */
    public function setOutputFile(?string $outputFile): TrailCamSorter
    {
        $this->outputFile = $outputFile;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     *
     * @return TrailCamSorter
     */
    public function setDebug(bool $debug): TrailCamSorter
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @return string
     */
    public function getDebugDir(): string
    {
        return $this->debugDir;
    }

    /**
     * @param string $debugDir
     *
     * @return TrailCamSorter
     */
    public function setDebugDir(string $debugDir): TrailCamSorter
    {
        $this->debugDir = $debugDir;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * @param bool $dryRun
     *
     * @return TrailCamSorter
     */
    public function setDryRun(bool $dryRun): TrailCamSorter
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     *
     * @return TrailCamSorter
     */
    public function setLimit(int $limit): TrailCamSorter
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getRemainingLimit(): int
    {
        return $this->remainingLimit;
    }

    /**
     * @param int $remainingLimit
     *
     * @return TrailCamSorter
     */
    public function setRemainingLimit(int $remainingLimit): TrailCamSorter
    {
        $this->remainingLimit = $remainingLimit;

        return $this;
    }

    /**
     * @return int
     */
    public function getFilesProcessed(): int
    {
        return $this->filesProcessed;
    }

    /**
     * @param int $filesProcessed
     *
     * @return TrailCamSorter
     */
    public function setFilesProcessed(int $filesProcessed): TrailCamSorter
    {
        $this->filesProcessed = $filesProcessed;

        return $this;
    }

    /**
     * @return array
     */
    public function getInputFiles(): array
    {
        return $this->inputFiles;
    }

    /**
     * @param array $inputFiles
     *
     * @return TrailCamSorter
     */
    public function setInputFiles(array $inputFiles): TrailCamSorter
    {
        $this->inputFiles = $inputFiles;

        return $this;
    }

    /**
     * @return SplFileInfo
     */
    public function getInputFile(): SplFileInfo
    {
        return $this->inputFile;
    }

    /**
     * @param SplFileInfo $inputFile
     *
     * @return TrailCamSorter
     */
    public function setInputFile(SplFileInfo $inputFile): TrailCamSorter
    {
        $this->inputFile = $inputFile;

        return $this;
    }

    /**
     * @return TrailCamData
     */
    public function getTrailCamData(): TrailCamData
    {
        return $this->trailCamData;
    }

    /**
     * @param TrailCamData $trailCamData
     *
     * @return TrailCamSorter
     */
    public function setTrailCamData(TrailCamData $trailCamData): TrailCamSorter
    {
        $this->trailCamData = $trailCamData;

        return $this;
    }

    /**
     * @return int
     */
    public function getInputFilesTotal(): int
    {
        return $this->inputFilesTotal;
    }

    /**
     * @param int $inputFilesTotal
     *
     * @return TrailCamSorter
     */
    public function setInputFilesTotal(int $inputFilesTotal): TrailCamSorter
    {
        $this->inputFilesTotal = $inputFilesTotal;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getTimerStart(): DateTime
    {
        return $this->timerStart;
    }

    /**
     * @param DateTime $timerStart
     *
     * @return TrailCamSorter
     */
    public function setTimerStart(DateTime $timerStart): TrailCamSorter
    {
        $this->timerStart = $timerStart;

        return $this;
    }

    /**
     * @return string
     */
    public function getFfmpegPath(): string
    {
        return $this->ffmpegPath;
    }

    /**
     * @param string $ffmpegPath
     *
     * @return TrailCamSorter
     */
    public function setFfmpegPath(string $ffmpegPath): TrailCamSorter
    {
        $this->ffmpegPath = $ffmpegPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getTesseractPath(): string
    {
        return $this->tesseractPath;
    }

    /**
     * @param string $tesseractPath
     *
     * @return TrailCamSorter
     */
    public function setTesseractPath(string $tesseractPath): TrailCamSorter
    {
        $this->tesseractPath = $tesseractPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getTessdataPath(): string
    {
        return $this->tessdataPath;
    }

    /**
     * @param string $tessdataPath
     *
     * @return TrailCamSorter
     */
    public function setTessdataPath(string $tessdataPath): TrailCamSorter
    {
        $this->tessdataPath = $tessdataPath;

        return $this;
    }

    /**
     * @return array
     */
    public function getCameraNameCorrections(): array
    {
        return $this->cameraNameCorrections;
    }

    /**
     * @param array $cameraNameCorrections
     *
     * @return TrailCamSorter
     */
    public function setCameraNameCorrections(array $cameraNameCorrections): TrailCamSorter
    {
        $this->cameraNameCorrections = $cameraNameCorrections;

        return $this;
    }

    /**
     * @return string
     */
    public function getCameraNameCorrectionsFile(): string
    {
        return $this->cameraNameCorrectionsFile;
    }

    /**
     * @param string $cameraNameCorrectionsFile
     *
     * @return TrailCamSorter
     */
    public function setCameraNameCorrectionsFile(string $cameraNameCorrectionsFile): TrailCamSorter
    {
        $this->cameraNameCorrectionsFile = $cameraNameCorrectionsFile;

        return $this;
    }
}
