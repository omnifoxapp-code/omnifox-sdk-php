<?php

declare(strict_types=1);

namespace Omnifox\Resource;

/**
 * Reports. Every method takes `['preset' => 'today|yesterday|7d|30d|90d|this_month|last_month']`
 * or `['from' => 'Y-m-d', 'to' => 'Y-m-d']`; the preset wins. Default: last 30 days.
 */
final class Reports extends AbstractResource
{
    /** Headline stats plus their trend against the previous period. @param array<string, mixed> $params */
    public function overview(array $params = []): mixed
    {
        return $this->fetch('/reports/overview', $params);
    }

    /** By channel, by status and message volume. @param array<string, mixed> $params */
    public function conversations(array $params = []): mixed
    {
        return $this->fetch('/reports/conversations', $params);
    }

    /** @param array<string, mixed> $params */
    public function agents(array $params = []): mixed
    {
        return $this->fetch('/reports/agents', $params);
    }

    /** Message volume by weekday and hour. @param array<string, mixed> $params */
    public function heatmap(array $params = []): mixed
    {
        return $this->fetch('/reports/heatmap', $params);
    }

    /** Per-stage funnel and conversion. CRM-gated. @param array<string, mixed> $params */
    public function crmFunnel(array $params = []): mixed
    {
        return $this->fetch('/crm/reports/funnel', $params);
    }

    /** @param array<string, mixed> $params */
    public function crmForecast(array $params = []): mixed
    {
        return $this->fetch('/crm/reports/forecast', $params);
    }

    /** @param array<string, mixed> $params */
    public function crmWinLossReasons(array $params = []): mixed
    {
        return $this->fetch('/crm/reports/win-loss-reasons', $params);
    }
}
