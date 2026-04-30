# Running Banner

`Running Banner` is a custom WordPress plugin that adds a scrolling banner block and shortcode for repeated text-and-image marquees.

## Features

-   Gutenberg block: `running-banner/running-banner`
-   Shortcode: `[running-banner]`
-   Repeated text items with optional image
-   Adjustable item count and animation speed
-   Text color and background color controls
-   Font size, weight, style, and family controls
-   Google Font loading for selected font families
-   Server-side rendering for block output

## Installation

1. Copy the plugin folder to `wp-content/plugins/running-banner`
2. Activate `Running Banner` in the WordPress admin
3. Add the `Running Banner` block in the editor or use the shortcode

## GitHub Updates

This plugin now checks GitHub releases for updates using the repository URL in its `Update URI` header.

To publish an update that WordPress can install:

1. Bump the plugin version in `running-banner.php` and `blocks/running-banner/block.json`
2. Create a Git tag that matches the version, such as `v1.0.2`
3. Publish a GitHub Release for that tag
4. GitHub Actions will build and attach `running-banner.zip` automatically

The workflow validates that the release tag matches the plugin and block versions before uploading the ZIP.

If you hit GitHub API rate limits, define `RUNNING_BANNER_GITHUB_TOKEN` in `wp-config.php` or filter it with `running_banner_github_token`.

## Block Usage

Add the `Running Banner` block from the block inserter, then configure:

-   Repeated text
-   Items per track
-   Animation speed
-   Optional image
-   Image alt text
-   Font size
-   Font weight
-   Font style
-   Font family
-   Text color
-   Background color

## Shortcode Usage

Basic example:

```text
[running-banner label="Latest" repeatCount="8" speed="18"]
```

Example with image and styles:

```text
[running-banner label="News" imageUrl="https://example.com/icon.png" imageAlt="News icon" repeatCount="10" speed="20" textColor="#ffffff" backgroundColor="#111111" fontFamily="Space Grotesk" fontSize="18" fontWeight="700" fontStyle="italic"]
```

## Shortcode Attributes

-   `label`
-   `imageId`
-   `imageUrl`
-   `imageAlt`
-   `repeatCount`
-   `speed`
-   `textColor`
-   `backgroundColor`
-   `fontFamily`
-   `fontSize`
-   `fontWeight`
-   `fontStyle`

## Notes

-   `repeatCount` is limited to values between `2` and `20`
-   `speed` is limited to values between `8` and `60`
-   `fontSize` is limited to values between `12` and `48`
-   `fontStyle` supports `normal` and `italic`
-   The plugin includes a curated built-in Google Fonts list for the editor control

## Files

-   `running-banner.php` - plugin bootstrap, block registration, shortcode rendering
-   `blocks/running-banner/block.json` - block metadata
-   `blocks/running-banner/editor.js` - editor UI and preview behavior
-   `assets/banner.css` - front-end and editor styling
