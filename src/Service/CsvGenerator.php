<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Service;

use Topdata\TopdataTopsellerExportSW6\Dto\TopsellerItem;

class CsvGenerator
{
    /**
     * Generates a CSV string from an array of TopsellerItem DTOs.
     *
     * @param TopsellerItem[] $data
     */
    public function generateTopsellerCsv(array $data): string
    {
        if (empty($data)) {
            return implode(';', ['articleNumber', 'productName', 'salesCount']) . "\n";
        }

        $handle = fopen('php://temp', 'rw+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open temporary file stream.');
        }

        $headers = array_keys($data[0]->toArray());
        fputcsv($handle, $headers, ';');

        foreach ($data as $item) {
            if (!$item instanceof TopsellerItem) {
                throw new \InvalidArgumentException('All items in data array must be of type TopsellerItem.');
            }
            fputcsv($handle, $item->toArray(), ';');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        if ($csvContent === false) {
            throw new \RuntimeException('Failed to read contents from temporary file stream.');
        }

        return $csvContent;
    }
}
