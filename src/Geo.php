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

use PEAR;

use function abs;
use function class_exists;
use function count;
use function floor;
use function in_array;
use function mb_strlen;
use function mb_strtoupper;
use function preg_match;
use function sprintf;

/**
 * The main "Geo" class is simply a container class with some static
 * methods for creating Geo objects as well as some utility functions
 * common to all parts of Geo.
 *
 * The object model of Geo is as follows (indentation means inheritance):
 *
 * Geo             The main Geo class. This is simply a utility class
 *                 with some "static" methods for creating Geo objects as
 *                 well as common utility functions for other Geo classes.
 *
 * Geo_Common      The base for each source implementation.
 * |
 * +-Geo_DB        The source implementation for a common database.
 *   |
 *   +-Geo_DB_Nima The source implementation for a Nima database.
 *                 Inherits Geo_DB. When calling Geo::setupSource('DB_Nima'),
 *                 the object returned is an instance of this class.
 *
 * Geo_Map         This class draws maps of GeoObjects and e00 files
 *
 * Geo_Object      Object with name, longitude, latitude
 *                 and additional information (depends on the source
 *                 implementation)
 */
final class Geo extends PEAR
{
    /**
     * Unit constants
     *
     * GEO_UNIT_KM => kilometers
     * GEO_UNIT_MI => miles
     * GEO_UNIT_IN => inches
     * GEO_UNIT_SM => sea-miles
     * GEO_UNIT_FT => foot
     * GEO_UNIT_YD => yards
     */
    public const GEO_UNIT_KM      = 1;
    public const GEO_UNIT_MI      = 2;
    public const GEO_UNIT_IN      = 3;
    public const GEO_UNIT_SM      = 4;
    public const GEO_UNIT_FT      = 11;
    public const GEO_UNIT_YD      = 12;
    public const GEO_UNIT_DEFAULT = self::GEO_UNIT_KM;

    public const GEO_UNIT_M2KM  = 0.001;
    public const GEO_UNIT_MI2KM = 1.609343994;
    public const GEO_UNIT_IN2KM = 0.0000254;
    public const GEO_UNIT_SM2KM = 1.852;
    public const GEO_UNIT_FT2KM = 0.0003048;
    public const GEO_UNIT_YD2KM = 0.0009144;

    public const GEO_UNIT_KM2M  = 1 / self::GEO_UNIT_M2KM;
    public const GEO_UNIT_KM2MI = 1 / self::GEO_UNIT_MI2KM;
    public const GEO_UNIT_KM2IN = 1 / self::GEO_UNIT_IN2KM;
    public const GEO_UNIT_KM2SM = 1 / self::GEO_UNIT_SM2KM;
    public const GEO_UNIT_KM2FT = 1 / self::GEO_UNIT_FT2KM;
    public const GEO_UNIT_KM2YD = 1 / self::GEO_UNIT_YD2KM;

    /**
     * This constant contains the radius of the earth in kilometers
     * GEO_EARTH_RADIUS is set to the mean value: 6371. km
     * equatorial radius as of WGS84: 6378.137 km
     */
    public const GEO_EARTH_RADIUS = 6371.0;

    /**
     * Following constants are for later use.
     */
    public const GEO_RELATION_EQUAL          = '=';
    public const GEO_RELATION_NOTEQUAL       = '!=';
    public const GEO_RELATION_LESS           = '<';
    public const GEO_RELATION_GREATER        = '>';
    public const GEO_RELATION_LESSOREQUAL    = '<=';
    public const GEO_RELATION_GREATEROREQUAL = '>=';
    public const GEO_RELATION_LIKE           = 'LIKE';
    public const GEO_RELATION_NOTLIKE        = 'NOT LIKE';

    /**
     * Possible forms of the orientation string
     *
     * GEO_ORIENTATION_SHORT => N, W, E, NE, etc.
     * GEO_ORIENTATION_LONG => north, south west, etc.
     */
    public const GEO_ORIENTATION_SHORT   = 5;
    public const GEO_ORIENTATION_LONG    = 6;
    public const GEO_ORIENTATION_DEFAULT = self::GEO_ORIENTATION_SHORT;

    /**
     * Languages
     */
    public const GEO_LANGUAGE_EN      = 7;
    public const GEO_LANGUAGE_DE      = 8;
    public const GEO_LANGUAGE_DEFAULT = self::GEO_LANGUAGE_EN;

    /**
     * Error codes
     *
     * GEO_ERR_ERROR => Default Error
     * GEO_ERR_NONAME => Default value for locations without a proper name
     */
    public const GEO_ERR_ERROR  = 9;
    public const GEO_ERR_NONAME = 10;

    /**
     * Encodings (for use with databases)
     * string instead of int to assure backwards compatibility
     */
    public const GEO_ENCODING_UTF_8      = 'utf8';
    public const GEO_ENCODING_ISO_8859_1 = 'latin1';
    public const GEO_ENCODING_DEFAULT    = self::GEO_ENCODING_ISO_8859_1;

    public const GEO_ORIENTATION_N  = 'N';
    public const GEO_ORIENTATION_NE = 'NE';
    public const GEO_ORIENTATION_E  = 'E';
    public const GEO_ORIENTATION_SE = 'SE';
    public const GEO_ORIENTATION_S  = 'S';
    public const GEO_ORIENTATION_SW = 'SW';
    public const GEO_ORIENTATION_W  = 'W';
    public const GEO_ORIENTATION_NW = 'NW';

    /**
     * All the following stuff is for internationalisation.
     * German and English are provided.
     * Perhaps I will include an external language file later.
     */
    public const CFG_STRINGS = [
        self::GEO_ORIENTATION_SHORT => [
            self::GEO_LANGUAGE_DE => [
                self::GEO_ORIENTATION_N => 'N',
                self::GEO_ORIENTATION_NE => 'NO',
                self::GEO_ORIENTATION_E => 'O',
                self::GEO_ORIENTATION_SE => 'SO',
                self::GEO_ORIENTATION_S => 'S',
                self::GEO_ORIENTATION_SW => 'SW',
                self::GEO_ORIENTATION_W => 'W',
                self::GEO_ORIENTATION_NW => 'NW',
            ],
            self::GEO_LANGUAGE_EN => [
                self::GEO_ORIENTATION_N => 'N',
                self::GEO_ORIENTATION_NE => 'NE',
                self::GEO_ORIENTATION_E => 'E',
                self::GEO_ORIENTATION_SE => 'SE',
                self::GEO_ORIENTATION_S => 'S',
                self::GEO_ORIENTATION_SW => 'SW',
                self::GEO_ORIENTATION_W => 'W',
                self::GEO_ORIENTATION_NW => 'NW',
            ],
        ],
        self::GEO_ORIENTATION_LONG => [
            self::GEO_LANGUAGE_DE => [
                self::GEO_ORIENTATION_N => 'Norden',
                self::GEO_ORIENTATION_NE => 'Nordosten',
                self::GEO_ORIENTATION_E => 'Osten',
                self::GEO_ORIENTATION_SE => 'Südosten',
                self::GEO_ORIENTATION_S => 'Süden',
                self::GEO_ORIENTATION_SW => 'Südwesten',
                self::GEO_ORIENTATION_W => 'Westen',
                self::GEO_ORIENTATION_NW => 'Nordwesten',
            ],
            self::GEO_LANGUAGE_EN => [
                self::GEO_ORIENTATION_N => 'north',
                self::GEO_ORIENTATION_NE => 'north east',
                self::GEO_ORIENTATION_E => 'east',
                self::GEO_ORIENTATION_SE => 'south east',
                self::GEO_ORIENTATION_S => 'south',
                self::GEO_ORIENTATION_SW => 'south west',
                self::GEO_ORIENTATION_W => 'west',
                self::GEO_ORIENTATION_NW => 'north west',
            ],
        ],
        self::GEO_ERR_ERROR => [
            self::GEO_LANGUAGE_DE => 'Fehler',
            self::GEO_LANGUAGE_EN => 'Error',
        ],
        self::GEO_ERR_NONAME => [
            self::GEO_LANGUAGE_DE => 'Ohne Namen',
            self::GEO_LANGUAGE_EN => 'unknown',
        ],
        self::GEO_UNIT_KM => 'km',
        self::GEO_UNIT_MI => 'miles',
        self::GEO_UNIT_IN => 'inch',
        self::GEO_UNIT_SM => 'sm',
        self::GEO_UNIT_FT => 'ft',
        self::GEO_UNIT_YD => 'yd',
    ];

    /**
     * Creates a new Geo object with the specified type
     *
     * @see DB
     *
     * @param string       $type    geo type, for example "DB_Nima"
     * @param string       $dsn     DSN
     * @param array<mixed> $options Options for implementation
     *
     * @return mixed   a newly created Geo object or an Error object on error
     */
    public function setupSource(string $type, string $dsn = '', array $options = [])
    {
        include_once 'Geo/sources/' . $type . '.php';
        $classname = 'Geo_' . $type;

        if (!class_exists($classname)) {
            return PEAR::raiseError('Class not found');
        }

        return new $classname($dsn, $options);
    }

    /**
     * Creates a new Geo_Map object
     */
    public function setupMap(int $x, int $y = -1): Map
    {
        return new Map($x, $y);
    }

    /**
     * Converts degrees/minutes/seconds to degrees
     *
     * Converts a string which represents a latitude/longitude as degree/minutes/seconds
     * to a float degree value. If no valid string is passed it will return 0.
     *
     * @param string $dms latitude/longitude as degree/minutes/seconds
     *
     * @return float   degree
     */
    public static function dms2deg(string $dms, int $language = self::GEO_LANGUAGE_DEFAULT): float
    {
        $negativeSigns       = [self::CFG_STRINGS[self::GEO_ORIENTATION_SHORT][$language][4], self::CFG_STRINGS[self::GEO_ORIENTATION_SHORT][$language][6], '-'];
        $negativeSignsString = self::CFG_STRINGS[self::GEO_ORIENTATION_SHORT][$language][4] . self::CFG_STRINGS[self::GEO_ORIENTATION_SHORT][$language][6];
        if (6 === mb_strlen($dms)) {
            $dms = '0' . $dms;
        } elseif (5 === mb_strlen($dms)) {
            $dms = '00' . $dms;
        }

        $searchPattern = sprintf("|\\s*([%$1s\\-\\+]?)\\s*(\\d{1,3})[\\°\\s]*(\\d{1,2})[\\'\\s]*(\\d{1,2})([\\,\\.]*)(\\d*)[\\'\"\\s]*([%$1s\\-\\+]?)|i", $negativeSignsString);

        if (!preg_match($searchPattern, $dms, $result)) {
            return PEAR::raiseError('No DMS-Format (Like 51° 24\' 32.123\'\' W)');
        }

        if (in_array(mb_strtoupper($result[1]), $negativeSigns, true) || in_array(mb_strtoupper($result[7]), $negativeSigns, true)) {
            $algSign = -1.;
        } else {
            $algSign = 1.;
        }

        if ((360 < 1. * $result[2]) || (60 <= $result[3]) || (60 <= $result[4])) {
            return PEAR::raiseError('Values out of range');
        }

        return $algSign * ($result[2] + (($result[3] + (($result[4] . '.' . $result[6]) * 10 / 6) / 100) * 10 / 6) / 100);
    }

    /**
     * Converts a float value to degrees/minutes/seconds
     *
     * Converts a float value to degrees/minutes/second (e.g. 50.1833300 to 50° 10' 60'')
     * The seconds could contain the number of decimal places one passes to the optional
     * parameter $decPlaces. The direction (N, S, W, E) must be added manually
     * (e.g. $output = "E ".deg2dms(7.441944); )
     *
     * @return string  degrees minutes seconds
     */
    public static function deg2dms(float $degFloat, int $decPlaces = 0): string
    {
        $deg        = abs($degFloat) + 0.5 / 3600 / 10 ** $decPlaces;
        $degree     = floor($deg);
        $deg        = 60 * ($deg - $degree);
        $minutes    = floor($deg);
        $deg        = 60 * ($deg - $minutes);
        $seconds    = floor($deg);
        $subseconds = $deg - $seconds;

        for ($i = 1; $i <= $decPlaces; ++$i) {
            $subseconds = 10 * $subseconds;
        }

        $subseconds = floor($subseconds);
        if (0 < $decPlaces) {
            $seconds .= '.' . sprintf('%0' . $decPlaces . 's', $subseconds);
        }

        return $degree . sprintf("° %s' %s''", $minutes, $seconds);
    }

    /**
     * Returns the radius of the earth
     *
     * Returns the radius of the earth in the given unit.
     * GEO_EARTH_RADIUS is set to the mean value: 6371. km
     * equatorial radius as of WGS84: 6378.137 km
     *
     * @param int $unit use the GEO_UNIT_* constants
     */
    public static function getEarthRadius(int $unit = self::GEO_UNIT_DEFAULT): float
    {
        switch ($unit) {
            case self::GEO_UNIT_KM:        // kilometer
                return self::GEO_EARTH_RADIUS;

            case self::GEO_UNIT_MI:        // mile
                return self::GEO_EARTH_RADIUS * self::GEO_UNIT_KM2MI;

            case self::GEO_UNIT_IN:        // inch
                return self::GEO_EARTH_RADIUS * self::GEO_UNIT_KM2IN;

            case self::GEO_UNIT_SM:        // nautical miles
                return self::GEO_EARTH_RADIUS * self::GEO_UNIT_KM2SM;

            case self::GEO_UNIT_FT:        // foot
                return self::GEO_EARTH_RADIUS * self::GEO_UNIT_KM2FT;

            case self::GEO_UNIT_YD:        // yard
                return self::GEO_EARTH_RADIUS * self::GEO_UNIT_KM2YD;

            default:
                return self::GEO_EARTH_RADIUS;
        }
    }

    /**
     * Returns the barycenter of the passed GeoObjects
     *
     * Early alpha-state, not even sure if it is correct.
     * If anybody has the accurate formula - please let me know.
     *
     * @param array<GeoObject> $theObjects array og GeoObjects
     * @param string           $name       Name of the new Geo Object
     *
     * @return GeoObject       a newly created Geo object
     */
    public static function getBarycenter(array $theObjects, string $name = 'Barycenter'): GeoObject
    {
        $latSum = 0;
        $lonSum = 0;

        foreach ($theObjects as $anObject) {
            $latSum += $anObject->latitude;
            $lonSum += $anObject->longitude;
        }

        $latitude  = $latSum / count($theObjects);
        $longitude = $lonSum / count($theObjects);

        return new GeoObject($name, $latitude, $longitude);
    }
}
