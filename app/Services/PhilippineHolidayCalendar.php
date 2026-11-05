<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class PhilippineHolidayCalendar
{
    /**
     * @var array<string, string>
     */
    private const FIXED_HOLIDAYS = [
        '01-01' => "New Year's Day",
        '04-09' => 'Araw ng Kagitingan',
        '05-01' => 'Labor Day',
        '06-12' => 'Independence Day',
        '08-21' => 'Ninoy Aquino Day',
        '11-01' => "All Saints' Day",
        '11-02' => "All Souls' Day",
        '11-30' => 'Bonifacio Day',
        '12-08' => 'Feast of the Immaculate Conception',
        '12-24' => 'Christmas Eve',
        '12-25' => 'Christmas Day',
        '12-30' => 'Rizal Day',
        '12-31' => 'Last Day of the Year',
    ];

    /**
     * Dates specified by annual proclamations, including dates for lunar holidays.
     *
     * @var array<int, array<string, string>>
     */
    private const PROCLAMATION_SPECIFIC_DATES = [
        2026 => [
            '02-17' => 'Chinese New Year',
            '03-20' => "Eid'l Fitr",
            '05-27' => "Eid'l Adha",
        ],
        2027 => [
            '02-06' => 'Chinese New Year',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public function forYear(int $year): array
    {
        $holidays = [];
        foreach (self::FIXED_HOLIDAYS as $monthAndDay => $name) {
            $holidays[sprintf('%04d-%s', $year, $monthAndDay)] = $name;
        }

        $easterSunday = $this->easterSunday($year);
        $holidays[$easterSunday->copy()->subDays(3)->toDateString()] = 'Maundy Thursday';
        $holidays[$easterSunday->copy()->subDays(2)->toDateString()] = 'Good Friday';
        $holidays[$easterSunday->copy()->subDay()->toDateString()] = 'Black Saturday';

        $nationalHeroesDay = Carbon::createFromDate($year, 8, 31, config('clinic.timezone'));
        while ($nationalHeroesDay->dayOfWeekIso !== Carbon::MONDAY) {
            $nationalHeroesDay->subDay();
        }
        $holidays[$nationalHeroesDay->toDateString()] = 'National Heroes Day';

        foreach (self::PROCLAMATION_SPECIFIC_DATES[$year] ?? [] as $monthAndDay => $name) {
            $holidays[sprintf('%04d-%s', $year, $monthAndDay)] = $name;
        }

        ksort($holidays);

        return $holidays;
    }

    public function nameFor(Carbon $date): ?string
    {
        return $this->forYear($date->year)[$date->toDateString()] ?? null;
    }

    private function easterSunday(int $year): Carbon
    {
        $century = intdiv($year, 100);
        $yearOfCentury = $year % 100;
        $correction = intdiv($century, 4);
        $centuryRemainder = $century % 4;
        $lunarCorrection = intdiv($century + 8, 25);
        $solarCorrection = intdiv($century - $lunarCorrection + 1, 3);
        $epact = (19 * ($year % 19) + $century - $correction - $solarCorrection + 15) % 30;
        $weekdayCorrection = intdiv($yearOfCentury, 4);
        $yearRemainder = $yearOfCentury % 4;
        $weekday = (32 + 2 * $centuryRemainder + 2 * $weekdayCorrection - $epact - $yearRemainder) % 7;
        $leapCorrection = intdiv(($year % 19) + 11 * $epact + 22 * $weekday, 451);
        $month = intdiv($epact + $weekday - 7 * $leapCorrection + 114, 31);
        $day = (($epact + $weekday - 7 * $leapCorrection + 114) % 31) + 1;

        return Carbon::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-%02d', $year, $month, $day),
            config('clinic.timezone'),
        );
    }
}
