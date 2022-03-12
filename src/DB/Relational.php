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

namespace GeoDB\DB;

use GeoDB\DB;
use GeoDB\Geo;
use GeoDB\GeoObject;

use function array_merge;
use function implode;
use function is_array;
use function is_string;

/**
 * Geo_DB_Relational
 *
 * Designed for databases which seperate tables for coordinates
 * and further information. It must use a join-condition, otherwise
 * use DB.
 */
class Relational extends DB
{
    /**
     * some options
     *
     * @var array<string, array<string, string>|bool|int|string>
     * @phpstan-var array{language: int, table: string, joins: array<string, string>, fields: array{name: string, longitude: string, latitude: string}, key: string, order: string, degree: bool, unit: int, encoding: string}
     */
    public array $options = [
        'language' => Geo::GEO_LANGUAGE_DEFAULT,
        'table' => 'coordinates co, further_information fi',
        'joins' => ['co.id = fi.id'],
        /*
         * fields must be unique within the related tables
         */
        'fields' => [
            'name' => 'name',
            'longitude' => 'lon',
            'latitude' => 'lat',
        ],
        'key' => 'id',
        'order' => 'name',
        'degree' => true,
        'unit' => Geo::GEO_UNIT_DEFAULT,
        'encoding' => Geo::GEO_ENCODING_DEFAULT,
    ];

    /**
     * @param array<string, int|string> $options
     * @phpstan-param  array{language: int, unit: int, encoding: string} $options
     */
    public function __construct(string $dsn, array $options = [])
    {
        $this->_connectDB($dsn);
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

            $whereExpression = implode(' AND ', array_merge($this->options['joins'], $where));
        } else {
            $whereExpression = implode(' AND ', $this->options['joins']) .
                               ' AND ' . $this->options['fields']['name'] . " LIKE '" . $searchConditions . "'";
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
     * @return array<GeoObject>
     *
     * @todo    void MySQL specific SQL
     */
    public function findCloseByGeoObjects(GeoObject $geoObject, int $maxRadius = 100, int $maxHits = 50): array
    {
        $query = 'SELECT *, ' .
                    $this->getDistanceFormula($geoObject) . ' AS distance' .
                 ' FROM ' . $this->options['table'] .
                 ' WHERE ' . implode(' AND ', $this->options['joins']) .
                            ' AND ' . $this->getDistanceFormula($geoObject) . ' < ' . $maxRadius .
                 ' ORDER BY distance ASC';
        if ($maxHits) {
            $query .= ' LIMIT 0, ' . $maxHits;
        }

        return $this->performQuery($query);
    }
}
