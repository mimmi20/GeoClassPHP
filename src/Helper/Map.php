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

use GdImage;

use function is_file;

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
final class Map
{
    /** @var false|GdImage|resource */
    private $img;

    /** @var false|int|string */
    private $size_x;

    /** @var false|int */
    private $size_y;

    /** @var array<int> */
    private array $min;

    /** @var array<int> */
    private array $max;

    private $scale;

    /**
     * prepares the image generation and inits the internal variables
     *
     * @param int|string $size_x width of the generated image or path to image (string)
     * @param int        $size_y (optional) height of the generated image
     */
    public function __construct($size_x, int $size_y = -1)
    {
        if (is_file($size_x)) {
            $this->img = imagecreatefrompng($size_x);

            $this->size_x = imagesx($this->img);
            $this->size_y = imagesy($this->img);
        } else {
            $this->size_x = $size_x;
            $this->size_y = $size_y;

            if (
                !isset($this->size_x)
                || 0 > $this->size_x
                || 2048 < $this->size_x
            ) {
                $this->size_x = 640;
            }

            if (
                !isset($this->size_y)
                || 0 > $this->size_y
                || 2048 < $this->size_y
            ) {
                $this->size_y = 480;
            }

            $this->img = imagecreate($this->size_x, $this->size_y);
        }

        $this->min = ['x' => 9, 'y' => 55];
        $this->max = ['x' => 11, 'y' => 54];
    }

    /**
     * set the range of the map which has to be generated
     *
     * @param float $x1 lower longitude
     * @param float $x2 higher longitude
     * @param float $y1 lower latitude
     * @param float $y2 higher latitude
     */
    public function setRange(float $x1, float $x2, float $y1, float $y2): void
    {
        $this->min = ['x' => $x1, 'y' => $y1];
        $this->max = ['x' => $x2, 'y' => $y2];
    }

    /**
     * scale a point from polar-coordinates to image-coordinates
     *
     * @param float|int $p point (array('x' => 0, 'y' => 1);
     * @param string    $d direction ('x' or 'y')
     */
    private function scale($p, string $d): int
    {
        if ('y' === $d) {
            $r = ($p - $this->max[$d]) * $this->size_y / ($this->min[$d] - $this->max[$d]);
        } else {
            $r = ($p - $this->min[$d]) * $this->size_x / ($this->max[$d] - $this->min[$d]);
        }

        return (int) $r;
    }

    /**
     * draw a clipped line
     *
     * @param int $x1 x-value of the start-point of the line
     * @param int $y1 y-value of the start-point of the line
     * @param int $x2 x-value of the end-point of the line
     * @param int $y2 y-value of the end-point of the line
     *
     * @private
     */
    private function draw_clipped(int $x1, int $y1, int $x2, int $y2, int $col): void
    {
        if (
            (
                $x1 > $this->max['x']
            || $x1 < $this->min['x']
            || $y1 > $this->max['y']
            || $y1 < $this->min['y']
            )
            && (
                $x2 > $this->max['x']
            || $x2 < $this->min['x']
            || $y2 > $this->max['y']
            || $y2 < $this->min['y']
            )
        ) {
            // clipp
            // printf('clipped x1: %d %d %d<br />', $x1, $this->min['x'], $this->max['x']);
            // printf('clipped y1: %d %d %d<br />', $y1, $this->min['y'], $this->max['y']);
            // printf('clipped x2: %d %d %d<br />', $x2, $this->min['x'], $this->max['x']);
            // printf('clipped y2: %d %d %d<br />', $y2, $this->min['y'], $this->max['y']);
        } else {
            imageline(
                $this->img,
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
     * @param string $fn filename
     */
    private function dump(string $fn)
    {
        return imagepng($this->img, $fn);
    }

    /**
     * allocate the colors for the image
     *
     * @param int $r red
     * @param int $g green
     * @param int $b blue
     */
    private function color(int $r, int $g, int $b)
    {
        return imagecolorallocate($this->img, $r, $g, $b);
    }
}
