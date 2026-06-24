<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Topdata\TopdataTopsellerExportSW6\Dto\TopsellerItem;

class TopsellerDataService
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Retrieves topseller items within a given date range.
     *
     * @return TopsellerItem[]
     */
    public function getTopsellers(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $languageId = null
    ): array {
        $defaultLanguageId = $this->getDefaultLanguageId();

        if ($languageId === null) {
            $languageId = $defaultLanguageId;
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select(
                'product.product_number AS articleNumber',
                'COALESCE(pt_requested.name, pt_default.name) AS productName',
                'SUM(lineItem.quantity) AS salesCount',
            )
            ->from('order_line_item', 'lineItem')
            ->innerJoin('lineItem', 'product', 'product', 'lineItem.product_id = product.id')
            ->leftJoin('product', 'product_translation', 'pt_requested',
                'product.id = pt_requested.product_id AND product.version_id = pt_requested.product_version_id AND pt_requested.language_id = :languageId')
            ->leftJoin('product', 'product_translation', 'pt_default',
                'product.id = pt_default.product_id AND product.version_id = pt_default.product_version_id AND pt_default.language_id = :defaultLanguageId')
            ->innerJoin('lineItem', '`order`', '`order`', 'lineItem.order_id = `order`.id')
            ->where('lineItem.type = :productLineItemType')
            ->andWhere('lineItem.product_id IS NOT NULL')
            ->andWhere('`order`.order_date BETWEEN :startDate AND :endDate')
            ->andWhere('`order`.version_id = :liveVersionId')
            ->andWhere('lineItem.version_id = :liveVersionId')
            ->andWhere('product.version_id = :liveVersionId')
            ->groupBy('product.id, product.product_number')
            ->orderBy('salesCount', 'DESC')
            ->addOrderBy('productName', 'ASC')
            ->setParameters([
                'productLineItemType' => 'product',
                'startDate' => $startDate->format(Defaults::STORAGE_DATE_FORMAT),
                'endDate' => $endDate->format(Defaults::STORAGE_DATE_FORMAT),
                'languageId' => Uuid::fromHexToBytes($languageId),
                'defaultLanguageId' => Uuid::fromHexToBytes($defaultLanguageId),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);

        $result = $queryBuilder->executeQuery()->fetchAllAssociative();

        $topsellers = [];
        foreach ($result as $row) {
            $topsellers[] = new TopsellerItem(
                (string) $row['articleNumber'],
                (string) $row['productName'],
                (int) $row['salesCount']
            );
        }

        return $topsellers;
    }

    private function getDefaultLanguageId(): string
    {
        $languageId = $this->connection->fetchOne(
            'SELECT `id` FROM `language` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        if ($languageId) {
            return Uuid::fromBytesToHex($languageId);
        }

        return Uuid::fromBytesToHex($this->connection->fetchOne('SELECT `id` FROM `language` LIMIT 1'));
    }
}
