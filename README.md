# `trail-cam-sorter-php`

With `trail-cam-sorter-php` you can process videos files, extract essential metadata such as
date, time, and camera name, and organize the files based on the extracted metadata.

## Key Features

- Uses Tesseract OCR: Uses Tesseract OCR to extract metadata from the video files.
- Metadata extraction: Extract essential metadata such as date, time, and camera name.
- Metadata correction: Correct camera names using a JSON file with camera name corrections.
- Automated organization: Organize videos into directories based on the extracted metadata for easy file management.
- Wide video format support: Support for a broad range of video formats, including AVI, MP4, and more, to ensure
  compatibility with various camera models.

## Getting Started

1. Clone the repository to your local machine.
2. Navigate to the `trail-cam-sorter-php` directory in your terminal.

### Run:

```bash
./trail-cam-sorter-php.php --input=/path/to/input --output=/path/to/output
```

### Optional Parameters

- `--dry-run=true` to skip renaming the files.
- `--limit=10` to process only the first 10 files.
- `--debug=true` to write debug images to file.
- `--corrections=camera-name-corrections.json` to provide a JSON file with camera name corrections.
- `--tessdata=/path/to/tessdata` to provide a path to the Tesseract tessdata directory.

### Run with optional parameters:

```bash
./trail-cam-sorter-php.php \
  --input=/path/to/input \
  --output=/path/to/output \
  --dry-run=true \
  --limit=10 \
  --debug=true \
  --corrections=camera-name-corrections.json \
  --tessdata=/path/to/tessdata
```

## License

`trail-cam-sorter-php` is licensed under the [GPL-3.0 License](https://www.gnu.org/licenses/gpl-3.0.en.html). Feel free
to use, modify, and distribute this software as needed.
