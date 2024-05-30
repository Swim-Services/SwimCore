<?php

namespace core\utils;

class SkinHelper
{

  public static function getSkinDataFromPNG(string $path): string
  {
    $bytes = "";
    if (!file_exists($path)) {
      return $bytes;
    }
    $img = imagecreatefrompng($path);
    [$width, $height] = getimagesize($path);
    for ($y = 0; $y < $height; ++$y) {
      for ($x = 0; $x < $width; ++$x) {
        $argb = imagecolorat($img, $x, $y);
        $bytes .= chr(($argb >> 16) & 0xff) . chr(($argb >> 8) & 0xff) . chr($argb & 0xff) . chr((~($argb >> 24) << 1) & 0xff);
      }
    }
    imagedestroy($img);
    return $bytes;
  }

  // this should probably have a lot of error checking in case someone has an exploited or broken invalid skin
  // raw skin data should be player->getSkin()->getSkinData()
  public static function getHeadData(string $rawSkinData): ImageData
  {
    $fileByteSize = strlen($rawSkinData);

    // default 64x skin values
    $width = 64;  // The width of the full skin
    $bytesPerPixel = 4;  // RGBA bytes per pixel
    $headWidth = 32;  // Width of the head area
    $headHeight = 16;  // Height of the head area
    $hoodStartX = 32; // hood start Y is just 0
    $hoodEndX = 63;
    // $hoodEndY = 15;

    // 128x skins
    if ($fileByteSize > 16384) {
      $width *= 2;
      $headWidth *= 2;
      $headHeight *= 2;
      $hoodStartX *= 2;
      $hoodEndX *= 2;
      // $hoodEndY *= 2;
    }

    // save head data to an array
    $headData = '';
    for ($y = 0; $y < $headHeight; $y++) {
      // Get the offset for the head pixels
      $offset = ($y * $width) * $bytesPerPixel;
      $headRow = substr($rawSkinData, $offset, $headWidth * $bytesPerPixel);

      // Get the offset for the hood pixels
      $hoodOffset = ($y * $width + $hoodStartX) * $bytesPerPixel;
      $hoodRow = substr($rawSkinData, $hoodOffset, ($hoodStartX - $hoodEndX + 1) * $bytesPerPixel);

      // Combine the head and hood rows
      $combinedRow = self::combineRows($headRow, $hoodRow, $headWidth);
      $headData .= $combinedRow;
    }

    return new ImageData($headData, $headWidth, $headHeight);
  }

  private static function combineRows(string $headRow, string $hoodRow, int $headWidth): string
  {
    $resultRow = '';
    for ($i = 0; $i < $headWidth * 4; $i += 4) {
      // Extract RGBA components from both rows
      $headR = ord($headRow[$i]);
      $headG = ord($headRow[$i + 1]);
      $headB = ord($headRow[$i + 2]);
      $headA = ord($headRow[$i + 3]);

      $hoodR = ord($hoodRow[$i]);
      $hoodG = ord($hoodRow[$i + 1]);
      $hoodB = ord($hoodRow[$i + 2]);
      $hoodA = ord($hoodRow[$i + 3]);

      // Check for hood opacity and combine pixels
      if ($hoodA > 0) {  // Hood pixel is visible, overlay it
        $finalR = $hoodR;
        $finalG = $hoodG;
        $finalB = $hoodB;
        $finalA = $hoodA;
      } else {  // Hood pixel is fully transparent, use head pixel
        $finalR = $headR;
        $finalG = $headG;
        $finalB = $headB;
        $finalA = $headA;
      }

      // Reconstruct the RGBA pixel
      $resultRow .= chr($finalR) . chr($finalG) . chr($finalB) . chr($finalA);
    }

    return $resultRow;
  }

  public static function emplaceDataOnNewCanvas(string $rawImageData, int $imageWidth, int $imageHeight, int $canvasWidth, int $canvasHeight): ImageData
  {
    $bytesPerPixel = 4;

    // Create a blank canvas with fully transparent pixels
    $canvasData = str_repeat("\x00\x00\x00\x00", $canvasWidth * $canvasHeight);

    // Iterate over each row and column within the bounds of the image data
    for ($y = 0; $y < $imageHeight; $y++) {
      for ($x = 0; $x < $imageWidth; $x++) {
        $imageIndex = ($y * $imageWidth + $x) * $bytesPerPixel;
        $canvasIndex = ($y * $canvasWidth + $x) * $bytesPerPixel;

        // Copy pixel data from image to canvas
        $pixelData = substr($rawImageData, $imageIndex, $bytesPerPixel);
        $canvasData = substr_replace($canvasData, $pixelData, $canvasIndex, $bytesPerPixel);
      }
    }

    return new ImageData($canvasData, $canvasWidth, $canvasHeight);
  }

  public static function saveAsPNG(string $rawImageData, string $outputPath, int $width, int $height): void
  {
    $bytesPerPixel = 4;  // Assuming RGBA

    // Create a blank true color image
    $img = imagecreatetruecolor($width, $height);

    // Preserve transparency settings
    imagesavealpha($img, true);  // Keep alpha transparency
    imagealphablending($img, false);  // Disable blending modes

    // Set a fully transparent background
    $transparentBackground = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparentBackground);

    // Loop through each pixel in the raw image data
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        // Calculate the offset for each pixel's data in the string
        $offset = (($y * $width) + $x) * $bytesPerPixel;
        // Extract RGBA components from the string data
        $r = ord($rawImageData[$offset]);
        $g = ord($rawImageData[$offset + 1]);
        $b = ord($rawImageData[$offset + 2]);
        $a = ord($rawImageData[$offset + 3]);
        // Convert alpha to a value appropriate for GD (0-127)
        $gdAlpha = (int)(127 - ($a / 2));
        // Allocate the color for the pixel
        $color = imagecolorallocatealpha($img, $r, $g, $b, $gdAlpha);
        // Set the pixel
        imagesetpixel($img, $x, $y, $color);
      }
    }

    // Save the image as PNG
    imagepng($img, $outputPath);

    // Destroy the image to free memory
    imagedestroy($img);
  }

}

class ImageData
{

  public string $bytes;
  public int $width;
  public int $height;

  public function __construct(string $bytes, int $width, int $height)
  {
    $this->bytes = $bytes;
    $this->width = $width;
    $this->height = $height;
  }

}