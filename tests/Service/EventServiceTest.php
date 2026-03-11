<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\EventService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventServiceTest extends TestCase
{
    private EventRepository&MockObject $repo;
    private ValidatorInterface&MockObject $validator;
    private EventService $service;

    protected function setUp(): void
    {
        $this->repo      = $this->createMock(EventRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->service   = new EventService($this->repo, $this->validator);
    }

    // ── findAllByOwner ────────────────────────────────────────

    public function testFindAllByOwnerReturnsSerialisedEvents(): void
    {
        $event = $this->makeEvent(1, 'Meeting', 'owner-uuid-1');

        $this->repo->expects($this->once())
            ->method('findAllByOwner')
            ->with('owner-uuid-1')
            ->willReturn([$event]);

        $result = $this->service->findAllByOwner('owner-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('Meeting', $result[0]['title']);
        $this->assertSame(1, $result[0]['id']);
    }

    // ── findUpcoming ──────────────────────────────────────────

    public function testFindUpcomingReturnsSerialisedEvents(): void
    {
        $event = $this->makeEvent(2, 'Conference', 'owner-uuid-2');

        $this->repo->expects($this->once())
            ->method('findUpcoming')
            ->with('owner-uuid-2')
            ->willReturn([$event]);

        $result = $this->service->findUpcoming('owner-uuid-2');

        $this->assertCount(1, $result);
        $this->assertSame('Conference', $result[0]['title']);
    }

    // ── create ────────────────────────────────────────────────

    public function testCreatePersistsAndReturnsPayload(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->expects($this->once())->method('save');

        $data   = ['title' => 'New Event', 'startAt' => '2025-06-01T10:00:00'];
        $result = $this->service->create('owner-uuid-1', $data);

        $this->assertSame('New Event', $result['title']);
        $this->assertArrayHasKey('id', $result);
    }

    public function testCreateTrimsTitleWhitespace(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->method('save');

        $result = $this->service->create('owner-uuid-1', ['title' => '  Trimmed  ', 'startAt' => '2025-06-01T10:00:00']);

        $this->assertSame('Trimmed', $result['title']);
    }

    public function testCreateThrowsOnValidationFailure(): void
    {
        $violations = $this->createMock(\Symfony\Component\Validator\ConstraintViolationListInterface::class);
        $violations->method('count')->willReturn(1);

        $singleViolation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $singleViolation->method('getMessage')->willReturn('Title is required.');

        $violations->method('getIterator')->willReturn(new \ArrayIterator([$singleViolation]));

        $this->validator->method('validate')->willReturn($violations);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title is required.');

        $this->service->create('owner-uuid-1', ['startAt' => '2025-06-01T10:00:00']);
    }

    // ── update ────────────────────────────────────────────────

    public function testUpdateChangesTitle(): void
    {
        $event = $this->makeEvent(3, 'Old Title', 'owner-uuid-1');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repo->expects($this->once())->method('save');

        $result = $this->service->update($event, ['title' => 'New Title']);

        $this->assertSame('New Title', $result['title']);
    }

    // ── delete ────────────────────────────────────────────────

    public function testDeleteCallsRepository(): void
    {
        $event = $this->makeEvent(4, 'To Delete', 'owner-uuid-1');

        $this->repo->expects($this->once())->method('remove')->with($event, true);

        $this->service->delete($event);
    }

    // ── serialize ─────────────────────────────────────────────

    public function testSerializeReturnsExpectedKeys(): void
    {
        $event = $this->makeEvent(5, 'Serialise Me', 'owner-uuid-1');

        $payload = $this->service->serialize($event);

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('description', $payload);
        $this->assertArrayHasKey('startAt', $payload);
        $this->assertArrayHasKey('endAt', $payload);
        $this->assertArrayHasKey('location', $payload);
        $this->assertArrayHasKey('createdAt', $payload);
        $this->assertArrayHasKey('updatedAt', $payload);
    }

    public function testSerializeIncludesLocationWhenSet(): void
    {
        $event = $this->makeEvent(6, 'Warsaw Event', 'owner-uuid-1');
        $event->setLocationName('Warsaw, Poland');
        $event->setLocationLat(52.22977);
        $event->setLocationLon(20.9989);

        $payload = $this->service->serialize($event);

        $this->assertNotNull($payload['location']);
        $this->assertSame('Warsaw, Poland', $payload['location']['display_name']);
        $this->assertEqualsWithDelta(52.22977, $payload['location']['lat'], 0.0001);
    }

    public function testSerializeLocationNullWhenNotSet(): void
    {
        $event = $this->makeEvent(7, 'No Location', 'owner-uuid-1');

        $payload = $this->service->serialize($event);

        $this->assertNull($payload['location']);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function makeEvent(int $id, string $title, string $ownerId): Event
    {
        $event = new Event();
        $event->setTitle($title);
        $event->setOwnerId($ownerId);
        $event->setStartAt(new \DateTimeImmutable('2025-06-01T10:00:00'));

        // Force the private $id via Reflection (no DB in unit tests)
        $ref = new \ReflectionProperty(Event::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($event, $id);

        return $event;
    }
}
