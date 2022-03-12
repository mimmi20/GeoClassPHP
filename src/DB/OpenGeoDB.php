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

namespace GeoDB\DB;

use GeoDB\Geo;
use GeoDB\GeoObject;

use function count;
use function define;
use function getdate;
use function is_array;

define('GEO_OGDB_UNKNOWN', 0);

// geodb_locations:
define('GEO_OGDB_CONTINENT', 100100000);
define('GEO_OGDB_STATE', 100200000);
define('GEO_OGDB_COUNTRY', 100300000);
define('GEO_OGDB_REGBEZIRK', 100400000);
define('GEO_OGDB_LANDKREIS', 100500000);
define('GEO_OGDB_POL_DIVISION', 100600000);
define('GEO_OGDB_POPULATED_AREA', 100700000);

// geodb_coordinates:
define('GEO_OGDB_WGS84', 200100000);

// geodb_*:
define('GEO_OGDB_EXACT_DATE', 300100000);
define('GEO_OGDB_EXACT_TO_YEAR', 300300000);
define('GEO_OGDB_UNKNOWN_FUTURE_DATE', 300500000);

// geodb_textdata:
define('GEO_OGDB_NAME', 500100000);
define('GEO_OGDB_NAME_ISO_3166', 500100001);
define('GEO_OGDB_NAME_7BITLC', 500100002); // 7 Bit, Lower case
define('GEO_OGDB_AREA_CODE', 500300000);
define('GEO_OGDB_KFZ', 500500000);
define('GEO_OGDB_AGS', 500600000);
define('GEO_OGDB_NAME_VG', 500700000);
define('GEO_OGDB_NAME_VG_7BITLC', 500700001); // 7 Bit, Lower case

// geodb_intdata:
define('GEO_OGDB_POPULATION', 600700000);
define('GEO_OGDB_EST_POPULATION', 650700001);
define('GEO_OGDB_EXACT_POPULATION', 650700002);

require_once 'Geo/sources/DB_Relational.php';

/**
 * Geo_DB_OpenGeoDB
 *
 * This class gives access to openGeoDB.de databases.
 */
final class OpenGeoDB extends Relational
{
    /**
     * some options
     *
     * @var  array    options
     */
    public array $options = [
        'language' => 'en',
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
        'unit' => GEO_UNIT_DEFAULT,
        'encoding' => GEO_ENCODING_UTF_8,
    ];

    /**
     * constructor Geo_DB_OpenGeoDB
     *
     * @return  void
     *
     * @var     string
     * @var     array
     */
    public function __construct($dsn, $options = [])
    {
        $this->_connectDB($dsn);
        $this->setOptions($options);
    }

    /**
     * findAreaCodeLoc
     *
     * Returns an GeoObjects for the AreaCode
     * Simple search looks up AreaCode and Name
     */
    public function findAreaCodeLoc(string $areaCode = '%'): array
    {
        $searchConditions = [
            "td.text_val LIKE '" . $areaCode . "'",
            'td.text_type = ' . GEO_OGDB_AREA_CODE,
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
     */
    public function getAreaCode(GeoObject $geoObject): array
    {
        $searchConditions = [
            'td.text_type = ' . GEO_OGDB_AREA_CODE,
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
     * @param mixed $searchConditions string or array
     */
    public function findGeoObject($searchConditions = '%'): array
    {
        if (is_array($searchConditions)) {
            return parent::findGeoObject($searchConditions);
        }

        // / default query in text_val is restricted to special parameters
        $searchConditions   = [$this->options['fields']['name'] . " LIKE '" . $searchConditions . "'"];
        $searchConditions[] = '(td.is_default_name = 1 or ' .
                        ' (td.is_default_name is null and td.is_native_lang = 1))';
        $searchConditions[] = 'td.text_type IN (' . GEO_OGDB_NAME . ', ' . GEO_OGDB_AREA_CODE . ')';

        return parent::findGeoObject($searchConditions);
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
                 'WHERE td.text_type=' . GEO_OGDB_NAME . ' AND ' .
                       'td.is_default_name = 1 AND ' .
                       'hi.loc_id = td.loc_id AND ' .
                       'hi.level >= 6 AND ' .
                       'co.loc_id = td.loc_id AND ' .
                       $this->getDistanceFormula($geoObject) . " < {$maxRadius} AND " .
                       'td.valid_until >= ' . $today . ' AND ' .
                       'hi.valid_until >= ' . $today . ' AND ' .
                       'co.valid_until >= ' . $today . ' ' .
                 'ORDER BY distance ASC';
        if ($maxHits) {
            $query .= " LIMIT 0, {$maxHits}";
        }

        return $this->performQuery($query);
    }

    public function getByLocID($id)
    {
        return $this->findGeoObject(['td.loc_id = ' . $id]);
    }
}
