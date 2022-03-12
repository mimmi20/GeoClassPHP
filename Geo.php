<?php
//
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
//

require_once 'PEAR.php';
require_once 'DB.php';
require_once 'Geo/Object.php';

/**
 * if GEO_DEBUG_SQL is true, SQL-Queries will be printed
 */
@define ('GEO_DEBUG_SQL',   false);

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

define ('GEO_UNIT_KM',      1);
define ('GEO_UNIT_MI',      2);
define ('GEO_UNIT_IN',      3);
define ('GEO_UNIT_SM',      4);
define ('GEO_UNIT_FT',      11);
define ('GEO_UNIT_YD',      12);
define ('GEO_UNIT_DEFAULT', GEO_UNIT_KM);

define ('GEO_UNIT_M2KM',     0.001);
define ('GEO_UNIT_MI2KM',    1.609343994);
define ('GEO_UNIT_IN2KM',    0.0000254);
define ('GEO_UNIT_SM2KM',    1.852);
define ('GEO_UNIT_FT2KM',    0.0003048);
define ('GEO_UNIT_YD2KM',    0.0009144);

define ('GEO_UNIT_KM2M',     (1/GEO_UNIT_M2KM));
define ('GEO_UNIT_KM2MI',    (1/GEO_UNIT_MI2KM));
define ('GEO_UNIT_KM2IN',    (1/GEO_UNIT_IN2KM));
define ('GEO_UNIT_KM2SM',    (1/GEO_UNIT_SM2KM));
define ('GEO_UNIT_KM2FT',    (1/GEO_UNIT_FT2KM));
define ('GEO_UNIT_KM2YD',    (1/GEO_UNIT_YD2KM));

/**
 * This constant contains the radius of the earth in kilometers
 * GEO_EARTH_RADIUS is set to the mean value: 6371. km
 * equatorial radius as of WGS84: 6378.137 km
 */

define('GEO_EARTH_RADIUS', 6371.0);

/**
 * Following constants are for later use.
 */

define('GEO_RELATION_EQUAL',            '=');
define('GEO_RELATION_NOTEQUAL',        '!=');
define('GEO_RELATION_LESS',             '<');
define('GEO_RELATION_GREATER',          '>');
define('GEO_RELATION_LESSOREQUAL',     '<=');
define('GEO_RELATION_GREATEROREQUAL',  '>=');
define('GEO_RELATION_LIKE',          'LIKE');
define('GEO_RELATION_NOTLIKE',   'NOT LIKE');

/**
 * Possible forms of the orientation string
 *
 * GEO_ORIENTATION_SHORT => N, W, E, NE, etc.
 * GEO_ORIENTATION_LONG => north, south west, etc.
 */

define("GEO_ORIENTATION_SHORT",   5);
define("GEO_ORIENTATION_LONG",    6);
define('GEO_ORIENTATION_DEFAULT', GEO_ORIENTATION_SHORT);

/**
 * Languages
 */

define('GEO_LANGUAGE_EN',      7);
define('GEO_LANGUAGE_DE',      8);
define('GEO_LANGUAGE_DEFAULT', GEO_LANGUAGE_EN);

/**
 * Error codes
 *
 * GEO_ERR_ERROR => Default Error
 * GEO_ERR_NONAME => Default value for locations without a proper name
 */

define('GEO_ERR_ERROR',     9);
define('GEO_ERR_NONAME',   10);

/**
 * Encodings (for use with databases)
 * string instead of int to assure backwards compatibility
 */

define('GEO_ENCODING_UTF_8',        'utf8');
define('GEO_ENCODING_ISO_8859_1',   'latin1');
define('GEO_ENCODING_DEFAULT',      GEO_ENCODING_ISO_8859_1);


/**
 * All the following stuff is for internationalisation.
 * German and English are provided.
 * Perhaps I will include an external language file later.
 */

$cfgStrings[GEO_ORIENTATION_SHORT][GEO_LANGUAGE_DE] = array('N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW');
$cfgStrings[GEO_ORIENTATION_SHORT][GEO_LANGUAGE_EN] = array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW');
$cfgStrings[GEO_ORIENTATION_LONG][GEO_LANGUAGE_DE]  = array('Norden', 'Nordosten', 'Osten', 'Südosten', 'Süden', 'Südwesten', 'Westen', 'Nordwesten');
$cfgStrings[GEO_ORIENTATION_LONG][GEO_LANGUAGE_EN]  = array('north', 'north east', 'east', 'south east', 'south', 'south west', 'west', 'north west');

$cfgStrings[GEO_UNIT_KM] = 'km';
$cfgStrings[GEO_UNIT_MI] = 'miles';
$cfgStrings[GEO_UNIT_IN] = 'inch';
$cfgStrings[GEO_UNIT_SM] = 'sm';
$cfgStrings[GEO_UNIT_FT] = 'ft';
$cfgStrings[GEO_UNIT_YD] = 'yd';

$cfgStrings[GEO_ERR_ERROR][GEO_LANGUAGE_DE]  = 'Fehler';
$cfgStrings[GEO_ERR_ERROR][GEO_LANGUAGE_EN]  = 'Error';
$cfgStrings[GEO_ERR_NONAME][GEO_LANGUAGE_DE] = 'Ohne Namen';
$cfgStrings[GEO_ERR_NONAME][GEO_LANGUAGE_EN] = 'unknown';


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
 *
 * @access   public
 * @package  Geo
 */
class Geo extends PEAR
{

    /**
     * Creates a new Geo object with the specified type
     *
     * @access  public
     * @param   string  $type     geo type, for example "DB_Nima"
     * @param   string  $dsn      DSN
     * @param   array   $options  Options for implementation
     * @return  mixed   a newly created Geo object or an Error object on error
     * @see     DB
     */
    function &setupSource($type,$dsn='',$options=array()) {
        include_once('Geo/sources/'.$type.'.php');
        $classname = 'Geo_'.$type;

        if (!class_exists($classname)) {
            return PEAR::raiseError('Class not found');
        }
        $obj =& new $classname($dsn, $options);
        return $obj;
    }

    /**
     * Creates a new Geo_Map object
     *
     * @access  public
     * @param   int     $x
     * @param   int     $y
     * @return  mixed   a newly created Geo object, or a Error object on error
     */
    function &setupMap($x, $y=-1) {
        @include_once('Geo/Map.php');

        if (!class_exists('Geo_Map')) {
            return PEAR::raiseError('Class not found');
        }
        $obj =& new Geo_Map($x,$y);
        return $obj;
    }

	/**
     * Converts degrees/minutes/seconds to degrees
     *
     * Converts a string which represents a latitude/longitude as degree/minutes/seconds
     * to a float degree value. If no valid string is passed it will return 0.
     *
     * @access  public
     * @param   string  $dms  latitude/longitude as degree/minutes/seconds
     * @return  float   degree
     */
    function dms2deg($dms, $language = GEO_LANGUAGE_DEFAULT) {
        global $cfgStrings;
		$negativeSigns = array($cfgStrings[GEO_ORIENTATION_SHORT][$language][4], $cfgStrings[GEO_ORIENTATION_SHORT][$language][6], "-");
		$negativeSignsString = $cfgStrings[GEO_ORIENTATION_SHORT][$language][4].$cfgStrings[GEO_ORIENTATION_SHORT][$language][6];
		if (strlen($dms) == 6) {
			$dms = "0".$dms;
		} elseif (strlen($dms) == 5) {
			$dms = "00".$dms;
		}
		$searchPattern = "|\s*([$negativeSignsString\-\+]?)\s*(\d{1,3})[\°\s]*(\d{1,2})[\'\s]*(\d{1,2})([\,\.]*)(\d*)[\'\"\s]*([$negativeSignsString\-\+]?)|i";
        if(preg_match($searchPattern, $dms, $result)) {
            if (in_array(strtoupper($result[1]), $negativeSigns) || in_array(strtoupper($result[7]), $negativeSigns)) {
                $algSign = -1.;
            } else {
                $algSign = 1.;
    	    }
			if (((1. * $result[2]) > 360) || ($result[3] >= 60) || ($result[4] >= 60)) {
				return PEAR::raiseError('Values out of range');
			}
            return $algSign * ($result[2] +(($result[3] + (($result[4].".".$result[6]) * 10/6)/100)*10/6)/100);
        } else {
            return PEAR::raiseError('No DMS-Format (Like 51° 24\' 32.123\'\' W)');
        }
    }

    /**
     * Converts a float value to degrees/minutes/seconds
     *
     * Converts a float value to degrees/minutes/second (e.g. 50.1833300 to 50° 10' 60'')
     * The seconds could contain the number of decimal places one passes to the optional
     * parameter $decPlaces. The direction (N, S, W, E) must be added manually
     * (e.g. $output = "E ".deg2dms(7.441944); )
     *
     * @access  public
     * @param   float   $degFloat
     * @param   int     $decPlaces
     * @return  string  degrees minutes seconds
     */
    function deg2dms($degFloat, $decPlaces = 0) {
        $deg = abs($degFloat) + 0.5 / 3600 / pow(10, $decPlaces);
        $degree = floor($deg);
        $deg = 60 * ($deg - $degree);
        $minutes = floor($deg);
        $deg = 60 * ($deg - $minutes);
        $seconds = floor($deg);
        $subseconds = ($deg - $seconds);
        for($i=1;$i<=$decPlaces;$i++) {
          $subseconds = 10 * $subseconds;
        }
        $subseconds = floor($subseconds);
        if ($decPlaces > 0) {
          $seconds = $seconds.".".sprintf("%0${decPlaces}s",$subseconds);
        }
        return $degree."° $minutes' $seconds''";
    }

    /**
     * Returns the radius of the earth
     *
     * Returns the radius of the earth in the given unit.
     * GEO_EARTH_RADIUS is set to the mean value: 6371. km
     * equatorial radius as of WGS84: 6378.137 km
     *
     * @access  public
     * @param   int     $unit  use the GEO_UNIT_* constants
     */
    function getEarthRadius($unit = GEO_UNIT_DEFAULT) {
        switch ($unit) {
            case GEO_UNIT_KM:        // kilometer
                return GEO_EARTH_RADIUS;
            case GEO_UNIT_MI:        // mile
                return GEO_EARTH_RADIUS * GEO_UNIT_KM2KM * GEO_UNIT_KM2MI;
            case GEO_UNIT_IN:        // inch
                return GEO_EARTH_RADIUS * GEO_UNIT_KM2KM * GEO_UNIT_KM2IN;
            case GEO_UNIT_SM:        // nautical miles
                return GEO_EARTH_RADIUS * GEO_UNIT_KM2KM * GEO_UNIT_KM2SM;
            case GEO_UNIT_FT:        // foot
                return GEO_EARTH_RADIUS * GEO_UNIT_KM2KM * GEO_UNIT_KM2FT;
            case GEO_UNIT_YD:        // yard
                return GEO_EARTH_RADIUS * GEO_UNIT_KM2KM * GEO_UNIT_KM2YD;
            default:
                return GEO_EARTH_RADIUS;
        }
    }

    /**
     * Returns the barycenter of the passed GeoObjects
     *
     * Early alpha-state, not even sure if it is correct.
     * If anybody has the accurate formula - please let me know.
     *
     * @access  public
     * @param   array       $theObjects  array og GeoObjects
     * @param   string      $name Name of the new Geo Object
     * @return  mixed       a newly created Geo object or null
     */
    function getBarycenter($theObjects, $name="Barycenter") {
        if (!is_array($theObjects)) {
            return null;
        }
        $latSum = 0;
        $lonSum = 0;
        foreach ($theObjects as $anObject) {
            $latSum += $anObject->latitude;
            $lonSum += $anObject->longitude;
        }
        $latitude = $latSum / count($theObjects);
        $longitude = $lonSum / count($theObjects);
        return new Geo_Object($name, $latitude, $longitude);
    }
    
}
?>
