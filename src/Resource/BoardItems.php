<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\RequestOptions;

/**
 * Board rows. Note the URL shape: items are addressed FLAT at
 * `/boards/items/{id}`, not nested under their board; only creation goes
 * through the board.
 */
final class BoardItems extends AbstractResource
{
    /**
     * @param array<string, mixed> $input title, group_id, parent_item_id, contact_id, deal_id, values (keyed by column id)
     * @return array<string, mixed>
     */
    public function create(int $boardId, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/boards/{$boardId}/items", $input, $options);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/boards/items/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/boards/items/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/boards/items/{$id}", null, $options);
    }

    /** @return array<string, mixed> */
    public function duplicate(int $id, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/boards/items/{$id}/duplicate", null, $options);
    }

    /**
     * The row drag: another group, another position, or both.
     *
     * @param array<string, mixed> $input ['group_id' => ..., 'position' => ...]
     */
    public function move(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('PATCH', "/boards/items/{$id}/move", $input, $options);
    }

    /**
     * Write ONE cell. The value is validated against the column type; an
     * empty string clears the cell.
     */
    public function setCellValue(int $id, int $columnId, mixed $value, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('PUT', "/boards/items/{$id}/columns/{$columnId}", ['value' => $value], $options);
    }

    /** The comment feed of the row. @param array<string, mixed> $params */
    public function listUpdates(int $id, array $params = []): Page
    {
        return $this->pageAt("/boards/items/{$id}/updates", $params);
    }

    /**
     * @param array<string, mixed> $input body (required), mentions (user ids), parent_id
     */
    public function addUpdate(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/boards/items/{$id}/updates", $input, $options);
    }
}
