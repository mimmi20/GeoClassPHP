<?php

/**
 * the e00 class generates maps based on arc-info files
 * it uses the map class for the image handling
 *
 * http://jan.kneschke.de/projects/
 */
class e00 extends map {
    /**
     * draws a datafile into the image
     *
     * @param $img image-handler
     * @param $fn  filename of the datafile
     * @param $col color used for drawing
     */

    function draw($fn, $col) {
    if (($f = fopen($fn, "r")) == false) return false;

    $num_records = 0;
    $ln = 0;

    while(0 || $line = fgets($f, 1024)) {
        $ln ++;

        # a node definition
        if ($num_records == 0 &&
        preg_match("#^\s+([0-9]+)\s+([-0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)#", $line, $a)) {
        $num_records = $a[7];

        $pl['x'] = -1;
        $pl['y'] = -1;

        # 2 coordinates
        } else if ($num_records &&
               preg_match("#^ *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2})#", $line, $a)) {

        #    print $a[0]."<br />";

        if ($pl['x'] != -1 &&
            $pl['y'] != -1
            ) {

            $this->draw_clipped($pl['x'], $pl['y'],
                    $a[1], $a[2],
                    $col);
        }

        $num_records--;

        $this->draw_clipped($a[1], $a[2],
                    $a[3], $a[4],
                    $col);

        $pl["x"] = $a[3];
        $pl["y"] = $a[4];

        $num_records--;

        # 1 coordinate
        } else if ($num_records &&
               preg_match("#^ *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2}) *([-+]?[0-9]\.[0-9]{7}E[-+][0-9]{2})#", $line, $a)) {

        if ($pl['x'] != -1 &&
            $pl['y'] != -1
            ) {

            $this->draw_clipped($pl['x'], $pl['y'],
                    $a[1], $a[2],
                    $col);

            $pl["x"] = $a[1];
            $pl["y"] = $a[2];
        }

        $num_records--;


        # done
        } else if ($ln > 2) {
        #        print "died at: ".$ln."<br />";
        break;
        } else {
        #    print $line."<br />";
        }
    }

    fclose($f);
    }
}

?>