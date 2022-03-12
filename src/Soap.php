<?php
/**
 * This file is part of the mimmi20/GeoClassPHP package.
 *
 * Copyright (c) 2022, Thomas Mueller <mimmi20@live.de>
 * Copyright (c) 2003-2004 Stefan Motz <stefan@multimediamotz.de>, Arne Klempert <arne@klempert.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace GeoDB;

use SOAP_Client;

use function unserialize;

/**
 * Geo_Soap
 */
final class Soap extends Common
{
    private SOAP_Client $soapClient;

    /**
     * @param array<string, int|string> $options
     * @phpstan-param  array{language: int, unit: int, encoding: string} $options
     */
    public function __construct(string $url = '', array $options = [])
    {
        $this->soapClient = new SOAP_Client($url);
        $this->setOptions($options);
    }

    /**
     * Find GeoObjects
     *
     * Returns an array of GeoObjects which fits the $searchConditions
     *
     * @param array<int|string, string>|string $searchConditions
     *
     * @return array<GeoObject>
     */
    public function findGeoObject($searchConditions = []): array
    {
        $parametersFindCity = [
            'returnType' => 0, // use 0 to receive an array of GeoObjects
            'name' => $searchConditions,
        ];
        $ret                = $this->soapClient->call('findGeoObject', $parametersFindCity);

        return unserialize($ret);
    }

    /**
     * Find GeoObjects near a given GeoObject
     *
     * Searches for GeoObjects, which are in a specified radius around the passed GeoBject.
     * Default is radius of 100 (100 of specified unit, see configuration and maxHits of 50
     * Returns an array of GeoObjects which lie in the radius of the passed GeoObject.
     *
     * @return array<GeoObject>
     */
    public function findCloseByGeoObjects(GeoObject $geoObject, int $maxRadius = 100, int $maxHits = 50): array
    {
        $parametersFindClose = [
            'returnType' => 0,
            'lat' => $geoObject->latitude,
            'long' => $geoObject->longitude,
            'maxRadius' => $maxRadius,
            'maxHits' => $maxHits,
            'placeClassificationSet' => '1, 2, 3, 4, 5, 0',
            'name' => $geoObject->name,
        ];

        $ret = $this->soapClient->call('findCloseByCity', $parametersFindClose);

        return unserialize($ret);
    }
}
