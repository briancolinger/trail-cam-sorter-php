<?php

namespace Briancolinger\TrailCamSorterPhp\TrailCamSorter\Classes;

use ReflectionClass;

final class LabelCoordinates
{
    public const TIMESTAMP = [0.487240, 0.972685, 0.233854, 0.054630];
    public const CAMERA_NAME = [0.864844, 0.972685, 0.270313, 0.054630];

    private function __construct()
    {
        // Prevent instantiation
    }

    /**
     * @return array<string, array<float>>
     */
    public static function cases(): array
    {
        $reflection = new ReflectionClass(__CLASS__);

        return $reflection->getConstants();
    }
}
