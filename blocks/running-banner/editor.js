(function (blocks, blockEditor, components, element, i18n) {
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var Button = components.Button;
    var BaseControl = components.BaseControl;
    var ColorPalette = components.ColorPalette;
    var RangeControl = components.RangeControl;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;
    var __ = i18n.__;
    var el = element.createElement;
    var BLOCK_NAME = 'running-banner/running-banner';
    var FONT_FAMILY_OPTIONS = window.runningBannerFontOptions || [
        { label: __('Theme Default', 'running-banner'), value: '' },
    ];
    var COLOR_PALETTE = [
        { name: 'Dark', color: '#3a3a3a' },
        { name: 'Yellow', color: '#ffff00' },
        { name: 'Pink', color: '#fd7ee7' },
        { name: 'Red', color: '#ff0000' },
        { name: 'White', color: '#ffffff' },
        { name: 'Black', color: '#000000' },
    ];
    var FONT_WEIGHT_OPTIONS = [
        { label: '400', value: '400' },
        { label: '500', value: '500' },
        { label: '600', value: '600' },
        { label: '700', value: '700' },
        { label: '800', value: '800' },
        { label: '900', value: '900' },
    ];
    var FONT_STYLE_OPTIONS = [
        { label: __('Normal', 'running-banner'), value: 'normal' },
        { label: __('Italic', 'running-banner'), value: 'italic' },
    ];
    var TEXT_TRANSFORM_OPTIONS = [
        { label: __('Uppercase', 'running-banner'), value: 'uppercase' },
        { label: __('Lowercase', 'running-banner'), value: 'lowercase' },
        { label: __('Capitalize', 'running-banner'), value: 'capitalize' },
        { label: __('None', 'running-banner'), value: 'none' },
    ];

    function buildItems(label, imageUrl, imageAlt, repeatCount) {
        var items = [];
        var safeLabel = label || __('Ultimas', 'running-banner');

        for (var index = 0; index < repeatCount; index += 1) {
            items.push(
                el(
                    'span',
                    {
                        className: 'running-banner__item',
                        key: 'item-' + index,
                    },
                    el(
                        'span',
                        {
                            className: 'running-banner__label',
                        },
                        safeLabel
                    ),
                    imageUrl
                        ? el('img', {
                              className: 'running-banner__icon',
                              src: imageUrl,
                              alt: imageAlt || '',
                          })
                        : null
                )
            );
        }

        return items;
    }

    function buildFontUrl(fontFamily, fontWeight, fontStyle) {
        if (!fontFamily) {
            return '';
        }

        var family = encodeURIComponent(fontFamily).replace(/%20/g, '+');
        var weight = fontWeight;

        if (weight === 'bold') {
            weight = '700';
        } else if (weight === 'normal' || !/^\d{3}$/.test(weight)) {
            weight = '400';
        }

        if (fontStyle === 'italic') {
            return 'https://fonts.googleapis.com/css2?family=' + family + ':ital,wght@0,' + weight + ';1,' + weight + '&display=swap';
        }

        return 'https://fonts.googleapis.com/css2?family=' + family + ':wght@' + weight + '&display=swap';
    }

    function ensureFontStylesheet(fontFamily, fontWeight, fontStyle) {
        var fontUrl = buildFontUrl(fontFamily, fontWeight, fontStyle);

        if (!fontUrl || !document || !document.head) {
            return;
        }

        var styleId = 'running-banner-font-' + fontFamily.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        var existing = document.getElementById(styleId);

        if (existing) {
            if (existing.href !== fontUrl) {
                existing.href = fontUrl;
            }
            return;
        }

        var link = document.createElement('link');
        link.id = styleId;
        link.rel = 'stylesheet';
        link.href = fontUrl;
        document.head.appendChild(link);
    }

    function getFontFamilyOptions(fontFamily) {
        var hasCurrentValue = FONT_FAMILY_OPTIONS.some(function (option) {
            return option.value === fontFamily;
        });

        if (!fontFamily || hasCurrentValue) {
            return FONT_FAMILY_OPTIONS;
        }

        return [
            { label: fontFamily, value: fontFamily },
        ].concat(FONT_FAMILY_OPTIONS);
    }

    function normalizeResponsiveFontSize(value, fallback) {
        return typeof value === 'number' && value >= 12 && value <= 48 ? value : fallback;
    }

    function createSettings() {
        return {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var label = attributes.label;
            var imageUrl = attributes.imageUrl;
            var imageAlt = attributes.imageAlt;
            var repeatCount = attributes.repeatCount || 10;
            var speed = attributes.speed || 18;
            var textColor = attributes.textColor;
            var backgroundColor = attributes.backgroundColor;
            var fontFamily = attributes.fontFamily;
            var fontSize = attributes.fontSize || 16;
            var fontSizeTablet = normalizeResponsiveFontSize(attributes.fontSizeTablet, fontSize);
            var fontSizeMobile = normalizeResponsiveFontSize(attributes.fontSizeMobile, fontSizeTablet);
            var fontWeight = attributes.fontWeight || '700';
            var fontStyle = attributes.fontStyle || 'normal';
            var textTransform = attributes.textTransform || 'none';
            var fontFamilyOptions = getFontFamilyOptions(fontFamily);
            var items = buildItems(label, imageUrl, imageAlt, repeatCount);
            var previewStyle = {
                '--running-banner-duration': speed + 's',
                '--running-banner-font-size': fontSize + 'px',
                '--running-banner-font-size-tablet': fontSizeTablet + 'px',
                '--running-banner-font-size-mobile': fontSizeMobile + 'px',
                '--running-banner-font-weight': fontWeight,
                '--running-banner-font-style': fontStyle,
                '--running-banner-text-transform': textTransform,
            };

            if (textColor) {
                previewStyle['--running-banner-text-color'] = textColor;
            }

            if (backgroundColor) {
                previewStyle['--running-banner-background'] = backgroundColor;
            }

            if (fontFamily) {
                previewStyle['--running-banner-font-family'] = fontFamily;
                ensureFontStylesheet(fontFamily, fontWeight, fontStyle);
            }

            return el(
                element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                        el(
                            PanelBody,
                            {
                            title: __('Banner Settings', 'running-banner'),
                            initialOpen: true,
                        },
                        el(TextControl, {
                            label: __('Repeated text', 'running-banner'),
                            value: label,
                            onChange: function (value) {
                                setAttributes({ label: value });
                            },
                        }),
                        el(RangeControl, {
                            label: __('Items per track', 'running-banner'),
                            value: repeatCount,
                            onChange: function (value) {
                                setAttributes({ repeatCount: value || 2 });
                            },
                            min: 2,
                            max: 20,
                        }),
                        el(RangeControl, {
                            label: __('Animation speed (seconds)', 'running-banner'),
                            value: speed,
                            onChange: function (value) {
                                setAttributes({ speed: value || 18 });
                            },
                            min: 0,
                            max: 60,
                        }),
                        el(TextControl, {
                            label: __('Image alt text', 'running-banner'),
                            value: imageAlt,
                            onChange: function (value) {
                                setAttributes({ imageAlt: value });
                            },
                        }),
                        el(
                            MediaUploadCheck,
                            null,
                            el(MediaUpload, {
                                onSelect: function (media) {
                                    setAttributes({
                                        imageId: media && media.id ? media.id : 0,
                                        imageUrl: media && media.url ? media.url : '',
                                        imageAlt: media && media.alt ? media.alt : imageAlt,
                                    });
                                },
                                allowedTypes: ['image'],
                                value: attributes.imageId,
                                render: function (mediaProps) {
                                    return el(
                                        Button,
                                        {
                                            variant: 'secondary',
                                            onClick: mediaProps.open,
                                        },
                                        imageUrl
                                            ? __('Replace image', 'running-banner')
                                            : __('Choose image', 'running-banner')
                                    );
                                },
                            })
                        ),
                        imageUrl
                            ? el(
                                  Button,
                                  {
                                      variant: 'tertiary',
                                      onClick: function () {
                                          setAttributes({
                                              imageId: 0,
                                              imageUrl: '',
                                              imageAlt: '',
                                          });
                                      },
                                  },
                                  __('Remove image', 'running-banner')
                              )
                            : null
                    ),
                    el(
                        PanelBody,
                        {
                            title: __('Style', 'running-banner'),
                            initialOpen: false,
                        },
                        el(RangeControl, {
                            label: __('Desktop font size', 'running-banner'),
                            value: fontSize,
                            onChange: function (value) {
                                setAttributes({ fontSize: value || 16 });
                            },
                            min: 12,
                            max: 48,
                        }),
                        el(RangeControl, {
                            label: __('Tablet font size', 'running-banner'),
                            value: fontSizeTablet,
                            onChange: function (value) {
                                setAttributes({ fontSizeTablet: value || fontSize });
                            },
                            min: 12,
                            max: 48,
                        }),
                        el(RangeControl, {
                            label: __('Mobile font size', 'running-banner'),
                            value: fontSizeMobile,
                            onChange: function (value) {
                                setAttributes({ fontSizeMobile: value || fontSizeTablet });
                            },
                            min: 12,
                            max: 48,
                        }),
                        el(SelectControl, {
                            label: __('Font weight', 'running-banner'),
                            value: fontWeight,
                            options: FONT_WEIGHT_OPTIONS,
                            onChange: function (value) {
                                setAttributes({ fontWeight: value });
                            },
                        }),
                        el(SelectControl, {
                            label: __('Font style', 'running-banner'),
                            value: fontStyle,
                            options: FONT_STYLE_OPTIONS,
                            onChange: function (value) {
                                setAttributes({ fontStyle: value || 'normal' });
                            },
                        }),
                        el(SelectControl, {
                            label: __('Text Transform', 'running-banner'),
                            value: textTransform,
                            options: TEXT_TRANSFORM_OPTIONS,
                            onChange: function (value) {
                                setAttributes({ textTransform: value || 'none' });
                            },
                        }),
                        el(SelectControl, {
                            label: __('Font family', 'running-banner'),
                            help: __('Choose a Google Font or keep the theme default.', 'running-banner'),
                            value: fontFamily || '',
                            options: fontFamilyOptions,
                            onChange: function (value) {
                                setAttributes({ fontFamily: value || '' });
                            },
                        }),
                        el(
                            BaseControl,
                            { label: __('Text color', 'running-banner') },
                            el(ColorPalette, {
                                colors: COLOR_PALETTE,
                                value: textColor,
                                onChange: function (value) {
                                    setAttributes({ textColor: value || '' });
                                },
                                clearable: true,
                            })
                        ),
                        el(
                            BaseControl,
                            { label: __('Background color', 'running-banner') },
                            el(ColorPalette, {
                                colors: COLOR_PALETTE,
                                value: backgroundColor,
                                onChange: function (value) {
                                    setAttributes({ backgroundColor: value || '' });
                                },
                                clearable: true,
                            })
                        )
                    )
                ),
                el(
                    'div',
                    useBlockProps({
                        className: 'running-banner running-banner--preview',
                        style: previewStyle,
                    }),
                    el(
                        'div',
                        { className: 'running-banner__viewport' },
                        el(
                            'div',
                            { className: 'running-banner__track' },
                            items
                        ),
                        el(
                            'div',
                            {
                                className: 'running-banner__track',
                                'aria-hidden': true,
                            },
                            items
                        )
                    )
                )
            );
        },
        save: function () {
            return null;
        },
        };
    }

    registerBlockType(BLOCK_NAME, createSettings());
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
