<?php

namespace App\Services\I18n;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's file loader, plus the strings of the interface languages added
 * from Settings (client request 2026-10-03), so `__()` speaks them too —
 * in pages, emails and toasts alike.
 */
final class DatabaseTranslationLoader implements Loader
{
    public function __construct(private readonly Loader $files) {}

    /** @return array<string, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if ($group === '*' && $namespace === '*') {
            return [...$lines, ...InterfaceLanguages::messages($locale)];
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    /** @return array<string, string> */
    public function namespaces(): array
    {
        return $this->files->namespaces();
    }
}
