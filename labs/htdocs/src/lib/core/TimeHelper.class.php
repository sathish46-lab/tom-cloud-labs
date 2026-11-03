<?php

/**
 * TimeHelper — single source of truth for time handling (reusable app-wide).
 *
 * Storage stays UTC (MongoDB BSON UTCDateTime) so queries and ordering remain
 * timezone-free; every RENDER goes through here so humans always see Indian
 * Standard Time in 12-hour AM/PM format.
 *
 * The app default timezone is already Asia/Kolkata (set in src/lib/load.php) —
 * this class makes that implicit setting explicit, reusable and testable.
 *
 * Usage:
 *   'created_at' => TimeHelper::now()                       // storage (UTC)
 *   TimeHelper::format($record['created_at'])               // "19 Oct 2026, 4:45 PM"
 *   TimeHelper::time($record['created_at'])                 // "4:45 PM"
 *   TimeHelper::formatWithZone($record['created_at'])       // "19 Oct 2026, 4:45 PM IST"
 */
class TimeHelper
{
    public const TIMEZONE = 'Asia/Kolkata';
    public const TIMEZONE_LABEL = 'IST';

    /**
     * Current instant for storage — MongoDB BSON UTCDateTime (UTC epoch millis).
     */
    public static function now(): \MongoDB\BSON\UTCDateTime
    {
        return new \MongoDB\BSON\UTCDateTime();
    }

    /**
     * Normalise any supported value to an IST DateTimeImmutable.
     *
     * @param \MongoDB\BSON\UTCDateTime|\DateTimeInterface|int|string|null $value
     *        BSON UTCDateTime, DateTime, unix timestamp (int/numeric string) or null
     * @return \DateTimeImmutable|null DateTime in Asia/Kolkata, null when unparseable
     */
    public static function toIST($value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            $value = $value->toDateTime()->getTimestamp();
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->getTimestamp();
        }
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }
        return (new \DateTimeImmutable('@' . (int) $value))
            ->setTimezone(new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * Start/end of "the day N days before today" in IST.
     *
     * Day boundaries must always be computed from this — a bare
     * `new DateTimeImmutable('today')` uses the server timezone (UTC), which
     * makes "today" slip over IST midnight (IST 00:00–05:30 is still the
     * previous UTC date) and misfiles articles between the Today / Yesterday tabs.
     *
     * @return array{start:\DateTimeImmutable, end:\DateTimeImmutable} [start, end) in IST
     */
    public static function dayRange(int $offsetDays = 0): array
    {
        $start = self::toIST(time())
            ->setTime(0, 0, 0)
            ->modify((string) -abs($offsetDays) . ' day');
        return ['start' => $start, 'end' => $start->modify('+1 day')];
    }

    /**
     * 12-hour IST rendering — default "19 Oct 2026, 4:45 PM".
     *
     * @param \MongoDB\BSON\UTCDateTime|\DateTimeInterface|int|string|null $value null = now
     */
    public static function format($value = null, string $format = 'd M Y, g:i A'): string
    {
        $dt = self::toIST($value !== null ? $value : time());
        return $dt !== null ? $dt->format($format) : '';
    }

    /**
     * Time only, 12-hour IST — e.g. "4:45 PM".
     *
     * @param \MongoDB\BSON\UTCDateTime|\DateTimeInterface|int|string|null $value null = now
     */
    public static function time($value = null): string
    {
        return self::format($value, 'g:i A');
    }

    /**
     * Date only, IST — e.g. "19 Oct 2026".
     *
     * @param \MongoDB\BSON\UTCDateTime|\DateTimeInterface|int|string|null $value null = now
     */
    public static function date($value = null): string
    {
        return self::format($value, 'd M Y');
    }

    /**
     * 12-hour IST with zone label — e.g. "19 Oct 2026, 4:45 PM IST".
     *
     * @param \MongoDB\BSON\UTCDateTime|\DateTimeInterface|int|string|null $value null = now
     */
    public static function formatWithZone($value = null): string
    {
        $formatted = self::format($value);
        return $formatted !== '' ? $formatted . ' ' . self::TIMEZONE_LABEL : '';
    }
}
