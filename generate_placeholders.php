<?php
// This script generates placeholder images for consultations
// Run once and delete, or keep for reference

$image_specs = [
    'traffic.jpg' => [
        'title' => 'Traffic Management',
        'colors' => ['#FF6B6B', '#FFA500', '#FFD93D'],
        'icon' => '🚗'
    ],
    'environment.jpg' => [
        'title' => 'Environment',
        'colors' => ['#2ECC71', '#27AE60', '#229954'],
        'icon' => '🌍'
    ],
    'housing.jpg' => [
        'title' => 'Housing',
        'colors' => ['#3498DB', '#2980B9', '#1F618D'],
        'icon' => '🏠'
    ],
    'youth.jpg' => [
        'title' => 'Youth Program',
        'colors' => ['#9B59B6', '#8E44AD', '#6C3483'],
        'icon' => '👨‍🎓'
    ]
];

foreach ($image_specs as $filename => $spec) {
    $img = imagecreatetruecolor(800, 400);
    
    // Create gradient background
    $color1 = hexToRgb($spec['colors'][0]);
    $color2 = hexToRgb($spec['colors'][1]);
    
    for ($y = 0; $y < 400; $y++) {
        $ratio = $y / 400;
        $r = (int)($color1['r'] + ($color2['r'] - $color1['r']) * $ratio);
        $g = (int)($color1['g'] + ($color2['g'] - $color1['g']) * $ratio);
        $b = (int)($color1['b'] + ($color2['b'] - $color1['b']) * $ratio);
        
        $line_color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, 800, $y, $line_color);
    }
    
    // Add text
    $white = imagecolorallocate($img, 255, 255, 255);
    $font = __DIR__ . '/arial.ttf';
    
    // Title
    imagettftext($img, 48, 0, 50, 150, $white, $font, $spec['title']);
    
    // Icon (using text as placeholder)
    imagettftext($img, 120, 0, 600, 280, $white, $font, $spec['icon']);
    
    $path = __DIR__ . '/images/' . $filename;
    imagejpeg($img, $path, 85);
    imagedestroy($img);
    
    echo "Created: $filename\n";
}

function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

echo "Placeholder images generated successfully!";
?>
