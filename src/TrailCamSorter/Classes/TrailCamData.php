<?php

namespace Briancolinger\TrailCamSorterPhp\TrailCamSorter\Classes;

use DateTime;

class TrailCamData
{
    /**
     * @var DateTime
     */
    public DateTime $timestamp;

    /**
     * @var string
     */
    public string $cameraName;

    public function __construct(DateTime $timestamp, string $cameraName)
    {
        $this->setTimestamp($timestamp);
        $this->setCameraName($cameraName);
    }

    /**
     * @return DateTime
     */
    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    /**
     * @param DateTime $timestamp
     *
     * @return TrailCamData
     */
    public function setTimestamp(DateTime $timestamp): TrailCamData
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * @return string
     */
    public function getCameraName(): string
    {
        return $this->cameraName;
    }

    /**
     * @param string $cameraName
     *
     * @return TrailCamData
     */
    public function setCameraName(string $cameraName): TrailCamData
    {
        $this->cameraName = $cameraName;

        return $this;
    }
}
