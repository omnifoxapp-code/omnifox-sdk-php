<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\RequestOptions;

/** Calendar: calendars, services, free slots and appointments. */
final class Appointments extends AbstractResource
{
    /** @return list<array<string, mixed>> */
    public function listCalendars(): array
    {
        return $this->fetch('/calendar/calendars') ?? [];
    }

    /** Bookable service types with duration and buffers. @return list<array<string, mixed>> */
    public function listServices(): array
    {
        return $this->fetch('/calendar/services') ?? [];
    }

    /**
     * Free slots on a calendar.
     *
     * @param array<string, mixed> $input calendar_id, from, to (required), service_id
     */
    public function slots(array $input): mixed
    {
        return $this->fetch('/calendar/slots', $input);
    }

    /**
     * @param array<string, mixed> $params calendar_id, from, to
     * @return list<array<string, mixed>>
     */
    public function list(array $params = []): array
    {
        $rows = $this->fetch('/calendar/appointments', $params);
        // Tolerate a paginator-shaped answer.
        if (is_array($rows) && isset($rows['data']) && is_array($rows['data'])) {
            return $rows['data'];
        }

        return $rows ?? [];
    }

    /**
     * Book an appointment. A slot already taken answers 409
     * ({@see \Omnifox\Exception\ConflictException}).
     *
     * `location_type => 'video_omnifox'` books an Omnifox video meeting: the
     * response carries `video_guest_url`, `video_agent_url` and
     * `video_opens_at`. `video_options` tunes it:
     *   ['screen_share' => true, 'translate_mode' => 'off|text|voice',
     *    'host_lang' => 'es', 'guest_lang' => 'en', 'start_muted' => null]
     * The server never turns on what the workspace has switched off.
     *
     * @param array<string, mixed> $input calendar_id, starts_at (required), service_id, contact_id,
     *                                    assigned_user_id, conversation_id, deal_id, title, ends_at,
     *                                    duration_minutes, notes, location_type, location, invitees, video_options
     * @return array<string, mixed>
     */
    public function book(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/calendar/appointments', $input, $options);
    }

    /**
     * Passing `starts_at` reschedules (the collision check runs again).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/calendar/appointments/{$id}", $input, $options);
    }

    /** @return array<string, mixed> */
    public function cancel(int $id, ?string $reason = null, RequestOptions|array|null $options = null): array
    {
        // An empty body must go out as {} (PHP would encode [] otherwise).
        return $this->call('POST', "/calendar/appointments/{$id}/cancel", $reason !== null ? ['reason' => $reason] : new \stdClass(), $options);
    }
}
