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

require_once 'Geo/sources/Common.php';

/**
 * Geo_RDF
 *
 * @access   public
 * @package  Geo
 */
class Geo_RDF extends Geo_Common {

    var $geoObjectArray=array();

    /**
     * Constructor Geo_RDF
     *
     * One could leave the parameter. getGeoObjectArray() will return an empty
     * array, until setArrayOfGeoObjects($rdfContent) sets a value.
     * It might be ok to just use the extractGeoObjects($rdfContent)-function.
     *
     * @access  public
     * @param   string  $rdfContent  content of the rdf-file
     * @param   array   $options
     * @return  void
     */
    function Geo_RDF($rdfContent,$options=array()) {
        $this->setArrayOfGeoObjects($rdfContent);
        $this->setOptions($options);
    }

    /**
     * Sets the instance-variable to the new value.
     *
     * @access  private
     * @param   string    $rdfContent    string (RDF or URL)
     * @return  void
     */
    function setArrayOfGeoObjects($rdfContent) {
        if (strpos($rdfContent, "http://") === 0) {
            $rdfContent = file_get_contents($rdfContent);
        }
        $this->geoObjectArray = $this->extractGeoObjects($rdfContent);
    }

    /**
     * Returns an array of GeoObjects, which are extracted from the passed string/file-content
     *
     * @access  private
     * @param   string    $rdfContent    string (RDF)
     * @return  array
     * @todo    void this ugly global
     * @todo    GEO_LANGUAGE_* as parameter
     */
    function extractGeoObjects($rdfContent) {
        global $cfgStrings;

        // Make the file flat
        $rdfContent = str_replace(array("\r", "\n"), array("", ""), $rdfContent);
        $rawPpointArray = explode("</geo:Point>", $rdfContent);
        $pointArray = array();
        foreach ($rawPpointArray as $onePointSetRaw) {
            $parts = explode("<geo:Point>", $onePointSetRaw);
            $pointArray[] = $parts[1];
        }
        $returnArray = array();
        foreach ($pointArray as $onePoint) {
            $error = false;
            $searchName = "|(.*)(<rdfs:label>)(.*)(</rdfs:label>)(.*)|i";
            if (preg_match($searchName, $onePoint, $searchResult)) {
                $name = $searchResult[3];
            } else {
                $name = $cfgStrings[GEO_ERR_NONAME][GEO_LANGUAGE_DEFAULT];
            }
            $searchLatitude = "|(.*)(<geo:lat>)(.*)(</geo:lat>)(.*)|i";
            if (preg_match($searchLatitude, $onePoint, $searchResult)) {
                $latitude = $searchResult[3];
            } else {
                $error = true;
            }
            $searchLongitude = "|(.*)(<geo:long>)(.*)(</geo:long>)(.*)|i";
            if (preg_match($searchLongitude, $onePoint, $searchResult)) {
                $longitude = $searchResult[3];
            } else {
                $error = true;
            }
            if (!$error) {
                $returnArray[] = new Geo_Object($name, $latitude, $longitude);
            }
        }
        return $returnArray;
    }

    /**
     * Find GeoObjects
     *
     * Returns an array of GeoObjects which fits the $searchConditions
     *
     * @access  public
     * @param   string  $searchConditions  string, see preg_match
     * @return  array
     * @todo    use $searchConditions
     */
    function findGeoObject($searchConditions='*') {
        $objects=array();
        foreach($this->geoObjectArray as $item) {
            if (preg_match($searchConditions, $item->name)) {
                $objects[] = $item;
            }
        }
        return $objects;
    }

    /**
     * Find GeoObjects near an overgiven GeoObject
     *
     * Searches for GeoObjects, which are in a specified radius around the passed GeoBject.
     * Default is radius of 100 (100 of specified unit, see configuration and maxHits of 50
     * Returns an array of GeoObjects which lie in the radius of the passed GeoObject.
     *
     * @access  public
     * @param   object  &$geoObject
     * @param   int     $maxRadius
     * @param   int     $maxHits
     * @return  array
     */
    function findCloseByGeoObjects(&$geoObject, $maxRadius = 100, $maxHits = 50) {
        $objects=array();
        foreach($this->geoObjectArray as $item) {
            if (count($objects) >= $maxHits) break;
            $distance = $geoObject->getDistance($item, GEO_UNIT_DEFAULT);
            if ($distance <= $maxRadius) {
                $copyOfItem = $item;
                $copyOfItem->dbValues['distance'] = $distance;
                $copyOfItem->dbValues['distanceTo'] = $geoObject->name;
                $objects[] = $copyOfItem;
            }
        }
        usort($objects, array("Geo_Object", "distanceSort"));
        return $objects;
    }

}
?>
