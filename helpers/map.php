<?php

/**
 * the map class provides the basic operations for creating maps
 * - image-handling
 *   - color-handling
 *   - file-handling
 *   - clipping
 *   - scaling
 * - coordinate convertion
 *   - long/lat to x/y
 *
 * http://jan.kneschke.de/projects/
 */
class map {
    var $img;
    var $size_x;
    var $size_y;

    var $min;
    var $max;

    var $scale;

    /**
     * prepares the image generation and inits the internal variables
     *
     * @param $size_x width of the generated image
     * @param $size_y height of the generated image
     */

    function map ($size_x, $size_y = -1) {
    if (is_file($size_x)) {
        $this->img = imagecreatefrompng($size_x);

        $this->size_x = imagesx($this->img);
        $this->size_y = imagesy($this->img);
    } else {
        $this->size_x = $size_x;
        $this->size_y = $size_y;

        if (!isset($this->size_x) ||
        $this->size_x < 0 ||
        $this->size_x > 2048) {
        $this->size_x = 640;
        }

        if (!isset($this->size_y) ||
        $this->size_y < 0 ||
        $this->size_y > 2048) {
        $this->size_y = 480;
        }

        $this->img = imagecreate($this->size_x, $this->size_y);
    }

    $this->min = array('x' => 9, 'y' => 55);
    $this->max = array('x' => 11, 'y' => 54);

    }

    /**
     * set the range of the map which has to be generated
     *
     * @param $x1 lower longitude
     * @param $x2 higher longitude
     * @param $y1 lower latitude
     * @param $y2 higher latitude
     *
     */

    function set_range($x1, $x2, $y1, $y2) {
    $this->min = array('x' => $x1, 'y' => $y1);
    $this->max = array('x' => $x2, 'y' => $y2);
    }

    /**
     * scale a point from polar-coordinates to image-coordinates
     *
     * @param $p point (array('x' => 0, 'y' => 1);
     * @param $d direction ('x' or 'y')
     */

    function scale($p, $d) {
    if ($d == 'y') {
        $r = ($p - $this->max[$d]) * ($this->size_y / ($this->min[$d] - $this->max[$d]));
    } else {
        $r = ($p - $this->min[$d]) * ($this->size_x / ($this->max[$d] - $this->min[$d]));
    }
    return $r;
    }

    /**
     * draw a clipped line
     *
     * @private
     * @param $x1 x-value of the start-point of the line
     * @param $y1 y-value of the start-point of the line
     * @param $x2 x-value of the end-point of the line
     * @param $y2 y-value of the end-point of the line
     */

    function draw_clipped($x1, $y1, $x2, $y2, $col) {
    if (($x1 > $this->max['x'] ||
         $x1 < $this->min['x'] ||
         $y1 > $this->max['y'] ||
         $y1 < $this->min['y']
         ) &&
        ($x2 > $this->max['x'] ||
         $x2 < $this->min['x'] ||
         $y2 > $this->max['y'] ||
         $y2 < $this->min['y']
         )) {
        ## clipp
#        printf('clipped x1: %d %d %d<br />', $x1, $this->min['x'], $this->max['x']);
#        printf('clipped y1: %d %d %d<br />', $y1, $this->min['y'], $this->max['y']);
#        printf('clipped x2: %d %d %d<br />', $x2, $this->min['x'], $this->max['x']);
#        printf('clipped y2: %d %d %d<br />', $y2, $this->min['y'], $this->max['y']);
    } else {
        imageline($this->img,
              $this->scale($x1, 'x'),
              $this->scale($y1, 'y'),
              $this->scale($x2, 'x'),
              $this->scale($y2, 'y'),
              $col
              );
    }
    }

    /**
     * Write the generated map the the harddisk
     *
     * @param $fn string filename
     */

    function dump($fn) {
    return imagepng($this->img, $fn);
    }

    /**
     * allocate the colors for the image
     *
     * @param $r red
     * @param $g green
     * @param $b blue
     */
    function color($r, $g, $b) {
    return imagecolorallocate($this->img, $r, $g, $b);
    }
}

?>
