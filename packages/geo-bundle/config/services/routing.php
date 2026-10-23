<?php

use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Command\RoutingMatrixCommand;
use Tito10047\GeoBundle\Command\RoutingRouteCommand;
use Tito10047\GeoBundle\Command\RoutingStatusCommand;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\GeoBundle\Routing\RoutingStatusProviderInterface;
use Tito10047\GeoBundle\Routing\ValhallaStatusProvider;
use Tito10047\Routing\Geometry\PolylineDecoder;
use Tito10047\Routing\Geometry\PolylineDecoderInterface;
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\RouterInterface;
use Tito10047\Routing\Valhalla\AdminCrossingCountryLocator;
use Tito10047\Routing\Valhalla\CountryLocatorInterface;
use Tito10047\Routing\Valhalla\ErrorTranslator;
use Tito10047\Routing\Valhalla\ErrorTranslatorInterface;
use Tito10047\Routing\Valhalla\ManeuverTollLocator;
use Tito10047\Routing\Valhalla\MatrixRequestEncoder;
use Tito10047\Routing\Valhalla\MatrixRequestEncoderInterface;
use Tito10047\Routing\Valhalla\MatrixResponseParser;
use Tito10047\Routing\Valhalla\MatrixResponseParserInterface;
use Tito10047\Routing\Valhalla\RequestEncoder;
use Tito10047\Routing\Valhalla\RequestEncoderInterface;
use Tito10047\Routing\Valhalla\ResponseParser;
use Tito10047\Routing\Valhalla\ResponseParserInterface;
use Tito10047\Routing\Valhalla\TollLocatorInterface;
use Tito10047\Routing\Valhalla\ValhallaMatrix;
use Tito10047\Routing\Valhalla\ValhallaRouter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The services of the routing (tito10047/routing), loaded when "geo.routing" is
 * enabled. Every collaborator is a service of its own, aliased to its interface, so that an
 * application can decorate or replace any of them. The abstract arguments are set from the
 * configuration by Tito10047GeoBundle::loadExtension().
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        // curl or amphp: Valhalla keeps connections open, a client reading until the socket closes would hang
        ->set('geo.routing.http_client', ClientInterface::class)
            ->factory(HttpClientFactory::class.'::valhalla')
            ->args([abstract_arg('timeout')])

        ->set('geo.routing.request_encoder', RequestEncoder::class)
        ->alias(RequestEncoderInterface::class, 'geo.routing.request_encoder')

        ->set('geo.routing.polyline_decoder', PolylineDecoder::class)
            ->args([6])
        ->alias(PolylineDecoderInterface::class, 'geo.routing.polyline_decoder')

        ->set('geo.routing.country_locator', AdminCrossingCountryLocator::class)
        ->alias(CountryLocatorInterface::class, 'geo.routing.country_locator')

        ->set('geo.routing.toll_locator', ManeuverTollLocator::class)
        ->alias(TollLocatorInterface::class, 'geo.routing.toll_locator')

        ->set('geo.routing.response_parser', ResponseParser::class)
            ->args([
                service('geo.routing.polyline_decoder'),
                service('geo.routing.country_locator'),
                service('geo.routing.toll_locator'),
            ])
        ->alias(ResponseParserInterface::class, 'geo.routing.response_parser')

        ->set('geo.routing.error_translator', ErrorTranslator::class)
        ->alias(ErrorTranslatorInterface::class, 'geo.routing.error_translator')

        ->set('geo.routing.router', ValhallaRouter::class)
            ->args([
                abstract_arg('Valhalla URL'),
                service('geo.routing.http_client'),
                service('geo.psr17_factory'),
                service('geo.psr17_factory'),
                service('logger')->nullOnInvalid(),
                service('geo.routing.request_encoder'),
                service('geo.routing.response_parser'),
                service('geo.routing.error_translator'),
            ])
            ->tag('monolog.logger', ['channel' => 'geo'])
        ->alias(RouterInterface::class, 'geo.routing.router')

        // the distance matrix, over the same HTTP client
        ->set('geo.routing.matrix_request_encoder', MatrixRequestEncoder::class)
        ->alias(MatrixRequestEncoderInterface::class, 'geo.routing.matrix_request_encoder')

        ->set('geo.routing.matrix_response_parser', MatrixResponseParser::class)
        ->alias(MatrixResponseParserInterface::class, 'geo.routing.matrix_response_parser')

        // every error of /sources_to_targets means "unavailable" (170 is a point outside the data there)
        ->set('geo.routing.matrix_error_translator', ErrorTranslator::class)
            ->factory(ErrorTranslator::class.'::forMatrix')

        ->set('geo.routing.matrix', ValhallaMatrix::class)
            ->args([
                abstract_arg('Valhalla URL'),
                service('geo.routing.http_client'),
                service('geo.psr17_factory'),
                service('geo.psr17_factory'),
                service('logger')->nullOnInvalid(),
                service('geo.routing.matrix_request_encoder'),
                service('geo.routing.matrix_response_parser'),
                service('geo.routing.matrix_error_translator'),
                abstract_arg('maximum of location pairs'),
            ])
            ->tag('monolog.logger', ['channel' => 'geo'])
        ->alias(MatrixInterface::class, 'geo.routing.matrix')

        ->set('geo.routing.status_provider', ValhallaStatusProvider::class)
            ->args([
                abstract_arg('Valhalla URL'),
                service('geo.routing.http_client'),
                service('geo.psr17_factory'),
            ])
        ->alias(RoutingStatusProviderInterface::class, 'geo.routing.status_provider')

        // commands
        ->set('geo.routing.command.route', RoutingRouteCommand::class)
            ->args([service('geo.routing.router'), abstract_arg('default radius')])
            ->tag('console.command', ['command' => 'geo:route'])

        ->set('geo.routing.command.matrix', RoutingMatrixCommand::class)
            ->args([service('geo.routing.matrix'), abstract_arg('default radius')])
            ->tag('console.command', ['command' => 'geo:matrix'])

        ->set('geo.routing.command.status', RoutingStatusCommand::class)
            ->args([service('geo.routing.status_provider')])
            ->tag('console.command', ['command' => 'geo:route:status'])
    ;
};
