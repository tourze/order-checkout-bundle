<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\OrderCheckoutBundle\Controller\OrderExtendedInfoCrudController;
use Tourze\OrderCheckoutBundle\Controller\ShippingTemplateAreaCrudController;
use Tourze\OrderCheckoutBundle\Controller\ShippingTemplateCrudController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    private RouteCollection $collection;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();

        $this->collection = new RouteCollection();
        $this->collection->addCollection($this->controllerLoader->load(OrderExtendedInfoCrudController::class));
        $this->collection->addCollection($this->controllerLoader->load(ShippingTemplateCrudController::class));
        $this->collection->addCollection($this->controllerLoader->load(ShippingTemplateAreaCrudController::class));
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    public function autoload(): RouteCollection
    {
        return $this->collection;
    }
}
