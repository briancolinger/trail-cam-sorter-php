<?php

namespace Briancolinger\TrailCamSorterPhp\TrailCamSorter\Enums;

/**
 * A list of files that should be ignored when sorting.
 */
enum IgnoredFiles: string
{
    case DS_STORE = '.DS_Store';
    case THUMBS_DB = 'Thumbs.db';
    case RECYCLE_BIN = '$RECYCLE.BIN';
    case SPOTLIGHT = '.Spotlight-V100';
    case SYSTEM_VOLUME_INFORMATION = 'System Volume Information';
    case FSEVENTSD = '.fseventsd';
    case TRASHES = '.Trashes';
    case TEMPORARYITEMS = '.TemporaryItems';
}
