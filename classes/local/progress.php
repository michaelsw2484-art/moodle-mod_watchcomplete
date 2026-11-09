<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// GNU GPL v3 or later. See <http://www.gnu.org/licenses/>.

/**
 * Watch-coverage logic: merging segments, unique coverage and anti-skipping rules.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\local;

/**
 * Pure logic for tracking unique watched time. Contains no database access so it can be unit tested.
 *
 * A segment is a [start, end] pair of whole seconds. Overlapping and touching segments are merged,
 * so re-watching a section never increases coverage.
 */
final class progress {
    /** Seconds of allowed forward movement past the furthest watched point (network and rounding slack). */
    public const TOLERANCE = 2;

    /** Longest accepted media, in seconds (24 hours). */
    public const MAX_DURATION = 86400;

    /** Maximum number of segments accepted in one request, or stored for one student. */
    public const MAX_SEGMENTS = 200;

    /** Most video seconds that can be credited per real second (allows 2x playback). */
    public const MAX_RATE = 2;

    /** Extra seconds of credit allowed per request, for timer and network slack. */
    public const RATE_SLACK = 2;

    /** Credit allowed when no earlier page view or save time is known (for example after a session expired). */
    public const UNKNOWN_BASELINE_CREDIT = 120;

    /**
     * The most new video seconds a single save may add, given the real time since the last known page view or save.
     *
     * This does not prove that anyone was watching. It stops one request from claiming a whole video that
     * could not have been played in the time that has passed.
     *
     * @param int|null $elapsed Seconds since the last known page view or save, or null if not known.
     * @return int
     */
    public static function max_new_seconds(?int $elapsed): int {
        if ($elapsed === null) {
            return self::UNKNOWN_BASELINE_CREDIT;
        }
        return max(0, $elapsed) * self::MAX_RATE + self::RATE_SLACK;
    }

    /**
     * Decode a stored "start-end,start-end" string.
     *
     * @param string $encoded
     * @return array Merged list of [start, end].
     */
    public static function parse(string $encoded): array {
        $segments = [];
        foreach (explode(',', $encoded) as $pair) {
            if (preg_match('/^(\d{1,7})-(\d{1,7})$/', trim($pair), $m)) {
                $segments[] = [(int) $m[1], (int) $m[2]];
            }
        }
        return self::merge($segments);
    }

    /**
     * Encode a list of segments for storage.
     *
     * @param array $segments
     * @return string
     */
    public static function encode(array $segments): string {
        return implode(',', array_map(static fn($s) => $s[0] . '-' . $s[1], $segments));
    }

    /**
     * Sort segments and merge any that overlap or touch.
     *
     * @param array $segments List of [start, end].
     * @return array Sorted, non-overlapping list.
     */
    public static function merge(array $segments): array {
        usort($segments, static fn($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($segments as [$start, $end]) {
            if ($end <= $start) {
                continue;
            }
            $last = count($out) - 1;
            if ($last >= 0 && $start <= $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $end);
            } else {
                $out[] = [$start, $end];
            }
        }
        return $out;
    }

    /**
     * The furthest point watched so far.
     *
     * @param array $merged
     * @return int
     */
    public static function reach(array $merged): int {
        $reach = 0;
        foreach ($merged as [, $end]) {
            $reach = max($reach, $end);
        }
        return $reach;
    }

    /**
     * Apply new segments, in the order they were played, to the stored ones.
     *
     * With prevent skipping on, a segment that starts more than TOLERANCE seconds beyond the
     * furthest watched point is discarded. Revisiting watched sections is always allowed.
     *
     * @param array $existing Currently merged segments.
     * @param array $incoming New [start, end] pairs in playback order.
     * @param int $duration Media length in seconds.
     * @param bool $preventskipping Whether jumping ahead is blocked.
     * @return array Merged segments.
     */
    public static function apply(array $existing, array $incoming, int $duration, bool $preventskipping): array {
        $merged = self::merge($existing);
        if ($duration <= 0) {
            return $merged;
        }
        foreach ($incoming as [$start, $end]) {
            $start = max(0, (int) $start);
            $end = min((int) $end, $duration);
            if ($end <= $start) {
                continue;
            }
            if ($preventskipping && $start > self::reach($merged) + self::TOLERANCE) {
                continue;
            }
            $merged = self::merge(array_merge($merged, [[$start, $end]]));
        }
        return $merged;
    }

    /**
     * Summarise merged segments against the media length and the completion threshold.
     *
     * @param array $merged
     * @param int $duration
     * @param int $threshold Percentage from 1 to 100.
     * @return array ['covered' => int, 'percent' => int, 'completed' => bool]
     */
    public static function summarise(array $merged, int $duration, int $threshold): array {
        if ($duration <= 0) {
            return ['covered' => 0, 'percent' => 0, 'completed' => false];
        }
        $covered = 0;
        foreach ($merged as [$start, $end]) {
            $covered += max(0, min($end, $duration) - max($start, 0));
        }
        $covered = min($covered, $duration);
        $percent = intdiv($covered * 100, $duration);
        return [
            'covered' => $covered,
            'percent' => $percent,
            'completed' => $percent >= $threshold,
        ];
    }
}
