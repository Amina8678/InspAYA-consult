<?php

namespace Tests\Unit;

use App\Rules\HexColour;
use App\Rules\SafeLink;
use App\Support\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidationRulesTest extends TestCase
{
    private function passes(object $rule, mixed $value): bool
    {
        $failed = false;
        $rule->validate('field', $value, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }

    /**
     * @return array<string, array{mixed, bool, bool}> value, passes as link, passes as web-only
     */
    public static function links(): array
    {
        return [
            'https' => ['https://example.com/page?a=1', true, true],
            'http' => ['http://example.com', true, true],
            'mailto' => ['mailto:hello@example.com', true, false],
            'tel' => ['tel:+1 555 0100', true, false],
            'site path' => ['/services/governance', true, false],
            'anchor' => ['#contact', true, false],
            'javascript' => ['javascript:alert(1)', false, false],
            'javascript with tab' => ["java\tscript:alert(1)", false, false],
            'data uri' => ['data:text/html,<b>x</b>', false, false],
            'protocol-relative' => ['//evil.example', false, false],
            'https without host' => ['https://', false, false],
            'bad mailto' => ['mailto:not-an-email', false, false],
            'ftp' => ['ftp://example.com', false, false],
            'bare domain (not a site path)' => ['example.com', false, false],
            'too long' => ['https://example.com/'.str_repeat('a', 500), false, false],
            'array' => [['https://example.com'], false, false],
        ];
    }

    #[DataProvider('links')]
    public function test_safe_link(mixed $value, bool $asLink, bool $asWebOnly): void
    {
        $this->assertSame($asLink, $this->passes(new SafeLink, $value), 'SafeLink');
        $this->assertSame($asWebOnly, $this->passes(new SafeLink(webOnly: true), $value), 'SafeLink(webOnly)');
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function colours(): array
    {
        return [
            '#RRGGBB' => ['#0A1E38', true],
            '#rrggbb lower' => ['#b49659', true],
            '#RGB' => ['#fff', true],
            'named colour' => ['red', false],
            'no hash' => ['0A1E38', false],
            'four digits' => ['#abcd', false],
            'eight digits' => ['#0A1E38FF', false],
            'css injection' => ['#fff;}</style><script>alert(1)</script>', false],
            'url()' => ['url(https://evil.example/x.png)', false],
            'rgb()' => ['rgb(0,0,0)', false],
            'trailing newline' => ["#ffffff\n", false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('colours')]
    public function test_hex_colour(mixed $value, bool $valid): void
    {
        $this->assertSame($valid, $this->passes(new HexColour, $value));
    }

    public function test_slug_pattern_matches_the_public_route_pattern(): void
    {
        foreach (['energy-policy', 'a', 'governance-2', 'x1-y2'] as $ok) {
            $this->assertMatchesRegularExpression(Slug::PATTERN, $ok);
        }
        foreach (['Energy', 'energy--policy', '-energy', 'energy-', 'energy_policy', 'ené'] as $bad) {
            $this->assertDoesNotMatchRegularExpression(Slug::PATTERN, $bad);
        }
    }
}
