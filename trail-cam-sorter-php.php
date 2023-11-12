#!/usr/bin/env php
<?php

// Load the Composer autoloader.
require __DIR__ . '/vendor/autoload.php';

use Briancolinger\TrailCamSorterPhp\TrailCamSorter\Classes\TrailCamSorter;

// Create an instance of the TrailCamSorter class.
$sorter = new TrailCamSorter();

// Process files based on the provided options.
try {
    $sorter->processFiles();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
