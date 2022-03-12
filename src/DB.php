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

use function implode;
use function is_array;
use function is_string;
use function utf8_decode;

/**
 * Geo_DB
 */
final class DB extends Common
{
    /**
     * DB object (PEAR::DB)
     *
     * @var     GeoObject|null   DB
     */
    private ?GeoObject $db = null;

    /**
     * some options
     *
     * @var     array     saves the options
     */
    private array $options = [
        'language' => Geo::GEO_LANGUAGE_DEFAULT,
        'table' => 'geo',
        'fields' => [
            'name' => 'name',
            'longitude' => 'longitude',
            'latitude' => 'latitude',
        ],
        'order' => 'ort',
        'degree' => true,
        'unit' => Geo::GEO_UNIT_DEFAULT,
        'encoding' => Geo::GEO_ENCODING_DEFAULT,
    ];

    /**
     * constructor Geo_DB
     *
     * @var     string
     * @var     array
     */
    public function __construct(string $dsn, array $options = [])
    {
        $this->connectDB($dsn);
        $this->setOptions($options);
    }

    /**
     * Find GeoObjects
     *
     * Returns an array of GeoObjects which fits the $searchConditions
     *
     * @param array|string $searchConditions string or array
     */
    public function findGeoObject($searchConditions = '%'): array
    {
        if (is_array($searchConditions)) {
            $where = [];

            foreach ($searchConditions as $key => $val) {
                if (is_string($key)) {
                    $where[] = $key . " = '" . $val . "'";
                } else {
                    $where[] = $val;
                }
            }

            $whereExpression = implode(' AND ', $where);
        } else {
            $whereExpression = $this->options['fields']['name'] . " LIKE '" . $searchConditions . "'";
        }

        $query = 'SELECT * FROM ' . $this->options['table'] .
                 ' WHERE ' . $whereExpression .
                 ' ORDER BY ' . $this->options['order'];

        return $this->performQuery($query);
    }

    /**
     * Find GeoObjects near an overgiven GeoObject
     *
     * Searches for GeoObjects, which are in a specified radius around the passed GeoBject.
     * Default is radius of 100 (100 of specified unit, see configuration and maxHits of 50
     * Returns an array of GeoDB-objects which lie in ther radius of the passed GeoObject.
     *
     * @todo    void MySQL specific SQL
     */
    public function findCloseByGeoObjects(GeoObject $geoObject, int $maxRadius = 100, int $maxHits = 50): array
    {
        $query  = 'SELECT *, ';
        $query .= $this->getDistanceFormula($geoObject) . ' AS distance';
        $query .= ' FROM ' . $this->options['table'];
        $query .= ' WHERE ' . $this->getDistanceFormula($geoObject) . " < {$maxRadius}";
        $query .= ' ORDER BY distance ASC';
        if ($maxHits) {
            $query .= " LIMIT 0, {$maxHits}";
        }

        return $this->performQuery($query);
    }

    /**
     * Performs a query on the database and delivers GeoObjects (or Errors)
     * To get ordinary results use $this->db->query($query) instead.
     *
     * @param string $query SQL query
     *
     * @return  mixed    array of GeoObjects or DBError
     */
    protected function performQuery(string $query)
    {
        $queryResult = $this->db->query($query);

        if (self::isError($queryResult)) {
            return $queryResult;
        }

        return $this->transformQueryResult($queryResult);
    }

    /**
     * Returns the formula which evaluates the distance between the passed GeoObject and
     * the elements in the database.
     */
    protected function getDistanceFormula(GeoObject $geoObject): string
    {
        $formula  = 'COALESCE(';
        $formula .= "(ACOS((SIN({$geoObject->latitudeRad})*SIN(RADIANS(" . $this->options['fields']['latitude'] . '))) + ';
        $formula .= "(COS({$geoObject->latitudeRad})*COS(RADIANS(" . $this->options['fields']['latitude'] . '))*COS(RADIANS(' . $this->options['fields']['longitude'] . ")-{$geoObject->longitudeRad}))) * ";
        $formula .= Geo::getEarthRadius() . ')';
        $formula .= ',0)';

        return $formula;
    }

    /**
     * Establishes a connection to the database
     */
    private function connectDB(string $dsn): void
    {
        $this->db = $this->connect($dsn);

        if (self::isError($this->db)) {
            return;
        }

        $this->db->setFetchMode(DB_FETCHMODE_ASSOC);
    }

    /**
     * Transforms a db-query-result to an array of GeoObjects.
     *
     * @return  array      GeoObjects
     */
    private function transformQueryResult(Result $queryResult): array
    {
        $foundGeoObjects = [];
        while ($dbValues = $queryResult->fetchRow()) {
            if (Geo::GEO_ENCODING_UTF_8 === $this->options['encoding']) {
                foreach ($dbValues as $key => $val) {
                    $dbValues[$key] = utf8_decode($val);
                }
            }

            $name              = $dbValues[$this->options['fields']['name']];
            $latitude          = $dbValues[$this->options['fields']['latitude']];
            $longitude         = $dbValues[$this->options['fields']['longitude']];
            $foundGeoObjects[] = new GeoObject($name, (float) $latitude, (float) $longitude, $this->options['degree'], $dbValues);
        }

        return $foundGeoObjects;
    }
}
