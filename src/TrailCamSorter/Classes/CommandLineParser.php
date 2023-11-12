<?php

namespace Briancolinger\TrailCamSorterPhp\TrailCamSorter\Classes;

/**
 * A class for parsing command line arguments.
 */
class CommandLineParser
{
    /**
     * @var array<string, bool|int|string|null>
     */
    private array $options;

    /**
     * @var array
     */
    private array $optionDefinitions;

    /**
     * @param array $optionDefinitions
     */
    public function __construct(array $optionDefinitions)
    {
        $this->optionDefinitions = $optionDefinitions;
        $this->parseCommandLine();
    }

    /**
     * @param string $key
     *
     * @return bool|int|string|null
     */
    public function getOption(string $key): bool | int | string | null
    {
        return $this->options[$key] ?? null;
    }

    /**
     * @return void
     */
    public function printUsage(): void
    {
        echo 'Usage: php script.php [options]' . PHP_EOL;
        echo 'Options:' . PHP_EOL;

        foreach ($this->optionDefinitions as $option) {
            $optionName        = $option['name'];
            $optionType        = $option['type'];
            $optionDescription = $option['description'];

            $defaultValue    = $option['default'] ?? null;
            $defaultValueStr = $defaultValue !== null ? ' (default: ' . var_export($defaultValue, true) . ')' : '';

            echo sprintf("  --%s=%s\t%s%s\n", $optionName, $optionType, $optionDescription, $defaultValueStr);
        }
    }

    /**
     * @return void
     */
    private function parseCommandLine(): void
    {
        // Define default values for options
        $this->options = [];

        foreach ($this->optionDefinitions as $optionDefinition) {
            $this->options[$optionDefinition['name']] = $optionDefinition['default'] ?? null;
        }

        // Parse command line arguments
        global $argv;
        foreach ($argv as $arg) {
            // Check if the argument is in the format "--key=value"
            if (preg_match('/^--([^=]+)=(.*)$/', $arg, $matches)) {
                $key   = $matches[1];
                $value = $matches[2];

                // Find the option definition by name
                $optionDefinition = null;
                foreach ($this->optionDefinitions as $option) {
                    if ($option['name'] === $key) {
                        $optionDefinition = $option;
                        break;
                    }
                }

                if ($optionDefinition === null) {
                    echo "Unknown option: $key" . PHP_EOL;
                    $this->printUsage();
                    exit(1);
                }

                $dataType = $optionDefinition['type'];

                // Set the option based on the data type
                switch ($dataType) {
                    case 'bool':
                        $this->options[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'int':
                        $this->options[$key] = (int)$value;
                        break;
                    case 'string':
                        $this->options[$key] = $value;
                        break;
                    default:
                        echo "Invalid data type for option: $key" . PHP_EOL;
                        $this->printUsage();
                        exit(1);
                }
            } elseif ($arg === '--help') {
                $this->printUsage();
                exit(0);
            }
        }
    }
}
