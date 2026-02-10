<?php
// Create placeholder images for consultations
$images = [
    'traffic.jpg' => [
        'color' => [255, 107, 107],
        'title' => 'TRAFFIC'
    ],
    'environment.jpg' => [
        'color' => [46, 204, 113],
        'title' => 'ENVIRONMENT'
    ],
    'housing.jpg' => [
        'color' => [52, 152, 219],
        'title' => 'HOUSING'
    ],
    'youth.jpg' => [
        'color' => [155, 89, 182],
        'title' => 'YOUTH'
    ]
];

foreach ($images as $filename => $info) {
    $img = imagecreatetruecolor(800, 400);
    
    // Fill with base color
    $color = imagecolorallocate($img, $info['color'][0], $info['color'][1], $info['color'][2]);
    imagefill($img, 0, 0, $color);
    
    // Create gradient effect
    for ($y = 0; $y < 400; $y++) {
        $ratio = $y / 400;
        $r = (int)($info['color'][0] - (20 * $ratio));
        $g = (int)($info['color'][1] - (20 * $ratio));
        $b = (int)($info['color'][2] - (20 * $ratio));
        
        $line_color = imagecolorallocate($img, max(0, $r), max(0, $g), max(0, $b));
        imageline($img, 0, $y, 800, $y, $line_color);
    }
    
    // Add white text using built-in font
    $white = imagecolorallocate($img, 255, 255, 255);
    $text = $info['title'];
    imagestring($img, 5, 250, 180, $text, $white);
    
    $path = __DIR__ . '/images/' . $filename;
    if (imagejpeg($img, $path, 85)) {
        echo "Created: $filename\n";
    } else {
        echo "Failed: $filename\n";
    }
    imagedestroy($img);
}
?>
