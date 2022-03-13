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

use UnexpectedValueException;

use function count;
use function explode;
use function file_get_contents;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function usort;

/**
 * Geo_RDF
 */
final class RDF extends Common
{
    /** @var array<int, GeoObject> */
    private array $geoObjectArray = [];

    /**
     * One could leave the parameter. getGeoObjectArray() will return an empty
     * array, until setArrayOfGeoObjects($rdfContent) sets a value.
     * It might be ok to just use the extractGeoObjects($rdfContent)-function.
     *
     * @param string                    $rdfContent content of the rdf-file
     * @param array<string, int|string> $options
     * @phpstan-param  array{language?: int, unit?: int, encoding?: string} $options
     *
     * @throws UnexpectedValueException
     */
    public function __construct(string $rdfContent, array $options = [])
    {
        $this->setArrayOfGeoObjects($rdfContent);
        $this->setOptions($options);
    }

    /**
     * Find GeoObjects
     *
     * Returns an array of GeoObjects which fits the $searchConditions
     *
     * @param string $searchConditions string, see preg_match
     *
     * @return array<GeoObject>
     *
     * @todo    use $searchConditions
     */
    public function findGeoObject(string $searchConditions = '*'): array
    {
        $objects = [];

        foreach ($this->geoObjectArray as $item) {
            if (!preg_match($searchConditions, $item->name)) {
                continue;
            }

            $objects[] = $item;
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
     * @return array<GeoObject>
     */
    public function findCloseByGeoObjects(GeoObject $geoObject, int $maxRadius = 100, int $maxHits = 50): array
    {
        $objects = [];
        foreach ($this->geoObjectArray as $item) {
            if (count($objects) >= $maxHits) {
                break;
            }

            $distance = $geoObject->getDistance($item, Geo::GEO_UNIT_DEFAULT);
            if ($distance > $maxRadius) {
                continue;
            }

            $copyOfItem                         = $item;
            $copyOfItem->dbValues['distance']   = $distance;
            $copyOfItem->dbValues['distanceTo'] = $geoObject->name;
            $objects[]                          = $copyOfItem;
        }

        usort($objects, [GeoObject::class, 'distanceSort']);

        return $objects;
    }

    /**
     * Sets the instance-variable to the new value.
     *
     * @param string $rdfContent string (RDF or URL)
     *
     * @throws UnexpectedValueException
     */
    private function setArrayOfGeoObjects(string $rdfContent): void
    {
        if (str_starts_with($rdfContent, 'http://') || str_starts_with($rdfContent, 'https://')) {
            $rdfContent = file_get_contents($rdfContent);
        }

        if (false === $rdfContent) {
            return;
        }

        $this->geoObjectArray = $this->extractGeoObjects($rdfContent);
    }

    /**
     * Returns an array of GeoObjects, which are extracted from the passed string/file-content
     *
     * @param string $rdfContent string (RDF)
     *
     * @return array<int, GeoObject>
     *
     * @throws UnexpectedValueException
     *
     * @todo    void this ugly global
     * @todo    GEO_LANGUAGE_* as parameter
     */
    private function extractGeoObjects(string $rdfContent): array
    {
        // Make the file flat
        $rdfContent     = str_replace(["\r", "\n"], ['', ''], $rdfContent);
        $rawPpointArray = explode('</geo:Point>', $rdfContent);
        $pointArray     = [];
        foreach ($rawPpointArray as $onePointSetRaw) {
            $parts        = explode('<geo:Point>', $onePointSetRaw);
            $pointArray[] = $parts[1];
        }

        $returnArray = [];
        foreach ($pointArray as $onePoint) {
            $searchName = '|(.*)(<rdfs:label>)(.*)(</rdfs:label>)(.*)|i';

            if (preg_match($searchName, $onePoint, $searchResult)) {
                $name = $searchResult[3];
            } else {
                $name = Geo::CFG_STRINGS[Geo::GEO_ERR_NONAME][Geo::GEO_LANGUAGE_DEFAULT];
            }

            $searchLatitude = '|(.*)(<geo:lat>)(.*)(</geo:lat>)(.*)|i';

            if (!preg_match($searchLatitude, $onePoint, $searchResult)) {
                continue;
            }

            $latitude = $searchResult[3];

            $searchLongitude = '|(.*)(<geo:long>)(.*)(</geo:long>)(.*)|i';

            if (!preg_match($searchLongitude, $onePoint, $searchResult)) {
                continue;
            }

            $longitude = $searchResult[3];

            $returnArray[] = new GeoObject($name, $latitude, $longitude);
        }

        return $returnArray;
    }
}
