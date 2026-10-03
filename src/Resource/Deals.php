<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** CRM deals (`/crm/deals`). */
final class Deals extends AbstractResource
{
    /** @param array<string, mixed> $params pipeline_id, stage_id, contact_id, company_id, status, owner_user_id, mine */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/crm/deals', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/crm/deals', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/crm/deals/{$id}");
    }

    /**
     * @param array<string, mixed> $input `title` and `pipeline_id` are required.
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/crm/deals', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/crm/deals/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/crm/deals/{$id}", null, $options);
    }

    /** The Kanban move: another stage, optionally at a position. */
    public function moveToStage(int $id, int $stageId, ?int $position = null, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('PATCH', "/crm/deals/{$id}/stage", self::compact([
            'stage_id' => $stageId,
            'position' => $position,
        ]), $options);
    }

    /**
     * Close won or lost: `['result' => 'won']`, plus optional
     * `close_reason_id` / `close_reason_note`. The pipeline needs a stage
     * flagged is_won / is_lost, otherwise 422.
     *
     * @param array<string, mixed> $input
     */
    public function close(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/crm/deals/{$id}/close", $input, $options);
    }

    /** Activities, lifecycle changes and linked messages, merged. */
    public function timeline(int $id): mixed
    {
        return $this->fetch("/crm/deals/{$id}/timeline");
    }

    /** Deals grouped by stage with per-column totals: the Kanban payload. */
    public function board(int $pipelineId): mixed
    {
        return $this->fetch("/crm/pipelines/{$pipelineId}/board");
    }

    /**
     * Act on up to 500 deals: `['ids' => [...], 'action' => 'reassign|move_stage|close|delete', 'payload' => [...]]`.
     *
     * @param array<string, mixed> $input
     */
    public function bulk(array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/crm/deals/bulk', $input, $options);
    }
}
