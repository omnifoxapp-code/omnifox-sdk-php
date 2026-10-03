<?php

declare(strict_types=1);

namespace Omnifox\Tests\Unit;

use Omnifox\Exception\InvalidArgumentException;
use Omnifox\OmnifoxClient;

final class ResourcesTest extends TestCase
{
    public function testEveryResourceIsWired(): void
    {
        $c = new OmnifoxClient('k');
        $names = ['contacts', 'conversations', 'messages', 'channels', 'broadcasts', 'customFields', 'tags', 'snippets',
            'webhooks', 'workflows', 'users', 'workspaces', 'teams', 'companies', 'deals', 'pipelines', 'crmActivities',
            'boards', 'boardItems', 'appointments', 'products', 'orders', 'catalogSettings', 'quotes', 'reports'];
        foreach ($names as $name) {
            self::assertIsObject($c->{$name}, $name);
        }
        self::assertCount(25, $names);
    }

    public function testContactSelectorIsUrlEncoded(): void
    {
        $c = $this->client([self::ok(['id' => 1])]);
        $c->contacts->get('email:ana+x@example.com');
        self::assertSame('/api/v1/contacts/email%3Aana%2Bx%40example.com', $this->request()->getUri()->getPath());
    }

    public function testMergeUsesPrimaryAndMergedIds(): void
    {
        $c = $this->client([self::ok(['id' => 1]), self::ok(['id' => 1])]);
        $c->contacts->merge(['primary_contact_id' => '10', 'merged_contact_id' => 11]);
        $c->contacts->merge(['target_id' => 10, 'source_id' => 11]);

        self::assertSame(['primary_contact_id' => 10, 'merged_contact_id' => 11], $this->jsonBody(0));
        self::assertSame(['primary_contact_id' => 10, 'merged_contact_id' => 11], $this->jsonBody(1));
    }

    public function testMergeRequiresBothIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client([])->contacts->merge(['primary_contact_id' => 1]);
    }

    public function testDetachTagsByNameIsDeleteWithBody(): void
    {
        $c = $this->client([self::ok(null), self::ok(null)]);
        $c->contacts->detachTagsByName(5, ['names' => ['vip']]);
        $c->contacts->detachTagsByName(5, ['vip', 'lead']);

        self::assertSame('DELETE', $this->request()->getMethod());
        self::assertSame('/api/v1/contacts/5/tags-by-name', $this->request()->getUri()->getPath());
        self::assertSame(['names' => ['vip']], $this->jsonBody(0));
        self::assertSame(['names' => ['vip', 'lead']], $this->jsonBody(1));
    }

    public function testAssignConversationShapes(): void
    {
        $c = $this->client(array_fill(0, 5, self::ok(['id' => 1])));
        $c->conversations->assign(1, ['assignee_type' => 'user', 'assignee_id' => 28]);
        $c->conversations->assign(1, ['assigned_user_id' => 28]);
        $c->conversations->assign(1, ['assigned_team_id' => 3, 'assign_logic' => 'round_robin', 'only_online' => false]);
        $c->conversations->assign(1, ['assigned_ai_agent_id' => 2]);
        $c->conversations->assign(1, []);

        self::assertSame(['assignee_type' => 'user', 'assignee_id' => 28], $this->jsonBody(0));
        self::assertSame(['assignee_type' => 'user', 'assignee_id' => 28], $this->jsonBody(1));
        self::assertSame(['assignee_type' => 'team', 'assignee_id' => 3, 'assign_logic' => 'round_robin', 'only_online' => false], $this->jsonBody(2));
        self::assertSame(['assignee_type' => 'ai_agent', 'assignee_id' => 2], $this->jsonBody(3));
        self::assertSame(['assignee_type' => 'none'], $this->jsonBody(4));
    }

    public function testCloseConversationMapsAliases(): void
    {
        $c = $this->client([self::ok([]), self::ok([])]);
        $c->conversations->close(1, ['reason' => 'resolved', 'summary' => 'done']);
        $c->conversations->close(1);
        self::assertSame(['resolution' => 'resolved', 'notes' => 'done'], $this->jsonBody(0));
        self::assertSame('{}', $this->body(1));
    }

    public function testMessagesSendNormalisation(): void
    {
        $c = $this->client(array_fill(0, 3, self::ok(['id' => 1])));
        $c->messages->send(['contact' => 'phone:+593999', 'channel_id' => 2, 'text' => 'Hi']);
        $c->messages->send(['contact' => 4, 'template' => ['name' => 'welcome', 'language' => 'es']]);
        $c->messages->send(['contact_id' => 4, 'type' => 'text', 'content_text' => 'raw']);

        self::assertSame(['contact_id' => 'phone:+593999', 'channel_id' => 2, 'type' => 'text', 'content_text' => 'Hi'], $this->jsonBody(0));
        self::assertSame(['contact_id' => 4, 'type' => 'template', 'template' => ['name' => 'welcome', 'language' => 'es']], $this->jsonBody(1));
        self::assertSame(['contact_id' => 4, 'type' => 'text', 'content_text' => 'raw'], $this->jsonBody(2));
    }

    public function testMessagesSendRequiresContact(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client([])->messages->send(['text' => 'x']);
    }

    public function testAssignContactKeepsExplicitNull(): void
    {
        $c = $this->client([self::ok([])]);
        $c->contacts->assignConversation(5, ['assignee' => null]);
        self::assertSame('{"assignee":null}', $this->body());
    }

    public function testCrudPathsAndVerbs(): void
    {
        $c = $this->client(array_fill(0, 12, self::ok(['id' => 1])));
        $c->customFields->update(1, ['label' => 'x']);
        $c->boardItems->setCellValue(7, 3, 'hello');
        $c->boardItems->move(7, ['group_id' => 2]);
        $c->deals->moveToStage(4, 9);
        $c->pipelines->updateStage(9, ['name' => 'Won']);
        $c->crmActivities->create('deal', 4, ['title' => 't']);
        $c->orders->setStatus(3, 'confirmed');
        $c->orders->recordPayment(3, 12.5, 'cash');
        $c->catalogSettings->applyPreset('retail');
        $c->teams->addMember(2, 28);
        $c->webhooks->rotateSecret(6);
        $c->quotes->send(3, ['channels' => ['email' => true], 'dry_run' => true]);

        $seen = array_map(fn ($h) => $h['request']->getMethod() . ' ' . $h['request']->getUri()->getPath(), $this->history);
        self::assertSame([
            'PATCH /api/v1/custom-fields/1',
            'PUT /api/v1/boards/items/7/columns/3',
            'PATCH /api/v1/boards/items/7/move',
            'PATCH /api/v1/crm/deals/4/stage',
            'PUT /api/v1/crm/stages/9',
            'POST /api/v1/crm/deal/4/activities',
            'POST /api/v1/orders/3/status',
            'POST /api/v1/orders/3/payment',
            'POST /api/v1/catalog/settings/preset',
            'POST /api/v1/teams/2/members',
            'POST /api/v1/webhooks/6/rotate-secret',
            'POST /api/v1/quotes/3/send',
        ], $seen);
        self::assertSame(['value' => 'hello'], $this->jsonBody(1));
        self::assertSame(['stage_id' => 9], $this->jsonBody(3));
        self::assertSame(['status' => 'confirmed'], $this->jsonBody(6));
        self::assertSame(['amount' => 12.5, 'method' => 'cash'], $this->jsonBody(7));
        self::assertSame(['user_id' => 28], $this->jsonBody(9));
    }

    public function testCrmActivitySubjectIsValidated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client([])->crmActivities->list('lead', 1);
    }

    public function testVideoOptionsTravelWithBooking(): void
    {
        $c = $this->client([self::ok(['id' => 1, 'video_guest_url' => 'https://x'])]);
        $appt = $c->appointments->book([
            'calendar_id' => 1, 'starts_at' => '2026-10-10T16:00:00Z', 'location_type' => 'video_omnifox',
            'video_options' => ['screen_share' => true, 'translate_mode' => 'voice', 'start_muted' => null],
        ]);
        self::assertSame('https://x', $appt['video_guest_url']);
        self::assertSame(['screen_share' => true, 'translate_mode' => 'voice', 'start_muted' => null], $this->jsonBody()['video_options']);
    }

    public function testReportsPassPreset(): void
    {
        $c = $this->client([self::ok([]), self::ok([])]);
        $c->reports->overview(['preset' => '7d']);
        $c->reports->crmWinLossReasons(['from' => '2026-01-01', 'to' => '2026-01-31']);
        self::assertSame(['preset' => '7d'], $this->query(0));
        self::assertSame('/api/v1/crm/reports/win-loss-reasons', $this->request(1)->getUri()->getPath());
    }
}
