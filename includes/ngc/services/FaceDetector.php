<?php

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
//
// @Author Karthik Tharavaad karthik_tharavaad@yahoo.com
// @Contributor Maurice Svay maurice@svay.Com
// @Contributor Abyss Morgan

declare(strict_types=1);

namespace NGC\Services;

use GdImage;
use Imagick;

/**
 * Class FaceDetector
 *
 * This class provides functionality for detecting faces in images using a Haar-like feature cascade classifier.
 * It uses GD and Imagick libraries for image manipulation.
 */
class FaceDetector {

	/**
	 * Stores the deserialized detection cascade data.
	 * @var array
	 */
	private array $detection_data;

	/**
	 * Stores the detected face's coordinates and width, or null if no face is detected.
	 * @var ?array
	 */
	private ?array $face;

	/**
	 * The original GD image resource.
	 * @var GdImage
	 */
	private GdImage $canvas;

	/**
	 * A scaled-down version of the image for detection.
	 * @var GdImage
	 */
	private GdImage $reduced_canvas;

	/**
	 * FaceDetector constructor.
	 *
	 * @param string $detection_data The path to the serialized detection data file.
	 */
	public function __construct(string $detection_data){
		$this->detection_data = unserialize(file_get_contents($detection_data));
	}

	/**
	 * Calculates the dimensions and position of a variant rectangle based on the detected face.
	 *
	 * @param float $multiplier A multiplier to adjust the size of the rectangle.
	 * @return array An associative array containing 'x', 'y', 'width', and 'height' of the variant rectangle.
	 */
	private function get_variant_rectangle(float $multiplier = 1.0) : array {
		$sw = $this->face['w'];
		$nw = $this->face['w'] * $multiplier;
		return [
			'x' => $this->face['x'] - ($nw - $sw) / 2.0,
			'y' => $this->face['y'] - ($nw - $sw) / 2.0,
			'width' => $nw,
			'height' => $nw
		];
	}

	/**
	 * Saves a cropped and optionally resized image based on the detected face and a multiplier.
	 *
	 * @param float $multiplier The multiplier to apply to the detected face's dimensions for cropping.
	 * @param string $input The path to the input image file.
	 * @param string $output The path where the output image will be saved.
	 * @param int $size If greater than 0, the output image will be resized to this width and height.
	 * @return bool True on success, false on failure.
	 */
	public function save_variant_image(float $multiplier, string $input, string $output, int $size) : bool {
		$rect = $this->get_variant_rectangle($multiplier);
		$image = new Imagick();
		$image->readImage($input);
		$image->cropImage((int)$rect['width'], (int)$rect['height'], (int)$rect['x'], (int)$rect['y']);
		if($size > 0) $image->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
		$image->setImageCompressionQuality(100);
		$image->writeImage($output);
		$image->clear();
		return true;
	}

	/**
	 * Performs face detection on the given GD image.
	 *
	 * The image is optionally scaled down for detection efficiency.
	 * The detected face coordinates are then scaled back to the original image's dimensions.
	 *
	 * @param GdImage $image The GD image resource to perform face detection on.
	 * @return bool True if a face is detected, false otherwise.
	 */
	public function face_detect(GdImage $image) : bool {
		$this->canvas = $image;
		$im_width = imagesx($this->canvas);
		$im_height = imagesy($this->canvas);
		$diff_width = 256 - $im_width;
		$diff_height = 256 - $im_height;
		if($diff_width > $diff_height){
			$ratio = $im_width / 256;
		} else {
			$ratio = $im_height / 256;
		}
		if($ratio != 0){
			$this->reduced_canvas = imagecreatetruecolor((int)($im_width / $ratio), (int)($im_height / $ratio));
			imagecopyresampled($this->reduced_canvas, $this->canvas, 0, 0, 0, 0, (int)($im_width / $ratio), (int)($im_height / $ratio), $im_width, $im_height);
			$stats = $this->get_img_stats($this->reduced_canvas);
			$this->face = $this->do_detect_greedy_big_to_small($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
			if(!is_null($this->face)){
				if($this->face['w'] > 0){
					$this->face['x'] *= $ratio;
					$this->face['y'] *= $ratio;
					$this->face['w'] *= $ratio;
				}
			}
		} else {
			$stats = $this->get_img_stats($this->canvas);
			$this->face = $this->do_detect_greedy_big_to_small($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
		}
		if(is_null($this->face)) return false;
		return $this->face['w'] > 0;
	}

	/**
	 * Calculates and returns image statistics, including width, height, and integral images (ii and ii2).
	 *
	 * @param GdImage $canvas The GD image resource.
	 * @return array An associative array containing 'width', 'height', 'ii' (integral image), and 'ii2' (squared integral image).
	 */
	private function get_img_stats(GdImage $canvas) : array {
		$image_width = imagesx($canvas);
		$image_height = imagesy($canvas);
		$iis = $this->compute_ii($canvas, $image_width, $image_height);
		return [
			'width' => $image_width,
			'height' => $image_height,
			'ii' => $iis['ii'],
			'ii2' => $iis['ii2']
		];
	}

	/**
	 * Computes the integral image (ii) and squared integral image (ii2) for a given GD image.
	 * These are used for efficient calculation of sums over rectangular regions.
	 *
	 * @param GdImage $canvas The GD image resource.
	 * @param int $image_width The width of the image.
	 * @param int $image_height The height of the image.
	 * @return array An associative array containing 'ii' and 'ii2'.
	 */
	private function compute_ii(GdImage $canvas, int $image_width, int $image_height) : array {
		$ii_w = $image_width + 1;
		$ii_h = $image_height + 1;
		$ii = [];
		$ii2 = [];
		for($i = 0; $i < $ii_w; $i++){
			$ii[$i] = 0;
			$ii2[$i] = 0;
		}
		for($i = 1; $i < $ii_h - 1; $i++){
			$ii[$i * $ii_w] = 0;
			$ii2[$i * $ii_w] = 0;
			$rowsum = 0;
			$rowsum2 = 0;
			for($j = 1; $j < $ii_w - 1; $j++){
				$rgb = imagecolorat($canvas, $j, $i);
				$red = ($rgb >> 16) & 0xFF;
				$green = ($rgb >> 8) & 0xFF;
				$blue = $rgb & 0xFF;
				$grey = (int)(0.2989 * $red + 0.587 * $green + 0.114 * $blue);
				$rowsum += $grey;
				$rowsum2 += $grey * $grey;
				$ii_above = ($i - 1) * $ii_w + $j;
				$ii_this = $i * $ii_w + $j;
				$ii[$ii_this] = $ii[$ii_above] + $rowsum;
				$ii2[$ii_this] = $ii2[$ii_above] + $rowsum2;
			}
		}
		return ['ii' => $ii, 'ii2' => $ii2];
	}

	/**
	 * Performs greedy face detection from big to small scale.
	 *
	 * It iterates through different scales and positions to find the best-fit face.
	 *
	 * @param array $ii The integral image.
	 * @param array $ii2 The squared integral image.
	 * @param int $width The width of the image.
	 * @param int $height The height of the image.
	 * @return ?array An associative array with 'x', 'y', 'w' (width/height of the detected face), or null if no face is found.
	 */
	private function do_detect_greedy_big_to_small(array $ii, array $ii2, int $width, int $height) : ?array {
		$s_w = $width / 20.0;
		$s_h = $height / 20.0;
		$start_scale = $s_h < $s_w ? $s_h : $s_w;
		$scale_update = 1 / 1.2;
		for($scale = $start_scale; $scale > 1; $scale *= $scale_update){
			$w = (int)(20 * $scale);
			$endx = $width - $w - 1;
			$endy = $height - $w - 1;
			$step = (int)(max($scale, 2));
			$inv_area = 1 / ($w * $w);
			for($y = 0; $y < $endy; $y += $step){
				for($x = 0; $x < $endx; $x += $step){
					$passed = $this->detect_on_sub_image($x, $y, $scale, $ii, $ii2, $w, $width + 1, $inv_area);
					if($passed){
						return ['x' => $x, 'y' => $y, 'w' => $w];
					}
				}
			}
		}
		return null;
	}

	/**
	 * Checks if a sub-image at a given position and scale passes the cascade classifier stages.
	 *
	 * @param int $x The x-coordinate of the top-left corner of the sub-image.
	 * @param int $y The y-coordinate of the top-left corner of the sub-image.
	 * @param float $scale The current detection scale.
	 * @param array $ii The integral image.
	 * @param array $ii2 The squared integral image.
	 * @param int $w The width of the sub-image.
	 * @param int $iiw The width of the integral image (image_width + 1).
	 * @param float $inv_area The inverse of the area of the sub-image (1 / (w*w)).
	 * @return bool True if the sub-image passes all detection stages, false otherwise.
	 */
	private function detect_on_sub_image(int $x, int $y, float $scale, array $ii, array $ii2, int $w, int $iiw, float $inv_area) : bool {
		$mean = ($ii[($y + $w) * $iiw + $x + $w] + $ii[$y * $iiw + $x] - $ii[($y + $w) * $iiw + $x] - $ii[$y * $iiw + $x + $w]) * $inv_area;
		$vnorm = ($ii2[($y + $w) * $iiw + $x + $w] + $ii2[$y * $iiw + $x] - $ii2[($y + $w) * $iiw + $x] - $ii2[$y * $iiw + $x + $w]) * $inv_area - ($mean * $mean);
		$vnorm = $vnorm > 1 ? sqrt($vnorm) : 1;
		$count_data = count($this->detection_data);
		for($i_stage = 0; $i_stage < $count_data; $i_stage++){
			$stage = $this->detection_data[$i_stage];
			$trees = $stage[0];
			$stage_thresh = $stage[1];
			$stage_sum = 0;
			$count_trees = count($trees);
			for($i_tree = 0; $i_tree < $count_trees; $i_tree++){
				$tree = $trees[$i_tree];
				$current_node = $tree[0];
				$tree_sum = 0;
				while($current_node != null){
					$node_thresh = $current_node[0][0];
					$leftval = $current_node[0][1];
					$rightval = $current_node[0][2];
					$leftidx = $current_node[0][3];
					$rightidx = $current_node[0][4];
					$rect_sum = 0;
					$count_rects = count($current_node[1]);
					for($i_rect = 0; $i_rect < $count_rects; $i_rect++){
						$rx = (int)($current_node[1][$i_rect][0] * $scale + $x);
						$ry = (int)($current_node[1][$i_rect][1] * $scale + $y);
						$rw = (int)($current_node[1][$i_rect][2] * $scale);
						$rh = (int)($current_node[1][$i_rect][3] * $scale);
						$rect_sum += (($ii[($ry + $rh) * $iiw + $rx + $rw] + $ii[$ry * $iiw + $rx] - $ii[($ry + $rh) * $iiw + $rx] - $ii[$ry * $iiw + $rx + $rw]) * $current_node[1][$i_rect][4]);
					}
					$rect_sum *= $inv_area;
					$current_node = null;
					if($rect_sum >= $node_thresh * $vnorm){
						if($rightidx == -1){
							$tree_sum = $rightval;
						} else {
							$current_node = $tree[$rightidx];
						}
					} else {
						if($leftidx == -1){
							$tree_sum = $leftval;
						} else {
							$current_node = $tree[$leftidx];
						}
					}
				}
				$stage_sum += $tree_sum;
			}
			if($stage_sum < $stage_thresh){
				return false;
			}
		}
		return true;
	}

}

?>