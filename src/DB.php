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

use PDO;
use PDOException;
use PDOStatement;
use UnexpectedValueException;

use function assert;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function utf8_decode;

/**
 * Geo_DB
 */
class DB extends Common
{
    /**
     * DB object (PEAR::DB)
     *
     * @var PDO DB
     */
    protected PDO $pdo;

    /**
     * some options
     *
     * @var array<string, array<string, string>|bool|int|string>
     * @phpstan-var array{language: int, table: string, fields: array{name: string, longitude: string, latitude: string}, order: string, degree: bool, unit: int, encoding: string}
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
     * @param array<string, int|string> $options
     * @phpstan-param  array{language?: int, unit?: int, encoding?: string} $options
     *
     * @throws PDOException
     */
    public function __construct(PDO $pdo, array $options = [])
    {
        $this->pdo = $pdo;

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
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function findGeoObject(array | string $searchConditions = '%'): array
    {
        if (is_array($searchConditions)) {
            $where = [];

            foreach ($searchConditions as $key => $val) {
                if (is_string($key)) {
                    $where[] = $key . ' = ' . $this->pdo->quote($val);
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
     * @return array<GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     *
     * @todo void MySQL specific SQL
     */
    public function findCloseByGeoObjects(GeoObject $geoObject, int $maxRadius = 100, int $maxHits = 50): array
    {
        $query  = 'SELECT *, ';
        $query .= $this->getDistanceFormula($geoObject) . ' AS distance';
        $query .= ' FROM ' . $this->options['table'];
        $query .= ' WHERE ' . $this->getDistanceFormula($geoObject) . ' < ' . $maxRadius;
        $query .= ' ORDER BY distance ASC';

        if ($maxHits) {
            $query .= ' LIMIT 0, ' . $maxHits;
        }

        return $this->performQuery($query);
    }

    /**
     * Performs a query on the database and delivers GeoObjects (or Errors)
     * To get ordinary results use $this->db->query($query) instead.
     *
     * @param string $query SQL query
     *
     * @return array<int, GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    protected function performQuery(string $query): array
    {
        $driverOptions = [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY];

        $stmt = $this->pdo->prepare($query, $driverOptions);
        assert($stmt instanceof PDOStatement);
        $stmt->execute();

        return $this->transformQueryResult($stmt);
    }

    /**
     * Returns the formula which evaluates the distance between the passed GeoObject and
     * the elements in the database.
     */
    protected function getDistanceFormula(GeoObject $geoObject): string
    {
        $formula  = 'COALESCE(';
        $formula .= sprintf('(ACOS((SIN(%01f)*SIN(RADIANS(', $geoObject->latitudeRad) . $this->options['fields']['latitude'] . '))) + ';
        $formula .= sprintf('(COS(%01f)*COS(RADIANS(' . $this->options['fields']['latitude'] . '))*COS(RADIANS(' . $this->options['fields']['longitude'] . ')-%01f))) * ', $geoObject->latitudeRad, $geoObject->longitudeRad);
        $formula .= Geo::getEarthRadius() . ')';
        $formula .= ',0)';

        return $formula;
    }

    /**
     * Transforms a db-query-result to an array of GeoObjects.
     *
     * @return array<int, GeoObject>
     *
     * @throws UnexpectedValueException
     */
    private function transformQueryResult(PDOStatement $stmt): array
    {
        $foundGeoObjects = [];

        while ($dbValues = $stmt->fetch(PDO::FETCH_ASSOC)) {
            assert(is_array($dbValues));

            if (Geo::GEO_ENCODING_UTF_8 === $this->options['encoding']) {
                foreach ($dbValues as $key => $val) {
                    $dbValues[$key] = utf8_decode($val);
                }
            }

            $name      = $dbValues[$this->options['fields']['name']];
            $latitude  = $dbValues[$this->options['fields']['latitude']];
            $longitude = $dbValues[$this->options['fields']['longitude']];

            $foundGeoObjects[] = new GeoObject((string) $name, (float) $latitude, (float) $longitude, $this->options['degree'], $dbValues);
        }

        return $foundGeoObjects;
    }
}
