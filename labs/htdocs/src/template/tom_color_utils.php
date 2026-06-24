<?php
// Matches background.js exactly to prevent gradient flash
function tomHexToRgbArray($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

function tomRgbArrayToHex($r, $g, $b) {
    return sprintf("#%02x%02x%02x", max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

function tomEnsureDarkness($hex, $maxLuminance) {
    $rgb = tomHexToRgbArray($hex);
    $luminance = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255;

    if ($luminance > $maxLuminance) {
        $factor = $maxLuminance / $luminance;
        $rgb[0] = round($rgb[0] * $factor);
        $rgb[1] = round($rgb[1] * $factor);
        $rgb[2] = round($rgb[2] * $factor);
        return tomRgbArrayToHex($rgb[0], $rgb[1], $rgb[2]);
    }
    return $hex;
}

function tomEnsureLightness($hex, $minLuminance) {
    $rgb = tomHexToRgbArray($hex);
    $luminance = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255;

    if ($luminance < $minLuminance) {
        $factor = (1 - $minLuminance) / (1 - $luminance);
        $rgb[0] = round(255 - (255 - $rgb[0]) * $factor);
        $rgb[1] = round(255 - (255 - $rgb[1]) * $factor);
        $rgb[2] = round(255 - (255 - $rgb[2]) * $factor);
        return tomRgbArrayToHex($rgb[0], $rgb[1], $rgb[2]);
    }
    return $hex;
}

function tomAdjustColorLightness($hex, $percent) {
    $rgb = tomHexToRgbArray($hex);
    $amt = round(2.55 * $percent);
    return tomRgbArrayToHex(
        $rgb[0] + $amt,
        $rgb[1] + $amt,
        $rgb[2] + $amt
    );
}

function tomHexToRgbaString($hex, $opacity) {
    $rgb = tomHexToRgbArray($hex);
    return "rgba({$rgb[0]}, {$rgb[1]}, {$rgb[2]}, {$opacity})";
}
?>
