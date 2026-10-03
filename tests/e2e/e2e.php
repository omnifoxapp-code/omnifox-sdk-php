<?php

/**
 * End-to-end exercise of omnifox/sdk against a LIVE workspace.
 *
 * Read calls always run; mutating flows need --write and delete whatever they
 * create (a cleanup pass at the end catches anything a failed step left).
 *
 *   OMNIFOX_TOKEN=... OMNIFOX_WORKSPACE_ID=1 \
 *   OMNIFOX_BASE_URL=https://app.omnifox.io/api/v1 \
 *   OMNIFOX_E2E_WORKFLOW_ID=12 OMNIFOX_E2E_BROADCAST_ID=1 \
 *   php tests/e2e/e2e.php --write [--logout]
 *
 * --logout revokes the token as the very last call (users->logout), which is
 * also how a throwaway e2e token cleans itself up.
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Omnifox\Exception\ApiException;
use Omnifox\Exception\AuthenticationException;
use Omnifox\OmnifoxClient;
use Omnifox\Pagination\Page;

$token = getenv('OMNIFOX_TOKEN') ?: '';
$baseUrl = getenv('OMNIFOX_BASE_URL') ?: 'https://app.omnifox.io/api/v1';
$workspaceId = (int) (getenv('OMNIFOX_WORKSPACE_ID') ?: 1);
$write = in_array('--write', $argv, true);
$logout = in_array('--logout', $argv, true);

if ($token === '') {
    fwrite(STDERR, "OMNIFOX_TOKEN is required\n");
    exit(2);
}

$client = new OmnifoxClient([
    'apiKey' => $token,
    'workspaceId' => $workspaceId,
    'baseUrl' => $baseUrl,
    'maxRetries' => 1,
]);

$results = [];
$cleanup = [];

/**
 * `expect` = a status the server is RIGHT to answer for the state we can
 * build; it counts as a pass only when the status matches.
 */
$step = function (string $label, callable $fn, ?int $expect = null, string $why = '') use (&$results) {
    $t = microtime(true);
    try {
        $value = $fn();
        $detail = match (true) {
            $value instanceof Page => count($value) . ' item(s) [page]',
            is_array($value) => (array_is_list($value) ? count($value) . ' item(s)' : 'object'),
            is_object($value) => get_class($value),
            default => gettype($value),
        };
        $results[] = ['label' => $label, 'status' => 'ok', 'detail' => $detail, 'ms' => (int) ((microtime(true) - $t) * 1000)];

        return $value;
    } catch (ApiException $e) {
        if ($expect !== null && $expect === $e->getStatusCode()) {
            $results[] = ['label' => $label, 'status' => 'expected', 'detail' => $e->getStatusCode() . ' ' . $e->getMessage(), 'note' => $why];

            return null;
        }
        $results[] = ['label' => $label, 'status' => 'FAIL', 'detail' => get_class($e) . ' ' . $e->getStatusCode() . ' ' . $e->getMessage(),
            'body' => substr(json_encode($e->getBody()) ?: '', 0, 300)];

        return null;
    } catch (\Throwable $e) {
        $results[] = ['label' => $label, 'status' => 'FAIL', 'detail' => get_class($e) . ': ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()];

        return null;
    }
};

/** Did the last step pass? Used to clear the cleanup entry of a deleted record. */
$passed = function () use (&$results): bool {
    return ($results[array_key_last($results)]['status'] ?? '') === 'ok';
};

$stamp = (string) time();

echo "php sdk e2e against {$baseUrl} (workspace {$workspaceId}), write=" . ($write ? 'true' : 'false') . "\n";

// ── users ────────────────────────────────────────────────────────────────
$step('users.me', fn () => $client->users->me());
$agents = $step('users.listAgents', fn () => $client->users->listAgents()->toArray(25));
$step('users.listAgentsPage', fn () => $client->users->listAgentsPage(['limit' => 5]));
$agentId = $agents[0]['id'] ?? null;
$agent = $agentId ? $step('users.getAgent', fn () => $client->users->getAgent($agentId)) : null;

// ── catalogues ───────────────────────────────────────────────────────────
$channels = $step('channels.list', fn () => $client->channels->list()->toArray(25));
$channelId = $channels[0]['id'] ?? null;
if ($channelId) {
    $step('channels.get', fn () => $client->channels->get($channelId));
    $step('channels.health', fn () => $client->channels->health($channelId));
    $step('channels.templates', fn () => $client->channels->templates($channelId));
}
$step('channels.listPage', fn () => $client->channels->listPage(['limit' => 5]));

$tags = $step('tags.list', fn () => $client->tags->list()->toArray(25));
if (!empty($tags[0]['id'])) {
    $step('tags.get', fn () => $client->tags->get($tags[0]['id']));
}
$step('tags.listPage', fn () => $client->tags->listPage(['limit' => 5]));

$teams = $step('teams.list', fn () => $client->teams->list()->toArray(25));
if (!empty($teams[0]['id'])) {
    $step('teams.get', fn () => $client->teams->get($teams[0]['id']));
}
$step('teams.listPage', fn () => $client->teams->listPage());

$step('snippets.list', fn () => $client->snippets->list()->toArray(25));
$step('snippets.listPage', fn () => $client->snippets->listPage());
$step('customFields.list', fn () => $client->customFields->list()->toArray(25));
$step('customFields.listPage', fn () => $client->customFields->listPage());
$step('workspaces.list', fn () => $client->workspaces->list()->toArray(25));
$step('workspaces.listPage', fn () => $client->workspaces->listPage());
$workspace = $step('workspaces.get', fn () => $client->workspaces->get($workspaceId));
$step('broadcasts.list', fn () => $client->broadcasts->list()->toArray(25));
$step('broadcasts.listPage', fn () => $client->broadcasts->listPage());
$step('webhooks.list', fn () => $client->webhooks->list()->toArray(25));
$step('webhooks.listPage', fn () => $client->webhooks->listPage());

$workflows = $step('workflows.list', fn () => $client->workflows->list()->toArray(25));
$step('workflows.listPage', fn () => $client->workflows->listPage());
$workflowId = (int) (getenv('OMNIFOX_E2E_WORKFLOW_ID') ?: 0) ?: null;
if ($workflowId || !empty($workflows[0]['id'])) {
    $step('workflows.get', fn () => $client->workflows->get($workflowId ?? $workflows[0]['id']));
}

// ── inbox reads ──────────────────────────────────────────────────────────
$contacts = $step('contacts.list', fn () => $client->contacts->list()->toArray(25));
$step('contacts.listPage', fn () => $client->contacts->listPage(['limit' => 5]));
$step('contacts.list (iterates >1 page)', function () use ($client) {
    $seen = $client->contacts->list(['limit' => 2, 'per_page' => 2])->toArray(5);
    $ids = array_column($seen, 'id');
    if (count($ids) !== count(array_unique($ids))) {
        throw new RuntimeException('pagination returned duplicates: ' . implode(',', $ids));
    }

    return $seen;
});
$step('contacts.search', fn () => $client->contacts->search('te', ['limit' => 5]));
$step('contacts.listViaDsl', fn () => $client->contacts->listViaDsl(['limit' => 5]));
$contactId = $contacts[0]['id'] ?? null;
if ($contactId) {
    $step('contacts.get', fn () => $client->contacts->get($contactId));
    $step('contacts.channels', fn () => $client->contacts->channels($contactId));
}

$conversations = $step('conversations.list', fn () => $client->conversations->list()->toArray(25));
$step('conversations.listPage', fn () => $client->conversations->listPage(['limit' => 5]));
$conversationId = $conversations[0]['id'] ?? null;
if ($conversationId) {
    $step('conversations.get', fn () => $client->conversations->get($conversationId));
    $step('conversations.windowStatus', fn () => $client->conversations->windowStatus($conversationId));
    $msgs = $step('conversations.listMessages', fn () => $client->conversations->listMessages($conversationId, ['limit' => 5]));
    $messageId = $msgs?->data[0]['id'] ?? null;
    if ($messageId) {
        $step('conversations.getMessage', fn () => $client->conversations->getMessage($conversationId, $messageId));
    }
}

if ($write) {
    echo "\n# write flows\n";

    $tag = $step('tags.create', fn () => $client->tags->create(['name' => "sdk-php-e2e-{$stamp}", 'color' => '#7C3AED']));
    if (!empty($tag['id'])) {
        $cleanup[] = fn () => $client->tags->delete($tag['id']);
        $step('tags.update', fn () => $client->tags->update($tag['id'], ['description' => 'edited by php e2e']));
        $step('tags.delete', fn () => $client->tags->delete($tag['id']));
        if ($passed()) {
            array_pop($cleanup);
        }
    }

    $snippet = $step('snippets.create', fn () => $client->snippets->create(['name' => "sdk-php-e2e-{$stamp}", 'content_text' => 'Hola desde el SDK PHP']));
    if (!empty($snippet['id'])) {
        $step('snippets.get', fn () => $client->snippets->get($snippet['id']));
        $step('snippets.update', fn () => $client->snippets->update($snippet['id'], ['shortcut' => "/phpe2e{$stamp}"]));
        $step('snippets.delete', fn () => $client->snippets->delete($snippet['id']));
    }

    $field = $step('customFields.create', fn () => $client->customFields->create([
        'key' => "php_e2e_{$stamp}", 'label' => "PHP E2E {$stamp}", 'type' => 'text', 'entity_type' => 'contact',
    ]));
    if (!empty($field['id'])) {
        $step('customFields.get', fn () => $client->customFields->get($field['id']));
        $step('customFields.update', fn () => $client->customFields->update($field['id'], ['label' => 'PHP E2E edited']));
        $step('customFields.delete', fn () => $client->customFields->delete($field['id']));
    }

    $team = $step('teams.create', fn () => $client->teams->create(['name' => "SDK PHP e2e {$stamp}", 'assignment_strategy' => 'round_robin']));
    if (!empty($team['id'])) {
        $step('teams.update', fn () => $client->teams->update($team['id'], ['description' => 'edited by php e2e']));
        if ($agentId) {
            $step('teams.addMember', fn () => $client->teams->addMember($team['id'], $agentId));
            $step('teams.removeMember', fn () => $client->teams->removeMember($team['id'], $agentId));
        }
        $step('teams.delete', fn () => $client->teams->delete($team['id']));
    }

    $hook = $step('webhooks.create', fn () => $client->webhooks->create([
        'name' => "SDK PHP e2e {$stamp}", 'url' => 'https://example.com/omnifox-php-e2e', 'events' => ['message.received'],
    ]));
    if (!empty($hook['id'])) {
        $step('webhooks.get', fn () => $client->webhooks->get($hook['id']));
        $step('webhooks.update', fn () => $client->webhooks->update($hook['id'], ['is_active' => false]));
        $step('webhooks.rotateSecret', fn () => $client->webhooks->rotateSecret($hook['id']));
        $step('webhooks.delete', fn () => $client->webhooks->delete($hook['id']));
    }

    // Workspace rename and back (the description is restored verbatim).
    if (is_array($workspace) && isset($workspace['name'])) {
        $origName = $workspace['name'];
        $step('workspaces.update', fn () => $client->workspaces->update($workspaceId, ['name' => $origName . ' (php e2e)']));
        $step('workspaces.update (restore)', function () use ($client, $workspaceId, $origName) {
            $ws = $client->workspaces->update($workspaceId, ['name' => $origName]);
            if (($ws['name'] ?? null) !== $origName) {
                throw new RuntimeException('workspace name not restored');
            }

            return $ws;
        });
    }

    // Agent status: set and restore.
    if ($agentId) {
        // 'away' passes validation but the column enum lacks it (server 500): use available/busy/offline.
        $origStatus = $agent['status'] ?? 'offline';
        if (!in_array($origStatus, ['available', 'busy', 'offline'], true)) {
            $origStatus = 'offline';
        }
        $step('users.setAgentStatus', fn () => $client->users->setAgentStatus($agentId, $origStatus === 'busy' ? 'available' : 'busy'));
        $step('users.setAgentStatus (restore)', fn () => $client->users->setAgentStatus($agentId, ['status' => $origStatus]));
    }

    // Contacts + conversations: the full parity surface.
    $contact = $step('contacts.upsert', fn () => $client->contacts->upsert([
        'unique_by' => ['email'], 'email' => "sdk-php-e2e-{$stamp}@example.com", 'first_name' => 'SDK', 'last_name' => 'PHP',
    ]));
    $newContactId = $contact['id'] ?? null;
    $created = $step('contacts.create', fn () => $client->contacts->create([
        'display_name' => 'SDK PHP Duplicate', 'first_name' => 'SDK', 'last_name' => 'Duplicate',
        'email' => "sdk-php-e2e-{$stamp}-dupe@example.com",
    ]));
    if (!empty($created['id'])) {
        $cleanup['dupe'] = fn () => $client->contacts->delete($created['id']);
    }

    if ($newContactId) {
        $cleanup['contact'] = fn () => $client->contacts->delete($newContactId);
        $step('contacts.get (by email selector)', function () use ($client, $stamp, $newContactId) {
            $c = $client->contacts->get("email:sdk-php-e2e-{$stamp}@example.com");
            if ((int) ($c['id'] ?? 0) !== (int) $newContactId) {
                throw new RuntimeException('selector resolved to another contact');
            }

            return $c;
        });
        $step('contacts.update', fn () => $client->contacts->update($newContactId, ['job_title' => 'QA']));
        $step('contacts.attachTagsByName', fn () => $client->contacts->attachTagsByName($newContactId, ['names' => ['sdk-php-e2e']]));
        $attached = $step('contacts.attachTag', function () use ($client, $newContactId) {
            $found = null;
            foreach ($client->tags->list() as $t) {
                if (($t['name'] ?? null) === 'sdk-php-e2e') {
                    $found = $t;
                    break;
                }
            }
            if ($found === null) {
                throw new RuntimeException('tag sdk-php-e2e not found after attachTagsByName');
            }
            $client->contacts->attachTag($newContactId, (int) $found['id']);

            return $found;
        });
        if (!empty($attached['id'])) {
            $step('contacts.detachTag', fn () => $client->contacts->detachTag($newContactId, (int) $attached['id']));
        }
        $step('contacts.detachTagsByName', fn () => $client->contacts->detachTagsByName($newContactId, ['sdk-php-e2e']));
        if (!empty($attached['id'])) {
            $step('tags.delete (sdk-php-e2e)', fn () => $client->tags->delete((int) $attached['id']));
        }

        if ($channelId) {
            $conv = $step('conversations.create', fn () => $client->conversations->create(['contact_id' => $newContactId, 'channel_id' => $channelId]));
            $convId = $conv['id'] ?? null;
            if ($convId) {
                $step('conversations.addNote', fn () => $client->conversations->addNote($convId, ['text' => 'Nota desde el SDK PHP']));
                $sent = $step('conversations.sendMessage', fn () => $client->conversations->sendMessage($convId, ['type' => 'note', 'content_text' => 'Mensaje interno desde el SDK PHP']));
                if (!empty($sent['id'])) {
                    $step('conversations.getMessage (own)', fn () => $client->conversations->getMessage($convId, (int) $sent['id']));
                }
                if ($agentId) {
                    $step('conversations.assign', fn () => $client->conversations->assign($convId, ['assignee_type' => 'user', 'assignee_id' => $agentId]));
                    $step('conversations.assign (legacy shape)', fn () => $client->conversations->assign($convId, ['assigned_user_id' => $agentId]));
                    $step('conversations.assign (unassign)', fn () => $client->conversations->assign($convId, []));
                }
                $step('contacts.comment', fn () => $client->contacts->comment($newContactId, ['text' => 'Comentario desde el SDK PHP']));
                $step('contacts.setConversationStatus', fn () => $client->contacts->setConversationStatus($newContactId, ['status' => 'resolved']));
                $step('contacts.openConversation', fn () => $client->contacts->openConversation($newContactId));
                $step('contacts.assignConversation', fn () => $client->contacts->assignConversation($newContactId, ['assignee' => null]));
                $cmsgs = $step('contacts.listMessages', fn () => $client->contacts->listMessages($newContactId, ['limit' => 5]));
                $sentC = $step('contacts.sendMessage', fn () => $client->contacts->sendMessage($newContactId, [
                    'channelId' => $channelId, 'message' => ['type' => 'text', 'text' => 'Hola desde el envelope de contacto (PHP)'],
                ]));
                $cMsgId = $sentC['messageId'] ?? $sentC['id'] ?? ($cmsgs?->data[0]['id'] ?? null);
                if ($cMsgId) {
                    $step('contacts.getMessage', fn () => $client->contacts->getMessage($newContactId, (int) $cMsgId));
                }
                $step('messages.send', fn () => $client->messages->send(['contact' => $newContactId, 'channel_id' => $channelId, 'text' => 'Hola desde messages->send (PHP)']));
                $step('conversations.close', fn () => $client->conversations->close($convId, ['resolution' => 'resolved', 'notes' => 'cerrada por el e2e PHP']));
                $step('conversations.reopen', fn () => $client->conversations->reopen($convId));
                $step('contacts.closeConversation', fn () => $client->contacts->closeConversation($newContactId));
            }
        }

        if (!empty($created['id'])) {
            $step('contacts.merge', fn () => $client->contacts->merge(['primary_contact_id' => $newContactId, 'merged_contact_id' => $created['id']]));
            if ($passed()) {
                $cleanup['dupe'] = null;
            }
        }
        $step('contacts.delete', fn () => $client->contacts->delete($newContactId));
        if ($passed()) {
            $cleanup['contact'] = null;
        }
    }

    if ($workflowId) {
        $step('workflows.activate', fn () => $client->workflows->activate($workflowId));
        $step('workflows.trigger', fn () => $client->workflows->trigger($workflowId, ['context' => ['source' => 'sdk-php-e2e'], 'force' => true]));
        $step('workflows.deactivate', fn () => $client->workflows->deactivate($workflowId));
    }

    // ── CRM ──────────────────────────────────────────────────────────────
    echo "# crm\n";
    $step('pipelines.list', fn () => $client->pipelines->list());
    $step('companies.list', fn () => $client->companies->list()->toArray(25));
    $step('companies.listPage', fn () => $client->companies->listPage());
    $step('deals.list', fn () => $client->deals->list()->toArray(25));
    $step('deals.listPage', fn () => $client->deals->listPage());

    $pipeline = $step('pipelines.create', fn () => $client->pipelines->create(['name' => "SDK PHP e2e {$stamp}"]));
    $stageId = null;
    if (!empty($pipeline['id'])) {
        $cleanup['pipeline'] = fn () => $client->pipelines->delete($pipeline['id']);
        $step('pipelines.get', fn () => $client->pipelines->get($pipeline['id']));
        $stage = $step('pipelines.createStage', fn () => $client->pipelines->createStage($pipeline['id'], ['name' => 'Qualified', 'is_won' => true]));
        $stageId = $stage['id'] ?? null;
        $stage2 = $step('pipelines.createStage (second)', fn () => $client->pipelines->createStage($pipeline['id'], ['name' => 'Lost', 'is_lost' => true]));
        if ($stageId) {
            $step('pipelines.updateStage', fn () => $client->pipelines->updateStage($stageId, ['name' => 'Qualified (renamed)']));
        }
        $step('pipelines.update', fn () => $client->pipelines->update($pipeline['id'], ['name' => "SDK PHP e2e {$stamp} (renamed)"]));
        $step('deals.board', fn () => $client->deals->board($pipeline['id']));
    }

    $company = $step('companies.create', fn () => $client->companies->create(['name' => "ACME SDK PHP {$stamp}", 'domain' => "acme-sdk-php-{$stamp}.test"]));
    if (!empty($company['id'])) {
        $cleanup['company'] = fn () => $client->companies->delete($company['id']);
        $step('companies.get', fn () => $client->companies->get($company['id']));
        $step('companies.update', fn () => $client->companies->update($company['id'], ['industry' => 'Testing']));
        if ($contactId) {
            $step('companies.attachContact', fn () => $client->companies->attachContact($company['id'], $contactId));
            $step('companies.detachContact', fn () => $client->companies->detachContact($company['id'], $contactId));
        }
    }

    $dealId = null;
    if (!empty($pipeline['id'])) {
        $deal = $step('deals.create', fn () => $client->deals->create(array_filter([
            'title' => "Deal SDK PHP {$stamp}", 'pipeline_id' => $pipeline['id'], 'amount' => 1500, 'currency' => 'USD',
            'company_id' => $company['id'] ?? null,
        ])));
        $dealId = $deal['id'] ?? null;
    }
    if ($dealId) {
        $cleanup['deal'] = fn () => $client->deals->delete($dealId);
        $step('deals.get', fn () => $client->deals->get($dealId));
        $step('deals.update', fn () => $client->deals->update($dealId, ['amount' => 2500]));
        $step('deals.timeline', fn () => $client->deals->timeline($dealId));
        if ($stageId) {
            $step('deals.moveToStage', fn () => $client->deals->moveToStage($dealId, $stageId, 0));
        }
        $activity = $step('crmActivities.create', fn () => $client->crmActivities->create('deal', $dealId, ['title' => 'Follow-up', 'type' => 'call']));
        if (!empty($activity['id'])) {
            $step('crmActivities.list', fn () => $client->crmActivities->list('deal', $dealId)->toArray());
            $step('crmActivities.listPage', fn () => $client->crmActivities->listPage('deal', $dealId));
            $step('crmActivities.get', fn () => $client->crmActivities->get($activity['id']));
            $step('crmActivities.update', fn () => $client->crmActivities->update($activity['id'], ['title' => 'Follow-up (edited)']));
            $step('crmActivities.complete', fn () => $client->crmActivities->complete($activity['id']));
            $step('crmActivities.delete', fn () => $client->crmActivities->delete($activity['id']));
        }
        $step('deals.close', fn () => $client->deals->close($dealId, ['result' => 'won']));
        $step('deals.bulk', fn () => $client->deals->bulk(['ids' => [$dealId], 'action' => 'reassign', 'payload' => ['owner_user_id' => $agentId]]));
        $step('deals.delete', fn () => $client->deals->delete($dealId));
        if ($passed()) {
            $cleanup['deal'] = null;
        }
    }
    if (!empty($company['id'])) {
        $step('companies.delete', fn () => $client->companies->delete($company['id']));
        if ($passed()) {
            $cleanup['company'] = null;
        }
    }
    if (!empty($stage2['id'])) {
        // A pipeline must keep at least one stage, so the second one is the one deleted.
        $step('pipelines.deleteStage', fn () => $client->pipelines->deleteStage((int) $stage2['id']));
    }
    if (!empty($pipeline['id'])) {
        $step('pipelines.delete', fn () => $client->pipelines->delete($pipeline['id']));
        if ($passed()) {
            $cleanup['pipeline'] = null;
        }
    }

    // ── Boards ───────────────────────────────────────────────────────────
    echo "# boards\n";
    $step('boards.list', fn () => $client->boards->list());
    $board = $step('boards.create', fn () => $client->boards->create(['name' => "Board SDK PHP {$stamp}", 'template' => 'blank']));
    if (!empty($board['id'])) {
        $cleanup['board'] = fn () => $client->boards->delete($board['id']);
        $step('boards.get', fn () => $client->boards->get($board['id']));
        $step('boards.view', fn () => $client->boards->view($board['id']));
        $step('boards.activity', fn () => $client->boards->activity($board['id'], ['limit' => 5]));
        $step('boards.update', fn () => $client->boards->update($board['id'], ['color' => '#0EA5E9']));
        $group = $step('boards.createGroup', fn () => $client->boards->createGroup($board['id'], ['name' => 'Sprint 1']));
        $columns = $step('boards.columns', fn () => $client->boards->columns($board['id']));
        $textColumn = null;
        foreach ($columns ?? [] as $c) {
            if (($c['type'] ?? null) === 'text') {
                $textColumn = $c;
                break;
            }
        }
        $item = $step('boardItems.create', fn () => $client->boardItems->create($board['id'], array_filter([
            'title' => "Task SDK PHP {$stamp}", 'group_id' => $group['id'] ?? null,
        ])));
        if (!empty($item['id'])) {
            $step('boardItems.get', fn () => $client->boardItems->get($item['id']));
            $step('boardItems.update', fn () => $client->boardItems->update($item['id'], ['title' => 'Task (edited)']));
            if (!empty($group['id'])) {
                $step('boardItems.move', fn () => $client->boardItems->move($item['id'], ['group_id' => $group['id'], 'position' => 0]));
            }
            if ($textColumn) {
                $step('boardItems.setCellValue', fn () => $client->boardItems->setCellValue($item['id'], (int) $textColumn['id'], 'written by the php sdk'));
            }
            $step('boardItems.addUpdate', fn () => $client->boardItems->addUpdate($item['id'], ['body' => 'Comment from the PHP SDK']));
            $step('boardItems.listUpdates', fn () => $client->boardItems->listUpdates($item['id'], ['limit' => 5]));
            $copy = $step('boardItems.duplicate', fn () => $client->boardItems->duplicate($item['id']));
            if (!empty($copy['id'])) {
                $step('boardItems.delete (copy)', fn () => $client->boardItems->delete($copy['id']));
            }
            $step('boardItems.delete', fn () => $client->boardItems->delete($item['id']));
        }
        $dup = $step('boards.duplicate', fn () => $client->boards->duplicate($board['id']));
        if (!empty($dup['id'])) {
            $step('boards.delete (copy)', fn () => $client->boards->delete($dup['id']));
        }
        $step('boards.delete', fn () => $client->boards->delete($board['id']));
        if ($passed()) {
            $cleanup['board'] = null;
        }
    }

    // ── Calendar ─────────────────────────────────────────────────────────
    echo "# calendar\n";
    $calendars = $step('appointments.listCalendars', fn () => $client->appointments->listCalendars());
    $step('appointments.listServices', fn () => $client->appointments->listServices());
    $step('appointments.list', fn () => $client->appointments->list());
    $calendarId = $calendars[0]['id'] ?? null;
    if ($calendarId) {
        $step('appointments.slots', fn () => $client->appointments->slots([
            'calendar_id' => $calendarId,
            'from' => gmdate('Y-m-d\TH:i:s\Z'),
            'to' => gmdate('Y-m-d\TH:i:s\Z', time() + 7 * 86400),
        ]));
        $startsAt = gmdate('Y-m-d\T16:00:00\Z', time() + (3 + random_int(0, 20)) * 86400);
        $appt = $step('appointments.book', fn () => $client->appointments->book([
            'calendar_id' => $calendarId, 'starts_at' => $startsAt, 'title' => "Appt SDK PHP {$stamp}", 'duration_minutes' => 30,
        ]));
        if (!empty($appt['id'])) {
            $cleanup['appt'] = fn () => $client->appointments->cancel($appt['id'], 'php e2e cleanup');
            $step('appointments.update', fn () => $client->appointments->update($appt['id'], ['title' => 'Appt (edited)']));
            $step('appointments.cancel', fn () => $client->appointments->cancel($appt['id'], 'php sdk cleanup'));
        if ($passed()) {
            $cleanup['appt'] = null;
        }
        }
        // video_options travels with an Omnifox video meeting (no reason, empty body => {}).
        $startsAt2 = gmdate('Y-m-d\T17:00:00\Z', time() + (3 + random_int(0, 20)) * 86400);
        $video = $step('appointments.book (video_omnifox + video_options)', fn () => $client->appointments->book([
            'calendar_id' => $calendarId, 'starts_at' => $startsAt2, 'title' => "Video SDK PHP {$stamp}", 'duration_minutes' => 30,
            'location_type' => 'video_omnifox',
            'video_options' => ['screen_share' => true, 'translate_mode' => 'off', 'start_muted' => null],
        ]));
        if (!empty($video['id'])) {
            $step('appointments.cancel (no reason)', fn () => $client->appointments->cancel($video['id']));
        }
    }

    // ── Catalog: products, quotes, orders, settings ──────────────────────
    echo "# catalog\n";
    $step('products.list', fn () => $client->products->list()->toArray(25));
    $step('products.listPage', fn () => $client->products->listPage());
    $step('quotes.list', fn () => $client->quotes->list()->toArray(25));
    $step('quotes.listPage', fn () => $client->quotes->listPage());
    $step('quotes.billingEntities', fn () => $client->quotes->billingEntities());
    $product = $step('products.create', fn () => $client->products->create([
        'name' => "Widget SDK PHP {$stamp}", 'unit_price' => 99, 'sku' => "SDKPHP-{$stamp}", 'is_orderable' => true,
    ]));
    $quoteIds = [];
    if (!empty($product['id'])) {
        $cleanup['product'] = fn () => $client->products->delete($product['id']);
        $step('products.get', fn () => $client->products->get($product['id']));
        $step('products.update', fn () => $client->products->update($product['id'], ['unit_price' => 120]));
        foreach (['a', 'b'] as $k) {
            $q = $step("quotes.create ({$k})", fn () => $client->quotes->create([
                'currency' => 'USD', 'notes' => 'Created by the PHP SDK e2e run',
                'items' => [['product_id' => $product['id'], 'quantity' => 2, 'unit_price' => 120, 'tax_rate' => 15]],
            ]));
            if (!empty($q['id'])) {
                $quoteIds[$k] = (int) $q['id'];
                $cleanup["quote{$k}"] = fn () => $client->quotes->delete((int) $q['id']);
            }
        }
    }
    if (isset($quoteIds['a'])) {
        $qa = $quoteIds['a'];
        $step('quotes.get', fn () => $client->quotes->get($qa));
        $step('quotes.update', fn () => $client->quotes->update($qa, ['notes' => 'Edited']));
        $step('quotes.pdf', function () use ($client, $qa) {
            $pdf = $client->quotes->pdf($qa);
            if (!str_contains($pdf->contentType, 'pdf') || !str_starts_with($pdf->data, '%PDF')) {
                throw new RuntimeException('expected a PDF, got ' . $pdf->contentType);
            }

            return $pdf;
        });
        $step('quotes.send (dry run)', fn () => $client->quotes->send($qa, ['channels' => ['email' => true], 'dry_run' => true]));
        $step('quotes.markViewed', fn () => $client->quotes->markViewed($qa));
        $step('quotes.accept', fn () => $client->quotes->accept($qa));
        $step('quotes.delete', fn () => $client->quotes->delete($qa));
        if ($passed()) {
            $cleanup['quotea'] = null;
        }
    }
    if (isset($quoteIds['b'])) {
        $qb = $quoteIds['b'];
        $step('quotes.reject', fn () => $client->quotes->reject($qb));
        $step('quotes.delete (b)', fn () => $client->quotes->delete($qb));
        if ($passed()) {
            $cleanup['quoteb'] = null;
        }
    }

    // Orders: amounts are priced by the server; we only send product + quantity.
    $step('orders.list', fn () => $client->orders->list()->toArray(25));
    $step('orders.listPage', fn () => $client->orders->listPage());
    if (!empty($product['id'])) {
        $order = $step('orders.create', fn () => $client->orders->create([
            // No unit_price: the server prices the line from the catalog (an explicit unit_price would override it).
            'items' => [['product_id' => $product['id'], 'quantity' => 2]],
            'fulfillment' => 'pickup', 'customer_name' => 'SDK PHP e2e', 'notes' => 'php e2e',
        ]));
        if (!empty($order['id'])) {
            $oid = (int) $order['id'];
            $cleanup['order'] = fn () => $client->orders->delete($oid);
            $step('orders.create priced by server', function () use ($order) {
                $line = $order['items'][0] ?? [];
                if ((float) ($line['unit_price'] ?? 0) !== 120.0) {
                    throw new RuntimeException('server did not price the line from the catalog: ' . json_encode($line));
                }

                return $order;
            });
            $step('orders.get', fn () => $client->orders->get($oid));
            $step('orders.update', fn () => $client->orders->update($oid, ['notes' => 'edited by php e2e']));
            $step('orders.setStatus', fn () => $client->orders->setStatus($oid, 'confirmed'));
            $step('orders.recordPayment', fn () => $client->orders->recordPayment($oid, 10, 'cash'));
            $step('orders.setStatus (illegal -> 422)', fn () => $client->orders->setStatus($oid, 'nonsense'), 422, 'invalid status is refused');
            $step('orders.setStatus (cancel)', fn () => $client->orders->setStatus($oid, 'cancelled', 'php e2e cleanup'));
            $step('orders.delete', fn () => $client->orders->delete($oid));
        if ($passed()) {
            $cleanup['order'] = null;
        }
        }
    }

    if (!empty($product['id'])) {
        $step('products.delete', fn () => $client->products->delete($product['id']));
        if ($passed()) {
            $cleanup['product'] = null;
        }
    }

    $settings = $step('catalogSettings.get', fn () => $client->catalogSettings->get());
    $before = $settings['settings'] ?? null;
    if (is_array($before)) {
        $step('catalogSettings.update', fn () => $client->catalogSettings->update(['number_prefix' => $before['number_prefix']]));
        $step('catalogSettings.applyPreset', fn () => $client->catalogSettings->applyPreset($before['business_preset'] ?? 'retail'));
        // Put back every field the preset changed.
        $step('catalogSettings.update (restore)', function () use ($client, $before) {
            $after = $client->catalogSettings->get()['settings'] ?? [];
            $restorable = ['document_flow', 'edit_policy', 'edit_window_minutes', 'requires_confirmation', 'auto_confirm_after_minutes',
                'auto_cancel_after_minutes', 'tax_mode', 'tax_rate', 'prices_include_tax', 'fulfillment_modes', 'delivery_fee',
                'free_delivery_from', 'min_order_total', 'required_customer_fields', 'stock_mode', 'block_out_of_stock',
                'orders_enabled', 'ai_can_create_orders', 'create_crm_deal', 'create_board_item', 'prep_time_minutes',
                'accept_orders_when_closed', 'cart_abandoned_after_minutes', 'meta_cart_mode'];
            $diff = [];
            foreach ($restorable as $k) {
                if (array_key_exists($k, $before) && ($after[$k] ?? null) != $before[$k]) {
                    $diff[$k] = $before[$k];
                }
            }

            return $diff === [] ? ['unchanged' => true] : $client->catalogSettings->update($diff);
        });
    }

    // ── Reports ──────────────────────────────────────────────────────────
    echo "# reports\n";
    foreach (['overview', 'conversations', 'agents', 'heatmap', 'crmFunnel', 'crmForecast', 'crmWinLossReasons'] as $name) {
        $step("reports.{$name}", fn () => $client->reports->{$name}(['preset' => '30d']));
    }

    $broadcastId = (int) (getenv('OMNIFOX_E2E_BROADCAST_ID') ?: 0);
    if ($broadcastId) {
        $step('broadcasts.get', fn () => $client->broadcasts->get($broadcastId));
        $step('broadcasts.cancel', fn () => $client->broadcasts->cancel($broadcastId));
    }

    // ── typed errors ─────────────────────────────────────────────────────
    echo "# errors\n";
    $step('NotFoundException on a missing contact', function () use ($client) {
        try {
            $client->contacts->get(999999999);
        } catch (\Omnifox\Exception\NotFoundException $e) {
            return ['status' => $e->getStatusCode()];
        }
        throw new RuntimeException('expected NotFoundException');
    });
    $step('ValidationException with field errors', function () use ($client) {
        try {
            $client->contacts->create([]);
        } catch (\Omnifox\Exception\ValidationException $e) {
            if ($e->getFieldErrors() === []) {
                throw new RuntimeException('no field errors: ' . json_encode($e->getBody()));
            }

            return $e->getFieldErrors();
        }
        throw new RuntimeException('expected ValidationException');
    });
}

// Best-effort cleanup of whatever a failed step left behind.
foreach (array_filter($cleanup) as $key => $fn) {
    try {
        $fn();
        echo "cleanup: removed leftover {$key}\n";
    } catch (\Throwable $e) {
        echo "cleanup: {$key} -> " . $e->getMessage() . "\n";
    }
}

if ($logout) {
    $step('users.logout', fn () => $client->users->logout());
    $step('token revoked (401 after logout)', function () use ($client) {
        try {
            $client->users->me();
        } catch (AuthenticationException $e) {
            return ['status' => 401];
        }
        throw new RuntimeException('token still valid after logout');
    });
}

// ── report ───────────────────────────────────────────────────────────────
$ok = array_filter($results, fn ($r) => $r['status'] === 'ok');
$expected = array_filter($results, fn ($r) => $r['status'] === 'expected');
$bad = array_filter($results, fn ($r) => $r['status'] === 'FAIL');

echo "\n" . str_repeat('=', 72) . "\n";
printf("%d calls · %d ok · %d refused as expected · %d failed\n", count($results), count($ok), count($expected), count($bad));
if ($bad) {
    echo "\nFAILURES\n";
    foreach ($bad as $r) {
        printf("  %-44s %s\n", $r['label'], $r['detail']);
        if (!empty($r['body'])) {
            echo "      body: {$r['body']}\n";
        }
    }
}
if ($expected) {
    echo "\nREFUSED AS EXPECTED\n";
    foreach ($expected as $r) {
        printf("  %-44s %s - %s\n", $r['label'], $r['detail'], $r['note'] ?? '');
    }
}
echo "\nOK\n";
foreach ($ok as $r) {
    printf("  %-44s %s\n", $r['label'], $r['detail']);
}

exit($bad ? 1 : 0);
