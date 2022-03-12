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

require_once "Geo/sources/DB.php";

/**
 * Geo_Nima
 *
 * @access   public
 * @package  Geo
 */
class Geo_DB_Nima extends Geo_DB {

    /**
     * some options
     *
     * @var  array    options
     */
    var $options = array(
        'language' => 'en',
        'table' => 'nima',
        'fields' => array(
            'name' => 'FULL_NAME',
            'longitude' => 'DD_LONG',
            'latitude' => 'DD_LAT',
        ),
        'order' => 'SORT_NAME',
        'native' => true,
        'degree' => true,
        'unit' => GEO_UNIT_DEFAULT,
        'encoding' => GEO_ENCODING_UTF_8
    );

    /**
     * country of the Nima database
     *
     * @var  string  $country
     */
    var $country = "unknown";

    /**
     * states of the Nima database
     *
     * @var  array  $states
     */
    var $states = array();


    /**
     * constructor Geo_Nima
     *
     * @var		string	$dsn
     * @var		array	$options
     * @return	void
     */
    function Geo_DB_Nima($dsn, $options=array()) {
        $this->_connectDB($dsn);
        $this->setOptions($options);
        $this->initNimaInformation();
    }

    /**
     * Determines further information of the database.
     *
     * Function is called by the constructor.
     *
     * @access	private
     * @return	void
     */
    function initNimaInformation() {
        global $cfgStrings;

        // find GeoObject with native name of the country
        $countryObjectArray = $this->performQuery("SELECT * FROM ".$this->options['table']." WHERE DSG = 'PCLI' AND NT = 'N'");

        if (count($countryObjectArray) == 1) {
            foreach($countryObjectArray AS $countryObject) {
                if ($countryObject->dbValues['SHORT_FORM']) {
                    $this->country = $countryObject->dbValues['SHORT_FORM'];
                } elseif ($countryObject->name) {
                    $this->country = $countryObject->name;
                } else {
                    $this->country = $cfgStrings[GEO_ERR_NONAME][GEO_LANGUAGE];
                }
            }
        } else {
            $this->country = $cfgStrings[GEO_ERR_NONAME][GEO_LANGUAGE];
        }

        // find GeoObject with native name of the ADM1 (states)
        $statesObjectArray = $this->performQuery("SELECT * FROM ".$this->options['table']." WHERE DSG = 'ADM1' AND NT = 'N' ORDER BY ADM1");
        foreach($statesObjectArray AS $statesObject) {
            $key = $statesObject->dbValues['ADM1'];
            if ($statesObject->dbValues['SHORT_FORM']) {
                $this->states[$key] = $statesObject->dbValues['SHORT_FORM'];
            } elseif ($statesObject->name) {
                $this->states[$key] = $statesObject->name;
            } else {
                $this->states[$key] = $cfgStrings[GEO_ERR_NONAME][GEO_LANGUAGE];
            }
        }
    }

    /**
     * Searches for populated places (FC = P) which fits the passed $name. You could use SQL compatible wildcards.
     *
     * A set or a single place classification could be passed.
     * By default all classifications (1=big, 5=small, 0=unclassified or very small) are considered.
     *
     * @access  public
     * @param   string  $name
     * @param   string  $placeClassificationSet
     * @return  array   GeoObjects
     */
    function findClassifiedPopulatedPlace($name, $placeClassificationSet = "1,2,3,4,5,0") {
        return $this->findGeoObject($name, "P", $placeClassificationSet);
    }

    /**
     * Searches for GeoObjects which fits the passed name (could contain SQL compatible wildcards)
     * and the given sets of feature classifications and place classifications.
     *
     * By default all classifications ("A,P,V,L,U,R,T,H,S") respective (1=big, 5=small, 0=unclassified or very small) are considered.
     *
     * @access  public
     * @param   string  $name
     * @param   string  $featureClassificationSet
     * @param   string  $placeClassificationSet
     * @return  mixed   arry of GeoObjects or DBError
     * @see     DB
     */
    function findGeoObject($name, $featureClassificationSet = "A,P,V,L,U,R,T,H,S", $placeClassificationSet = "0,1,2,3,4,5,6") {
        if ($this->options["encoding"] == GEO_ENCODING_UTF_8) {
            $name = utf8_encode($name);
        }
        $query = "SELECT *".
                 " FROM  ".$this->options['table'].
                 " WHERE FC IN ('".str_replace(",", "','", $featureClassificationSet)."') AND PC IN ($placeClassificationSet)".
                 " AND ".$this->options['fields']['name']." LIKE '".$name."'".
                 " ORDER BY ".$this->options['order'];
        return $this->performQuery($query);
    }

    /**
     * Searches for GeoObjects, which are in a specified radius around the passed GeoObject.
     *
     * Default is radius of 100 (100 of specified unit, see configuration and maxHits of 50.
     * A set or a single feature classifications and a single or a set of feature classifications place classification could be passed.
     * By default all classifications ("A,P,V,L,U,R,T,H,S") respective (1=big, 5=small, 0=unclassified or very small) are considered.
     *
     * @access  public
     * @param   object  &$geoObject
     * @param   int     $maxRadius
     * @param   int     $maxHits
     * @param   string  $featureClassificationSet
     * @param   string  $placeClassificationSet
     * @return  mixed   arry of GeoObjects or DBError
     */
    function findCloseByGeoObjects(&$geoObject, $maxRadius = 100, $maxHits = 50, $featureClassificationSet = "A,P,V,L,U,R,T,H,S", $placeClassificationSet = "0,1,2,3,4,5,6") {
        $query = "SELECT *,";
        $query .= " ".$this->getDistanceFormula($geoObject)." AS distance";
        $query .= " FROM ".$this->options['table'];
        $query .= " WHERE FC IN ('".str_replace(",", "','", $featureClassificationSet)."') AND PC IN ($placeClassificationSet)";
        $query .= " AND ".$this->getDistanceFormula($geoObject)." < $maxRadius";
        if ($this->options['native']) {
            $query .= " AND NT = 'N'";
        }
        $query .= " ORDER BY distance";

        if ($maxHits) {
            $query .= " LIMIT 0, $maxHits";
        }
        return $this->performQuery($query);
    }

}
?>
