<?php

namespace Tests\Unit;

use App\Support\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeUrlTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function urls(): array
    {
        return [
            'https' => ['https://example.com/a?b=1', 'https://example.com/a?b=1'],
            'http' => ['http://example.com', 'http://example.com'],
            'mailto' => ['mailto:hello@example.com', 'mailto:hello@example.com'],
            'tel' => ['tel:+15550100', 'tel:+15550100'],
            'relative path' => ['/contact', '/contact'],
            'anchor' => ['#services', '#services'],
            'trimmed' => ['  https://example.com  ', 'https://example.com'],
            'javascript' => ['javascript:alert(1)', null],
            'javascript mixed case' => ['JaVaScRiPt:alert(1)', null],
            'javascript with tab' => ["java\tscript:alert(1)", null],
            'javascript with leading space' => ['   javascript:alert(1)', null],
            'data uri' => ['data:text/html,<script>alert(1)</script>', null],
            'vbscript' => ['vbscript:msgbox(1)', null],
            'protocol-relative' => ['//evil.example', null],
            'empty' => ['', null],
            'not a string' => [['https://example.com'], null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('urls')]
    public function test_only_safe_urls_survive(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, SafeUrl::sanitize($input));
    }
}
