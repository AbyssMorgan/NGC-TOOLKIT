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

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Imagick;

class FaceDetector {

	private array $detection_data;
	private array|null $face;
	private GdImage $canvas;
	private GdImage $reduced_canvas;

	public function __construct(string $detection_data){
		$this->detection_data = unserialize(file_get_contents($detection_data));
	}

	private function getVariantRectangle(float $multiplier = 1.0) : array {
		$sw = $this->face['w'];
		$nw = $this->face['w'] * $multiplier;
		return [
			'x' => $this->face['x'] - (($nw - $sw) / 2.0),
			'y' => $this->face['y'] - (($nw - $sw) / 2.0),
			'width' => $nw,
			'height' => $nw
		];
	}

	public function saveVariantImage(float $multiplier, string $input, string $output, int $size) : bool {
		$rect = $this->getVariantRectangle($multiplier);
		$image = new Imagick();
		$image->readImage($input);
		$image->cropImage((int)$rect['width'], (int)$rect['height'], (int)$rect['x'], (int)$rect['y']);
		if($size > 0) $image->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
		$image->setImageCompressionQuality(100);
		$image->writeImage($output);
		$image->clear();
		return true;
	}

	public function faceDetect(GdImage $image) : bool {
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
			$stats = $this->getImgStats($this->reduced_canvas);
			$this->face = $this->doDetectGreedyBigToSmall($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
			if(!is_null($this->face)){
				if($this->face['w'] > 0){
					$this->face['x'] *= $ratio;
					$this->face['y'] *= $ratio;
					$this->face['w'] *= $ratio;
				}
			}
		} else {
			$stats = $this->getImgStats($this->canvas);
			$this->face = $this->doDetectGreedyBigToSmall($stats['ii'], $stats['ii2'], $stats['width'], $stats['height']);
		}
		if(is_null($this->face)) return false;
		return ($this->face['w'] > 0);
	}

	private function getImgStats(GdImage $canvas) : array {
		$image_width = imagesx($canvas);
		$image_height = imagesy($canvas);
		$iis = $this->computeII($canvas, $image_width, $image_height);
		return [
			'width' => $image_width,
			'height' => $image_height,
			'ii' => $iis['ii'],
			'ii2' => $iis['ii2']
		];
	}

	private function computeII(GdImage $canvas, int $image_width, int $image_height) : array {
		$ii_w = $image_width+1;
		$ii_h = $image_height+1;
		$ii = [];
		$ii2 = [];
		for($i = 0; $i < $ii_w; $i++){
			$ii[$i] = 0;
			$ii2[$i] = 0;
		}
		for($i = 1; $i < $ii_h-1; $i++){
			$ii[$i*$ii_w] = 0;
			$ii2[$i*$ii_w] = 0;
			$rowsum = 0;
			$rowsum2 = 0;
			for($j = 1; $j < $ii_w-1; $j++){
				$rgb = imagecolorat($canvas, $j, $i);
				$red = ($rgb >> 16) & 0xFF;
				$green = ($rgb >> 8) & 0xFF;
				$blue = $rgb & 0xFF;
				$grey = (int)(0.2989*$red + 0.587*$green + 0.114*$blue);
				$rowsum += $grey;
				$rowsum2 += $grey*$grey;
				$ii_above = ($i-1)*$ii_w + $j;
				$ii_this = $i*$ii_w + $j;
				$ii[$ii_this] = $ii[$ii_above] + $rowsum;
				$ii2[$ii_this] = $ii2[$ii_above] + $rowsum2;
			}
		}
		return ['ii' => $ii, 'ii2' => $ii2];
	}

	private function doDetectGreedyBigToSmall(array $ii, array $ii2, int $width, int $height) : array|null {
		$s_w = $width/20.0;
		$s_h = $height/20.0;
		$start_scale = $s_h < $s_w ? $s_h : $s_w;
		$scale_update = 1 / 1.2;
		for($scale = $start_scale; $scale > 1; $scale *= $scale_update){
			$w = (int)(20*$scale);
			$endx = $width - $w - 1;
			$endy = $height - $w - 1;
			$step = (int)(max($scale, 2));
			$inv_area = 1 / ($w*$w);
			for($y = 0; $y < $endy; $y += $step){
				for($x = 0; $x < $endx; $x += $step){
					$passed = $this->detectOnSubImage($x, $y, $scale, $ii, $ii2, $w, $width+1, $inv_area);
					if($passed){
						return ['x' => $x, 'y' => $y, 'w' => $w];
					}
				}
			}
		}
		return null;
	}

	private function detectOnSubImage(int $x, int $y, float $scale, array $ii, array $ii2, int $w, int $iiw, float $inv_area) : bool {
		$mean = ($ii[($y+$w)*$iiw + $x + $w] + $ii[$y*$iiw+$x] - $ii[($y+$w)*$iiw+$x] - $ii[$y*$iiw+$x+$w])*$inv_area;
		$vnorm = ($ii2[($y+$w)*$iiw + $x + $w] + $ii2[$y*$iiw+$x] - $ii2[($y+$w)*$iiw+$x] - $ii2[$y*$iiw+$x+$w])*$inv_area - ($mean*$mean);
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
						$rx = (int)(($current_node[1][$i_rect][0]*$scale)+$x);
						$ry = (int)(($current_node[1][$i_rect][1]*$scale)+$y);
						$rw = (int)($current_node[1][$i_rect][2]*$scale);
						$rh = (int)($current_node[1][$i_rect][3]*$scale);
						$rect_sum += (($ii[($ry+$rh)*$iiw + $rx + $rw] + $ii[$ry*$iiw+$rx] - $ii[($ry+$rh)*$iiw+$rx] - $ii[$ry*$iiw+$rx+$rw])*$current_node[1][$i_rect][4]);
					}
					$rect_sum *= $inv_area;
					$current_node = null;
					if($rect_sum >= $node_thresh*$vnorm){
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
