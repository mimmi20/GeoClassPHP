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

namespace GeoDB;

use GeoDB\Helper\E00;

use function abs;
use function array_merge;
use function count;
use function end;
use function explode;
use function file;
use function file_exists;
use function function_exists;
use function implode;
use function mb_strpos;
use function mb_strtolower;
use function mb_substr;
use function preg_replace;
use function round;
use function str_contains;
use function trim;

/**
 * Geo_Map
 *
 * this class provides functions to generate images from
 * GIS data and GeoObjects. In the future these methods
 * should be implemented in PEAR::Image_GIS
 */
final class Map extends E00
{
    /** @var array<string, false|int> */
    private array $color = [];

    private float $latitudeMin;
    private float $latitudeMax;
    private float $longitudeMin;
    private float $longitudeMax;
    private int $radius = 4;

    /**
     * @var array<int, array<string, float|GeoObject|int|string>>
     * @phpstan-var array<int, array{name: string, x: float, y: float, r: int, o: GeoObject, count: int, color: string}>
     */
    private array $imageMap = [];

    /**
     * @param int|string $x width of the generated image or path to image (string)
     * @param int        $y (optional) height of the generated image
     */
    public function __construct($x = false, int $y = -1)
    {
        parent::__construct($x, $y);

        $this->color['white']    = $this->color(255, 255, 255);
        $this->color['red']      = $this->color(255, 0, 0);
        $this->color['black']    = $this->color(0, 0, 0);
        $this->color['green']    = $this->color(178, 237, 90);
        $this->color['blue']     = $this->color(148, 208, 255);
        $this->color['grey']     = $this->color(192, 192, 192);
        $this->color['darkgrey'] = $this->color(124, 124, 124);
    }

    /**
     * Sets the range of the map from overgiven degree-values-array
     *
     * @param array<int, float> $rangeArray
     */
    public function setRangeByArray(array $rangeArray): void
    {
        $this->setRange($rangeArray[0], $rangeArray[1], $rangeArray[2], $rangeArray[3]);
    }

    /**
     * Calculates distances between the corners and returns an ratio or values
     *
     * @param array<int, float> $rangeArray
     * @param int               $width      preseted width, basis for height
     * @param int               $height     vice versa
     *
     * @return array<int, float> width and height
     */
    public function getSizeByRange(array $rangeArray, int $width = 0, int $height = 0): array
    {
        $eol   = new GeoObject('eol', $rangeArray[3], $rangeArray[0]);
        $eor   = new GeoObject('eor', $rangeArray[3], $rangeArray[1]);
        $eul   = new GeoObject('eul', $rangeArray[2], $rangeArray[0]);
        $eur   = new GeoObject('eur', $rangeArray[2], $rangeArray[1]);
        $ns1   = abs($eol->getDistance($eul));
        $ns2   = abs($eor->getDistance($eur));
        $we1   = abs($eol->getDistance($eor));
        $we2   = abs($eul->getDistance($eur));
        $ns    = ($ns1 + $ns2) / 2;
        $we    = ($we1 + $we2) / 2;
        $ratio = $we / $ns;

        if ((0 === $width) && (0 === $height)) {
            return [$ratio, 1];
        }

        if ((0 !== $width) && (0 === $height)) {
            return [$width, round($width / $ratio)];
        }

        if ((0 === $width) && (0 !== $height)) {
            return [round($height * $ratio), $height];
        }

        $calcHeight = round($width / $ratio);
        $calcWidth  = round($height * $ratio);
        if ($calcHeight <= $height) {
            return [$width, $calcHeight];
        }

        return [$calcWidth, $height];
    }

    /**
     * Sets the range of the map from overgiven GeoObjects
     *
     * @see setRange(),setRangeByGeoObject()
     *
     * @param array<GeoObject> $geoObjects Array of GeoObjects
     * @param float            $border     degrees
     */
    public function setRangeByGeoObjects(array $geoObjects, float $border = 0.1): void
    {
        foreach ($geoObjects as $geoObject) {
            if (!$this->longitudeMin || ($geoObject->longitude < $this->longitudeMin)) {
                $this->longitudeMin = $geoObject->longitude;
            }

            if (!$this->longitudeMax || ($geoObject->longitude > $this->longitudeMax)) {
                $this->longitudeMax = $geoObject->longitude;
            }

            if (!$this->latitudeMin || ($geoObject->latitude < $this->latitudeMin)) {
                $this->latitudeMin = $geoObject->latitude;
            }

            if ($this->latitudeMax && ($geoObject->latitude <= $this->latitudeMax)) {
                continue;
            }

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
     * Sets the range of the map from an overgiven GeoObject
     *
     * @see setRange(),setRangeByGeoObjects()
     *
     * @param float $border degrees
     */
    public function setRangeByGeoObject(GeoObject $geoObject, float $border = 0.1): void
    {
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
     * @see addGeoObjects()
     */
    public function addGeoObject(GeoObject $geoObject, string $color = 'black', int $radius = 0): void
    {
        $x = round($this->scale((int) $geoObject->longitude, 'x'));
        $y = round($this->scale((int) $geoObject->latitude, 'y'));
        if (($x > $this->sizeX) || ($y > $this->sizeY)) {
            return;
        }

        $hasDrawn = false;
        if (function_exists('imagefilledellipse')) {
            $hasDrawn = imagefilledellipse($this->img, (int) $x, (int) $y, $radius * 2, $radius * 2, $this->color[$color]);
        }

        if (!$hasDrawn) {
            for ($i = 1; $i <= $radius; ++$i) {
                ImageArc($this->img, (int) $x, (int) $y, $i, $i, 0, 360, $this->color[$color]);
            }
        }

        $this->imageMap[] = [
            'name' => $geoObject->name,
            'x' => $x,
            'y' => $y,
            'r' => $radius ? $radius : $this->radius,
            'o' => $geoObject,
            'count' => 1,
            'color' => $color,
        ];
    }

    /**
     * Adds a GeoObject to the map, respects already added objects and increases     * drawn circles, tolerance is the last radius
     *
     * @param array<int, int> $radii different sizes for different count of GeoObjects at one spot
     */
    public function addGeoObjectIncrease(GeoObject $geoObject, string $color = 'black', array $radii = [0 => 2, 5 => 3, 10 => 4, 15 => 5, 1000 => 4]): void
    {
        $x         = round($this->scale($geoObject->longitude, 'x'));
        $y         = round($this->scale($geoObject->latitude, 'y'));
        $tolerance = end($radii);
        $wasFound  = false;
        for ($imc = 0; $imc < count($this->imageMap); ++$imc) {
            if (
                ($this->imageMap[$imc]['x'] > $x + $tolerance)
                || ($this->imageMap[$imc]['x'] < $x - $tolerance)
                || ($this->imageMap[$imc]['y'] > $y + $tolerance)
                || ($this->imageMap[$imc]['y'] < $y - $tolerance)
            ) {
                continue;
            }

            // Namen hinzufuegen
            if (!str_contains($this->imageMap[$imc]['name'], $geoObject->name)) {
                $this->imageMap[$imc]['name'] .= ', ' . $geoObject->name;
            }

            ++$this->imageMap[$imc]['count'];
            if (isset($radii[$this->imageMap[$imc]['count']])) {
                $hasDrawn = false;
                if (function_exists('imagefilledellipse')) {
                    $hasDrawn = imagefilledellipse($this->img, $this->imageMap[$imc]['x'], $this->imageMap[$imc]['y'], $radii[$this->imageMap[$imc]['count']] * 2, $radii[$this->imageMap[$imc]['count']] * 2, $this->color[$this->imageMap[$imc]['color']]);
                }

                if (!$hasDrawn) {
                    for ($i = $this->imageMap[$imc]['r']; $i <= $radii[$this->imageMap[$imc]['count']]; ++$i) {
                        imagearc($this->img, $this->imageMap[$imc]['x'], $this->imageMap[$imc]['y'], $i, $i, 0, 360, $this->color[$this->imageMap[$imc]['color']]);
                    }
                }

                $this->imageMap[$imc]['r'] = $radii[$this->imageMap[$imc]['count']];
            }

            $wasFound = true;
            break;
        }

        if ($wasFound) {
            return;
        }

        $this->addGeoObject($geoObject, $color, $radii[0]);
    }

    /**
     * Adds GeoObjects to the map
     *
     * @see addGeoObject()
     *
     * @param array<GeoObject> $geoObjects Array of GeoObjects
     */
    public function addGeoObjects(array $geoObjects, string $color = 'black'): void
    {
        foreach ($geoObjects as $geoObject) {
            $this->addGeoObject($geoObject, $color);
        }
    }

    /**
     * Adds an e00-file to the image
     *
     * container for API compatibility with PEAR::Image_GIS
     *
     * @see \map::draw()
     *
     * @param string $data path to e00-file
     */
    public function addDataFile(string $data, string $color = 'black'): bool
    {
        if ('.ovl' === mb_strtolower(mb_substr($data, -4))) {
            return $this->addOvlFile($data, $color);
        }

        if (file_exists($data)) {
            $this->draw($data, $this->color[$color]);

            return true;
        }

        return false;
    }

    /**
     * Adds an ovl-file to the image
     *
     * @see \map::draw()
     *
     * @param string $data path to ovl-file
     */
    public function addOvlFile(string $data, string $color = 'black'): bool
    {
        if (file_exists($data)) {
            $ovlRows       = file($data);
            $importantRows = [];
            foreach ($ovlRows as $aRow) {
                if (1 !== mb_strpos($aRow, 'Koord')) {
                    continue;
                }

                $importantRows[] = trim($aRow);
            }

            $pointArray = [];
            $lastIndex  = 0;
            $lastX      = 0;
            $lastY      = 0;
            for ($i = 0; $i < count($importantRows); $i += 2) {
                [$cruft, $data] = explode('Koord', $importantRows[$i]);
                [$idA, $XA]     = explode('=', $data);
                [$cruft, $data] = explode('Koord', $importantRows[$i + 1]);
                [$idB, $YB]     = explode('=', $data);
                $x              = $this->scale($XA, 'x');
                $y              = $this->scale($YB, 'y');
                if ($idA > $lastIndex) {
                    imageline($this->img, $lastX, $lastY, $x, $y, $this->color[$color]);
                }

                $lastIndex = $idA;
                $lastX     = $x;
                $lastY     = $y;
            }

            return true;
        }

        return false;
    }

    /**
     * Saves the image
     *
     * container for API compatibility with PEAR::Image_GIS
     *
     * @see \map::dump()
     */
    public function saveImage(string $file): void
    {
        $this->dump($file);
    }

    /**
     * Creates an image map (html)
     *
     * @param string $name name of the ImageMap
     *
     * @return string  html
     */
    public function getImageMap(string $name = 'map'): string
    {
        $html = '<map name="' . $name . '">';
        foreach ($this->imageMap as $koord) {
            $html .= '<area shape="circle" coords="' . round($koord['x']) . ',' . round($koord['y']) . ',' . $koord['r'] . '" href="#" alt="' . $koord['name'] . '">';
        }

        $html .= '</map>';

        return $html;
    }

    /**
     * Creates an image map (html)
     *
     * Attributes is an associate array, where the key is the attribute.
     * array("alt"=>"http://example.com/show.php?id=[id]") where id is a dbValue     *
     *
     * @param string                $name       name of the ImageMap
     * @param array<string, string> $attributes attributes for the area
     *
     * @return string html
     */
    public function getImageMapExtended(string $name = 'map', array $attributes = [], string $areas = ''): string
    {
        $defaultAttributes = ['href' => '#', 'alt' => ''];
        $attributes        = array_merge($defaultAttributes, $attributes);
        $html              = '<map name="' . $name . "\">\n";
        foreach ($this->imageMap as $koord) {
            $theObject           = $koord['o'];
            $imArray             = [
                'imagemap_name' => $koord['name'],
                'imagemap_x' => $koord['x'],
                'imagemap_y' => $koord['y'],
                'imagemap_r' => $koord['r'],
                'imagemap_count' => $koord['count'],
                'imagemap_color' => $koord['color'],
            ];
            $theObject->dbValues = array_merge($theObject->dbValues, $imArray);
            $attributeList       = [];
            foreach ($attributes as $attKey => $attVal) {
                if ('href' === $attKey) {
                    $attributeList[] = $attKey . '="' .
                        preg_replace('|(\[)([^\]]*)(\])|ie', '(isset($theObject->dbValues[\2])?urlencode($theObject->dbValues[\2]):"")', $attVal) .
                        '"';
                } else {
                    $attributeList[] = $attKey . '="' .
                        preg_replace('|(\[)([^\]]*)(\])|ie', '(isset($theObject->dbValues[\2])?$theObject->dbValues[\2]:"")', $attVal) .
                        '"';
                }
            }

            $html .= '<area shape="circle" coords="' . round($koord['x']) . ',' . round($koord['y']) . ',' . $koord['r'] . '" ' . implode(' ', $attributeList) . ">\n";
        }

        $html .= $areas;
        $html .= '</map>';

        return $html;
    }
}
