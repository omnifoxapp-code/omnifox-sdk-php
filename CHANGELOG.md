# Changelog

All notable changes to `omnifox/sdk` (PHP) are documented here. The version
number is kept aligned with the Node (`@omnifox/sdk`) and Python (`omnifox`)
SDKs, which share the same resource surface.

## 0.4.1 - 2026-10-02

First release of the PHP SDK, at parity with the Node and Python SDKs 0.4.1.

- 25 resources: contacts, conversations, messages, channels, broadcasts,
  customFields, tags, snippets, webhooks, workflows, users, workspaces, teams,
  companies, deals, pipelines, crmActivities, boards, boardItems, appointments,
  products, orders, catalogSettings, quotes, reports.
- PSR-18 transport with Guzzle 7 as the default; per-call timeout with Guzzle.
- Typed exceptions per status (401, 402, 403, 404, 409, 422, 429, 5xx),
  `NetworkException` and `InvalidArgumentException`, all implementing
  `OmnifoxException`.
- `Paginator` (lazy iterator) and `Page` understand the four listing envelopes
  of the API and fall back from cursor to page mode.
- Retries with exponential backoff and jitter on 429 / 5xx / connection errors,
  honouring `Retry-After`.
- Fixes carried over from the other SDKs: workspace sent as the `workspace_id`
  query param (the `X-Workspace-Id` header is ignored by the API) and in the
  body of the four endpoints that require it; contact merge uses
  `primary_contact_id` / `merged_contact_id`; assignment uses
  `assignee_type` + `assignee_id`; close uses `resolution` / `notes`;
  `{success, data}` unwrapped; DELETE with a body (tags by name); quote PDF
  returned as bytes; empty request bodies sent as `{}`; `video_options` on
  appointments; orders and catalog settings.
- Verified against production (`pruebas` organization): 212 calls, 0 failures.
