<?php

use App\Support\PublicTheme;

test('contrast uses WCAG linearized sRGB luminance', function ($foreground, $background, $expected) {
    expect(PublicTheme::contrast($foreground, $background))->toBeGreaterThan($expected - 0.03)->toBeLessThan($expected + 0.03);
    expect(PublicTheme::contrast($foreground, $background))->toEqual(PublicTheme::contrast($background, $foreground));
})->with([
    'black on white' => ['#000000', '#ffffff', 21.0],
    'white on black' => ['#ffffff', '#000000', 21.0],
    'dark on cream' => ['#0a0a0a', '#f7f3eb', 17.89],
    'white on cream' => ['#ffffff', '#f7f3eb', 1.11],
    'gold on white' => ['#e8af4b', '#ffffff', 1.99],
    'white on gold' => ['#ffffff', '#e8af4b', 1.99],
    'button classic' => ['#0a0a0a', '#e8af4b', 10.05],
    'footer classic' => ['#0a0a0a', '#faf8f4', 18.67],
    'identical colors' => ['#123456', '#123456', 1.0],
]);
