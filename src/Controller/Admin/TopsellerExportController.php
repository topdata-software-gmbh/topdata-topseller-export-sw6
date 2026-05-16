<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Controller\Admin;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Topdata\TopdataTopsellerExportSW6\Service\CsvGenerator;
use Topdata\TopdataTopsellerExportSW6\Service\TopsellerDataService;

#[Route(defaults: ['_routeScope' => ['api']])]
class TopsellerExportController extends AbstractController
{
    public function __construct(
        private readonly TopsellerDataService $topsellerDataService,
        private readonly CsvGenerator $csvGenerator,
        private readonly EntityRepository $languageRepository
    ) {
    }

    #[Route(
        path: '/api/_action/topdata-topseller-export-sw6/export',
        name: 'api.action.topsellerexportsw6.export',
        methods: ['GET']
    )]
    public function export(Request $request): Response
    {
        $startDateStr = $request->query->get('startDate');
        $endDateStr = $request->query->get('endDate');
        $languageCode = $request->query->get('languageCode');

        if (!$startDateStr || !$endDateStr) {
            return new Response('Missing startDate or endDate parameter.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTimeImmutable($startDateStr);
            $endDate = new \DateTimeImmutable($endDateStr);
            $endDate = $endDate->setTime(23, 59, 59);

            if ($startDate > $endDate) {
                return new Response('Start date cannot be after end date.', Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            return new Response('Invalid date format. Use YYYY-MM-DD.', Response::HTTP_BAD_REQUEST);
        }

        $languageId = $this->getLanguageIdByCode($languageCode);

        try {
            $topsellers = $this->topsellerDataService->getTopsellers($startDate, $endDate, $languageId);
            $csvContent = $this->csvGenerator->generateTopsellerCsv($topsellers);

            $filename = sprintf(
                'topsellers_%s_to_%s_%s.csv',
                $startDate->format('Ymd'),
                $endDate->format('Ymd'),
                (new \DateTimeImmutable('now'))->format('His')
            );

            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename
            );

            $response = new Response($csvContent);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', $disposition);

            return $response;

        } catch (\Exception $e) {
            return new Response('An error occurred during export: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
