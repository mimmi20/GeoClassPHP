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
 * Geo_DB_Relational
 * 
 * Designed for databases which seperate tables for coordinates
 * and further information. It must use a join-condition, otherwise
 * use DB.
 *
 * @access   public
 * @package  Geo
 */
class Geo_DB_Relational extends Geo_DB {

    /**
     * some options
     *
     * @var  array    options
     */
    var $options = array(
        'language' => 'en',
        'table' => 'coordinates co, further_information fi',
        'joins' => array(
            'co.id = fi.id'
        ),
        /**
         * fields must be unique within the related tables
         */
        'fields' => array(
            'name' => 'name',
            'longitude' => 'lon',
            'latitude' => 'lat',
        ),
        'key' => 'id',
        'order' => 'name',
        'degree' => true,
        'unit' => GEO_UNIT_DEFAULT,
        'encoding' => GEO_ENCODING_DEFAULT
    );

    /**
     * constructor Geo_DB_Relational
     *
     * @var		string	$dsn
     * @var		array	$options
     * @return	void
     */
    function Geo_DB_Relational($dsn, $options=array()) {
        $this->_connectDB($dsn);
        $this->setOptions($options);
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
    function findGeoObject($searchConditions = "%") {
        if (is_array($searchConditions)) {
            foreach($searchConditions AS $key=>$val) {
                if (is_string($key)) {
                    $where[] = $key." = '".$val."'";
                } else {
                    $where[] = $val;
                }
            }
            $whereExpression = join(" AND ", array_merge($this->options['joins'], $where));
        } else {
            $whereExpression = join(" AND ", $this->options['joins']).
                               " AND ".$this->options['fields']['name']." LIKE '".$searchConditions."'";
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
        $query = "SELECT *, ".
                    $this->getDistanceFormula($geoObject)." AS distance".
                 " FROM ".$this->options['table'].
                 " WHERE ".join(" AND ", $this->options['joins']).
                            " AND ".$this->getDistanceFormula($geoObject)." < $maxRadius".
                 " ORDER BY distance ASC";
        if ($maxHits) {
            $query .= " LIMIT 0, $maxHits";
        }
        return $this->performQuery($query);
    }
}
