<?php
/**
 * EcoSight logo asset generator (PHP GD build).
 *
 * Renders the full set of logo PNG assets from public/images/logo-original.png
 * (the 2000x2000 source lockup). Use this on machines without Python/Pillow.
 * A Pillow port lives alongside as make-logo-assets.py.
 *
 * What it does, per spec:
 *  - Color-to-alpha against the white background (alpha = distance from white,
 *    original color recovered by un-blending). Pixels within a few levels of
 *    white become fully transparent.
 *  - Trims each asset to its exact content bounding box, then adds ~4% padding.
 *  - Vertically splits the lockup at the empty row band between the icon and
 *    the wordmark (detected from the data, not hard-coded).
 *  - Produces: logo-stacked, logo-mark, logo-wordmark, logo-horizontal,
 *    light variants (stacked/mark/horizontal) with charcoal + dark-green
 *    recolored to white and leaf green kept, logo.png alias of stacked,
 *    plus favicon.png (32), favicon-192.png (192), apple-touch-icon.png (180,
 *    on a solid white background) and favicon.ico (16/32/48).
 *  - No artwork is cropped: every asset is trimmed to its own bbox + padding.
 */

declare(strict_types=1);

$base    = dirname(__DIR__);
$srcPath = $base . '/public/images/logo-original.png';
$outDir  = $base . '/public/images';

const BG_TOL = 8;            // near-white threshold (levels)
const ALPHA_CUT = 0.35;      // meaningful-content alpha threshold for trim/band detection
const PAD = 0.04;            // 4% padding on each side
const DARK_GREEN = [0x18, 0x56, 0x31];
const CHARCOAL    = [0x20, 0x2C, 0x2E];
const LEAF_GREEN  = [0x5E, 0xA7, 0x3B];

if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "GD extension missing\n");
    exit(1);
}

$src = imagecreatefrompng($srcPath);
if (!$src) { fwrite(STDERR, "cannot read $srcPath\n"); exit(1); }
$w = imagesx($src);
$h = imagesy($src);
echo "Source: {$w}x{$h}\n";

/**
 * True if the source pixel holds meaningful content (distance from white past
 * BG_TOL and past ALPHA_CUT). For the source image (not the keyed layers).
 */
function srcHasContent($im, int $x, int $y): bool
{
    $rgb = imagecolorat($im, $x, $y);
    $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
    $d = max(255 - $r, 255 - $g, 255 - $b);
    return $d > BG_TOL && $d / 255 > ALPHA_CUT;
}

/** True if the keyed GD image has meaningful content at x,y (reads baked alpha). */
function keyedHasContent($im, int $x, int $y): bool
{
    $rgb = imagecolorat($im, $x, $y);
    $alpha = ($rgb >> 24) & 0x7F; // 0..127, 0 = opaque
    return $alpha < 90;            // a = 1 - alpha/127
}

/** Row occupancy of "meaningful" content for a y range. */
function occupancy($src, int $y0, int $y1): array
{
    $rows = [];
    for ($y = $y0; $y <= $y1; $y++) {
        $count = 0;
        for ($x = 0, $cx = imagesx($src); $x < $cx; $x++) {
            if (srcHasContent($src, $x, $y)) $count++;
        }
        $rows[$y] = $count;
    }
    return $rows;
}

/** Column occupancy (meaningful alpha) within a region [x0,x1]x[y0,y1]. */
function columnMinMax($src, int $x0, int $x1, int $y0, int $y1): ?array
{
    $minX = PHP_INT_MAX; $maxX = -1;
    for ($x = $x0; $x <= $x1; $x++) {
        for ($y = $y0; $y <= $y1; $y++) {
            if (srcHasContent($src, $x, $y)) {
                $minX = min($minX, $x); $maxX = max($maxX, $x);
                break;
            }
        }
    }
    return $minX === PHP_INT_MAX ? null : [$minX, $maxX];
}

/**
 * Build a new image with color-to-alpha applied (and optional recolor).
 * Returns the keyed GD image; alpha is baked into each pixel.
 *
 * Color handling: the artwork is opaque ink on a white background. Alpha is
 * derived from the per-pixel distance to white; the original RGB is kept
 * (no un-blending) so pale semi-transparent fills such as the deer's light
 * green tint stay near-white and translucent instead of being over-saturated.
 * For light variants, genuinely dark pixels (dark-green ring/deer and charcoal
 * wordmark) are recolored to white; the saturated leaf green is kept.
 */
function buildKeyed($src, bool $recolor): GdImage
{
    $w = imagesx($src); $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($src, $x, $y);
            $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
            $d = max(255 - $r, 255 - $g, 255 - $b);
            if ($d <= BG_TOL) {
                $col = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            } else {
                if ($recolor && max($r, $g, $b) < 110) { // dark ring/deer/wordmark ink
                    $r = 255; $g = 255; $b = 255;
                }
                $gdAlpha = (int) round((1 - $d / 255) * 127);
                $col = imagecolorallocatealpha($dst, $r, $g, $b, $gdAlpha);
            }
            imagesetpixel($dst, $x, $y, $col);
        }
    }
    return $dst;
}

function nearestOf(array $col, array $targets): float
{
    $best = PHP_FLOAT_MAX;
    foreach ($targets as $t) {
        $d = (($col[0] - $t[0]) ** 2) + (($col[1] - $t[1]) ** 2) + (($col[2] - $t[2]) ** 2);
        $best = min($best, sqrt($d));
    }
    return $best;
}

/**
 * Crop a region out of a keyed image (content bbox within the region + pad),
 * return [gd, naturalWidth, naturalHeight].
 */
function cropRegion(string $label, $keyed, array $region, int $maxDim): array
{
    [$r0, $r1, $c0, $c1] = $region; // y range, x range (inclusive)
    $minX = PHP_INT_MAX; $minY = PHP_INT_MAX; $maxX = -1; $maxY = -1;
    for ($y = $r0; $y <= $r1; $y++) {
        for ($x = $c0; $x <= $c1; $x++) {
            if (keyedHasContent($keyed, $x, $y)) {
                $minX = min($minX, $x); $minY = min($minY, $y);
                $maxX = max($maxX, $x); $maxY = max($maxY, $y);
            }
        }
    }
    if ($maxX < 0) { throw new RuntimeException("empty region $label"); }

    $pw = (int) round(($maxX - $minX + 1) * PAD);
    $ph = (int) round(($maxY - $minY + 1) * PAD);
    $bx = max(0, $minX - $pw); $by = max(0, $minY - $ph);
    $bw = ($maxX - $minX + 1) + (2 * $pw);
    $bh = ($maxY - $minY + 1) + (2 * $ph);
    $srcW = imagesx($keyed); $srcH = imagesy($keyed);
    $bw = min($bw, $srcW - $bx); $bh = min($bh, $srcH - $by);

    $crop = imagecrop($keyed, ['x' => $bx, 'y' => $by, 'width' => $bw, 'height' => $bh]);
    if (!$crop) { throw new RuntimeException("crop failed $label"); }

    $scale = min(1.0, $maxDim / max($bw, $bh));
    if ($scale < 1.0) {
        $nw = (int) round($bw * $scale);
        $nh = (int) round($bh * $scale);
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $crop, 0, 0, 0, 0, $nw, $nh, $bw, $bh);
        imagedestroy($crop);
        $crop = $resized;
    }
    echo "  crop[$label] origin=($bx,$by) {bw x bh}=" . imagesx($crop) . 'x' . imagesy($crop) . "\n";
    return [$crop, $bw, $bh];
}

function savePng($im, string $path, int $maxBytes = 100000): void
{
    $scaler = $im;
    $shrunk = false;
    while (true) {
        ob_start();
        imagepng($scaler, null, 9);
        $blob = ob_get_clean();
        if (strlen($blob) <= $maxBytes || imagesx($scaler) < 120) break;
        // scale down by 10% and retry
        $nw = (int) (imagesx($scaler) * 0.9);
        $nh = (int) (imagesy($scaler) * 0.9);
        $r = imagecreatetruecolor($nw, $nh);
        imagealphablending($r, false);
        imagesavealpha($r, true);
        imagecopyresampled($r, $scaler, 0, 0, 0, 0, $nw, $nh, imagesx($scaler), imagesy($scaler));
        if ($scaler !== $im) imagedestroy($scaler);
        $scaler = $r;
        $shrunk = true;
    }
    file_put_contents($path, $blob);
    if ($shrunk) printf("  (scaled to fit %d-byte limit: now %dx%d)\n", $maxBytes, imagesx($scaler), imagesy($scaler));
    printf("  wrote %s (%d bytes)\n", $path, strlen($blob));
}

/** Compose horizontal lockup: mark left, gap, wordmark right, vertically centered. */
function composeHorizontal($mark, $word, float $xHeight): GdImage
{
    $iconW = imagesx($mark); $iconH = imagesy($mark);
    $wordW = imagesx($word); $wordH = imagesy($word);
    $gap = (int) max(1, round($xHeight * 0.5));
    $H = max($iconH, $wordH);
    $W = $iconW + $gap + $wordW;
    $out = imagecreatetruecolor($W, $H);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $transp = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $transp);
    imagealphablending($out, true);
    imagecopy($out, $mark, 0, (int) (($H - $iconH) / 2), 0, 0, $iconW, $iconH);
    imagecopy($out, $word, $iconW + $gap, (int) (($H - $wordH) / 2), 0, 0, $wordW, $wordH);
    imagealphablending($out, false);
    printf("  horizontal: icon=%dx%d word=%dx%d gap=%d -> %dx%d\n", $iconW, $iconH, $wordW, $wordH, $gap, $W, $H);
    return $out;
}

/** Estimate wordmark x-height from row-ink profile. */
function estimateXHeight($src, int $y0, int $y1): float
{
    $rows = occupancy($src, $y0, $y1);
    $maxRow = max($rows);
    if ($maxRow === 0) return ($y1 - $y0) * 0.5;
    $thr = 0.3 * $maxRow;
    $capTop = null; $baseLine = null;
    foreach ($rows as $y => $c) {
        if ($c >= 0.6 * $maxRow && $capTop === null) $capTop = $y; // wide caps
        if ($c >= $thr) $baseLine = $y;                            // last heavy row ~bottom of body
    }
    if ($capTop === null || $baseLine === null) return ($y1 - $y0) * 0.5;
    // Sans-serif wordmark: x-height is usually ~0.7 of cap height.
    return max(1.0, 0.70 * ($baseLine - $capTop));
}

/** Encode an ICO file containing PNG blobs for the given sizes. */
function writeIco(array $pngPaths, string $outPath): void
{
    $entries = [];
    $blobs = [];
    foreach ($pngPaths as $p) {
        $blob = file_get_contents($p);
        $im = imagecreatefromstring($blob);
        $size = imagesx($im);
        $entries[] = ['w' => $size >= 256 ? 0 : $size, 'size' => $size, 'bytes' => strlen($blob)];
        $blobs[] = $blob;
    }
    $header = '';
    $header .= pack('v', 0); // reserved
    $header .= pack('v', 1); // icon type
    $header .= pack('v', count($entries));
    $offset = 6 + 16 * count($entries);
    $body = '';
    foreach ($entries as $i => $e) {
        $header .= pack('C', $e['w']) . pack('C', $e['w']) . pack('C', 0) . pack('C', 0)
                 . pack('v', 1) . pack('v', 32);
        $header .= pack('V', $e['bytes']);
        $header .= pack('V', $offset);
        $offset += $e['bytes'];
        $body .= $blobs[$i];
    }
    file_put_contents($outPath, $header . $body);
    printf("  wrote %s (%d bytes, sizes 16/32/48)\n", $outPath, filesize($outPath));
}

// --- Build keyed layers -----------------------------------------------------
echo "Building keyed layers (dark)...\n";
$keyed = buildKeyed($src, false);
echo "Building keyed layers (light)...\n";
$keyedLight = buildKeyed($src, true);

// Band detection: find the empty horizontal band between icon and wordmark.
$rows = occupancy($src, 0, $h - 1);
$prevInside = false;
$bands = [];
for ($y = 0; $y < $h; $y++) {
    $inside = ($rows[$y] ?? 0) === 0;
    if ($inside && !$prevInside) $bands[] = ['start' => $y, 'end' => $y];
    elseif ($inside) $bands[count($bands) - 1]['end'] = $y;
    $prevInside = $inside;
}
echo "Empty bands found: " . json_encode($bands) . "\n";

$contentRows = occupancy($src, 0, $h - 1);
$topY = null; $bottomY = null;
for ($y = 0; $y < $h; $y++) { if ($contentRows[$y] > 0) { if ($topY === null) $topY = $y; $bottomY = $y; } }

// Pick the band that sits strictly between the top and bottom content edges
// (excludes leading/trailing margin whitespace).
$innerBands = array_values(array_filter($bands, fn($b) => $b['start'] > $topY && $b['end'] < $bottomY));
if (count($innerBands) === 0) { fwrite(STDERR, "could not find icon/wordmark band\n"); exit(1); }
$band = $innerBands[0];
$iconBottom = $band['start'] - 1;
$wordTop    = $band['end'] + 1;
printf("Split: icon y %d..%d, wordmark y %d..%d (full content %d..%d)\n", $topY, $iconBottom, $wordTop, $bottomY, $topY, $bottomY);

$iconX = columnMinMax($src, 0, $w - 1, $topY, $iconBottom);
$wordX = columnMinMax($src, 0, $w - 1, $wordTop, $bottomY);
echo "icon x-range: " . json_encode($iconX) . ", wordmark x-range: " . json_encode($wordX) . "\n";

$xHeight = estimateXHeight($src, $wordTop, $bottomY);
printf("wordmark x-height estimate: %.1f px\n", $xHeight);

// --- Assets -----------------------------------------------------------------
// Stacked (dark): whole lockup.
[$stacked, , ]     = cropRegion('stacked', $keyed, [$topY, $bottomY, 0, $w - 1], 1000);
[$stackedLight, , ] = cropRegion('stacked-light', $keyedLight, [$topY, $bottomY, 0, $w - 1], 1000);

// Mark (icon).
[$mark, , ]     = cropRegion('mark', $keyed, [$topY, $iconBottom, $iconX[0] - 50, $iconX[1] + 50], 512);
[$markLight, , ] = cropRegion('mark-light', $keyedLight, [$topY, $iconBottom, $iconX[0] - 50, $iconX[1] + 50], 512);

// Wordmark (dark only).
[$word, , ] = cropRegion('wordmark', $keyed, [$wordTop, $bottomY, $wordX[0] - 50, $wordX[1] + 50], 900);

// Horizontal dark + light (mark + gap + wordmark), vertically centered.
$horizontal = composeHorizontal($mark, $word, $xHeight * (512 / max(imagesx($mark), imagesy($mark))));
// scale to 1100 wide
$hw = imagesx($horizontal); $hh = imagesy($horizontal);
if ($hw > 1100) {
    $nh = (int) round($hh * (1100 / $hw)); $nw = 1100;
    $r = imagecreatetruecolor($nw, $nh);
    imagealphablending($r, false); imagesavealpha($r, true);
    imagecopyresampled($r, $horizontal, 0, 0, 0, 0, $nw, $nh, $hw, $hh);
    imagedestroy($horizontal); $horizontal = $r;
}

// light wordmark for horizontal-light: re-key light layer wordmark region
[$wordLight, , ] = cropRegion('wordlight', $keyedLight, [$wordTop, $bottomY, $wordX[0] - 50, $wordX[1] + 50], 900);
$horizontalLight = composeHorizontal($markLight, $wordLight, $xHeight * (512 / max(imagesx($markLight), imagesy($markLight))));
$hw = imagesx($horizontalLight); $hh = imagesy($horizontalLight);
if ($hw > 1100) {
    $nh = (int) round($hh * (1100 / $hw)); $nw = 1100;
    $r = imagecreatetruecolor($nw, $nh);
    imagealphablending($r, false); imagesavealpha($r, true);
    imagecopyresampled($r, $horizontalLight, 0, 0, 0, 0, $nw, $nh, $hw, $hh);
    imagedestroy($horizontalLight); $horizontalLight = $r;
}

// --- Write everything -------------------------------------------------------
savePng($stacked, "$outDir/logo-stacked.png", 120000);       // the one allowed to be a bit larger
savePng($stackedLight, "$outDir/logo-stacked-light.png", 100000);
savePng($mark, "$outDir/logo-mark.png", 100000);
savePng($markLight, "$outDir/logo-mark-light.png", 100000);
savePng($word, "$outDir/logo-wordmark.png", 100000);
savePng($horizontal, "$outDir/logo-horizontal.png", 100000);
savePng($horizontalLight, "$outDir/logo-horizontal-light.png", 100000);
copy("$outDir/logo-stacked.png", "$outDir/logo.png");

// favicons from logo-mark.png (opaque white bg for apple-touch-icon)
$favDir = $base . '/public';
savePng(resizeTo($mark, 32), "$favDir/favicon.png");
savePng(resizeTo($mark, 192), "$favDir/favicon-192.png");
savePng(makeOpaqueOnWhite(resizeTo($mark, 180)), "$favDir/apple-touch-icon.png");

// favicon.ico: 16/32/48
$icoDir = "$base/public/.ico.tmp"; @mkdir($icoDir, 0777, true);
$sizes = [16, 32, 48];
$icoPngs = [];
foreach ($sizes as $s) {
    $p = "$icoDir/fav-$s.png";
    savePng(resizeTo($mark, $s), $p);
    $icoPngs[] = $p;
}
writeIco($icoPngs, "$favDir/favicon.ico");
foreach ($icoPngs as $p) @unlink($p);
@rmdir($icoDir);

echo "Done.\n";

function resizeTo($im, int $size): GdImage
{
    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $srcS = max(imagesx($im), imagesy($im));
    $s = $size / $srcS;
    $dx = (int) round($s * imagesx($im));
    $dy = (int) round($s * imagesy($im));
    $ox = (int) (($size - $dx) / 2);
    $oy = (int) (($size - $dy) / 2);
    // copy into center region of transparent canvas
    imagecopyresampled($dst, $im, $ox, $oy, 0, 0, $dx, $dy, imagesx($im), imagesy($im));
    return $dst;
}

function makeOpaqueOnWhite($im): GdImage
{
    $w = imagesx($im); $h = imagesy($im);
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $white);
    imagealphablending($out, true);
    imagecopy($out, $im, 0, 0, 0, 0, $w, $h);
    imagealphablending($out, false);
    return $out;
}