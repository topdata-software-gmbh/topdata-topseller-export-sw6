<?php declare(strict_types=1);

namespace Topdata\TopdataTopsellerExportSW6\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[RouteScope(scopes: ['storefront'])]
class StorefrontExampleController extends StorefrontController
{
    #[Route(
        path: '/topsellerexportsw6/example', 
        name: 'frontend.topsellerexportsw6.example', 
        methods: ['GET']
    )]
    public function exampleAction(): Response
    {
        return $this->renderStorefront('@TopdataTopsellerExportSW6/storefront/example.html.twig', [
            'pluginName' => 'TopsellerExportSW6'
        ]);
    }
}