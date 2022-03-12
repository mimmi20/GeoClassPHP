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

namespace GeoDB\Helper;

use function fclose;
use function fgets;
use function fopen;
use function preg_match;

/**
 * the e00 class generates maps based on arc-info files
 * it uses the map class for the image handling
 *
 * http://jan.kneschke.de/projects/
 */
final class E00 extends Map
{
    /**
     * draws a datafile into the image
     *
     * @param string $fn  filename of the datafile
     * @param int    $col color used for drawing
     */
    public function draw(string $fn, int $col): bool
    {
        if (false === ($f = fopen($fn, 'r'))) {
            return false;
        }

        $num_records = 0;
        $ln          = 0;

        while (0 || $line = fgets($f, 1024)) {
            ++$ln;

            // a node definition
            if (
                0 === $num_records
                && preg_match('#^\s+([0-9]+)\s+([-0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)#', $line, $a)
            ) {
                $num_records = $a[7];

                $pl['x'] = -1;
                $pl['y'] = -1;

            // 2 coordinates
            } elseif (
                $num_records
                && preg_match('#^ *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2})#', $line, $a)
            ) {
                // print $a[0]."<br />";

                if (
                    -1 !== $pl['x']
                    && -1 !== $pl['y']
                ) {
                    $this->draw_clipped(
                        $pl['x'],
                        $pl['y'],
                        $a[1],
                        $a[2],
                        $col
                    );
                }

                --$num_records;

                $this->draw_clipped(
                    $a[1],
                    $a[2],
                    $a[3],
                    $a[4],
                    $col
                );

                $pl['x'] = $a[3];
                $pl['y'] = $a[4];

                --$num_records;

            // 1 coordinate
            } elseif (
                $num_records
                && preg_match('#^ *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2})#', $line, $a)
            ) {
                if (
                    -1 !== $pl['x']
                    && -1 !== $pl['y']
                ) {
                    $this->draw_clipped(
                        $pl['x'],
                        $pl['y'],
                        $a[1],
                        $a[2],
                        $col
                    );

                    $pl['x'] = $a[1];
                    $pl['y'] = $a[2];
                }

                --$num_records;

            // done
            } elseif (2 < $ln) {
                // print "died at: ".$ln."<br />";
                break;
            }
            // print $line."<br />";
        }

        fclose($f);

        return true;
    }
}
