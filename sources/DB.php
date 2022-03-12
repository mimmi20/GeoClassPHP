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
 * Geo_DB
 *
 * @access   public
 * @package  Geo
 */
class Geo_DB extends Geo_Common {

    /**
     * DB object (PEAR::DB)
     * @var     object  $db  DB
     */
    var $db = false;

    /**
     * some options
     *
     * @access	private
     * @var		array    $options  saves the options
     */
    var $options = array(
        'language' => GEO_LANGUAGE_DEFAULT,
        'table' => 'geo',
        'fields' => array(
            'name' => 'name',
            'longitude' => 'longitude',
            'latitude' => 'latitude',
        ),
        'order' => 'ort',
        'degree' => true,
        'unit' => GEO_UNIT_DEFAULT,
        'encoding' => GEO_ENCODING_DEFAULT
    );

    /**
     * constructor Geo_DB
     *
     * @access	public
     * @var     string  $dsn
     * @var     array   $options
     * @return  void
     */
    function Geo_DB($dsn, $options=array()) {
        $this->_connectDB($dsn);
        $this->setOptions($options);
    }

    /**
     * Establishes a connection to the database
     *
     * @access  private
     * @param   string  $dsn
     * @return  void
     */
    function _connectDB($dsn) {
        $this->db = DB::connect($dsn);
        if (DB::isError($this->db)) {
            return $this->db;
        }
        $this->db->setFetchMode(DB_FETCHMODE_ASSOC);
    }

    /**
     * Find GeoObjects
     *
     * Returns an array of GeoObjects which fits the $searchConditions
     *
     * @access  public
     * @param   mixed    $searchConditions  string or array
     * @return  array
     */
    function findGeoObject($searchConditions="%") {
        if (is_array($searchConditions)) {
            foreach($searchConditions AS $key=>$val) {
                if (is_string($key)) {
                    $where[] = $key." = '".$val."'";
                } else {
                    $where[] = $val;
                }
            }
            $whereExpression = join(" AND ", $where);
        } else {
            $whereExpression = $this->options['fields']['name']." LIKE '".$searchConditions."'";
        }
        $query = "SELECT * FROM ".$this->options['table'].
                 " WHERE ".$whereExpression.
                 " ORDER BY ".$this->options['order'];
        return $this->performQuery($query);
    }

    /**
     * Find GeoObjects near an overgiven GeoObject
     *
     * Searches for GeoObjects, which are in a specified radius around the passed GeoBject.
     * Default is radius of 100 (100 of specified unit, see configuration and maxHits of 50
     * Returns an array of GeoDB-objects which lie in ther radius of the passed GeoObject.
     *
     * @access  public
     * @param   object  &$geoObject
     * @param   int     $maxRadius
     * @param   int     $maxHits
     * @return  array
     * @todo    void MySQL specific SQL
     */
    function findCloseByGeoObjects(&$geoObject, $maxRadius = 100, $maxHits = 50) {
        $query = "SELECT *, ";
        $query .= $this->getDistanceFormula($geoObject)." AS distance";
        $query .= " FROM ".$this->options['table']."";
        $query .= " WHERE ".$this->getDistanceFormula($geoObject)." < $maxRadius";
        $query .= " ORDER BY distance ASC";
        if ($maxHits) {
            $query .= " LIMIT 0, $maxHits";
        }
        return $this->performQuery($query);
    }

    /**
     * Performs a query on the database and delivers GeoObjects (or Errors)
     * To get ordinary results use $this->db->query($query) instead.
     *
     * @access  private
     * @param   string   $query  SQL query
     * @return  mixed    array of GeoObjects or DBError
     */
    function performQuery($query) {
        if (GEO_DEBUG_SQL) print "<pre>".$query."</pre>";
        $queryResult = $this->db->query($query);
        if (DB::isError($queryResult)) {
            return $queryResult;
        }
        return $this->transformQueryResult($queryResult);
    }

    /**
     * Transforms a db-query-result to an array of GeoObjects.
     *
     * @access  private
     * @param   DB_Result  &$queryResult
     * @return  array      GeoObjects
     */
    function transformQueryResult(&$queryResult) {
        $foundGeoObjects = array();
        while ($dbValues = $queryResult->fetchRow()) {
            if ($this->options['encoding'] == GEO_ENCODING_UTF_8) {
                foreach($dbValues AS $key=>$val) {
                    $dbValues[$key] = utf8_decode($val);
                }
            }
            $name = $dbValues[$this->options['fields']['name']];
            $latitude = $dbValues[$this->options['fields']['latitude']];
            $longitude = $dbValues[$this->options['fields']['longitude']];
            $foundGeoObjects[] = new Geo_Object($name, $latitude, $longitude, $this->options['degree'], $dbValues);
        }
        return $foundGeoObjects;
    }

    /**
     * Returns the formula which evaluates the distance between the passed GeoObject and
     * the elements in the database.
     *
     * @access  private
     * @param   Geo_Object  &$geoObject
     * @return  string
     */
    function getDistanceFormula(&$geoObject) {
        $formula = 'COALESCE(';
        $formula .= "(ACOS((SIN($geoObject->latitudeRad)*SIN(RADIANS(".$this->options['fields']['latitude']."))) + ";
        $formula .= "(COS($geoObject->latitudeRad)*COS(RADIANS(".$this->options['fields']['latitude']."))*COS(RADIANS(".$this->options['fields']['longitude'].")-$geoObject->longitudeRad))) * ";
        $formula .= Geo::getEarthRadius().')';
        $formula .= ',0)';
        return $formula;
    }

}
?>
