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

namespace GeoDB\Helper;

use function fclose;
use function feof;
use function fgets;
use function fopen;
use function preg_match;

/**
 * the e00 class generates maps based on arc-info files
 * it uses the map class for the image handling
 *
 * http://jan.kneschke.de/projects/
 */
class E00 extends Map
{
    /**
     * draws a datafile into the image
     *
     * @param string $fn  filename of the datafile
     * @param int    $col color used for drawing
     */
    public function draw(string $fn, int $col): bool
    {
        $f = fopen($fn, 'r');

        if (false === $f) {
            return false;
        }

        $numRecords = 0;
        $ln         = 0;
        $pl         = [
            'x' => 0,
            'y' => 0,
        ];

        while (!feof($f)) {
            $line = fgets($f, 1024);

            if (false === $line) {
                return false;
            }

            ++$ln;

            // a node definition
            if (
                0 === $numRecords
                && preg_match('#^\s+([0-9]+)\s+([-0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)#', $line, $a)
            ) {
                $numRecords = $a[7];

                $pl['x'] = -1;
                $pl['y'] = -1;

                continue;
            }

            // 2 coordinates
            if (
                0 < $numRecords
                && preg_match('#^ *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2})#', $line, $a)
            ) {
                // print $a[0]."<br />";

                if (
                    -1 !== $pl['x']
                    && -1 !== $pl['y']
                ) {
                    $this->drawClipped(
                        $pl['x'],
                        $pl['y'],
                        $a[1],
                        $a[2],
                        $col
                    );
                }

                --$numRecords;

                $this->drawClipped(
                    $a[1],
                    $a[2],
                    $a[3],
                    $a[4],
                    $col
                );

                $pl['x'] = $a[3];
                $pl['y'] = $a[4];

                --$numRecords;

                continue;
            }

            // 1 coordinate
            if (
                0 < $numRecords
                && preg_match('#^ *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2})#', $line, $a)
            ) {
                if (
                    -1 !== $pl['x']
                    && -1 !== $pl['y']
                ) {
                    $this->drawClipped(
                        $pl['x'],
                        $pl['y'],
                        $a[1],
                        $a[2],
                        $col
                    );

                    $pl['x'] = $a[1];
                    $pl['y'] = $a[2];
                }

                --$numRecords;

                continue;
            }

            if (2 < $ln) {
                // print "died at: ".$ln."<br />";
                break;
            }
            // print $line."<br />";
        }

        fclose($f);

        return true;
    }
}
