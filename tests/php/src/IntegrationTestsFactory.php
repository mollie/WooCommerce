<?php

declare(strict_types=1);

namespace Inpsyde\Fasti\Tests;

use Faker\Generator;
use Inpsyde\Fasti\Domain\Event\Date;
use Inpsyde\Fasti\Domain\Event\Dates;
use Inpsyde\Fasti\Domain\Event\Event;
use Inpsyde\Fasti\Domain\Event\LocatedEvent;
use Inpsyde\Fasti\Domain\Event\SimpleEvent;
use Inpsyde\Fasti\Domain\Service\PersistenceRegistry;
use Inpsyde\Fasti\Domain\Uuid;
use Inpsyde\Fasti\Domain\Venue\Address;
use Inpsyde\Fasti\Domain\Venue\Coordinates;
use Inpsyde\Fasti\Domain\Venue\Venue;
use Inpsyde\Fasti\Domain\Venue\Venues;

class IntegrationTestsFactory
{
    /**
     * @param int $howMany
     * @param bool $fullDay
     * @return Dates
     */
    public static function factoryDates(int $howMany = 1, bool $fullDay = false): Dates
    {
        if ($howMany < 1) {
            return PersistenceRegistry::new()->dates();
        }

        $dates = [];
        $lastDate = null;
        for ($i = 0; $i < $howMany; $i++) {
            if (!$lastDate) {
                $year = random_int(1982, 2022);
                $month = random_int(10, 12);
                $day = random_int(10, 28);

                $lastDate = \DateTimeImmutable::createFromFormat(
                    'Y-m-d',
                    "{$year}-{$month}-{$day}"
                );
            }

            $end = $lastDate->modify('+1 day');

            if ($fullDay) {
                $dates[] = Date::allDay($lastDate);
                $lastDate = $end;
                continue;
            }

            $dates[] = Date::new($lastDate, $end);
            $lastDate = $end->modify('+1 day');
        }

        shuffle($dates);

        return PersistenceRegistry::new()->dates()->wrap(...$dates);
    }

    /**
     * @param Generator $faker
     * @param int $howManyDates
     * @param bool $fullDay
     * @return LocatedEvent
     */
    public static function factoryEvent(
        Generator $faker,
        int $howManyDates = 1,
        bool $fullDay = false
    ): Event {

        return SimpleEvent::new(Uuid::new($faker->uuid))
            ->withDates(static::factoryDates($howManyDates, $fullDay))
            ->withTitle($faker->text(20))
            ->withDescription($faker->randomHtml())
            ->withSummary($faker->text(50));
    }

    /**
     * @param Generator $faker
     * @return Venues
     */
    public static function factoryVenues(Generator $faker, int $howMany = 1): Venues
    {
        $venues = PersistenceRegistry::new()->venues();
        if ($howMany < 1) {
            return $venues;
        }

        $wrap = [];
        for ($i = 0; $i < $howMany; $i++) {
            $wrap[] = static::factoryVenue($faker);
        }

        return $venues->wrap(...$wrap);
    }

    /**
     * @param Generator $faker
     * @return Venue
     */
    public static function factoryVenue(Generator $faker): Venue
    {
        $address = Address::new(
            $faker->country,
            $faker->state,
            $faker->city,
            $faker->streetAddress,
            $faker->postcode
        );

        $coordinates = Coordinates::precise(
            $faker->randomFloat(7, -90, +90),
            $faker->randomFloat(7, -90, +90)
        );

        return Venue::new(Uuid::new($faker->uuid))
            ->withAddress($address)
            ->withCoordinates($coordinates)
            ->withTitle($faker->text(20))
            ->withDescription($faker->text(200))
            ->withSummary($faker->text(50));
    }
}
