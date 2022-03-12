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

use function array_merge;

/**
 * Geo_Common
 *
 * some common vars and methods used by the specific classes
 */
class Common
{
    /**
     * Options
     *
     * @var array<string, int|string>
     * @phpstan-var array{language: int, unit: int, encoding: string}
     */
    private array $options = [
        'language' => Geo::GEO_LANGUAGE_DEFAULT,
        'unit' => Geo::GEO_UNIT_DEFAULT,
        'encoding' => Geo::GEO_ENCODING_DEFAULT,
    ];

    /**
     * set options
     *
     * @param array<string, int|string> $options
     * @phpstan-param  array{language: int, unit: int, encoding: string} $options
     */
    public function setOptions(array $options = []): void
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Returns an RDF-Data-File
     *
     * Returns an RDF-Data-File as described here: http://www.w3.org/2003/01/geo/
     * respective as shownhere, with label/name: http://www.w3.org/2003/01/geo/test/xplanet/la.rdf
     * or, with multiple entries, here: http://www.w3.org/2003/01/geo/test/towns.rdf
     *
     * @param array<int|string, GeoObject> $geoObjectArray
     */
    public function getRDFDataFile(array $geoObjectArray): string
    {
        $rdfData  = "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n";
        $rdfData .= "\txmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\"\n";
        $rdfData .= "\txmlns:geo=\"http://www.w3.org/2003/01/geo/wgs84_pos#\">\n\n";

        foreach ($geoObjectArray as $anGeoObject) {
            $rdfData .= $anGeoObject->getRDFPointEntry(1);
            $rdfData .= "\n";
        }

        return $rdfData . '</rdf:RDF>';
    }
}
