<?php

namespace Briancolinger\TrailCamSorterPhp\TrailCamSorter\Enums;

/**
 * A list of media formats that are supported by this library.
 */
enum MediaFormat: string
{
    // Video Formats
    case AVI = 'avi';
    case MP4 = 'mp4';
    case MKV = 'mkv';
    case MOV = 'mov';
    case WMV = 'wmv';
    case FLV = 'flv';
    case WebM = 'webm';
    case _3GP = '3gp';
    case MPEG = 'mpeg';
    case ASF = 'asf';
    case OGG = 'ogg';

    // Image Formats
    case JPEG = 'jpeg';
    case PNG = 'png';
    case GIF = 'gif';
    case BMP = 'bmp';
    case TIFF = 'tiff';
    case TGA = 'tga';
    case PSD = 'psd';
}
