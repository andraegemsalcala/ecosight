<?php
// Verify generated logo assets + write background-check previews to storage/logo-check/
declare(strict_types=1);
$base = 'C:/Users/Rens Castro/ecosight';
$out  = $base . '/storage/logo-check';
@mkdir($out, 0777, true);

$checkDir = $base . '/storage/logo-check';
@mkdir($checkDir, 0777, true);

function px($im, int $x, int $y): array
{
    $c = imagecolorat($im, $x, $y);
    $a = ($c >> 24) & 0x7F; // 0 opaque .. 127 transparent
    return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, $a];
}

$files = [
    'logo-stacked.png', 'logo-stacked-light.png', 'logo-mark.png', 'logo-mark-light.png',
    'logo-wordmark.png', 'logo-horizontal.png', 'logo-horizontal-light.png', 'logo.png',
    'favicon.png', 'favicon-192.png', 'apple-touch-icon.png',
];

$allOk = true;
foreach ($files as $f) {
    $path = "$base/public/images/$f";
    if (!is_file($path)) { $path = "$base/public/$f"; }
    $im = @imagecreatefrompng($path);
    if (!$im) { echo "!! $f: unreadable\n"; $allOk = false; continue; }
    $w = imagesx($im); $h = imagesy($im);
    // corners
    [$r0, $g0, $b0, $a0] = px($im, 0, 0);
    [$rE, $gE, $bE, $aE] = px($im, $w - 1, $h - 1);
    // edge touching: any pixel within 1px of an edge that is nearly opaque?
    $edgeViolations = 0;
    for ($x = 0; $x < $w; $x++) {
        foreach ([0, 1, $h - 2, $h - 1] as $y) {
            [, , , $a] = px($im, $x, $y);
            if ($a < 20) $edgeViolations++;
        }
    }
    for ($y = 0; $y < $h; $y++) {
        foreach ([0, 1, $w - 2, $w - 1] as $x) {
            [, , , $a] = px($im, $x, $y);
            if ($a < 20) $edgeViolations++;
        }
    }
    $tag = $f === 'apple-touch-icon.png' || ($a0 > 100 && $aE > 100 && $edgeViolations === 0) ? 'OK' : 'VIOLATION';
    if ($tag !== 'OK') $allOk = false;
    echo sprintf("%-24s %4dx%-4d cornerA=%3d/%3d edgeTouches=%d  %s\n", $f, $w, $h, $a0, $aE, $edgeViolations, $tag);

    imagedestroy($im);
}

// Recolor check for light variants: no dark-green/charcoal remnants.
foreach (['logo-mark-light.png', 'logo-stacked-light.png', 'logo-horizontal-light.png'] as $f) {
    $im = imagecreatefrompng("$base/public/images/$f");
    $bad = 0; $leaf = 0;
    for ($y = 0; $y < imagesy($im); $y++) {
        for ($x = 0; $x < imagesx($im); $x++) {
            [$r, $g, $b, $a] = px($im, $x, $y);
            if ($a > 100) continue; // transparent
            // distance to dark green (#185631) and charcoal (#202C2E)
            $d1 = sqrt(pow($r - 0x18, 2) + pow($g - 0x56, 2) + pow($b - 0x31, 2));
            $d2 = sqrt(pow($r - 0x20, 2) + pow($g - 0x2C, 2) + pow($b - 0x2E, 2));
            if ($d1 < 70 || $d2 < 70) $bad++;
            if (abs($r - 0x5E) < 40 && abs($g - 0xA7) < 40 && $b < 0x70) $leaf++;
        }
    }
    $tag = $bad === 0 ? 'OK' : "REMAIN $bad";
    if ($bad !== 0) $allOk = false;
    echo sprintf("recolor check %-24s darkRemnants=%-4d leafPx=%d  %s\n", $f, $bad, $leaf, $tag);
    imagedestroy($im);
}

// Background previews
function composite(string $name, string $imgPath, array $bg, int $maxDim = 720): void
{
    $base = 'C:/Users/Rens Castro/ecosight';
    $logo = imagecreatefrompng($imgPath);
    $ratio = $maxDim / max(imagesx($logo), imagesy($logo));
    if ($ratio < 1) {
        $nw = (int) (imagesx($logo) * $ratio); $nh = (int) (imagesy($logo) * $ratio);
        $r = imagecreatetruecolor($nw, $nh);
        imagealphablending($r, false); imagesavealpha($r, true);
        imagecopyresampled($r, $logo, 0, 0, 0, 0, $nw, $nh, imagesx($logo), imagesy($logo));
        $logo = $r;
    }
    $lw = imagesx($logo); $lh = imagesy($logo);
    $pad = 40;
    $W = $lw + 2 * $pad; $H = $lh + 2 * $pad;
    $bgIm = imagecreatetruecolor($W, $H);
    $c = imagecolorallocate($bgIm, $bg[0], $bg[1], $bg[2]);
    imagefill($bgIm, 0, 0, $c);
    imagealphablending($bgIm, true);
    imagecopy($bgIm, $logo, $pad, $pad, 0, 0, $lw, $lh);
    imagepng($bgIm, "$base/storage/logo-check/$name");
    printf("  preview %s -> storage/logo-check/%s\n", $name, $name);
}

composite('stacked-on-white.png', "$base/public/images/logo-stacked.png", [255, 255, 255]);
composite('stacked-light-on-emerald.png', "$base/public/images/logo-stacked-light.png", [24, 86, 49]);
composite('mark-light-on-emerald.png', "$base/public/images/logo-mark-light.png", [24, 86, 49]);
composite('horizontal-on-white.png', "$base/public/images/logo-horizontal.png", [255, 255, 255]);
composite('horizontal-light-on-dark.png', "$base/public/images/logo-horizontal-light.png", [10, 30, 20]);
composite('mark-on-white.png', "$base/public/images/logo-mark.png", [255, 255, 255]);
composite('wordmark-on-white.png', "$base/public/images/logo-wordmark.png", [255, 255, 255]);

echo $allOk ? "ALL CHECKS PASSED\n" : "SOME CHECKS FAILED (see above)\n";
exit($allOk ? 0 : 1);