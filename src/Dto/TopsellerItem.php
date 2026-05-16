<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Dto;

class TopsellerItem
{
    public function __construct(
        public readonly string $articleNumber,
        public readonly string $productName,
        public readonly int $salesCount
    ) {
    }

    public function toArray(): array
    {
        return [
            'articleNumber' => $this->articleNumber,
            'productName' => $this->productName,
            'salesCount' => $this->salesCount,
        ];
    }
}
