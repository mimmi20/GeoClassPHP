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

use function abs;
use function acos;
use function atan2;
use function cos;
use function deg2rad;
use function in_array;
use function is_object;
use function mb_strstr;
use function mb_strtolower;
use function rad2deg;
use function round;
use function sin;
use function str_repeat;
use function utf8_encode;

use const M_PI;

/**
 * Represents georeferenced data. Latitude, longitude and name
 * of the location are the basic information.
 */
final class GeoObject
{
    /**
     * Name
     */
    public string $name;

    /**
     * Latitude (degrees)
     */
    public float $latitude;

    /**
     * Latitude (degrees)
     */
    public float $longitude;

    /**
     * Latitude RAD
     */
    public float $latitudeRad;

    /**
     * Longitude RAD
     */
    public float $longitudeRad;

    /**
     * Latitude DMS
     */
    public string $latitudeDMS;

    /**
     * Longitude DMS (degrees/minutes/seconds)
     */
    public string $longitudeDMS;

    /**
     * Database values
     */
    public array $dbValues = [];

    /**
     * Constructor Geo_Object
     *
     * $latitude and $longitude absolute > PI, otherwise there is an auto-detection
     *
     * @param string $name      name of the location
     * @param float  $latitude  latitude given as radiant or degree
     * @param float  $longitude longitude given as radiant or degree
     * @param bool   $degree    false by default, has to be set to true if
     * @param array  $dbValues  database values, specific to the source implementation
     *
     * @return  void
     */
    public function __construct(string $name = '', float $latitude = 0.0, float $longitude = 0.0, bool $degree = false, array $dbValues = [])
    {
        $this->name = $name;

        $this->dbValues = $dbValues;

        if (mb_strstr((string) $latitude, ' ') && mb_strstr((string) $longitude, ' ')) {
            $this->latitude     = Geo::dms2deg((string) $latitude);
            $this->longitude    = Geo::dms2deg((string) $longitude);
            $this->latitudeRad  = Geo::deg2rad($this->latitude);
            $this->longitudeRad = Geo::deg2rad($this->longitude);
        } else {
            if ((M_PI < abs($latitude)) || (M_PI < abs($longitude))) {
                $degree = true;
            }

            if ($degree) {
                $this->latitude     = $latitude;
                $this->longitude    = $longitude;
                $this->latitudeRad  = deg2rad($this->latitude);
                $this->longitudeRad = deg2rad($this->longitude);
            } else {
                $this->latitude     = rad2deg($latitude);
                $this->longitude    = rad2deg($longitude);
                $this->latitudeRad  = $latitude;
                $this->longitudeRad = $longitude;
            }
        }

        $this->latitudeDMS  = (0 < $this->latitude ? Geo::CFG_STRINGS[Geo::GEO_ORIENTATION_SHORT][Geo::GEO_LANGUAGE_DEFAULT][0] : Geo::CFG_STRINGS[Geo::GEO_ORIENTATION_SHORT][Geo::GEO_LANGUAGE_DEFAULT][3]) . ' ' . Geo::deg2dms($this->latitude);
        $this->longitudeDMS = (0 < $this->longitude ? Geo::CFG_STRINGS[Geo::GEO_ORIENTATION_SHORT][Geo::GEO_LANGUAGE_DEFAULT][2] : Geo::CFG_STRINGS[Geo::GEO_ORIENTATION_SHORT][Geo::GEO_LANGUAGE_DEFAULT][5]) . ' ' . Geo::deg2dms($this->longitude);
    }

    /**
     * Distance between this and a given GeoObject (float)
     *
     * Returns the distance between an overgiven and this
     * GeoObject in the passed unit as float.
     *
     * @see     getDistanceString()
     *
     * @param GeoObject $geoObject GeoObject
     * @param int       $unit      please use GEO_UNIT_* constants
     */
    public function getDistance(self $geoObject, int $unit = Geo::GEO_UNIT_DEFAULT): float
    {
        $earthRadius = Geo::getEarthRadius($unit);

        return acos((sin($this->latitudeRad) * sin($geoObject->latitudeRad)) + (cos($this->latitudeRad) * cos($geoObject->latitudeRad) * cos($this->longitudeRad - $geoObject->longitudeRad))) * $earthRadius;
    }

    /**
     * Distance between this and the passed GeoObject (string)
     *
     * Returns the distance between an overgiven and this GeoObject
     * rounded to 2 decimal places. The passed unit is returned at
     * the end of the sting.
     *
     * @see     getDistance()
     *
     * @param GeoObject $geoObject GeoObject
     * @param int       $unit      please use GEO_UNIT_* constants
     */
    public function getDistanceString(self $geoObject, int $unit = Geo::GEO_UNIT_DEFAULT): string
    {
        return round($this->getDistance($geoObject, $unit), 2) . ' ' . Geo::CFG_STRINGS[$unit];
    }

    /**
     * north-south distance between this and the passed GeoObject
     *
     * Returns the north-south distance between this and the passed
     * object in the passed unit as float.
     *
     * @see     getDistanceWE()
     *
     * @param GeoObject $geoObject GeoObject
     * @param int       $unit      please use GEO_UNIT_* constants
     */
    public function getDistanceNS(self $geoObject, int $unit = Geo::GEO_UNIT_DEFAULT): float
    {
        $earthRadius = GEO::getEarthRadius($unit);
        if ($this->latitudeRad > $geoObject->latitudeRad) {
            $direction = -1;
        } else {
            $direction = 1;
        }

        return $direction * acos((sin($this->latitudeRad) * sin($geoObject->latitudeRad)) + (cos($this->latitudeRad) * cos($geoObject->latitudeRad))) * $earthRadius;
    }

    /**
     * west-east distance between this and the passed GeoObject
     *
     * Returns the west-east distance between this and the passed
     * object in the passed unit as float.
     *
     * @see     getDistanceNS()
     *
     * @param int $unit please use GEO_UNIT_* constants
     */
    public function getDistanceWE(self $geoObject, int $unit = Geo::GEO_UNIT_DEFAULT): float
    {
        $earthRadius = Geo::getEarthRadius($unit);
        if ($this->longitudeRad > $geoObject->longitudeRad) {
            $direction = -1;
        } else {
            $direction = 1;
        }

        return $direction * acos(sin($this->latitudeRad) ** 2 + (cos($this->latitudeRad) ** 2 * cos($this->longitudeRad - $geoObject->longitudeRad))) * $earthRadius;
    }

    /**
     * Returns orientation between this and the passed GeoObject
     *
     * Returns the orientation of the parametric GeoObject to this,
     * like "GeoObject lies to the north of this"
     *
     * @param int $form     GEO_ORIENTATION_SHORT or GEO_ORIENTATION_LONG
     * @param int $language defined in GEO_LANGUAGE_*
     *
     * @todo    void this ugly global
     */
    public function getOrientation(self $geoObject, int $form = Geo::GEO_ORIENTATION_SHORT, int $language = Geo::GEO_LANGUAGE_DEFAULT): string
    {
        $availableLanguages = [Geo::GEO_LANGUAGE_EN, Geo::GEO_LANGUAGE_DE];
        if (!in_array($language, $availableLanguages, true)) {
            $language = Geo::GEO_LANGUAGE_EN;
        }

        $availableForms = [Geo::GEO_ORIENTATION_SHORT, Geo::GEO_ORIENTATION_LONG];
        if (!in_array($form, $availableForms, true)) {
            $form = Geo::GEO_ORIENTATION_SHORT;
        }

        $x     = $this->getDistanceWE($geoObject);
        $y     = $this->getDistanceNS($geoObject);
        $angle = rad2deg(atan2($y, $x));
        if ((67.5 < $angle) && (112.5 >= $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_N];
        }

        if ((22.5 < $angle) && (67.5 >= $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_NE];
        }

        if ((22.5 >= $angle) && (-22.5 < $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_E];
        }

        if ((-22.5 >= $angle) && (-67.5 < $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_SE];
        }

        if ((-67.5 >= $angle) && (-112.5 < $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_S];
        }

        if ((-112.5 >= $angle) && (-157.5 < $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_SW];
        }

        if ((157.5 < $angle) || (-157.5 >= $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_W];
        }

        if ((112.5 < $angle) && (157.5 >= $angle)) {
            return Geo::CFG_STRINGS[$form][$language][Geo::GEO_ORIENTATION_NW];
        }

        return '';
    }

    /**
     * Returns an RDF-point entry
     *
     * Returns an RDF-point entry as described here: http://www.w3.org/2003/01/geo/
     * respective as shownhere, with label/name: http://www.w3.org/2003/01/geo/test/xplanet/la.rdf
     * or, with multiple entries, here: http://www.w3.org/2003/01/geo/test/towns.rdf
     * Use getRDFDataFile() for a document with header and footer.
     * The indent-paramter allows a better structure.
     *
     * @see     getRDFDataFile()
     */
    public function getRDFPointEntry(int $indent = 0): string
    {
        $indentString = str_repeat("\t", $indent);
        $rdfEntry     = $indentString . "<geo:Point>\n";
        $rdfEntry    .= $indentString . "\t<rdfs:label>" . utf8_encode($this->name) . "</rdfs:label>\n";
        $rdfEntry    .= $indentString . "\t<geo:lat>" . $this->latitude . "</geo:lat>\n";
        $rdfEntry    .= $indentString . "\t<geo:long>" . $this->longitude . "</geo:long>\n";
        $rdfEntry    .= $indentString . "</geo:Point>\n";

        return $rdfEntry;
    }

    /**
     * Returns an RDF-Data-File
     *
     * Returns an RDF-Data-File as described here: http://www.w3.org/2003/01/geo/
     * respective as shownhere, with label/name: http://www.w3.org/2003/01/geo/test/xplanet/la.rdf
     * or, with multiple entries, here: http://www.w3.org/2003/01/geo/test/towns.rdf
     * The only entry is this Object.
     *
     * @see     getRDFPointEntry()
     *
     * @param int
     */
    public function getRDFDataFile(): string
    {
        $rdfData  = "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n";
        $rdfData .= "\txmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\"\n";
        $rdfData .= "\txmlns:geo=\"http://www.w3.org/2003/01/geo/wgs84_pos#\">\n\n";
        $rdfData .= $this->getRDFPointEntry(1);
        $rdfData .= "\n</rdf:RDF>";

        return $rdfData;
    }

    /**
     * Returns a short info about the GeoObject
     */
    public function getInfo(): string
    {
        return $this->name . ' (' . $this->latitude . '/' . $this->longitude . ')';
    }

    /**
     * Sort-function for GeoObjects
     */
    public static function alphaSort(string $a, string $b): int
    {
        if (mb_strtolower($a) === mb_strtolower($b)) {
            return 0;
        }

        if (mb_strtolower($a) < mb_strtolower($b)) {
            return -1;
        }

        return 1;
    }

    public function nameSort(self $a, self $b): int
    {
        if (!is_object($a) || !is_object($b)) {
            return 0;
        }

        if (!isset($a->name) || !isset($b->name)) {
            return 0;
        }

        return self::alphaSort($a->name, $b->name);
    }

    public function distanceSort(self $a, self $b): int
    {
        if (!isset($a->dbValues['distance']) || !isset($b->dbValues['distance'])) {
            return 0;
        }

        if ($a->dbValues['distance'] === $b->dbValues['distance']) {
            return 0;
        }

        if ($a->dbValues['distance'] < $b->dbValues['distance']) {
            return -1;
        }

        return 1;
    }
}
