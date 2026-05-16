<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Yaml\Yaml;

class ProductExportService
{
    private const PAGE_LIMIT = 250;

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly CsvFileWriter $csvFileWriter,
        private readonly PropertyAccessorInterface $propertyAccessor
    ) {
    }

    public function export(string $configPath, string $outputPath, Context $context): int
    {
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException(sprintf('Configuration file not found: %s', $configPath));
        }

        $config = Yaml::parseFile($configPath);
        $columns = $config['columns'] ?? [];

        if (empty($columns)) {
            throw new \RuntimeException('No columns defined in the configuration file.');
        }

        $this->csvFileWriter->open($outputPath);

        $headers = array_column($columns, 'header');
        $this->csvFileWriter->writeRow($headers);

        $criteria = new Criteria();
        $criteria->setLimit(self::PAGE_LIMIT);

        foreach ($columns as $column) {
            $field = $column['field'] ?? '';
            $parts = explode('.', $field);
            if (count($parts) > 1 && $parts[0] !== 'translated') {
                $criteria->addAssociation($parts[0]);
            }
        }

        $totalExported = 0;
        $offset = 0;

        do {
            $criteria->setOffset($offset);
            $result = $this->productRepository->search($criteria, $context);
            $entities = $result->getEntities();

            foreach ($entities as $entity) {
                $row = [];
                foreach ($columns as $column) {
                    $fieldPath = $column['field'] ?? '';
                    try {
                        $value = $this->propertyAccessor->getValue($entity, $fieldPath);

                        if (is_bool($value)) {
                            $value = $value ? '1' : '0';
                        } elseif (is_array($value) || is_object($value)) {
                            $value = '';
                        }

                        $row[] = (string) $value;
                    } catch (\Exception $e) {
                        $row[] = '';
                    }
                }
                $this->csvFileWriter->writeRow($row);
                $totalExported++;
            }

            $offset += self::PAGE_LIMIT;
        } while ($entities->count() === self::PAGE_LIMIT);

        $this->csvFileWriter->close();

        return $totalExported;
    }
}
