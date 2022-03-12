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

require_once 'Geo/Geo.php';

/**
 * Class Geo_Object
 *
 * Represents georeferenced data. Latitude, longitude and name
 * of the location are the basic information.
 *
 * @access   public
 * @package  Geo
 */
class Geo_Object {

    /**
     * Name
     *
     * @var  string
     */
    var $name;

    /**
     * Latitude (degrees)
     *
     * @var  float
     */
    var $latitude;

    /**
     * Latitude (degrees)
     *
     * @var  float
     */
    var $longitude;

    /**
     * Latitude RAD
     *
     * @var  float
     */
    var $latitudeRad;

    /**
     * Longitude RAD
     *
     * @var  float
     */
    var $longitudeRad;

    /**
     * Latitude DMS
     *
     * @var  string
     */
    var $latitudeDMS;

    /**
     * Longitude DMS (degrees/minutes/seconds)
     *
     * @var  string
     */
    var $longitudeDMS;

    /**
     * Database values
     *
     * @var         array  $databaseValues
     * @deprecated  use $dbValues instead
     * @see         $dbValues
     */
    var $databaseValues = array();

    /**
     * Database values
     *
     * @var         array
     */
    var $dbValues = array();

    /**
     * Constructor Geo_Object
     *
     * $latitude and $longitude absolute > PI, otherwise there is an auto-detection
     *
     * @access  private
     * @param   string    $name       name of the location
     * @param   float     $latitude   latitude given as radiant or degree
     * @param   float     $longitude  longitude given as radiant or degree
     * @param   boolean   $degree     false by default, has to be set to true if
     * @param   array     $dbValues   database values, specific to the source implementation
     * @return  void
     */
    function Geo_Object($name = '', $latitude = 0.0, $longitude= 0.0, $degree = false, $dbValues=array()) {
        global $cfgStrings;

        $this->databaseValues =& $dbValues;

        $this->name = $name;

        $this->dbValues = $dbValues;

        if (strstr($latitude, ' ') && strstr($longitude, ' ')) {
            $this->latitude     = Geo::dms2deg($latitude);
            $this->longitude    = Geo::dms2deg($longitude);
            $this->latitudeRad  = Geo::deg2rad($this->latitude);
            $this->longitudeRad = Geo::deg2rad($this->longitude);
        } else {
            if ((abs($latitude) > M_PI) || (abs($longitude) > M_PI)) {
                $degree = true;
            }
            if ($degree) {
                $this->latitude = $latitude;
                $this->longitude = $longitude;
                $this->latitudeRad = deg2rad($this->latitude);
                $this->longitudeRad = deg2rad($this->longitude);
            } else {
                $this->latitude = rad2deg($latitude);
                $this->longitude = rad2deg($longitude);
                $this->latitudeRad = $latitude;
                $this->longitudeRad = $longitude;
            }
        }
        $this->latitudeDMS = ($this->latitude > 0?$cfgStrings[GEO_ORIENTATION_SHORT][GEO_LANGUAGE_DEFAULT][0]:$cfgStrings[GEO_ORIENTATION_SHORT][GEO_LANGUAGE_DEFAULT][3]).' '.Geo::deg2dms($this->latitude);
        $this->longitudeDMS = ($this->longitude > 0?$cfgStrings[GEO_ORIENTATION_SHORT][GEO_LANGUAGE_DEFAULT][2]:$cfgStrings[GEO_ORIENTATION_SHORT][GEO_LANGUAGE_DEFAULT][5]).' '.Geo::deg2dms($this->longitude);
    }

    /**
     * Distance between this and a given GeoObject (float)
     *
     * Returns the distance between an overgiven and this
     * GeoObject in the passed unit as float.
     *
     * @access  public
     * @param   object  &$geoObject  GeoObject
     * @param   int     $unit        please use GEO_UNIT_* constants
     * @return  float
     * @see     getDistanceString()
     */
    function getDistance(&$geoObject, $unit = GEO_UNIT_DEFAULT) {
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
     * @access  public
     * @param   object   &$geoObject  GeoObject
     * @param   int      $unit        please use GEO_UNIT_* constants
     * @return  string
     * @see     getDistance()
     */
    function getDistanceString(&$geoObject, $unit = GEO_UNIT_DEFAULT) {
        global $cfgStrings;
        return round($this->getDistance($geoObject, $unit), 2).' '.$cfgStrings[$unit];;
    }

    /**
     * north-south distance between this and the passed GeoObject
     *
     * Returns the north-south distance between this and the passed
     * object in the passed unit as float.
     *
     * @access  public
     * @param   object  &$geoObject  GeoObject
     * @param   int     $unit        please use GEO_UNIT_* constants
     * @return  float
     * @see     getDistanceWE()
     */
    function getDistanceNS(&$geoObject, $unit = GEO_UNIT_DEFAULT) {
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
     * @access  public
     * @param   GeoObject  &$geoObject
     * @param   int        $unit        please use GEO_UNIT_* constants
     * @return  float
     * @see     getDistanceNS()
     */
    function getDistanceWE(&$geoObject, $unit = GEO_UNIT_DEFAULT) {
        $earthRadius = Geo::getEarthRadius($unit);
        if ($this->longitudeRad > $geoObject->longitudeRad) {
            $direction = -1;
        } else {
            $direction = 1;
        }
        return $direction * acos(pow(sin($this->latitudeRad), 2) + (pow(cos($this->latitudeRad), 2) * cos($this->longitudeRad - $geoObject->longitudeRad))) * $earthRadius;
    }

    /**
     * Returns orientation between this and the passed GeoObject
     *
     * Returns the orientation of the parametric GeoObject to this,
     * like "GeoObject lies to the north of this"
     *
     * @access  public
     * @param   object  &$geoObject
     * @param   int     $form        GEO_ORIENTATION_SHORT or GEO_ORIENTATION_LONG
     * @param   int     $language    defined in GEO_LANGUAGE_*
     * @return  string
     * @todo    void this ugly global
     */
    function getOrientation(&$geoObject, $form = GEO_ORIENTATION_SHORT, $language = GEO_LANGUAGE_DEFAULT) {

        global $cfgStrings,$cfgLanguages;

        $availableLanguages = array('en', 'de');
        if (!in_array($language, $availableLanguages)) {
            $language = 'en';
        }

        $availableForms = array(GEO_ORIENTATION_SHORT, GEO_ORIENTATION_LONG);
        if (!in_array($form, $availableForms)) {
            $form = GEO_ORIENTATION_SHORT;
        }
        $x = $this->getDistanceWE($geoObject);
        $y = $this->getDistanceNS($geoObject);
        $angle = rad2deg(atan2($y, $x));
        if (($angle > 67.5) && ($angle <= 112.5)) {
            return $cfgStrings[$form][$language][0];
        } elseif (($angle > 22.5) && ($angle <= 67.5)) {
            return $cfgStrings[$form][$language][1];
        } elseif (($angle <= 22.5) && ($angle > -22.5)) {
            return $cfgStrings[$form][$language][2];
        } elseif (($angle <= -22.5) && ($angle > -67.5)) {
            return $cfgStrings[$form][$language][3];
        } elseif (($angle <= -67.5) && ($angle > -112.5)) {
            return $cfgStrings[$form][$language][4];
        } elseif (($angle <= -112.5) && ($angle > -157.5)) {
            return $cfgStrings[$form][$language][5];
        } elseif (($angle > 157.5) || ($angle <= -157.5)) {
            return $cfgStrings[$form][$language][6];
        } elseif (($angle > 112.5) && ($angle <= 157.5)) {
            return $cfgStrings[$form][$language][7];
        } else {
            return '';
        }
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
     * @access  public
     * @param   int     $indent
     * @return  string
     * @see     getRDFDataFile()
     */
    function getRDFPointEntry($indent=0) {
        $indentString = str_repeat ("\t", $indent);
        $rdfEntry  = $indentString."<geo:Point>\n";
        $rdfEntry .= $indentString."\t<rdfs:label>".utf8_encode($this->name)."</rdfs:label>\n";
        $rdfEntry .= $indentString."\t<geo:lat>".$this->latitude."</geo:lat>\n";
        $rdfEntry .= $indentString."\t<geo:long>".$this->longitude."</geo:long>\n";
        $rdfEntry .= $indentString."</geo:Point>\n";
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
     * @access  public
     * @param   int
     * @return  string
     * @see     getRDFPointEntry()
     */
    function getRDFDataFile() {
        $rdfData = "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n";
        $rdfData .= "\txmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\"\n";
        $rdfData .= "\txmlns:geo=\"http://www.w3.org/2003/01/geo/wgs84_pos#\">\n\n";
        $rdfData .= $this->getRDFPointEntry(1);
        $rdfData .= "\n</rdf:RDF>";
        return $rdfData;
    }

    /**
     * Returns a short info about the GeoObject
     *
     * @access  public
     * @return  string
     */
    function getInfo() {
        return $this->name.' ('.$this->latitude.'/'.$this->longitude.')';
    }

    
    /**
     * Sort-function for GeoObjects
     */
    function alphaSort($a, $b) {
        if (strtolower($a) == strtolower($b)) return 0;
        if (strtolower($a) < strtolower($b)) return -1;
        return 1;
    }
    
    function nameSort($a, $b) {
        if (!is_object($a) || !is_object($b)) return 0;
        if (!isset($a->name) || !isset($b->name)) return 0;
        return Object::alphaSort($a->name, $b->name, $ab);
    }

    function distanceSort($a, $b) {
        if (!is_object($a) || !is_object($b)) return 0;
        if (!isset($a->dbValues['distance']) || !isset($b->dbValues['distance'])) return 0;
        if ($a->dbValues['distance'] == $b->dbValues['distance']) return 0;
        if ($a->dbValues['distance'] < $b->dbValues['distance']) return -1;
        return 1;
    }
}
?>
