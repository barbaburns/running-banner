<?php
/**
 * Plugin Name: Running Banner
 * Plugin URI: https://github.com/barbaburns/running-banner
 * Description: Adds a reusable running banner block and shortcode for repeated word-and-image marquees.
 * Version: 1.0.2
 * Author: Bruno Fernandes
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/barbaburns/running-banner
 * Text Domain: running-banner
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Running_Banner {
    private const VERSION = '1.0.2';
    private const SHORTCODE = 'running-banner';
    private const SLUG = 'running-banner';
    private const UPDATE_URI = 'https://github.com/barbaburns/running-banner';
    private const REPOSITORY = 'barbaburns/running-banner';
    private const RELEASE_ASSET = 'running-banner.zip';
    private const RELEASE_CACHE_KEY = 'running_banner_github_release';
    private const RELEASE_CACHE_TTL = 21600;

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
        add_filter('update_plugins_github.com', [__CLASS__, 'filter_plugin_update'], 10, 4);
        add_filter('plugins_api', [__CLASS__, 'filter_plugin_info'], 10, 3);
        add_action('upgrader_process_complete', [__CLASS__, 'clear_release_cache'], 10, 2);
    }

    public static function register() {
        $style_path = plugin_dir_path(__FILE__) . 'assets/banner.css';
        $script_path = plugin_dir_path(__FILE__) . 'blocks/running-banner/editor.js';

        wp_register_style(
            'running-banner-style',
            plugin_dir_url(__FILE__) . 'assets/banner.css',
            [],
            file_exists($style_path) ? filemtime($style_path) : self::VERSION
        );

        wp_register_script(
            'running-banner-editor',
            plugin_dir_url(__FILE__) . 'blocks/running-banner/editor.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'],
            file_exists($script_path) ? filemtime($script_path) : self::VERSION,
            true
        );

        wp_add_inline_script(
            'running-banner-editor',
            'window.runningBannerFontOptions = ' . wp_json_encode(self::get_google_font_options()) . ';',
            'before'
        );

        register_block_type(
            __DIR__ . '/blocks/running-banner',
            [
                'render_callback' => [__CLASS__, 'render_block'],
            ]
        );

        add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
    }

    public static function render_shortcode($atts = []) {
        return self::render($atts, false);
    }

    public static function render_block($attributes = [], $content = '', $block = null) {
        return self::render($attributes, true);
    }

    public static function filter_plugin_update($update, $plugin_data, $plugin_file, $locales) {
        unset($locales);

        if (self::plugin_basename() !== $plugin_file) {
            return $update;
        }

        $release = self::get_latest_release();

        if (empty($release['version']) || version_compare($release['version'], self::VERSION, '<=')) {
            return $update;
        }

        if (empty($release['package'])) {
            return $update;
        }

        return [
            'id' => self::UPDATE_URI,
            'slug' => self::SLUG,
            'plugin' => self::plugin_basename(),
            'url' => $release['url'],
            'package' => $release['package'],
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

        if (empty($release['version'])) {
            return $result;
        }

        return (object) [
            'name' => 'Running Banner',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => '<a href="https://github.com/barbaburns">Bruno Fernandes</a>',
            'homepage' => self::UPDATE_URI,
            'download_link' => $release['package'],
            'last_updated' => $release['published_at'],
            'sections' => [
                'description' => wp_kses_post('<p>Adds a reusable running banner block and shortcode for repeated word-and-image marquees.</p>'),
                'changelog' => wp_kses_post(wpautop(self::get_release_notes($release))),
            ],
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

    private static function render($attributes, $is_block) {
        $attributes = self::normalize_attributes($attributes);

        $defaults = [
            'label'           => __('Ultimas', 'running-banner'),
            'imageId'         => 0,
            'imageUrl'        => '',
            'imageAlt'        => '',
            'repeatCount'     => 10,
            'speed'           => 18,
            'textColor'       => '',
            'backgroundColor' => '',
            'fontFamily'      => '',
            'fontSize'        => 16,
            'fontSizeTablet'  => null,
            'fontSizeMobile'  => null,
            'fontWeight'      => '700',
            'fontStyle'       => 'normal',
        ];

        $attributes = wp_parse_args(is_array($attributes) ? $attributes : [], $defaults);

        $label = trim(wp_strip_all_tags((string) $attributes['label']));
        $label = '' !== $label ? $label : $defaults['label'];

        $image_id = absint($attributes['imageId']);
        $image_url = esc_url_raw((string) $attributes['imageUrl']);
        $image_alt = sanitize_text_field((string) $attributes['imageAlt']);
        $repeat_count = max(2, min(20, absint($attributes['repeatCount'])));
        $speed = max(8, min(60, absint($attributes['speed'])));
        $text_color = sanitize_hex_color((string) $attributes['textColor']);
        $background_color = sanitize_hex_color((string) $attributes['backgroundColor']);
        $font_family = self::sanitize_font_family((string) $attributes['fontFamily']);
        $font_size = max(12, min(48, absint($attributes['fontSize'])));
        $font_size_tablet = self::sanitize_responsive_font_size($attributes['fontSizeTablet'], $font_size);
        $font_size_mobile = self::sanitize_responsive_font_size($attributes['fontSizeMobile'], $font_size_tablet);
        $font_weight = self::sanitize_font_weight((string) $attributes['fontWeight']);
        $font_style = self::sanitize_font_style((string) $attributes['fontStyle']);

        wp_enqueue_style('running-banner-style');

        if ('' !== $font_family) {
            self::enqueue_google_font($font_family, $font_weight, $font_style);
        }

        $items = '';

        for ($index = 0; $index < $repeat_count; $index++) {
            $items .= self::render_item($label, $image_id, $image_url, $image_alt);
        }

        if ('' === $items) {
            return '';
        }

        $style_rules = [
            '--running-banner-duration' => $speed . 's',
            '--running-banner-font-size' => $font_size . 'px',
            '--running-banner-font-size-tablet' => $font_size_tablet . 'px',
            '--running-banner-font-size-mobile' => $font_size_mobile . 'px',
            '--running-banner-font-weight' => $font_weight,
            '--running-banner-font-style' => $font_style,
        ];

        if ('' !== $text_color) {
            $style_rules['--running-banner-text-color'] = $text_color;
        }

        if ('' !== $background_color) {
            $style_rules['--running-banner-background'] = $background_color;
        }

        if ('' !== $font_family) {
            $style_rules['--running-banner-font-family'] = $font_family;
        }

        $wrapper_attributes = [
            'class' => 'running-banner',
            'style' => self::build_style_string($style_rules),
        ];

        if ($is_block && function_exists('get_block_wrapper_attributes')) {
            $wrapper = get_block_wrapper_attributes($wrapper_attributes);
        } else {
            $wrapper = self::stringify_attributes($wrapper_attributes);
        }

        return sprintf(
            '<div %1$s><div class="running-banner__viewport"><div class="running-banner__track">%2$s</div><div class="running-banner__track" aria-hidden="true">%2$s</div></div></div>',
            $wrapper,
            $items
        );
    }

    private static function render_item($label, $image_id, $image_url, $image_alt) {
        $icon = '';

        if ($image_id > 0) {
            $image_attributes = ['class' => 'running-banner__icon'];

            if ('' !== $image_alt) {
                $image_attributes['alt'] = $image_alt;
            }

            $icon = wp_get_attachment_image($image_id, 'full', false, $image_attributes);
        } elseif ('' !== $image_url) {
            $icon = sprintf(
                '<img class="running-banner__icon" src="%1$s" alt="%2$s" loading="lazy" decoding="async" />',
                esc_url($image_url),
                esc_attr($image_alt)
            );
        }

        return sprintf(
            '<span class="running-banner__item"><span class="running-banner__label">%1$s</span>%2$s</span>',
            esc_html($label),
            $icon
        );
    }

    private static function normalize_attributes($attributes) {
        if (!is_array($attributes)) {
            return [];
        }

        $aliases = [
            'imageid'      => 'imageId',
            'image_id'     => 'imageId',
            'imageurl'     => 'imageUrl',
            'image_url'    => 'imageUrl',
            'imagealt'     => 'imageAlt',
            'image_alt'    => 'imageAlt',
            'repeatcount'  => 'repeatCount',
            'repeat_count' => 'repeatCount',
            'textcolor' => 'textColor',
            'text_color' => 'textColor',
            'backgroundcolor' => 'backgroundColor',
            'background_color' => 'backgroundColor',
            'fontfamily' => 'fontFamily',
            'font_family' => 'fontFamily',
            'fontsize' => 'fontSize',
            'font_size' => 'fontSize',
            'fontsizetablet' => 'fontSizeTablet',
            'font_size_tablet' => 'fontSizeTablet',
            'fontsizemobile' => 'fontSizeMobile',
            'font_size_mobile' => 'fontSizeMobile',
            'fontweight' => 'fontWeight',
            'font_weight' => 'fontWeight',
            'fontstyle' => 'fontStyle',
            'font_style' => 'fontStyle',
        ];

        foreach ($aliases as $old_key => $new_key) {
            if (isset($attributes[$old_key]) && !isset($attributes[$new_key])) {
                $attributes[$new_key] = $attributes[$old_key];
            }
        }

        return $attributes;
    }

    private static function sanitize_font_family($value) {
        $value = wp_strip_all_tags($value);
        return trim(str_replace([';', '{', '}'], '', $value));
    }

    private static function sanitize_responsive_font_size($value, $fallback) {
        if (null === $value || '' === $value) {
            return $fallback;
        }

        return max(12, min(48, absint($value)));
    }

    private static function sanitize_font_weight($value) {
        $allowed = ['400', '500', '600', '700', '800', '900', 'normal', 'bold'];
        return in_array($value, $allowed, true) ? $value : '700';
    }

    private static function sanitize_font_style($value) {
        $allowed = ['normal', 'italic'];
        return in_array($value, $allowed, true) ? $value : 'normal';
    }

    private static function get_google_font_options() {
        static $options = null;

        if (null !== $options) {
            return $options;
        }

        $options = [
            [
                'label' => __('Theme Default', 'running-banner'),
                'value' => '',
            ],
            [
                'label' => 'Abril Fatface',
                'value' => 'Abril Fatface',
            ],
            [
                'label' => 'Anton',
                'value' => 'Anton',
            ],
            [
                'label' => 'Bebas Neue',
                'value' => 'Bebas Neue',
            ],
            [
                'label' => 'Bitter',
                'value' => 'Bitter',
            ],
            [
                'label' => 'Cormorant Garamond',
                'value' => 'Cormorant Garamond',
            ],
            [
                'label' => 'DM Sans',
                'value' => 'DM Sans',
            ],
            [
                'label' => 'DM Serif Display',
                'value' => 'DM Serif Display',
            ],
            [
                'label' => 'Figtree',
                'value' => 'Figtree',
            ],
            [
                'label' => 'Inter',
                'value' => 'Inter',
            ],
            [
                'label' => 'Lato',
                'value' => 'Lato',
            ],
            [
                'label' => 'Libre Baskerville',
                'value' => 'Libre Baskerville',
            ],
            [
                'label' => 'Lora',
                'value' => 'Lora',
            ],
            [
                'label' => 'Manrope',
                'value' => 'Manrope',
            ],
            [
                'label' => 'Merriweather',
                'value' => 'Merriweather',
            ],
            [
                'label' => 'Montserrat',
                'value' => 'Montserrat',
            ],
            [
                'label' => 'Nunito Sans',
                'value' => 'Nunito Sans',
            ],
            [
                'label' => 'Open Sans',
                'value' => 'Open Sans',
            ],
            [
                'label' => 'Oswald',
                'value' => 'Oswald',
            ],
            [
                'label' => 'Playfair Display',
                'value' => 'Playfair Display',
            ],
            [
                'label' => 'Poppins',
                'value' => 'Poppins',
            ],
            [
                'label' => 'Roboto',
                'value' => 'Roboto',
            ],
            [
                'label' => 'Space Grotesk',
                'value' => 'Space Grotesk',
            ],
            [
                'label' => 'Syne',
                'value' => 'Syne',
            ],
            [
                'label' => 'Work Sans',
                'value' => 'Work Sans',
            ],
        ];

        usort($options, static function ($left, $right) {
            if ('' === $left['value']) {
                return -1;
            }

            if ('' === $right['value']) {
                return 1;
            }

            return strcmp($left['label'], $right['label']);
        });

        return $options;
    }

    private static function enqueue_google_font($font_family, $font_weight, $font_style) {
        $font_url = self::build_google_font_url($font_family, $font_weight, $font_style);

        if ('' === $font_url) {
            return;
        }

        wp_enqueue_style(
            'running-banner-font-' . md5($font_family . $font_weight . $font_style),
            $font_url,
            [],
            null
        );
    }

    private static function build_google_font_url($font_family, $font_weight, $font_style) {
        $font_family = trim($font_family);

        if ('' === $font_family) {
            return '';
        }

        $font_slug = str_replace('%20', '+', rawurlencode($font_family));
        $font_weight = self::normalize_font_weight($font_weight);

        if ('italic' === $font_style) {
            return sprintf(
                'https://fonts.googleapis.com/css2?family=%1$s:ital,wght@0,%2$s;1,%2$s&display=swap',
                $font_slug,
                $font_weight
            );
        }

        return sprintf(
            'https://fonts.googleapis.com/css2?family=%1$s:wght@%2$s&display=swap',
            $font_slug,
            $font_weight
        );
    }

    private static function normalize_font_weight($font_weight) {
        if ('bold' === $font_weight) {
            return '700';
        }

        if ('normal' === $font_weight) {
            return '400';
        }

        return preg_match('/^\d{3}$/', $font_weight) ? $font_weight : '400';
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

    private static function plugin_basename() {
        return plugin_basename(__FILE__);
    }

    private static function build_style_string($style_rules) {
        $compiled = [];

        foreach ($style_rules as $property => $value) {
            if ('' === $value || null === $value) {
                continue;
            }

            $compiled[] = sprintf('%1$s:%2$s', $property, $value);
        }

        return implode(';', $compiled) . ';';
    }

    private static function stringify_attributes($attributes) {
        $compiled = [];

        foreach ($attributes as $name => $value) {
            if ('' === $value || null === $value) {
                continue;
            }

            $compiled[] = sprintf('%1$s="%2$s"', esc_attr($name), esc_attr($value));
        }

        return implode(' ', $compiled);
    }
}

Running_Banner::init();
