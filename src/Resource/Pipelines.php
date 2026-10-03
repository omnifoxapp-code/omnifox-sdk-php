<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\RequestOptions;

/** CRM pipelines and their stages. */
final class Pipelines extends AbstractResource
{
    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return $this->fetch('/crm/pipelines') ?? [];
    }

    /** A pipeline with its stages in board order. @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/crm/pipelines/{$id}");
    }

    /**
     * @param array<string, mixed> $input ['name' => ...]
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/crm/pipelines', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/crm/pipelines/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/crm/pipelines/{$id}", null, $options);
    }

    /**
     * @param array<string, mixed> $input name (required), color, position, is_won, is_lost
     * @return array<string, mixed>
     */
    public function createStage(int $pipelineId, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/crm/pipelines/{$pipelineId}/stages", $input, $options);
    }

    /**
     * Stages are addressed globally once created, not under their pipeline.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateStage(int $stageId, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/crm/stages/{$stageId}", $input, $options);
    }

    public function deleteStage(int $stageId, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/crm/stages/{$stageId}", null, $options);
    }
}
