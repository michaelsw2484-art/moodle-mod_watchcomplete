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
 * Validation and parsing of video sources.
 *
 * @package    mod_watchcomplete
 * @copyright  2026 Michael Williams <michael.williams@outlook.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_watchcomplete\local;

/**
 * Parses YouTube, Vimeo and direct MP4/WebM URLs. Only HTTPS URLs on known hosts are accepted.
 */
final class video {
    /** @var string[] Supported source types. */
    public const SOURCES = ['youtube', 'vimeo', 'mp4'];

    /**
     * Parse a video URL for the given source.
     *
     * @param string $source One of SOURCES.
     * @param string $url The URL entered by the teacher.
     * @return array|null ['source', 'id'] for embeds, ['source', 'url'] for files, or null if invalid.
     */
    public static function parse(string $source, string $url): ?array {
        $url = trim($url);
        if ($url === '' || !in_array($source, self::SOURCES, true)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        switch ($source) {
            case 'youtube':
                if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
                    return null;
                }
                if ($host === 'youtu.be') {
                    $id = ltrim($path, '/');
                } else {
                    parse_str($parts['query'] ?? '', $query);
                    $id = is_string($query['v'] ?? null) ? $query['v'] : '';
                    if ($id === '' && preg_match('#^/(?:embed|shorts)/([^/]+)#', $path, $m)) {
                        $id = $m[1];
                    }
                }
                return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['source' => 'youtube', 'id' => $id] : null;

            case 'vimeo':
                if (!in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
                    return null;
                }
                return preg_match('#/(?:video/)?(\d{6,12})(?:/|$)#', $path, $m)
                    ? ['source' => 'vimeo', 'id' => $m[1]] : null;

            default:
                // Direct file: mp4, webm or m4v.
                return preg_match('/\.(mp4|webm|m4v)$/i', $path) ? ['source' => 'mp4', 'url' => $url] : null;
        }
    }
}
