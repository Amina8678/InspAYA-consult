<?php

namespace Tests\Feature\Deploy;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The Docker image installs PHP extensions by hand. A view or class that
 * needs one the image lacks only fails in production (as Number::fileSize()
 * did on /admin/media), because CI installs its own extension list.
 */
class ContainerExtensionsTest extends TestCase
{
    public function test_the_image_installs_intl_whenever_the_code_uses_laravels_number_helper(): void
    {
        $root = dirname(__DIR__, 3);
        $usesNumber = false;

        foreach (['app', 'resources'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (preg_match('/\.php$/', $file->getFilename()) && preg_match('/Support\\\\Number|\bNumber::/', file_get_contents($file->getPathname()))) {
                    $usesNumber = true;
                }
            }
        }

        if ($usesNumber) {
            $dockerfile = file_get_contents($root.'/Dockerfile');
            $this->assertMatchesRegularExpression('/docker-php-ext-install[^\n]*\bintl\b/', $dockerfile);
            $this->assertStringContainsString('"intl"', $dockerfile, 'the build-time extension check must list intl');
        }

        $this->addToAssertionCount(1);
    }
}
