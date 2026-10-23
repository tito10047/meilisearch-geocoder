<?php

use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Command\RoutingRouteCommand;
use Tito10047\GeoBundle\Command\RoutingStatusCommand;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\GeoBundle\Routing\RoutingStatusProviderInterface;
use Tito10047\GeoBundle\Routing\ValhallaStatusProvider;
use Tito10047\Routing\Geometry\PolylineDecoder;
use Tito10047\Routing\Geometry\PolylineDecoderInterface;
use Tito10047\Routing\RouterInterface;
use Tito10047\Routing\Valhalla\AdminCrossingCountryLocator;
use Tito10047\Routing\Valhalla\CountryLocatorInterface;
use Tito10047\Routing\Valhalla\ErrorTranslator;
use Tito10047\Routing\Valhalla\ErrorTranslatorInterface;
use Tito10047\Routing\Valhalla\ManeuverTollLocator;
use Tito10047\Routing\Valhalla\RequestEncoder;
use Tito10047\Routing\Valhalla\RequestEncoderInterface;
use Tito10047\Routing\Valhalla\ResponseParser;
use Tito10047\Routing\Valhalla\ResponseParserInterface;
use Tito10047\Routing\Valhalla\TollLocatorInterface;
use Tito10047\Routing\Valhalla\ValhallaRouter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The services of the routing (tito10047/routing), loaded when "tito10047_geo.routing" is
 * enabled. Every collaborator is a service of its own, aliased to its interface, so that an
 * application can decorate or replace any of them. The abstract arguments are set from the
 * configuration by Tito10047GeoBundle::loadExtension().
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        // curl or amphp: Valhalla keeps connections open, a client reading until the socket closes would hang
        ->set('tito10047_geo.routing.http_client', ClientInterface::class)
            ->factory(HttpClientFactory::class.'::valhalla')
            ->args([abstract_arg('timeout')])

        ->set('tito10047_geo.routing.request_encoder', RequestEncoder::class)
        ->alias(RequestEncoderInterface::class, 'tito10047_geo.routing.request_encoder')

        ->set('tito10047_geo.routing.polyline_decoder', PolylineDecoder::class)
            ->args([6])
        ->alias(PolylineDecoderInterface::class, 'tito10047_geo.routing.polyline_decoder')

        ->set('tito10047_geo.routing.country_locator', AdminCrossingCountryLocator::class)
        ->alias(CountryLocatorInterface::class, 'tito10047_geo.routing.country_locator')

        ->set('tito10047_geo.routing.toll_locator', ManeuverTollLocator::class)
        ->alias(TollLocatorInterface::class, 'tito10047_geo.routing.toll_locator')

        ->set('tito10047_geo.routing.response_parser', ResponseParser::class)
            ->args([
                service('tito10047_geo.routing.polyline_decoder'),
                service('tito10047_geo.routing.country_locator'),
                service('tito10047_geo.routing.toll_locator'),
            ])
        ->alias(ResponseParserInterface::class, 'tito10047_geo.routing.response_parser')

        ->set('tito10047_geo.routing.error_translator', ErrorTranslator::class)
        ->alias(ErrorTranslatorInterface::class, 'tito10047_geo.routing.error_translator')

        ->set('tito10047_geo.routing.router', ValhallaRouter::class)
            ->args([
                abstract_arg('Valhalla URL'),
                service('tito10047_geo.routing.http_client'),
                service('tito10047_geo.psr17_factory'),
                service('tito10047_geo.psr17_factory'),
                service('logger')->nullOnInvalid(),
                service('tito10047_geo.routing.request_encoder'),
                service('tito10047_geo.routing.response_parser'),
                service('tito10047_geo.routing.error_translator'),
            ])
            ->tag('monolog.logger', ['channel' => 'tito10047_geo'])
        ->alias(RouterInterface::class, 'tito10047_geo.routing.router')

        ->set('tito10047_geo.routing.status_provider', ValhallaStatusProvider::class)
            ->args([
                abstract_arg('Valhalla URL'),
                service('tito10047_geo.routing.http_client'),
                service('tito10047_geo.psr17_factory'),
            ])
        ->alias(RoutingStatusProviderInterface::class, 'tito10047_geo.routing.status_provider')

        // commands
        ->set('tito10047_geo.routing.command.route', RoutingRouteCommand::class)
            ->args([service('tito10047_geo.routing.router'), abstract_arg('default radius')])
            ->tag('console.command', ['command' => 'tito10047-geo:routing:route'])

        ->set('tito10047_geo.routing.command.status', RoutingStatusCommand::class)
            ->args([service('tito10047_geo.routing.status_provider')])
            ->tag('console.command', ['command' => 'tito10047-geo:routing:status'])
    ;
};
