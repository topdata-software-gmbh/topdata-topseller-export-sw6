<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Service;

class CsvFileWriter
{
    /** @var resource|null */
    private $handle = null;

    public function open(string $filePath): void
    {
        $this->handle = fopen($filePath, 'w');
        if ($this->handle === false) {
            throw new \RuntimeException(sprintf('Could not open file for writing: %s', $filePath));
        }
    }

    /**
     * @param array<string|int|float|bool|null> $row
     */
    public function writeRow(array $row): void
    {
        if ($this->handle === null) {
            throw new \RuntimeException('File handle is not open. Call open() first.');
        }

        fputcsv($this->handle, $row, ';', '"', '\\');
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
