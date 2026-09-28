<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WCAG 2.2 AA contrast for every colour pair site.css uses, read from the
 * real tokens in public/css/theme.css, so a rebrand that breaks contrast
 * fails here. Text needs >= 4.5:1; focus rings, borders and icons >= 3:1.
 */
class ThemeContrastTest extends TestCase
{
    private const TEXT = 4.5;

    private const NON_TEXT = 3.0;

    /** @var array<string, string>|null */
    private static ?array $tokens = null;

    /**
     * [foreground token, background token, minimum ratio]. A background of
     * "gradient-hero" etc. means every colour stop of that gradient;
     * "hero-overlay-on-white" is the 80% overlay over a pure white photo
     * (the worst case for white hero text).
     *
     * @return array<string, array{string, string, float}>
     */
    public static function pairs(): array
    {
        return [
            // Body text and headings on page backgrounds.
            'body text on white' => ['color-text', 'color-bg', self::TEXT],
            'body text on light sections' => ['color-text', 'color-surface', self::TEXT],
            'strong text on white' => ['color-text-strong', 'color-bg', self::TEXT],
            'strong text on light sections' => ['color-text-strong', 'color-surface', self::TEXT],
            'headings on white' => ['color-heading', 'color-bg', self::TEXT],
            'headings on light sections' => ['color-heading', 'color-surface', self::TEXT],
            'muted text on white' => ['color-text-muted', 'color-bg', self::TEXT],
            'muted text on light sections' => ['color-text-muted', 'color-surface', self::TEXT],
            'tag text on tag background' => ['color-text-strong', 'color-surface-strong', self::TEXT],
            // Links and labels.
            'links on white' => ['color-primary', 'color-bg', self::TEXT],
            'links on light sections' => ['color-primary', 'color-surface', self::TEXT],
            'link hover on white' => ['color-primary-hover', 'color-bg', self::TEXT],
            'eyebrow on white' => ['color-accent', 'color-bg', self::TEXT],
            'eyebrow on light sections' => ['color-accent', 'color-surface', self::TEXT],
            'gold eyebrow on dark gradient' => ['color-gold', 'gradient-hero', self::TEXT],
            // Buttons, badges, service card hover, icon circles.
            'button text on button' => ['color-button-text', 'color-button-bg', self::TEXT],
            'button hover text on gold' => ['color-button-hover-text', 'color-button-hover-bg', self::TEXT],
            'light button hover (navy on white)' => ['color-navy', 'color-hero-text', self::TEXT],
            'badge text on navy' => ['color-button-text', 'color-navy', self::TEXT],
            'white on brand blue (service hover, icons)' => ['color-on-primary', 'color-primary', self::TEXT],
            // Header.
            'nav links on header' => ['color-header-text', 'color-header-bg', self::TEXT],
            'active nav marker on header' => ['color-header-active', 'color-header-bg', self::NON_TEXT],
            // Hero, page bands, banners, callouts.
            'hero text on gradient' => ['color-hero-text', 'gradient-hero', self::TEXT],
            'hero text on overlay over a white photo' => ['color-hero-text', 'hero-overlay-on-white', self::TEXT],
            'brand mark on feature gradient' => ['color-gold', 'gradient-feature', self::NON_TEXT],
            // Footer.
            'footer text on footer' => ['color-footer-text', 'color-footer-bg', self::TEXT],
            'footer text on bottom bar' => ['color-footer-text', 'color-footer-bottom-bg', self::TEXT],
            'footer headings on footer' => ['color-footer-heading', 'color-footer-bg', self::TEXT],
            'footer link hover on footer' => ['color-footer-link-hover', 'color-footer-bg', self::TEXT],
            // Messages.
            'error text on error background' => ['color-error', 'color-error-bg', self::TEXT],
            'error text on white' => ['color-error', 'color-bg', self::TEXT],
            'success text on success background' => ['color-success', 'color-success-bg', self::TEXT],
            // Focus rings and borders.
            'focus ring on white' => ['color-focus', 'color-bg', self::NON_TEXT],
            'focus ring on light sections' => ['color-focus', 'color-surface', self::NON_TEXT],
            'dark focus ring on header' => ['color-focus-on-dark', 'color-header-bg', self::NON_TEXT],
            'dark focus ring on footer' => ['color-focus-on-dark', 'color-footer-bg', self::NON_TEXT],
            'dark focus ring on bottom bar' => ['color-focus-on-dark', 'color-footer-bottom-bg', self::NON_TEXT],
            'dark focus ring on hero gradient' => ['color-focus-on-dark', 'gradient-hero', self::NON_TEXT],
            'dark focus ring on hovered service card' => ['color-focus-on-dark', 'color-primary', self::NON_TEXT],
            'form borders on white' => ['color-border', 'color-bg', self::NON_TEXT],
            'icon circles on white' => ['color-primary', 'color-bg', self::NON_TEXT],
        ];
    }

    #[DataProvider('pairs')]
    public function test_colour_pair_meets_wcag_aa(string $foreground, string $background, float $minimum): void
    {
        $fg = $this->colour($foreground);

        foreach ($this->backgrounds($background) as $label => $bg) {
            $ratio = self::ratio($fg, $bg);

            $this->assertGreaterThanOrEqual(
                $minimum,
                round($ratio, 2),
                sprintf('--%s (%s) on %s (%s) is %.2f:1, needs %.1f:1', $foreground, $fg, $label, $bg, $ratio, $minimum),
            );
        }
    }

    public function test_the_old_failing_body_grey_is_not_used(): void
    {
        foreach ([$this->themePath(), dirname($this->themePath()).'/site.css'] as $file) {
            // Comments may mention the old value; declarations must not use it.
            $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($file));

            $this->assertDoesNotMatchRegularExpression('/#888(888)?\b/i', $css, basename($file));
        }
    }

    /**
     * @return array<string, string> label => hex
     */
    private function backgrounds(string $name): array
    {
        if ($name === 'hero-overlay-on-white') {
            return [$name => self::blend($this->tokens()['color-hero-overlay'], '#ffffff')];
        }

        if (str_starts_with($name, 'gradient-')) {
            preg_match_all('/#[0-9a-f]{6}/i', $this->tokens()[$name], $stops);
            $this->assertNotEmpty($stops[0], "No colour stops found in --{$name}");

            return array_combine(array_map(fn ($s) => "--{$name} stop {$s}", $stops[0]), $stops[0]);
        }

        return ["--{$name}" => $this->colour($name)];
    }

    private function colour(string $name): string
    {
        $value = $this->tokens()[$name] ?? null;
        $this->assertNotNull($value, "--{$name} is not defined in theme.css");
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $value, "--{$name} must be a 6-digit hex colour");

        return strtolower($value);
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        if (self::$tokens === null) {
            $css = (string) file_get_contents($this->themePath());
            preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER);

            self::$tokens = [];
            foreach ($matches as [, $name, $value]) {
                self::$tokens[$name] = trim((string) preg_replace('~/\*.*?\*/~s', '', $value));
            }
        }

        return self::$tokens;
    }

    private function themePath(): string
    {
        return dirname(__DIR__, 2).'/public/css/theme.css';
    }

    /**
     * "rgb(r g b / a)" composited over an opaque hex background.
     */
    private static function blend(string $rgba, string $background): string
    {
        preg_match('/rgb\(\s*(\d+)\s+(\d+)\s+(\d+)\s*\/\s*([\d.]+)\s*\)/', $rgba, $m);
        [$r, $g, $b, $a] = [(int) $m[1], (int) $m[2], (int) $m[3], (float) $m[4]];
        [$br, $bg, $bb] = sscanf($background, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', round($r * $a + $br * (1 - $a)), round($g * $a + $bg * (1 - $a)), round($b * $a + $bb * (1 - $a)));
    }

    private static function ratio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
