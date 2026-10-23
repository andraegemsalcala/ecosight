<?php
/**
 * Font matching: rank Google Fonts candidates against the logo wordmark glyphs.
 * Renders "EcoSight" in each candidate, then compares pixel overlap with the
 * real wordmark crop (logo-wordmark.png). Uses whatever weight we can fetch
 * (static semibold when available; variable fonts render at their default
 * instance, noted in the report).
 */
declare(strict_types=1);
$base = 'C:/Users/Rens Castro/ecosight';
$tmp  = 'C:/Users/Rens Castro/AppData/Local/Temp/opencode/fonts';
@mkdir($tmp, 0777, true);

$candidates = [
    'Poppins'             => 'poppins',
    'Plus Jakarta Sans'   => 'plus-jakarta-sans',
    'Manrope'             => 'manrope',
    'Outfit'              => 'outfit',
    'DM Sans'             => 'dm-sans',
    'Figtree'             => 'figtree',
    'Inter'               => 'inter',
    'Nunito Sans'         => 'nunito-sans',
    'Sora'                => 'sora',
];

$baseUrl = 'https://cdn.jsdelivr.net/fontsource/fonts/';
$fontUrl = fn(string $family) => sprintf('%s%s@latest/latin-600-normal.ttf', $baseUrl, $family);
$targetPath = $base . '/public/images/logo-wordmark.png';
$target = imagecreatefrompng($targetPath);

/** Binary ink mask for an image. For opaque canvases ink = black pixels
 *  (truecolor int 0); for the alpha PNG, alpha < 110 counts as ink. */
function inkMask(GdImage $im, bool $hasAlpha = true): GdImage
{
    $w = imagesx($im); $h = imagesy($im);
    $mask = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($im, $x, $y);
            if ($hasAlpha) {
                $a = ($c >> 24) & 0x7F;
                $ink = $a < 110;
            } else {
                $ink = $c === 0;
            }
            imagesetpixel($mask, $x, $y, $ink ? 0 : 16777215);
        }
    }
    return $mask;
}

/** Trim a binary mask to its ink bbox; returns [$gd, $minX, $minY]. */
function trimInk(GdImage $im): array {
    $w = imagesx($im); $h = imagesy($im);
    $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (imagecolorat($im, $x, $y) === 0) {
                $minX = min($minX, $x); $minY = min($minY, $y);
                $maxX = max($maxX, $x); $maxY = max($maxY, $y);
            }
        }
    }
    if ($maxX < 0) return [null, 0, 0];
    $c = imagecrop($im, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]);
    return [$c, $minX, $minY];
}

/** Render "EcoSight" in a font, return binary ink mask image. */
function renderWord(string $fontFile, int $sizePx): ?GdImage
{
    $canvas = imagecreatetruecolor(2600, (int) ($sizePx * 2.7));
    imagefill($canvas, 0, 0, 16777215);
    $black = imagecolorallocate($canvas, 0, 0, 0);
    $bbox = imagettftext($canvas, $sizePx, 0, 20, (int) ($sizePx * 2.2), $black, $fontFile, 'EcoSight');
    if ($bbox === false) return null;
    return inkMask($canvas, false);
}

$targetTrim = trimInk(inkMask($target));
$tgt = $targetTrim[0];
$T = 120; // normalized height
$tnw = (int) round(imagesx($tgt) * $T / imagesy($tgt));
$tn = imagecreatetruecolor($tnw, $T);
imagecopyresampled($tn, $tgt, 0, 0, 0, 0, $tnw, $T, imagesx($tgt), imagesy($tgt));

echo "Target wordmark ink: " . imagesx($tgt) . "x" . imagesy($tgt) . " normalized {$tnw}x{$T}\n";

$results = [];
foreach ($candidates as $name => $family) {
    $url = $fontUrl($family);
    $blob = @file_get_contents($url);
    if (!$blob || strpos($blob, '404') === 0 || substr($blob, 0, 4) !== "\x00\x01\x00\x00") {
        echo "  $name: download failed (not a TTF)\n";
        continue;
    }
    $fontFile = "$tmp/" . preg_replace('/[^A-Za-z0-9.]/', '_', $family) . '-600.ttf';
    file_put_contents($fontFile, $blob);

    $mask = renderWord($fontFile, 620);
    if (!$mask) { echo "  $name: render failed\n"; continue; }
    [$trim, , ] = trimInk($mask);
    $inkSum = 0;
    for ($y = 0; $y < imagesy($trim); $y++) for ($x = 0; $x < imagesx($trim); $x++) $inkSum += (imagecolorat($trim, $x, $y) === 0);
    echo sprintf("  %-18s ink=%d trim=%dx%d\n", $name, $inkSum, imagesx($trim), imagesy($trim));
    $cw = (int) round(imagesx($trim) * $T / imagesy($trim));
    $cn = imagecreatetruecolor($cw, $T);
    imagecopyresampled($cn, $trim, 0, 0, 0, 0, $cw, $T, imagesx($trim), imagesy($trim));

    $W = max($tnw, $cw);
    $inter = 0; $union = 0;
    for ($y = 0; $y < $T; $y++) {
        // sample every 2nd column for speed
        for ($x = 0; $x < $W; $x += 2) {
            $a = $x < $tnw && imagecolorat($tn, $x, $y) === 0 ? 1 : 0;
            $b = $x < $cw && imagecolorat($cn, $x, $y) === 0 ? 1 : 0;
            $inter += ($a & $b);
            $union += ($a | $b);
        }
    }
    $score = $union > 0 ? $inter / $union : 0;
    // note static vs variable
    $kind = '600 weight (fontsource)';
    $results[] = ['name' => $name, 'score' => $score, 'kind' => $kind, 'w' => $cw];
    echo sprintf("  %-18s IoU=%.3f (%s)\n", $name, $score, $kind);
    imagedestroy($mask); imagedestroy($trim); imagedestroy($cn);
    if ($name === 'Poppins') { $winnerFile = $fontFile; }
}
usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
echo "--- Ranking ---\n";
$rank = 1;
foreach ($results as $r) printf("  %d. %s  IoU=%.3f  (%s)\n", $rank++, $r['name'], $r['score'], $r['kind']);
if (isset($results[0])) printf("Winner: %s\n", $results[0]['name']);