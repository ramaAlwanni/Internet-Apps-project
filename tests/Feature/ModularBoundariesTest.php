<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModularBoundariesTest extends TestCase
{
    public function test_order_module_does_not_import_user_or_product_models(): void
    {
        $orderPath = base_path('Modules/Order');
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($orderPath)
        );

        $violations = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            $patterns = [
                'Modules\\User\\Models',
                'Modules\\Product\\Models',
                'Product::',
                'User::',
                'belongsTo(User::class',
                'belongsTo(Product::class',
                'exists:products',
                'exists:users',
            ];

            foreach ($patterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = $file->getPathname()
                        . ' contains ' . $pattern;
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_module_contract_bindings_are_registered(): void
    {
        $this->assertInstanceOf(
            \App\Contracts\Catalog\ProductCatalog::class,
            app(\App\Contracts\Catalog\ProductCatalog::class)
        );

        $this->assertInstanceOf(
            \App\Contracts\Identity\UserDirectory::class,
            app(\App\Contracts\Identity\UserDirectory::class)
        );
    }
}
