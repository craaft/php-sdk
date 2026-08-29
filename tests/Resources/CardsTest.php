<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Enums\Priority;
use Craaft\Exceptions\ValidationError;
use Craaft\Tests\ClientBuilder;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CardsTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function card(): array
    {
        return [
            'id' => 'card1',
            'projectId' => 'p1',
            'column' => 'todo',
            'title' => 'x',
            'position' => 1.0,
            'description' => null,
            'dueDate' => null,
            'assignedUserId' => null,
            'size' => null,
            'priority' => null,
            'createdBy' => null,
            'attachmentCount' => 0,
            'tags' => [],
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    private function summary(): array
    {
        return [
            'id' => 'card1',
            'projectId' => 'p1',
            'projectName' => 'Demo',
            'columnKey' => 'todo',
            'columnTitle' => 'To Do',
            'title' => 'x',
        ];
    }

    private function comment(): array
    {
        return [
            'id' => 'cm1',
            'cardId' => 'card1',
            'authorId' => 'u1',
            'body' => 'hi',
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testUpdateTranslatesFields(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->card());
        $b->client()->cards->update(
            'card1',
            title: 'New',
            description: 'd',
            column: 'doing',
            position: 2.0,
            dueDate: '2026-06-01T10:00:00+00:00',
            assignedUserId: 'u2',
            size: 5,
            priority: Priority::Urgent,
            tags: ['launch'],
        );
        $call = $b->stub()->lastCall();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame(self::BASE . '/cards/card1', $call['url']);
        $this->assertSame(
            [
                'title' => 'New',
                'description' => 'd',
                'column' => 'doing',
                'position' => 2.0,
                'dueDate' => '2026-06-01T10:00:00+00:00',
                'assignedUserId' => 'u2',
                'size' => 5,
                'priority' => 'urgent',
                'tags' => ['launch'],
            ],
            json_decode($call['body'], true),
        );
    }

    public function testUpdateSerializesDatetimeWithTz(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->card());
        $b->client()->cards->update(
            'card1',
            dueDate: new DateTimeImmutable('2026-06-01T10:00:00', new DateTimeZone('UTC')),
        );
        $body = json_decode($b->stub()->lastCall()['body'], true);
        $this->assertSame('2026-06-01T10:00:00+00:00', $body['dueDate']);
    }

    public function testUpdateOmitsUnspecified(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->card());
        $b->client()->cards->update('card1', title: 'x');
        $this->assertSame(['title' => 'x'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new \Craaft\Http\HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $this->assertNull($b->client()->cards->delete('card1'));
        $this->assertSame('DELETE', $b->stub()->lastCall()['method']);
    }

    public function testUpcoming(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [array_merge($this->summary(), [
            'dueDate' => '2026-05-05T00:00:00+02:00',
            'assignedUserId' => 'u1',
            'priority' => 'high',
        ])]);
        $cards = $b->client()->cards->upcoming();
        $this->assertSame('Demo', $cards[0]->projectName);
        $this->assertSame('To Do', $cards[0]->columnTitle);
        $this->assertSame('todo', $cards[0]->columnKey);
        $this->assertSame(Priority::High, $cards[0]->priority);
        $this->assertNotNull($cards[0]->dueDate);
    }

    public function testSearchDefaultLimit(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => [$this->summary()]]);
        $cards = $b->client()->cards->search(q: 'hello');
        $this->assertCount(1, $cards);
        $this->assertSame('todo', $cards[0]->columnKey);
        $url = $b->stub()->lastCall()['url'];
        $this->assertStringContainsString('q=hello', $url);
        $this->assertStringContainsString('limit=20', $url);
    }

    public function testSearchCustomLimit(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => []]);
        $b->client()->cards->search(q: 'hello', limit: 5);
        $this->assertStringContainsString('limit=5', $b->stub()->lastCall()['url']);
    }

    public function testSearchLimitValidation(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->cards->search(q: 'x', limit: 0);
    }

    public function testSearchLimitUpperBound(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->cards->search(q: 'x', limit: 51);
    }

    public function testSearch400RaisesValidation(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(400, ['error' => 'bad q']);
        $this->expectException(ValidationError::class);
        $b->client()->cards->search(q: '');
    }

    public function testListComments(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [$this->comment()]);
        $comments = $b->client()->cards->listComments('card1');
        $this->assertSame('hi', $comments[0]->body);
    }

    public function testAddComment(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->comment());
        $cm = $b->client()->cards->addComment('card1', body: 'hi');
        $this->assertSame('cm1', $cm->id);
        $this->assertSame(['body' => 'hi'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testMove(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->card());
        $b->client()->cards->move('card1', targetProjectId: 'p2', column: 'todo');
        $this->assertSame(
            ['targetProjectId' => 'p2', 'column' => 'todo'],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testHygieneSendsTypeParam(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, []);
        $b->client()->cards->hygiene(\Craaft\Enums\HygieneType::Stuck);
        $this->assertStringContainsString('type=stuck', $b->stub()->lastCall()['url']);
    }

    public function testBulkUpdatePassesItemsVerbatim(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => [$this->card()]]);
        $b->client()->cards->bulkUpdate([
            ['id' => 'card1', 'title' => 'Renamed', 'dueDate' => null, 'assignedUserId' => null],
        ]);
        $call = $b->stub()->lastCall();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame(self::BASE . '/cards/bulk', $call['url']);
        // Explicit nulls must survive into the JSON body (null = clear),
        // and absent keys must stay absent (absent = leave alone).
        $this->assertSame(
            '{"cards":[{"id":"card1","title":"Renamed","dueDate":null,"assignedUserId":null}]}',
            $call['body'],
        );
    }

    public function testBulkUpdateNormalizesValueTypes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => []]);
        $b->client()->cards->bulkUpdate([
            [
                'id' => 'card1',
                'dueDate' => new DateTimeImmutable('2026-06-01T10:00:00', new DateTimeZone('UTC')),
                'priority' => Priority::Urgent,
                'tags' => [3 => 'launch'],
            ],
        ]);
        $body = json_decode($b->stub()->lastCall()['body'], true);
        $this->assertSame('2026-06-01T10:00:00+00:00', $body['cards'][0]['dueDate']);
        $this->assertSame('urgent', $body['cards'][0]['priority']);
        $this->assertSame(['launch'], $body['cards'][0]['tags']);
    }

    public function testBulkUpdateReturnsCardsInOrder(): void
    {
        $b = new ClientBuilder();
        $second = array_merge($this->card(), ['id' => 'card2', 'title' => 'y']);
        $b->stub()->enqueueJson(200, ['cards' => [$this->card(), $second]]);
        $cards = $b->client()->cards->bulkUpdate([
            ['id' => 'card1', 'title' => 'x'],
            ['id' => 'card2', 'title' => 'y'],
        ]);
        $this->assertCount(2, $cards);
        $this->assertSame('card1', $cards[0]->id);
        $this->assertSame('card2', $cards[1]->id);
    }

    public function testBulkUpdateRejectsEmptyBatch(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->cards->bulkUpdate([]);
    }

    public function testBulkUpdateRejectsOversizedBatch(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->cards->bulkUpdate(array_fill(0, 101, ['id' => 'x']));
    }

    public function testBulkUpdate400NamesOffendingIndex(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(400, ['error' => 'cards[3]: title is required']);
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('cards[3]: title is required');
        $b->client()->cards->bulkUpdate([['id' => 'card1', 'title' => '']]);
    }

    public function testBulkMoveSameBoard(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => [$this->card()]]);
        $cards = $b->client()->cards->bulkMove(['card1', 'card2'], column: 'done');
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/cards/bulk/move', $call['url']);
        $this->assertSame(
            ['ids' => ['card1', 'card2'], 'column' => 'done'],
            json_decode($call['body'], true),
        );
        $this->assertSame('card1', $cards[0]->id);
    }

    public function testBulkMoveToAnotherBoard(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => []]);
        $b->client()->cards->bulkMove(['card1'], column: 'todo', targetProjectId: 'p2');
        $this->assertSame(
            ['ids' => ['card1'], 'column' => 'todo', 'targetProjectId' => 'p2'],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testBulkMoveRejectsEmptyBatch(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->cards->bulkMove([], column: 'done');
    }

    public function testBulkMove400OnMultipleBoardsWithoutTarget(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(400, ['error' => 'cards span multiple boards; pass targetProjectId']);
        $this->expectException(ValidationError::class);
        $b->client()->cards->bulkMove(['card1', 'card9'], column: 'done');
    }

    public function testBulkMove422OnUnknownColumn(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(422, ['error' => 'column does not exist']);
        $this->expectException(ValidationError::class);
        $b->client()->cards->bulkMove(['card1'], column: 'nope', targetProjectId: 'p2');
    }

    private function checklistItem(): array
    {
        return [
            'id' => 'ck1',
            'cardId' => 'card1',
            'text' => 'write tests',
            'done' => false,
            'position' => 1.0,
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testListChecklist(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [$this->checklistItem()]);
        $items = $b->client()->cards->listChecklist('card1');
        $this->assertSame(self::BASE . '/cards/card1/checklist', $b->stub()->lastCall()['url']);
        $this->assertSame('write tests', $items[0]->text);
        $this->assertFalse($items[0]->done);
    }

    public function testAddChecklistItem(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->checklistItem());
        $item = $b->client()->cards->addChecklistItem('card1', text: 'write tests');
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/cards/card1/checklist', $call['url']);
        $this->assertSame(['text' => 'write tests'], json_decode($call['body'], true));
        $this->assertSame('ck1', $item->id);
    }

    public function testGetFetchesASingleCard(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['title' => 'Fetched'] + $this->card());
        $card = $b->client()->cards->get('card1');
        $call = $b->stub()->lastCall();
        $this->assertSame('GET', $call['method']);
        $this->assertSame(self::BASE . '/cards/card1', $call['url']);
        $this->assertSame('Fetched', $card->title);
    }

    public function testGetEscapesTheId(): void
    {
        // Ids arrive from callers unvalidated; a traversal-shaped one must
        // not rewrite the request path.
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->card());
        $b->client()->cards->get('../../admin');
        $this->assertSame(self::BASE . '/cards/..%2F..%2Fadmin', $b->stub()->lastCall()['url']);
    }

    public function testCardReadsFollowingFlag(): void
    {
        // The API has always returned `following`; the model used to drop it.
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['following' => true] + $this->card());
        $this->assertTrue($b->client()->cards->get('card1')->following);
    }

    public function testCardFollowingDefaultsFalseWhenAbsent(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->card());
        $this->assertFalse($b->client()->cards->get('card1')->following);
    }

    public function testFollowAndUnfollowSendNoBody(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(204);
        $b->stub()->enqueueResponse(204);
        $client = $b->client();
        $client->cards->follow('card1');
        $first = $b->stub()->lastCall();
        $this->assertSame('POST', $first['method']);
        $this->assertSame(self::BASE . '/cards/card1/follow', $first['url']);

        $client->cards->unfollow('card1');
        $second = $b->stub()->lastCall();
        $this->assertSame('DELETE', $second['method']);
        $this->assertSame(self::BASE . '/cards/card1/follow', $second['url']);
    }
}
