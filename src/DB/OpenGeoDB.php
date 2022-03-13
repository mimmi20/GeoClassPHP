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

use GeoDB\Geo;
use GeoDB\GeoObject;
use PDOException;
use UnexpectedValueException;

use function count;
use function getdate;
use function is_array;

/**
 * Geo_DB_OpenGeoDB
 *
 * This class gives access to openGeoDB.de databases.
 */
final class OpenGeoDB extends Relational
{
    public const GEO_OGDB_UNKNOWN = 0;

    // geodb_locations:
    public const GEO_OGDB_CONTINENT      = 100100000;
    public const GEO_OGDB_STATE          = 100200000;
    public const GEO_OGDB_COUNTRY        = 100300000;
    public const GEO_OGDB_REGBEZIRK      = 100400000;
    public const GEO_OGDB_LANDKREIS      = 100500000;
    public const GEO_OGDB_POL_DIVISION   = 100600000;
    public const GEO_OGDB_POPULATED_AREA = 100700000;

    // geodb_coordinates:
    public const GEO_OGDB_WGS84 = 200100000;

    // geodb_*:
    public const GEO_OGDB_EXACT_DATE          = 300100000;
    public const GEO_OGDB_EXACT_TO_YEAR       = 300300000;
    public const GEO_OGDB_UNKNOWN_FUTURE_DATE = 300500000;

    // geodb_textdata:
    public const GEO_OGDB_NAME           = 500100000;
    public const GEO_OGDB_NAME_ISO_3166  = 500100001;
    public const GEO_OGDB_NAME_7BITLC    = 500100002; // 7 Bit, Lower case
    public const GEO_OGDB_AREA_CODE      = 500300000;
    public const GEO_OGDB_KFZ            = 500500000;
    public const GEO_OGDB_AGS            = 500600000;
    public const GEO_OGDB_NAME_VG        = 500700000;
    public const GEO_OGDB_NAME_VG_7BITLC = 500700001; // 7 Bit, Lower case

    // geodb_intdata:
    public const GEO_OGDB_POPULATION       = 600700000;
    public const GEO_OGDB_EST_POPULATION   = 650700001;
    public const GEO_OGDB_EXACT_POPULATION = 650700002;

    /**
     * some options
     *
     * @var array<string, array<int|string, string>|bool|int|string>
     * @phpstan-var array{language: int, table_prefix: string, table: string, joins: array<int|string, string>, fields: array{name: string, longitude: string, latitude: string}, key: string, order: string, degree: bool, unit: int, encoding: string}
     */
    public array $options = [
        'language' => Geo::GEO_LANGUAGE_DEFAULT,
        'table_prefix' => 'geodb_',
        'table' => 'geodb_textdata td, geodb_coordinates co',
        'joins' => ['td.loc_id = co.loc_id'],
        'fields' => [
            'name' => 'text_val',
            'longitude' => 'lon',
            'latitude' => 'lat',
        ],
        'key' => 'loc_id',
        'order' => 'td.text_val',
        'degree' => true,
        'unit' => Geo::GEO_UNIT_DEFAULT,
        'encoding' => Geo::GEO_ENCODING_UTF_8,
    ];

    /**
     * findAreaCodeLoc
     *
     * Returns an GeoObjects for the AreaCode
     * Simple search looks up AreaCode and Name
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function findAreaCodeLoc(string $areaCode = '%'): ?GeoObject
    {
        $searchConditions = [
            "td.text_val LIKE '" . $areaCode . "'",
            'td.text_type = ' . self::GEO_OGDB_AREA_CODE,
        ];
        $result           = $this->findGeoObject($searchConditions);
        $resCount         = count($result);
        if (1 < $resCount) {
            $finalObject = Geo::getBarycenter($result, $areaCode . ' (' . $resCount . ')');
        } elseif (0 === $resCount) {
            $finalObject = null;
        } else {
            [$finalObject] = $result;
        }

        return $finalObject;
    }

    /**
     * getAreaCode
     *
     * Returns an GeoObjects for the AreaCode
     * Simple search looks up AreaCode and Name
     *
     * @return array<GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function getAreaCode(GeoObject $geoObject): array
    {
        $searchConditions = [
            'td.text_type = ' . self::GEO_OGDB_AREA_CODE,
            'td.loc_id = ' . $geoObject->dbValues['loc_id'],
        ];

        return $this->findGeoObject($searchConditions);
    }

    /**
     * Find GeoObjects
     *
     * Returns an array of GeoObjects which fits the $searchConditions
     * Simple search looks up AreaCode and Name
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
            return parent::findGeoObject($searchConditions);
        }

        // / default query in text_val is restricted to special parameters
        $searchConditions   = [$this->options['fields']['name'] . " LIKE '" . $searchConditions . "'"];
        $searchConditions[] = '(td.is_default_name = 1 or (td.is_default_name is null and td.is_native_lang = 1))';
        $searchConditions[] = 'td.text_type IN (' . self::GEO_OGDB_NAME . ', ' . self::GEO_OGDB_AREA_CODE . ')';

        return parent::findGeoObject($searchConditions);
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
        // We restrict the search to the current date to avoid double data sets
        // for no obvious reasons...
        $date  = getdate();
        $today = "'" . $date['year'] . '-' . $date['mon'] . '-' . $date['mday'] . "'";

        // returning id, name, distance, level, lon, lat until now:

        $query = 'SELECT td.loc_id, ' .
                        'td.text_val, ' .
                        $this->getDistanceFormula($geoObject) . ' AS distance, ' .
                        "hi.level AS 'typ', " .
                        'lon, ' .
                        'lat ' .
                 'FROM ' . $this->options['table_prefix'] . 'textdata td, ' .
                      $this->options['table_prefix'] . 'hierarchies hi, ' .
                      $this->options['table_prefix'] . 'coordinates co ' .
                 'WHERE td.text_type=' . self::GEO_OGDB_NAME . ' AND ' .
                       'td.is_default_name = 1 AND ' .
                       'hi.loc_id = td.loc_id AND ' .
                       'hi.level >= 6 AND ' .
                       'co.loc_id = td.loc_id AND ' .
                       $this->getDistanceFormula($geoObject) . ' < ' . $maxRadius . ' AND ' .
                       'td.valid_until >= ' . $today . ' AND ' .
                       'hi.valid_until >= ' . $today . ' AND ' .
                       'co.valid_until >= ' . $today . ' ' .
                 'ORDER BY distance ASC';

        if ($maxHits) {
            $query .= ' LIMIT 0, ' . $maxHits;
        }

        return $this->performQuery($query);
    }

    /**
     * @return array<GeoObject>
     *
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function getByLocID(int | string $id): array
    {
        return $this->findGeoObject(['td.loc_id = ' . $id]);
    }
}
