<?php
// One-time: generates the PWA icons referenced by manifest.json (images/icon-192.png,
// images/icon-512.png). Re-run only if you want to change the icon design.
$sizes = [192, 512];
$bg = [0x12, 0x16, 0x2a];   // matches the app's dark navy background
$fg = [0xcd, 0xa1, 0x5c];   // matches the app's brass/gold accent (btn-primary)

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    $bgColor = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    imagefill($img, 0, 0, $bgColor);

    // Gold filled circle, echoing the header's "logo-dot" ● mark.
    $fgColor = imagecolorallocate($img, $fg[0], $fg[1], $fg[2]);
    $r = (int) ($size * 0.28);
    imagefilledellipse($img, (int) ($size / 2), (int) ($size / 2), $r * 2, $r * 2, $fgColor);

    imagepng($img, __DIR__ . '/../images/icon-' . $size . '.png');
    imagedestroy($img);
    echo "Wrote images/icon-$size.png\n";
}
