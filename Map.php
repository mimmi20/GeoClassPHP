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

require_once 'helpers/map.php';
require_once 'helpers/e00.php';

/**
 * Geo_Map
 *
 * this class provides functions to generate images from
 * GIS data and GeoObjects. In the future these methods
 * should be implemented in PEAR::Image_GIS
 *
 * @access   public
 * @package  Geo
 */
class Geo_Map extends e00 {

    var $colors = array();

    var $latitudeMin;
    var $latitudeMax;
    var $longitudeMin;
    var $longitudeMax;
    var $objects = array();
    var $imageMap = array();
    var $radius = 4;

    /**
     * constructor
     *
     * @param   mixed  $x  image-width (int) or path to image (string)
     * @param   int    $y  image-height
     * @return  void
     */
    function Geo_Map($x=false,$y=false) {
        $this->e00($x,$y);
        $this->color['white']    = $this->color(255, 255, 255);
        $this->color['red']      = $this->color(255, 0, 0);
        $this->color['black']    = $this->color(0, 0, 0);
        $this->color['green']    = $this->color(178, 237, 90);
        $this->color['blue']     = $this->color(148, 208, 255);
        $this->color['grey']     = $this->color(192, 192, 192);
        $this->color['darkgrey'] = $this->color(124, 124, 124);
    }

    /**
     * Sets the range of the map from overgiven degree-values
     *
     * container for API compatibility with PEAR::Image_GIS
     *
     * @access  public
     * @param   float   $x1
     * @param   float   $x2
     * @param   float   $y1
     * @param   float   $y2
     * @return  void
     */
    function setRange($x1,$x2,$y1,$y2) {
        $this->set_range($x1, $x2, $y1, $y2);
    }

    /**
     * Sets the range of the map from overgiven degree-values-array
     * 
     * @access  public
     * @param   array   $rangeArray
     * @return  void
     */
    function setRangeByArray($rangeArray) {
        $this->set_range($rangeArray[0], $rangeArray[1], $rangeArray[2], $rangeArray[3]);
    }

    /**
     * Calculates distances between the corners and returns an ratio or values
     * 
     * @access  public
     * @param   array   $rangeArray
     * @param   int     $width      preseted width, basis for height
     * @param   int     $height     vice versa
     * @return  array   width and height
     */
    function getSizeByRange($rangeArray, $width = 0, $height = 0) {
        $eol = new Geo_Object("eol", $rangeArray[3], $rangeArray[0]);
        $eor = new Geo_Object("eor", $rangeArray[3], $rangeArray[1]);
        $eul = new Geo_Object("eul", $rangeArray[2], $rangeArray[0]);
        $eur = new Geo_Object("eur", $rangeArray[2], $rangeArray[1]);
        $ns1 = abs($eol->getDistance($eul));
        $ns2 = abs($eor->getDistance($eur));
        $we1 = abs($eol->getDistance($eor));
        $we2 = abs($eul->getDistance($eur));
        $ns = ($ns1 + $ns2) / 2;
        $we = ($we1 + $we2) / 2;
        $ratio = $we / $ns;
        if (($width == 0) && ($height == 0)) return array($ratio, 1);
        if (($width != 0) && ($height == 0)) return array($width, round($width/$ratio));
        if (($width == 0) && ($height != 0)) return array(round($height * $ratio), $height);
        $calcHeight = round($width/$ratio);
        $calcWidth = round($height * $ratio);
        if ($calcHeight <= $height) return array($width, $calcHeight);
        return array($calcWidth, $height);
    }

    /**
     * Sets the range of the map from overgiven GeoObjects
     *
     * @access  public
     * @param   array   &$geoObjects  Array of GeoObjects
     * @param   float   $border       degrees
     * @return  void
     * @see     setRange(),setRangeByGeoObject()
     */
    function setRangeByGeoObjects(&$geoObjects,$border=0.1) {
        foreach($geoObjects AS $geoObject) {
            if (!$this->longitudeMin || ($geoObject->longitude < $this->longitudeMin)) {
                $this->longitudeMin = $geoObject->longitude;
            }
            if (!$this->longitudeMax || ($geoObject->longitude > $this->longitudeMax)) {
                $this->longitudeMax = $geoObject->longitude;
            }
            if (!$this->latitudeMin || ($geoObject->latitude < $this->latitudeMin)) {
                $this->latitudeMin = $geoObject->latitude;
            }
            if (!$this->latitudeMax || ($geoObject->latitude > $this->latitudeMax)) {
                $this->latitudeMax = $geoObject->latitude;
            }
        }

        $this->setRange(
            $this->longitudeMin - $border,
            $this->longitudeMax + $border,
            $this->latitudeMin - $border,
            $this->latitudeMax + $border
        );
    }

    /**
     * Sets the range of the map from an overgiven GeoObject
     *
     * @access  public
     * @param   array   &$geoObject  GeoObject
     * @param   float   $border      degrees
     * @return  void
     * @see     setRange(),setRangeByGeoObjects()
     */
    function setRangeByGeoObject(&$geoObject,$border=0.1) {
        if (!$this->longitudeMin || ($geoObject->longitude < $this->longitudeMin)) {
            $this->longitudeMin = $geoObject->longitude;
        }
        if (!$this->longitudeMax || ($geoObject->longitude > $this->longitudeMax)) {
            $this->longitudeMax = $geoObject->longitude;
        }
        if (!$this->latitudeMin || ($geoObject->latitude < $this->latitudeMin)) {
            $this->latitudeMin = $geoObject->latitude;
        }
        if (!$this->latitudeMax || ($geoObject->latitude > $this->latitudeMax)) {
            $this->latitudeMax = $geoObject->latitude;
        }

        $this->setRange(
            $this->longitudeMin - $border,
            $this->longitudeMax + $border,
            $this->latitudeMin - $border,
            $this->latitudeMax + $border
        );
    }

    /**
     * Adds a GeoObject to the map
     *
     * @access  public
     * @param   array   &$geoObject  GeoObject
     * @param   string  $color
     * @param   int     $radius
     * @return  void
     * @see     addGeoObjects()
     */
    function addGeoObject(&$geoObject, $color='black', $radius=0) {
        $x = round($this->scale($geoObject->longitude, 'x'));
        $y = round($this->scale($geoObject->latitude, 'y'));
        if (($x > $this->size_x) || ($y > $this->size_y)) return false;
        $hasDrawn = false;
        if (function_exists("imagefilledellipse")) {
            $hasDrawn = imagefilledellipse($this->img, $x, $y, ($radius*2), ($radius*2), $this->color[$color]);
        }
        if (!$hasDrawn) {
            for($i=1;$i<=$radius;$i++) {
                ImageArc($this->img, $x, $y, $i, $i, 0, 360, $this->color[$color]);
            }
        }
        $this->imageMap[] = array(
            "name"  => $geoObject->name,
            "x"     => $x,
            "y"     => $y,
            "r"     => $radius?$radius:$this->radius,
            "o"     => $geoObject,
            "count" =>  1,
            "color" => $color
        );
    }
    
    /**
     * Adds a GeoObject to the map, respects already added objects and increases     * drawn circles, tolerance is the last radius
     *
     * @access  public
     * @param   array   &$geoObject  GeoObject
     * @param   string  $color
     * @param   array   $radii different sizes for different count of GeoObjects at one spot
     * @return  void
     */
    function addGeoObjectIncrease(&$geoObject, $color='black', $radii=array(0=>2, 5=>3, 10=>4, 15=>5, 1000=>4)) {
        $x = round($this->scale($geoObject->longitude, 'x'));
        $y = round($this->scale($geoObject->latitude, 'y'));
        $tolerance = end($radii);
        $wasFound = false;
        for ($imc = 0; $imc<count($this->imageMap); $imc++) {
            if (($this->imageMap[$imc]['x'] <= ($x + $tolerance))
                && ($this->imageMap[$imc]['x'] >= ($x - $tolerance))
                && ($this->imageMap[$imc]['y'] <= ($y + $tolerance))
                && ($this->imageMap[$imc]['y'] >= ($y - $tolerance))) {
                // Namen hinzufuegen
                if (strpos($this->imageMap[$imc]['name'], $geoObject->name) === false) {
                    $this->imageMap[$imc]['name'] .= ", ".$geoObject->name;
                }
                
                $this->imageMap[$imc]['count'] = $this->imageMap[$imc]['count'] + 1;
                if (isset($radii[$this->imageMap[$imc]['count']])) {
                
                    $hasDrawn = false;
                    if (function_exists("imagefilledellipse")) {
                        $hasDrawn = imagefilledellipse($this->img, $this->imageMap[$imc]['x'], $this->imageMap[$imc]['y'], ($radii[$this->imageMap[$imc]['count']]*2), ($radii[$this->imageMap[$imc]['count']]*2), $this->color[$this->imageMap[$imc]['color']]);
                    }
                    if (!$hasDrawn) {
                        for($i=$this->imageMap[$imc]['r'];$i<=$radii[$this->imageMap[$imc]['count']];$i++) {
                            imagearc($this->img, $this->imageMap[$imc]['x'], $this->imageMap[$imc]['y'], $i, $i, 0, 360, $this->color[$this->imageMap[$imc]['color']]);
                        }
                    }
                    $this->imageMap[$imc]['r'] = $radii[$this->imageMap[$imc]['count']];
                }
                $wasFound = true;
                break;
            }
        }
        if (!$wasFound) $this->addGeoObject($geoObject, $color, $radii[0]);
    }

    /**
     * Adds GeoObjects to the map
     *
     * @access  public
     * @param   array   &$geoObjects  Array of GeoObjects
     * @param   string  $color
     * @return  void
     * @see     addGeoObject()
     */
    function addGeoObjects(&$geoObjects,$color='black') {
        foreach($geoObjects AS $geoObject) {
            $this->addGeoObject($geoObject,$color);
        }
    }

    /**
     * Adds an e00-file to the image
     *
     * container for API compatibility with PEAR::Image_GIS
     *
     * @access  public
     * @param   string  $data  path to e00-file
     * @return  boolean
     * @see     map::draw()
     */
    function addDataFile($data, $color='black') {
        if (strtolower(substr($data, -4)) == ".ovl") {
            return $this->addOvlFile($data, $color);
        }
        if (file_exists($data)) {
        	$this->draw($data, $this->color[$color]);
        	return true;
        } else {
        	return false;
        }
    }

    /**
     * Adds an ovl-file to the image
     *
     * @access  public
     * @param   string  $data  path to ovl-file
     * @return  boolean
     * @see     map::draw()
     */
    function addOvlFile($data, $color='black') {
        if (file_exists($data)) {
            $ovlRows = file($data);
            $importantRows = array();
            foreach ($ovlRows as $aRow) {
                if (strpos($aRow, "Koord") == 1) {
                    $importantRows[] = trim($aRow);
                }
            }
            $pointArray = array();
            $lastIndex = 0;
            $lastX = 0;
            $lastY = 0;
            for ($i = 0; $i < count($importantRows); $i += 2) {
                list($cruft, $data) = explode("Koord", $importantRows[$i]);
                list($idA, $XA) = explode("=", $data);
                list($cruft, $data) = explode("Koord", $importantRows[$i + 1]);
                list($idB, $YB) = explode("=", $data);
                $x = $this->scale($XA, "x");
                $y = $this->scale($YB, "y");
                if ($idA > $lastIndex) {
                    imageline($this->img, $lastX, $lastY, $x, $y, $this->color[$color]);
                }
                $lastIndex = $idA;
                $lastX = $x;
                $lastY = $y;
            }
        	return true;
        } else {
        	return false;
        }
    }

    /**
     * Saves the image
     *
     * container for API compatibility with PEAR::Image_GIS
     *
     * @access  public
     * @param   string  $file
     * @return  void
     * @see     map::dump()
     */
    function saveImage($file) {
        $this->dump($file);
    }

    /**
     * Creates an image map (html)
     *
     * @access  public
     * @param   string  $name  name of the ImageMap
     * @return  string  html
     */
    function getImageMap($name="map") {
        $html = '<map name="'.$name.'">';
        foreach($this->imageMap as $koord) {
            $html .= '<area shape="circle" coords="'.round($koord['x']).','.round($koord['y']).','.$koord['r'].'" href="#" alt="'.$koord['name'].'">';
        }
        $html.='</map>';
        return $html;
    }

    /**
     * Creates an image map (html)
     *
     * Attributes is an associate array, where the key is the attribute.
     * array("alt"=>"http://example.com/show.php?id=[id]") where id is a dbValue     *
     * @access  public
     * @param   string  $name           name of the ImageMap
     * @param   array   $attributes     attributes for the area
     * @return  string  html
     */
    function getImageMapExtended($name="map", $attributes=array(), $areas="") {
        $defaultAttributes = array("href"=>"#", "alt"=>"");
        $attributes = array_merge($defaultAttributes, $attributes);
        $html = "<map name=\"".$name."\">\n";
        foreach($this->imageMap as $koord) {
            $theObject = $koord['o'];
            $im_array = array(
                "imagemap_name"     => $koord['name'],
                "imagemap_x"        => $koord['x'],
                "imagemap_y"        => $koord['y'],
                "imagemap_r"        => $koord['r'],
                "imagemap_count"    => $koord['count'],
                "imagemap_color"    => $koord['color']
            );
            $theObject->dbValues = array_merge($theObject->dbValues, $im_array);            $attributeList = array();
            foreach($attributes as $attKey=>$attVal) {
                if ($attKey == "href") {
                    $attributeList[] = $attKey."=\"".
                        preg_replace("|(\[)([^\]]*)(\])|ie", '(isset($theObject->dbValues[\2])?urlencode($theObject->dbValues[\2]):"")', $attVal).
                        "\"";
                } else {
                    $attributeList[] = $attKey."=\"".
                        preg_replace("|(\[)([^\]]*)(\])|ie", '(isset($theObject->dbValues[\2])?$theObject->dbValues[\2]:"")', $attVal).
                        "\"";
                }
            }
            $html .= "<area shape=\"circle\" coords=\"".round($koord['x']).",".round($koord['y']).",".$koord['r']."\" ".implode(" ", $attributeList).">\n";
        }
        $html.=$areas;
        $html.='</map>';
        return $html;
    }
}
?>