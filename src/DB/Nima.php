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
use PDO;
use PDOException;
use UnexpectedValueException;

use function count;
use function is_array;
use function is_string;
use function str_replace;
use function utf8_encode;

/**
 * Geo_Nima
 */
final class Nima extends DB
{
    /**
     * some options
     *
     * @var array<string, array<string, string>|bool|int|string>
     * @phpstan-var array{language: int, table: string, fields: array{name: string, longitude: string, latitude: string}, order: string, native: bool, degree: bool, unit: int, encoding: string}
     */
    public array $options = [
        'language' => Geo::GEO_LANGUAGE_DEFAULT,
        'table' => 'nima',
        'fields' => [
            'name' => 'FULL_NAME',
            'longitude' => 'DD_LONG',
            'latitude' => 'DD_LAT',
        ],
        'order' => 'SORT_NAME',
        'native' => true,
        'degree' => true,
        'unit' => Geo::GEO_UNIT_DEFAULT,
        'encoding' => Geo::GEO_ENCODING_UTF_8,
    ];

    /**
     * country of the Nima database
     */
    public string $country = 'unknown';

    /**
     * states of the Nima database
     *
     * @var array<mixed>
     */
    public array $states = [];

    /**
     * @param array<string, array<string, string>|bool|int|string> $options
     * @phpstan-param array{language?: int, table?: string, fields?: array{name: string, longitude: string, latitude: string}, order?: string, native?: bool, degree?: bool, unit?: int, encoding?: string} $options
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function __construct(PDO $pdo, array $options = [])
    {
        parent::__construct($pdo, $options);

        $this->initNimaInformation();
    }

    /**
     * Determines further information of the database.
     *
     * Function is called by the constructor.
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function initNimaInformation(): void
    {
        // find GeoObject with native name of the country
        $countryObjectArray = $this->performQuery('SELECT * FROM ' . $this->options['table'] . " WHERE DSG = 'PCLI' AND NT = 'N'");

        if (is_array($countryObjectArray) && 1 === count($countryObjectArray)) {
            foreach ($countryObjectArray as $countryObject) {
                if ($countryObject->dbValues['SHORT_FORM']) {
                    $this->country = $countryObject->dbValues['SHORT_FORM'];
                } elseif ($countryObject->name) {
                    $this->country = $countryObject->name;
                } else {
                    $this->country = Geo::CFG_STRINGS[Geo::GEO_ERR_NONAME][Geo::GEO_LANGUAGE_DEFAULT];
                }
            }
        } else {
            $this->country = Geo::CFG_STRINGS[Geo::GEO_ERR_NONAME][Geo::GEO_LANGUAGE_DEFAULT];
        }

        // find GeoObject with native name of the ADM1 (states)
        $statesObjectArray = $this->performQuery('SELECT * FROM ' . $this->options['table'] . " WHERE DSG = 'ADM1' AND NT = 'N' ORDER BY ADM1");

        if (!is_array($statesObjectArray)) {
            return;
        }

        foreach ($statesObjectArray as $statesObject) {
            $key = $statesObject->dbValues['ADM1'];

            if ($statesObject->dbValues['SHORT_FORM']) {
                $this->states[$key] = $statesObject->dbValues['SHORT_FORM'];
            } elseif ($statesObject->name) {
                $this->states[$key] = $statesObject->name;
            } else {
                $this->states[$key] = Geo::CFG_STRINGS[Geo::GEO_ERR_NONAME][Geo::GEO_LANGUAGE_DEFAULT];
            }
        }
    }

    /**
     * Searches for populated places (FC = P) which fits the passed $name. You could use SQL compatible wildcards.
     *
     * A set or a single place classification could be passed.
     * By default all classifications (1=big, 5=small, 0=unclassified or very small) are considered.
     *
     * @return array<GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function findClassifiedPopulatedPlace(string $name, string $placeClassificationSet = '1,2,3,4,5,0'): array
    {
        return $this->findGeoObject($name, 'P', $placeClassificationSet);
    }

    /**
     * Searches for GeoObjects which fits the passed name (could contain SQL compatible wildcards)
     * and the given sets of feature classifications and place classifications.
     *
     * By default all classifications ("A,P,V,L,U,R,T,H,S") respective (1=big, 5=small, 0=unclassified or very small) are considered.
     *
     * @see DB
     *
     * @param array<int|string, string>|string $searchConditions
     *
     * @return array<GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function findGeoObject(array | string $searchConditions = '%', string $featureClassificationSet = 'A,P,V,L,U,R,T,H,S', string $placeClassificationSet = '0,1,2,3,4,5,6'): array
    {
        if (is_string($searchConditions) && Geo::GEO_ENCODING_UTF_8 === $this->options['encoding']) {
            $searchConditions = utf8_encode($searchConditions);
        }

        $query = 'SELECT * FROM  ' . $this->options['table'] .
                 " WHERE FC IN ('" . str_replace(',', "','", $featureClassificationSet) . "') AND PC IN (" . $placeClassificationSet . ')' .
                 ' AND ' . $this->options['fields']['name'] . " LIKE '" . $searchConditions . "'" .
                 ' ORDER BY ' . $this->options['order'];

        return $this->performQuery($query);
    }

    /**
     * Searches for GeoObjects, which are in a specified radius around the passed GeoObject.
     *
     * Default is radius of 100 (100 of specified unit, see configuration and maxHits of 50.
     * A set or a single feature classifications and a single or a set of feature classifications place classification could be passed.
     * By default all classifications ("A,P,V,L,U,R,T,H,S") respective (1=big, 5=small, 0=unclassified or very small) are considered.
     *
     * @return array<GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function findCloseByGeoObjects(GeoObject $geoObject, int $maxRadius = 100, int $maxHits = 50, string $featureClassificationSet = 'A,P,V,L,U,R,T,H,S', string $placeClassificationSet = '0,1,2,3,4,5,6'): array
    {
        $query  = 'SELECT *, ';
        $query .= $this->getDistanceFormula($geoObject) . ' AS distance';
        $query .= ' FROM ' . $this->options['table'];
        $query .= " WHERE FC IN ('" . str_replace(',', "','", $featureClassificationSet) . "') AND PC IN (" . $placeClassificationSet . ')';
        $query .= ' AND ' . $this->getDistanceFormula($geoObject) . ' < ' . $maxRadius;

        if ($this->options['native']) {
            $query .= " AND NT = 'N'";
        }

        $query .= ' ORDER BY distance';

        if ($maxHits) {
            $query .= ' LIMIT 0, ' . $maxHits;
        }

        return $this->performQuery($query);
    }
}
