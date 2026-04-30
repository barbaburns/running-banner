<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Running_Banner_Updater {
    private const SLUG = 'running-banner';
    private const UPDATE_URI = 'https://github.com/barbaburns/running-banner';
    private const REPOSITORY = 'barbaburns/running-banner';
    private const RELEASE_ASSET = 'running-banner.zip';
    private const RELEASE_CACHE_KEY = 'running_banner_github_release';
    private const RELEASE_CACHE_TTL = 21600;
    private const SHORT_DESCRIPTION = 'Scrolling text-and-image banner block and shortcode for WordPress.';

    private static $plugin_file = '';
    private static $version = '';

    public static function init($plugin_file, $version) {
        self::$plugin_file = (string) $plugin_file;
        self::$version = (string) $version;

        add_filter('update_plugins_github.com', [__CLASS__, 'filter_plugin_update'], 10, 4);
        add_filter('plugins_api', [__CLASS__, 'filter_plugin_info'], 10, 3);
        add_action('upgrader_process_complete', [__CLASS__, 'clear_release_cache'], 10, 2);
    }

    public static function filter_plugin_update($update, $plugin_data, $plugin_file, $locales) {
        unset($locales);

        if (self::plugin_basename() !== $plugin_file) {
            return $update;
        }

        $release = self::get_latest_release();

        if (empty($release['version'])) {
            return $update;
        }

        $has_newer_version = version_compare($release['version'], self::$version, '>');

        if ($has_newer_version && empty($release['package'])) {
            return $update;
        }

        return [
            'id' => self::UPDATE_URI,
            'slug' => self::SLUG,
            'plugin' => self::plugin_basename(),
            'url' => $release['url'],
            'package' => !empty($release['package']) ? $release['package'] : '',
            'version' => $release['version'],
            'new_version' => $release['version'],
            'tested' => isset($plugin_data['RequiresWP']) ? (string) $plugin_data['RequiresWP'] : '',
            'requires_php' => isset($plugin_data['RequiresPHP']) ? (string) $plugin_data['RequiresPHP'] : '',
            'icons' => [],
            'banners' => [],
            'banners_rtl' => [],
            'translations' => [],
        ];
    }

    public static function filter_plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || self::SLUG !== $args->slug) {
            return $result;
        }

        $release = self::get_latest_release();
        $metadata = self::get_plugin_metadata();

        if (empty($release['version'])) {
            return $result;
        }

        return (object) [
            'name' => $metadata['name'],
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => self::get_author_markup($metadata),
            'author_profile' => $metadata['author_uri'],
            'homepage' => $metadata['plugin_uri'],
            'download_link' => $release['package'],
            'short_description' => self::SHORT_DESCRIPTION,
            'last_updated' => $release['published_at'],
            'requires' => $metadata['requires'],
            'tested' => $metadata['tested'],
            'requires_php' => $metadata['requires_php'],
            'sections' => self::get_plugin_sections($release),
        ];
    }

    public static function clear_release_cache($upgrader, $options) {
        unset($upgrader);

        if (empty($options['action']) || 'update' !== $options['action']) {
            return;
        }

        if (empty($options['type']) || 'plugin' !== $options['type']) {
            return;
        }

        if (empty($options['plugins']) || !is_array($options['plugins'])) {
            return;
        }

        if (!in_array(self::plugin_basename(), $options['plugins'], true)) {
            return;
        }

        delete_site_transient(self::RELEASE_CACHE_KEY);
    }

    private static function get_latest_release() {
        $cached = get_site_transient(self::RELEASE_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/releases/latest', self::REPOSITORY),
            [
                'headers' => self::get_github_headers(),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if (200 !== $code) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            return [];
        }

        $release = [
            'version' => self::normalize_release_version(isset($body['tag_name']) ? $body['tag_name'] : ''),
            'url' => isset($body['html_url']) ? esc_url_raw((string) $body['html_url']) : self::UPDATE_URI,
            'package' => self::find_release_package($body),
            'published_at' => isset($body['published_at']) ? (string) $body['published_at'] : '',
            'body' => isset($body['body']) ? (string) $body['body'] : '',
        ];

        if ('' === $release['version']) {
            return [];
        }

        set_site_transient(self::RELEASE_CACHE_KEY, $release, self::RELEASE_CACHE_TTL);

        return $release;
    }

    private static function get_github_headers() {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Running-Banner-Updater',
        ];

        $token = defined('RUNNING_BANNER_GITHUB_TOKEN') ? (string) RUNNING_BANNER_GITHUB_TOKEN : '';
        $token = apply_filters('running_banner_github_token', $token);

        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private static function find_release_package($release) {
        if (empty($release['assets']) || !is_array($release['assets'])) {
            return '';
        }

        foreach ($release['assets'] as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = isset($asset['name']) ? (string) $asset['name'] : '';

            if (self::RELEASE_ASSET !== $name) {
                continue;
            }

            return isset($asset['browser_download_url']) ? esc_url_raw((string) $asset['browser_download_url']) : '';
        }

        foreach ($release['assets'] as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = isset($asset['name']) ? (string) $asset['name'] : '';

            if (!preg_match('/\.zip$/i', $name)) {
                continue;
            }

            return isset($asset['browser_download_url']) ? esc_url_raw((string) $asset['browser_download_url']) : '';
        }

        return '';
    }

    private static function normalize_release_version($version) {
        $version = trim((string) $version);
        $version = preg_replace('/^[vV]/', '', $version);

        return preg_match('/^\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '';
    }

    private static function get_release_notes($release) {
        if (!empty($release['body'])) {
            return (string) $release['body'];
        }

        return sprintf(
            'Latest release: %1$s. Download it from %2$s.',
            $release['version'],
            $release['url']
        );
    }

    private static function get_plugin_metadata() {
        static $metadata = null;

        if (null !== $metadata) {
            return $metadata;
        }

        $headers = get_file_data(
            self::$plugin_file,
            [
                'name' => 'Plugin Name',
                'author_name' => 'Author',
                'author_uri' => 'Author URI',
                'plugin_uri' => 'Plugin URI',
                'requires' => 'Requires at least',
                'tested' => 'Tested up to',
                'requires_php' => 'Requires PHP',
            ],
            'plugin'
        );

        $metadata = [
            'name' => !empty($headers['name']) ? (string) $headers['name'] : 'Running Banner',
            'author_name' => !empty($headers['author_name']) ? (string) $headers['author_name'] : 'Bruno Fernandes',
            'author_uri' => !empty($headers['author_uri']) ? esc_url_raw((string) $headers['author_uri']) : '',
            'plugin_uri' => !empty($headers['plugin_uri']) ? esc_url_raw((string) $headers['plugin_uri']) : self::UPDATE_URI,
            'requires' => !empty($headers['requires']) ? (string) $headers['requires'] : '',
            'tested' => !empty($headers['tested']) ? (string) $headers['tested'] : '',
            'requires_php' => !empty($headers['requires_php']) ? (string) $headers['requires_php'] : '',
        ];

        return $metadata;
    }

    private static function get_author_markup($metadata) {
        $author_name = trim((string) $metadata['author_name']);
        $author_uri = trim((string) $metadata['author_uri']);

        if ('' === $author_name) {
            $author_name = 'Bruno Fernandes';
        }

        if ('' === $author_uri) {
            return esc_html($author_name);
        }

        return sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url($author_uri),
            esc_html($author_name)
        );
    }

    private static function get_plugin_sections($release) {
        return [
            'description' => self::build_description_section(),
            'installation' => self::build_installation_section(),
            'faq' => self::build_faq_section(),
            'other_notes' => self::build_other_notes_section(),
            'changelog' => self::build_changelog_section($release),
        ];
    }

    private static function build_description_section() {
        $html = implode(
            '',
            [
                '<p>Running Banner adds a reusable marquee-style banner for WordPress with both a block and shortcode interface.</p>',
                '<p>Use it to create repeating text-and-image tracks for announcements, headlines, promotions, or visual separators.</p>',
                '<h4>Features</h4>',
                self::render_html_list(
                    [
                        'Gutenberg block: <code>running-banner/running-banner</code>',
                        'Shortcode support: <code>[running-banner]</code>',
                        'Repeated text items with optional image output',
                        'Controls for speed, repeat count, colors, font family, font size, font weight, font style, and text transform',
                        'Responsive typography controls for tablet and mobile',
                        'Server-side rendering for consistent front-end output',
                        'Google Font loading for selected font families',
                    ]
                ),
                '<h4>Example shortcode</h4>',
                '<pre><code>[running-banner label="Latest" repeatCount="8" speed="18"]</code></pre>',
            ]
        );

        return wp_kses_post($html);
    }

    private static function build_installation_section() {
        $html = implode(
            '',
            [
                '<ol>',
                '<li>Upload the plugin folder to <code>wp-content/plugins/running-banner</code>.</li>',
                '<li>Activate <strong>Running Banner</strong> from the Plugins screen in WordPress.</li>',
                '<li>Add the <strong>Running Banner</strong> block in the editor, or insert the shortcode in content.</li>',
                '</ol>',
            ]
        );

        return wp_kses_post($html);
    }

    private static function build_faq_section() {
        $html = implode(
            '',
            [
                '<p><strong>How do I add a banner?</strong></p>',
                '<p>Insert the Running Banner block from the block inserter, or use the shortcode in posts, pages, templates, or widget areas that support shortcodes.</p>',
                '<p><strong>Can I add an image?</strong></p>',
                '<p>Yes. Each repeated item can include an optional image from the media library or a direct image URL.</p>',
                '<p><strong>What can I customize?</strong></p>',
                self::render_html_list(
                    [
                        'Label text',
                        'Repeat count and animation speed',
                        'Text and background colors',
                        'Font family, size, weight, style, and text transform',
                        'Responsive font sizes for tablet and mobile',
                        'Image source and alt text',
                    ]
                ),
                '<p><strong>Does it work without the block editor?</strong></p>',
                '<p>Yes. The shortcode is available independently of the block editor.</p>',
            ]
        );

        return wp_kses_post($html);
    }

    private static function build_other_notes_section() {
        $html = implode(
            '',
            [
                '<h4>Attribute notes</h4>',
                self::render_html_list(
                    [
                        '<code>repeatCount</code> accepts values from <code>2</code> to <code>20</code>',
                        '<code>speed</code> accepts values from <code>0</code> to <code>60</code>, where <code>0</code> shows a static banner',
                        '<code>fontSize</code> accepts values from <code>12</code> to <code>48</code>',
                        '<code>fontStyle</code> supports <code>normal</code> and <code>italic</code>',
                        '<code>textTransform</code> supports <code>none</code>, <code>uppercase</code>, <code>lowercase</code>, and <code>capitalize</code>',
                    ]
                ),
                '<h4>Updates</h4>',
                '<p>This plugin checks GitHub releases for updates using the repository defined in its <code>Update URI</code> header. If you run into GitHub API rate limits, define <code>RUNNING_BANNER_GITHUB_TOKEN</code> in <code>wp-config.php</code> or filter it with <code>running_banner_github_token</code>.</p>',
            ]
        );

        return wp_kses_post($html);
    }

    private static function build_changelog_section($release) {
        return wp_kses_post(self::format_release_notes(self::get_release_notes($release)));
    }

    private static function format_release_notes($notes) {
        $notes = trim((string) $notes);

        if ('' === $notes) {
            return '<p>No changelog details are available for this release.</p>';
        }

        $lines = preg_split('/\r\n|\r|\n/', $notes);
        $html = [];
        $in_list = false;

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ('' === $line) {
                if ($in_list) {
                    $html[] = '</ul>';
                    $in_list = false;
                }

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                if (!$in_list) {
                    $html[] = '<ul>';
                    $in_list = true;
                }

                $html[] = sprintf('<li>%s</li>', esc_html($matches[1]));
                continue;
            }

            if ($in_list) {
                $html[] = '</ul>';
                $in_list = false;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $matches)) {
                $html[] = sprintf('<p><strong>%s</strong></p>', esc_html($matches[1]));
                continue;
            }

            $html[] = sprintf('<p>%s</p>', esc_html($line));
        }

        if ($in_list) {
            $html[] = '</ul>';
        }

        return implode('', $html);
    }

    private static function render_html_list($items) {
        $html = '<ul>';

        foreach ($items as $item) {
            $html .= sprintf('<li>%s</li>', $item);
        }

        $html .= '</ul>';

        return $html;
    }

    private static function plugin_basename() {
        return plugin_basename(self::$plugin_file);
    }
}
