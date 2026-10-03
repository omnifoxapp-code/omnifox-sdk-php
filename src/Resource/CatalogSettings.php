<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\RequestOptions;

/** How the catalog and orders behave in the workspace. One row, no id. */
final class CatalogSettings extends AbstractResource
{
    /** Returns `['settings' => [...], 'presets' => [...]]`. @return array<string, mixed> */
    public function get(): array
    {
        return $this->fetch('/catalog/settings');
    }

    /**
     * @param array<string, mixed> $input any subset of the settings fields
     * @return array<string, mixed>
     */
    public function update(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', '/catalog/settings', $input, $options);
    }

    /** Sensible defaults per business type: retail | restaurant | services | wholesale. */
    public function applyPreset(string $preset, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/catalog/settings/preset', ['preset' => $preset], $options);
    }
}
