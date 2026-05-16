<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class AdminApiExampleController extends AbstractController
{
    #[Route(
        path: '/api/_action/topdata-topseller-export-sw6/example', 
        name: 'api.action.topsellerexportsw6.example', 
        methods: ['GET']
    )]
    public function exampleAction(): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }
}