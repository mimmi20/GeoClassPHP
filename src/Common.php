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

use function array_merge;

/**
 * Geo_Common
 *
 * some common vars and methods used by the specific classes
 */
final class Common
{
    /**
     * Options
     */
    private array $options = [
        'language' => Geo::GEO_LANGUAGE_DEFAULT,
        'unit' => Geo::GEO_UNIT_DEFAULT,
        'encoding' => Geo::GEO_ENCODING_DEFAULT,
    ];

    /**
     * set options
     *
     * @var     array
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
     * @param array<GeoObject> $geoObjectArray
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
