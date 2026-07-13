<?php
declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required but not installed.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = \Matrimony\Database\Connection::pdo();

// Get all profiles with their user IDs
$stmt = $pdo->query('SELECT p.user_id, p.first_name, p.last_name, p.gender FROM profiles p ORDER BY p.user_id');
$profiles = $stmt->fetchAll();

echo "Found " . count($profiles) . " profiles\n";

$targetDir = __DIR__ . '/../public_html/uploads/photos';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
    echo "Created directory: $targetDir\n";
}

// Color palettes for male and female
$maleColors = [
    ['#2196F3', '#1565C0'], // Blue
    ['#4CAF50', '#2E7D32'], // Green
    ['#FF9800', '#E65100'], // Orange
    ['#9C27B0', '#6A1B9A'], // Purple
    ['#00BCD4', '#00838F'], // Cyan
    ['#607D8B', '#37474F'], // Blue Grey
    ['#795548', '#4E342E'], // Brown
    ['#3F51B5', '#1A237E'], // Indigo
];

$femaleColors = [
    ['#E91E63', '#AD1457'], // Pink
    ['#F48FB1', '#C2185B'], // Light Pink
    ['#CE93D8', '#7B1FA2'], // Purple
    ['#FF7043', '#D84315'], // Deep Orange
    ['#EC407A', '#C2185B'], // Rose
    ['#AB47BC', '#6A1B9A'], // Orchid
    ['#26A69A', '#00695C'], // Teal
    ['#FFA726', '#E65100'], // Amber
];

$count = 0;

foreach ($profiles as $profile) {
    $userId = (int) $profile['user_id'];
    $firstName = $profile['first_name'] ?: 'User';
    $lastName = $profile['last_name'] ?? '';
    $gender = $profile['gender'] ?: 'male';
    
    $filePath = $targetDir . '/user' . $userId . '_1.jpg';
    
    // Skip if file already exists
    if (file_exists($filePath) && filesize($filePath) > 1000) {
        echo "  SKIP user_id=$userId (file exists)\n";
        continue;
    }
    
    // Pick colors based on gender
    $palette = $gender === 'female' ? $femaleColors : $maleColors;
    $colors = $palette[$userId % count($palette)];
    
    // Dimensions
    $width = 400;
    $height = 500;
    
    // Create image
    $img = imagecreatetruecolor($width, $height);
    
    // Parse hex colors
    $c1 = hexToRgb($colors[0]);
    $c2 = hexToRgb($colors[1]);
    $color1 = imagecolorallocate($img, $c1[0], $c1[1], $c1[2]);
    $color2 = imagecolorallocate($img, $c2[0], $c2[1], $c2[2]);
    
    // Draw gradient background
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int) ($c1[0] + ($c2[0] - $c1[0]) * $ratio);
        $g = (int) ($c1[1] + ($c2[1] - $c1[1]) * $ratio);
        $b = (int) ($c1[2] + ($c2[2] - $c1[2]) * $ratio);
        $col = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $width, $y, $col);
    }
    
    // Draw decorative circles
    for ($i = 0; $i < 3; $i++) {
        $cx = $width * (0.2 + 0.6 * ($i / 2));
        $cy = $height * (0.15 + 0.35 * ($i / 2));
        $radius = 60 + $i * 20;
        $circleColor = imagecolorallocatealpha($img, 255, 255, 255, 60 + $i * 30);
        imagefilledellipse($img, (int)$cx, (int)$cy, $radius * 2, $radius * 2, $circleColor);
    }
    
    // Draw user silhouette outline
    $headY = (int)($height * 0.22);
    $headR = 50;
    $bodyTopY = $headY + $headR + 10;
    $bodyBottomY = (int)($height * 0.65);
    $bodyW = 120;
    
    // Head circle
    $headColor = imagecolorallocatealpha($img, 255, 255, 255, 40);
    imagefilledellipse($img, (int)($width / 2), $headY, $headR * 2, $headR * 2, $headColor);
    
    // Body (trapezoid shape)
    $bodyPoints = [
        (int)(($width - $bodyW) / 2), $bodyTopY,
        (int)(($width + $bodyW) / 2), $bodyTopY,
        (int)(($width + $bodyW * 0.8) / 2), $bodyBottomY,
        (int)(($width - $bodyW * 0.8) / 2), $bodyBottomY,
    ];
    imagefilledpolygon($img, $bodyPoints, $headColor);
    
    // Get initials
    $initials = strtoupper($firstName[0] . ($lastName ? $lastName[0] : ''));
    
    // Draw initials text
    $fontSize = 120;
    $fontColor = imagecolorallocatealpha($img, 255, 255, 255, 20);
    
    // Try to find a font, fall back to built-in
    $font = __DIR__ . '/../public_html/assets/fonts/Roboto-Bold.ttf';
    if (file_exists($font)) {
        $bbox = imagettfbbox($fontSize, 0, $font, $initials);
        if ($bbox) {
            $textX = (int)(($width - ($bbox[2] - $bbox[0])) / 2);
            $textY = (int)(($height + ($bbox[1] - $bbox[7])) / 2);
            imagettftext($img, $fontSize, 0, $textX, $textY, $fontColor, $font, $initials);
        }
    }
    
    // Draw subtle bottom gradient
    $bottomColor = imagecolorallocatealpha($img, 0, 0, 0, 60);
    imagefilledrectangle($img, 0, (int)($height * 0.85), $width, $height, $bottomColor);
    
    // Name text at bottom
    $nameText = $firstName . ' ' . $lastName;
    $nameColor = imagecolorallocatealpha($img, 255, 255, 255, 30);
    if (file_exists($font)) {
        $nFontSize = 28;
        $bbox = imagettfbbox($nFontSize, 0, $font, $nameText);
        if ($bbox) {
            $textX = (int)(($width - ($bbox[2] - $bbox[0])) / 2);
            $textY = (int)($height * 0.92);
            imagettftext($img, $nFontSize, 0, $textX, $textY, $nameColor, $font, $nameText);
        }
    }
    
    // Save as JPEG
    imagejpeg($img, $filePath, 85);
    imagedestroy($img);
    
    echo "  CREATED user_id=$userId ($firstName $lastName) -> $filePath\n";
    $count++;
}

echo "\nDone! Created $count images.\n";

function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}
