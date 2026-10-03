<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Exception\InvalidArgumentException;
use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/**
 * CRM activities (call, meeting, note, email, task) logged against a
 * contact, company or deal.
 */
final class CrmActivities extends AbstractResource
{
    private const SUBJECTS = ['contact', 'company', 'deal'];

    /** @param array<string, mixed> $params type, open_only, ... */
    public function list(string $subject, int $subjectId, array $params = []): Paginator
    {
        return $this->paginate(self::path($subject, $subjectId), $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(string $subject, int $subjectId, array $params = []): Page
    {
        return $this->pageAt(self::path($subject, $subjectId), $params);
    }

    /**
     * @param array<string, mixed> $input `title` is required; type, body, user_id, due_at, completed_at
     * @return array<string, mixed>
     */
    public function create(string $subject, int $subjectId, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', self::path($subject, $subjectId), $input, $options);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/crm/activities/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/crm/activities/{$id}", $input, $options);
    }

    public function complete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/crm/activities/{$id}/complete", null, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/crm/activities/{$id}", null, $options);
    }

    private static function path(string $subject, int $subjectId): string
    {
        if (!in_array($subject, self::SUBJECTS, true)) {
            throw new InvalidArgumentException("crmActivities: subject must be one of contact, company, deal; got '{$subject}'");
        }

        return "/crm/{$subject}/{$subjectId}/activities";
    }
}
