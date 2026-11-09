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
 * Unit tests for watch coverage and anti-skipping.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\local;

/**
 * Tests for the progress and video classes.
 *
 * @group mod_watchcomplete
 * @covers \mod_watchcomplete\local\progress
 * @covers \mod_watchcomplete\local\video
 */
final class progress_test extends \advanced_testcase {
    /**
     * Overlapping and touching segments merge.
     */
    public function test_merge_combines_overlaps(): void {
        $merged = progress::merge([[0, 300], [200, 400], [400, 450], [600, 700]]);
        $this->assertEquals([[0, 450], [600, 700]], $merged);
    }

    /**
     * Skipped sections are not counted: the spec example, 10 of 15 minutes is 67%.
     */
    public function test_skipped_sections_are_not_counted(): void {
        $merged = progress::merge([[0, 300], [600, 900]]);
        $summary = progress::summarise($merged, 900, 100);
        $this->assertEquals(600, $summary['covered']);
        $this->assertEquals(66, $summary['percent']);
        $this->assertFalse($summary['completed']);
    }

    /**
     * Re-watching does not raise coverage.
     */
    public function test_rewatching_does_not_double_count(): void {
        $merged = progress::merge([[0, 300], [0, 300], [100, 200]]);
        $summary = progress::summarise($merged, 900, 50);
        $this->assertEquals(300, $summary['covered']);
    }

    /**
     * Completion needs the threshold, and 100% needs every second.
     */
    public function test_threshold_and_full_coverage(): void {
        $this->assertTrue(progress::summarise([[0, 900]], 900, 100)['completed']);
        $this->assertFalse(progress::summarise([[0, 899]], 900, 100)['completed']);
        $this->assertTrue(progress::summarise([[0, 810]], 900, 90)['completed']);
        $this->assertFalse(progress::summarise([[0, 809]], 900, 90)['completed']);
    }

    /**
     * With prevent skipping, a jump past unwatched content is discarded.
     */
    public function test_prevent_skipping_discards_forward_jumps(): void {
        $merged = progress::apply([], [[0, 300], [600, 700]], 900, true);
        $this->assertEquals([[0, 300]], $merged);
    }

    /**
     * Without prevent skipping, the same jump is stored but only the played parts count.
     */
    public function test_jumps_allowed_when_prevent_skipping_off(): void {
        $merged = progress::apply([], [[0, 300], [600, 700]], 900, false);
        $summary = progress::summarise($merged, 900, 100);
        $this->assertEquals(400, $summary['covered']);
    }

    /**
     * Revisiting watched sections is allowed with prevent skipping on.
     */
    public function test_revisiting_watched_sections_is_allowed(): void {
        $merged = progress::apply([[0, 300]], [[100, 200]], 900, true);
        $this->assertEquals([[0, 300]], $merged);
    }

    /**
     * Segments are clamped to the duration and invalid values are ignored.
     */
    public function test_segments_are_clamped(): void {
        $merged = progress::apply([], [[-5, 50], [40, 5000], [70, 60]], 100, false);
        $this->assertEquals([[0, 100]], $merged);
    }

    /**
     * Encoding round-trips.
     */
    public function test_encode_and_parse_round_trip(): void {
        $merged = [[0, 30], [60, 90]];
        $this->assertEquals($merged, progress::parse(progress::encode($merged)));
        $this->assertEquals([], progress::parse('garbage,1-,-2'));
    }

    /**
     * Video URL parsing accepts known hosts and rejects others.
     */
    public function test_video_url_parsing(): void {
        $this->assertEquals(
            ['source' => 'youtube', 'id' => 'dQw4w9WgXcQ'],
            video::parse('youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
        $this->assertEquals(
            ['source' => 'youtube', 'id' => 'dQw4w9WgXcQ'],
            video::parse('youtube', 'https://youtu.be/dQw4w9WgXcQ')
        );
        $this->assertEquals(
            ['source' => 'vimeo', 'id' => '76979871'],
            video::parse('vimeo', 'https://vimeo.com/76979871')
        );
        $this->assertNull(video::parse('youtube', 'http://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(video::parse('youtube', 'https://example.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(video::parse('vimeo', 'https://vimeo.com/notavideo'));
        $this->assertNull(video::parse('mp4', 'https://example.com/page.html'));
    }

    /**
     * The per-request credit limit grows with elapsed time and has a fallback when no baseline is known.
     */
    public function test_max_new_seconds(): void {
        $this->assertSame(2, progress::max_new_seconds(0));
        $this->assertSame(32, progress::max_new_seconds(15));
        $this->assertSame(2, progress::max_new_seconds(-5));
        $this->assertSame(progress::UNKNOWN_BASELINE_CREDIT, progress::max_new_seconds(null));
        // A forged 15 minute claim one second after opening the page is refused by the limit.
        $this->assertLessThan(900, progress::max_new_seconds(1));
    }
}
