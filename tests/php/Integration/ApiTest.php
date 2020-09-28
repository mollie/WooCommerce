<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests\Integration;

use Inpsyde\Fasti\Domain\Event\CreateEventByArray;
use Inpsyde\Fasti\Domain\Event\Date;
use Inpsyde\Fasti\Domain\Event\Event;
use Inpsyde\Fasti\Domain\Event\EventStatus;
use Inpsyde\Fasti\Domain\Event\LocatedEvent;
use Inpsyde\Fasti\Domain\Event\SimpleEvent;
use Inpsyde\Fasti\Domain\Service\PersistenceRegistry;
use Inpsyde\Fasti\Domain\Uuid;
use Inpsyde\Fasti\Domain\Venue\Address;
use Inpsyde\Fasti\Domain\Venue\Coordinates;
use Inpsyde\Fasti\Domain\Venue\Venue;
use Inpsyde\Fasti\Domain\Venue\VenueStatus;
use Inpsyde\Fasti\Tests\IntegrationTestCase;

use function Inpsyde\Fasti\allEventsStartingAt;
use function Inpsyde\Fasti\eventAddressesByPostId;
use function Inpsyde\Fasti\eventByPostId;
use function Inpsyde\Fasti\eventCoordinatesByPostId;
use function Inpsyde\Fasti\eventDatesByPostId;
use function Inpsyde\Fasti\events;
use function Inpsyde\Fasti\eventsAtVenueByPostId;
use function Inpsyde\Fasti\eventUuidByPostId;
use function Inpsyde\Fasti\eventVenuesByPostId;
use function Inpsyde\Fasti\venueByPostId;
use function Inpsyde\Fasti\venues;
use function Inpsyde\Fasti\venueUuidByPostId;

/**
 * @runTestsInSeparateProcesses
 */
final class ApiTest extends IntegrationTestCase
{
    /**
     * @test
     */
    public function eventByPostId(): void
    {
        $eventFromDb = $this->createEvent();
        $event = eventByPostId($eventFromDb->id());

        static::assertEquals($eventFromDb->uuid(), $event->uuid());
        static::assertEquals($eventFromDb->id(), $event->id());
        static::assertEquals($eventFromDb->description(), $event->description());
        static::assertEquals($eventFromDb->summary(), $event->summary());
        static::assertEquals($eventFromDb->dates()->toArray(), $event->dates()->toArray());
        static::assertEquals($eventFromDb->venues()->toArray(), $event->venues()->toArray());
    }

    /**
     * @test
     */
    public function eventUuidByPostId(): void
    {
        $event = $this->createEvent();

        static::assertEquals($event->uuid(), eventUuidByPostId($event->id()));
    }

    /**
     * @test
     */
    public function eventDatesByPostId(): void
    {
        $eventFromDb = $this->createEvent();
        $eventDates = eventDatesByPostId($eventFromDb->id());

        static::assertEquals($eventFromDb->dates()->toArray(), $eventDates->toArray());
    }

    /**
     * @test
     */
    public function eventVenuesByPostId(): void
    {
        $eventFromDb = $this->createEvent();

        $eventVenues = eventVenuesByPostId($eventFromDb->id());

        static::assertEquals($eventFromDb->venues()->toArray(), $eventVenues->toArray());
    }

    /**
     * @test
     */
    public function eventsAtVenueByPostId(): void
    {
        /** @var LocatedEvent $event */
        $event = $this->createEvent();
        $events = eventsAtVenueByPostId($event->venues()->first()->id());

        static::assertEquals([$event->toArray()], $events->toArray());
    }

    /**
     * @test
     */
    public function eventAddressesByPostId(): void
    {
        $event = $this->createEvent();

        /** @var Address $address */
        foreach (eventAddressesByPostId($event->id()) as $address) {
            static::assertEquals(
                $event->venues()->first()->address()->toArray(),
                $address->toArray()
            );
        }
    }

    /**
     * @test
     */
    public function eventCoordinatesByPostId(): void
    {
        $event = $this->createEvent();

        /** @var Coordinates $coordinates */
        foreach (eventCoordinatesByPostId($event->id()) as $coordinates) {
            static::assertEquals(
                $event->venues()->first()->coordinates()->toArray(),
                $coordinates->toArray()
            );
        }
    }

    /**
     * @test
     */
    public function events(): void
    {
        $eventIds = [
            $this->createEvent()->id(),
            $this->createEvent()->id(),
            $this->createEvent()->id(),
        ];

        /** @var Event $event */
        foreach (events() as $event) {
            static::assertContains($event->id(), $eventIds);
        }
    }

    /**
     * @test
     */
    public function venueByPostId(): void
    {
        $venueFromDb = $this->createVenue();
        $venue = venueByPostId($venueFromDb->id());

        static::assertEquals($venueFromDb->uuid(), $venue->uuid());
        static::assertEquals($venueFromDb->title(), $venue->title());
        static::assertEquals($venueFromDb->description(), $venue->description());
        static::assertEquals($venueFromDb->summary(), $venue->summary());
        static::assertEquals($venueFromDb->status(), $venue->status());
        static::assertEquals($venueFromDb->address()->toArray(), $venue->address()->toArray());
        static::assertEquals(
            $venueFromDb->coordinates()->toArray(),
            $venue->coordinates()->toArray()
        );
    }

    /**
     * @test
     */
    public function venueUuidByPostId(): void
    {
        $venue = $this->createVenue();

        static::assertEquals($venue->uuid(), venueUuidByPostId($venue->id()));
    }

    /**
     * @test
     */
    public function venues(): void
    {
        $venuesIds = [
            $this->createVenue()->id(),
            $this->createVenue()->id(),
            $this->createVenue()->id(),
        ];

        /** @var Venue $venue */
        foreach (venues() as $venue) {
            static::assertContains($venue->id(), $venuesIds);
        }
    }

    private function createEvent(): Event
    {
        $eventDate = Date::new(
            new \DateTimeImmutable('1970-01-01 12:00:00', new \DateTimeZone('Europe/Rome')),
            new \DateTimeImmutable('now', new \DateTimeZone('Europe/Rome'))
        );
        $venue = $this->createVenue();

        $eventUuid = Uuid::new();
        $event = CreateEventByArray::ofClass(SimpleEvent::class)(
            [
                Event::UUID => $eventUuid,
                Event::DESCRIPTION => $this->faker->text,
                Event::SUMMARY => substr($this->faker->text, 0, 50),
                Event::TITLE => $this->faker->title,
                Event::STATUS => EventStatus::SCHEDULED(),
                Event::DATES => [$eventDate->toArray()],
                LocatedEvent::VENUES => [$venue->toArray()],
            ]
        );
        $event->persist();

        $persistenceRegistry = PersistenceRegistry::new();

        return $persistenceRegistry->events()->ofUuid($eventUuid)->first();
    }

    private function createVenue(): Venue
    {
        $coordinates = Coordinates::fromArray(
            [
                Coordinates::LATITUDE => $this->faker->latitude,
                Coordinates::LONGITUDE => $this->faker->longitude,
            ]
        );
        $address = Address::fromArray(
            [
                Address::COUNTRY => $this->faker->country,
                Address::STATE => $this->faker->state,
                Address::CITY => $this->faker->city,
                Address::STREET => $this->faker->streetName,
                Address::POSTAL_CODE => $this->faker->postcode,
            ]
        );

        $venueUuid = Uuid::new();
        $venue = Venue::fromArray(
            array_merge(
                [
                    Venue::UUID => $venueUuid,
                    Venue::TITLE => $this->faker->title,
                    Venue::DESCRIPTION => $this->faker->text,
                    Venue::SUMMARY => substr($this->faker->text, 0, 50),
                    Venue::STATUS => VenueStatus::VISIBLE(),
                ],
                $address->toArray(),
                $coordinates->toArray()
            )
        );
        $venue->persist();

        $persistenceRegistry = PersistenceRegistry::new();

        return $persistenceRegistry->venues()->ofUuid($venueUuid)->first();
    }
}
