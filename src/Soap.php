<?php
/**
 * This file is part of the mimmi20/GeoClassPHP package.
 *
 * Copyright (c) 2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

// +----------------------------------------------------------------------+
// | GeoClass                                                             |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003-04 multimediamotz, Stefan Motz                    |
// +----------------------------------------------------------------------+
// | License (LGPL)                                                       |
// | This library is free software; you can redistribute it and/or        |
// | modify it under the terms of the GNU Lesser General Public           |
// | License as published by the Free Software Foundation; either         |
// | version 2.1 of the License, or (at your option) any later version.   |
// +----------------------------------------------------------------------+
// | This library is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU     |
// | Lesser General Public License for more details.                      |
// +----------------------------------------------------------------------+
// | You should have received a copy of the GNU Lesser General Public     |
// | License along with this library; if not, write to the Free Software  |
// | Foundation Inc., 59 Temple Place,Suite 330, Boston,MA 02111-1307 USA |
// +----------------------------------------------------------------------+
// | Authors:  Stefan Motz   <stefan@multimediamotz.de>                   |
// |           Arne Klempert <arne@klempert.de>                           |
// | Version:  0.3.1a                                                     |
// | Homepage: http://geoclassphp.sourceforge.net                         |
// +----------------------------------------------------------------------+

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
     * constructor Geo_Soap
     *
     * @return  void
     *
     * @var     string
     * @var     array
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
     * @param mixed $searchConditions string or array
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
