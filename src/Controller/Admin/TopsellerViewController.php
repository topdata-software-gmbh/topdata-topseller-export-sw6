<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Controller\Admin;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Topdata\TopdataTopsellerExportSW6\Service\TopsellerDataService;

#[Route(defaults: ['_routeScope' => ['api']])]
class TopsellerViewController extends AbstractController
{
    public function __construct(
        private readonly TopsellerDataService $topsellerDataService,
        private readonly EntityRepository $languageRepository
    ) {
    }

    #[Route(
        path: '/api/topdata/topseller-export/topsellers',
        name: 'api.topdata.topseller_export.topsellers',
        methods: ['GET']
    )]
    public function getTopsellers(Request $request): JsonResponse
    {
        $startDateStr = $request->query->get('startDate');
        $endDateStr = $request->query->get('endDate');
        $languageCode = $request->query->get('languageCode');

        if (!$startDateStr || !$endDateStr) {
            return new JsonResponse(['error' => 'Missing startDate or endDate parameter.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable($startDateStr);
            $endDate = new \DateTimeImmutable($endDateStr);
            $endDate = $endDate->setTime(23, 59, 59);

            if ($startDate > $endDate) {
                return new JsonResponse(['error' => 'Start date cannot be after end date.'], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], Response::HTTP_BAD_REQUEST);
        }

        $languageId = $languageCode ? $this->getLanguageIdByCode($languageCode) : null;

        try {
            $topsellers = $this->topsellerDataService->getTopsellers($startDate, $endDate, $languageId);

            $items = array_map(fn($item) => $item->toArray(), $topsellers);

            return new JsonResponse([
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
                'total' => count($items),
                'data' => array_values($items),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getLanguageIdByCode(?string $languageCode): ?string
    {
        if (!$languageCode) {
            return null;
        }

        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $languageCode));

        $language = $this->languageRepository->search($criteria, $context)->first();

        return $language?->getId();
    }
}
