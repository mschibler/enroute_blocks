(function() {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var MediaUpload = wp.blockEditor.MediaUpload;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var el = wp.element.createElement;
    var __ = wp.i18n.__;

    var data = window.enrouteBlocksData || { colors: [], guidesUrl: '', ajaxUrl: '', nonce: '' };

    var COLORS = (data.colors || []).map(function(c) {
        return { label: 'Color ' + c.index, value: String(c.index), color: c.color };
    });

    // ── Block category ────────────────────────────────────────────────────────
    wp.hooks.addFilter('blocks.registerBlockType', 'enroute/category', function(settings) {
        return settings;
    });

    if (wp.blocks.getCategories) {
        var cats = wp.blocks.getCategories();
        var hasEnroute = cats.some(function(c) { return c.slug === 'enroute'; });
        if (!hasEnroute) {
            wp.blocks.setCategories([{ slug: 'enroute', title: 'Enroute', icon: null }].concat(cats));
        }
    }

    // ── Color picker ──────────────────────────────────────────────────────────
    function ColorPicker(props) {
        return el('div', { style: { marginTop: '1rem' } },
            el('p', { style: { marginBottom: '0.25rem', fontSize: '0.85rem', fontWeight: 600 } }, __('Background Color', 'enroute_blocks')),
            el('div', { style: { display: 'flex', gap: '0.4rem', flexWrap: 'wrap' } },
                COLORS.map(function(c) {
                    return el('div', {
                        key: c.value,
                        onClick: function() { props.onChange(Number(c.value)); },
                        title: c.label,
                        style: {
                            width: 28, height: 28, borderRadius: '50%',
                            background: c.color,
                            border: Number(props.value) === Number(c.value) ? '3px solid #000' : '2px solid #ccc',
                            cursor: 'pointer',
                        }
                    });
                })
            )
        );
    }

    // ── Post search ───────────────────────────────────────────────────────────
    function PostSearch(props) {
        var postType = props.postType;
        var onChange = props.onChange;
        var currentId = props.value;
        var placeholder = props.placeholder || __('Search…', 'enroute_blocks');

        var state = useState({ search: '', results: [], loading: false, selected: null });
        var s = state[0]; var setS = state[1];

        useEffect(function() {
            if (currentId && !s.selected) {
                fetch(data.ajaxUrl + '?action=enroute_blocks_search&nonce=' + data.nonce + '&post_type=' + postType + '&search=')
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            var found = d.data.find(function(p) { return p.id === currentId; });
                            if (found) setS(function(prev) { return Object.assign({}, prev, { selected: found }); });
                        }
                    });
            }
        }, [currentId]);

        function doSearch(search) {
            setS(function(prev) { return Object.assign({}, prev, { search: search }); });
            if (search.length < 2) { setS(function(prev) { return Object.assign({}, prev, { results: [] }); }); return; }
            setS(function(prev) { return Object.assign({}, prev, { loading: true }); });
            fetch(data.ajaxUrl + '?action=enroute_blocks_search&nonce=' + data.nonce + '&post_type=' + postType + '&search=' + encodeURIComponent(search))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    setS(function(prev) { return Object.assign({}, prev, { loading: false, results: d.success ? d.data : [] }); });
                });
        }

        return el('div', { style: { marginBottom: '1rem' } },
            s.selected && el('div', { style: { display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem', padding: '0.5rem', background: '#f0f0f0' } },
                s.selected.thumb && el('img', { src: s.selected.thumb, style: { width: 40, height: 40, objectFit: 'cover' } }),
                el('span', { style: { flex: 1, fontSize: '0.85rem' } }, s.selected.title),
                el(Button, { isSmall: true, isDestructive: true, onClick: function() { setS(function(prev) { return Object.assign({}, prev, { selected: null }); }); onChange(0); } }, '×')
            ),
            el(TextControl, { label: placeholder, value: s.search, onChange: doSearch }),
            s.loading && el(Spinner, null),
            s.results.length > 0 && el('div', { style: { border: '1px solid #ddd', maxHeight: 200, overflowY: 'auto', background: '#fff' } },
                s.results.map(function(p) {
                    return el('div', {
                        key: p.id,
                        style: { display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.4rem 0.6rem', cursor: 'pointer', borderBottom: '1px solid #f0f0f0' },
                        onClick: function() {
                            setS(function(prev) { return Object.assign({}, prev, { selected: p, results: [], search: '' }); });
                            onChange(p.id);
                        }
                    },
                        p.thumb && el('img', { src: p.thumb, style: { width: 32, height: 32, objectFit: 'cover' } }),
                        el('span', { style: { fontSize: '0.85rem' } }, p.title)
                    );
                })
            )
        );
    }

    function getColor(index) {
        var c = COLORS.find(function(c) { return Number(c.value) === Number(index); });
        return c ? c.color : '#EBD84A';
    }

    // ── OFFER BLOCK ──────────────────────────────────────────────────────────
    var FEATURED_OPTIONS = [{ label: __('— Select featured slot —', 'enroute_blocks'), value: '0' }];
    for (var fi = 1; fi <= 12; fi++) { FEATURED_OPTIONS.push({ label: 'Featured #' + fi, value: String(fi) }); }

    registerBlockType('enroute/featured-offer', {
        title: __('Featured Offer', 'enroute_blocks'),
        icon: 'tag',
        category: 'enroute',
        attributes: {
            postId:      { type: 'number', default: 0 },
            featuredNum: { type: 'number', default: 0 },
            mode:        { type: 'string', default: 'specific' },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block' });
            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Offer', 'enroute_blocks') },
                        el(SelectControl, {
                            label: __('Selection mode', 'enroute_blocks'),
                            value: attrs.mode,
                            options: [
                                { label: __('Specific offer', 'enroute_blocks'), value: 'specific' },
                                { label: __('Featured slot (1–12)', 'enroute_blocks'), value: 'featured' },
                            ],
                            onChange: function(v) { setAttrs({ mode: v }); }
                        }),
                        attrs.mode === 'specific' && el(PostSearch, { postType: 'offer', value: attrs.postId, onChange: function(v) { setAttrs({ postId: v }); }, placeholder: __('Search offers…', 'enroute_blocks') }),
                        attrs.mode === 'featured' && el(SelectControl, {
                            label: __('Featured slot', 'enroute_blocks'),
                            value: String(attrs.featuredNum),
                            options: FEATURED_OPTIONS,
                            onChange: function(v) { setAttrs({ featuredNum: Number(v) }); }
                        })
                    )
                ),
                el('div', { style: { padding: '1rem', background: '#f8f8f8', border: '2px solid #EBD84A', fontFamily: 'Montserrat,sans-serif' } },
                    el('strong', null, '📋 ' + __('Featured Offer', 'enroute_blocks')),
                    el('span', { style: { marginLeft: '0.5rem', fontSize: '0.85rem', color: '#666' } },
                        attrs.mode === 'featured'
                            ? (attrs.featuredNum ? 'Featured #' + attrs.featuredNum : __('— Select slot →', 'enroute_blocks'))
                            : (attrs.postId ? 'ID: ' + attrs.postId : __('— Select in sidebar →', 'enroute_blocks'))
                    )
                )
            );
        },
        save: function() { return null; }
    });

    // ── GUIDE BLOCK ──────────────────────────────────────────────────────────
    registerBlockType('enroute/featured-guide', {
        title: __('Featured Guide', 'enroute_blocks'),
        icon: 'admin-users',
        category: 'enroute',
        attributes: {
            mode:       { type: 'string', default: 'random' },
            postId:     { type: 'number', default: 0 },
            colorIndex: { type: 'number', default: 1 },
            linkText:   { type: 'string', default: 'Zu den Guides' },
            linkUrl:    { type: 'string', default: '' },
            guidesUrl:  { type: 'string', default: '' },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block' });
            var color = getColor(attrs.colorIndex);
            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Guide', 'enroute_blocks') },
                        el(SelectControl, {
                            label: __('Mode', 'enroute_blocks'),
                            value: attrs.mode,
                            options: [
                                { label: __('Random active guide', 'enroute_blocks'), value: 'random' },
                                { label: __('Specific guide', 'enroute_blocks'), value: 'specific' },
                            ],
                            onChange: function(v) { setAttrs({ mode: v }); }
                        }),
                        attrs.mode === 'specific' && el(PostSearch, { postType: 'guide', value: attrs.postId, onChange: function(v) { setAttrs({ postId: v }); }, placeholder: __('Search guides…', 'enroute_blocks') }),
                        el(ColorPicker, { value: attrs.colorIndex, onChange: function(v) { setAttrs({ colorIndex: v }); } }),
                        el(TextControl, { label: __('Link text', 'enroute_blocks'), value: attrs.linkText, onChange: function(v) { setAttrs({ linkText: v }); }, style: { marginTop: '1rem' } }),
                        el(TextControl, { label: __('Guides page URL', 'enroute_blocks'), value: attrs.linkUrl || data.guidesUrl || '', onChange: function(v) { setAttrs({ linkUrl: v }); } })
                    )
                ),
                el('div', { style: { padding: '1rem', background: color, border: '2px solid #ccc', fontFamily: 'Montserrat,sans-serif' } },
                    el('strong', null, '👤 ' + __('Featured Guide', 'enroute_blocks')),
                    el('span', { style: { marginLeft: '0.5rem', fontSize: '0.85rem' } },
                        attrs.mode === 'random' ? __('Random', 'enroute_blocks') : ('ID: ' + attrs.postId)
                    )
                )
            );
        },
        save: function() { return null; }
    });

    // ── EVENT BLOCK ──────────────────────────────────────────────────────────
    registerBlockType('enroute/featured-event', {
        title: __('Featured Event', 'enroute_blocks'),
        icon: 'calendar-alt',
        category: 'enroute',
        attributes: {
            mode:       { type: 'string', default: 'next' },
            postId:     { type: 'number', default: 0 },
            colorIndex: { type: 'number', default: 1 },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block' });
            var color = getColor(attrs.colorIndex);
            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Event', 'enroute_blocks') },
                        el(SelectControl, {
                            label: __('Selection', 'enroute_blocks'),
                            value: attrs.mode,
                            options: [
                                { label: __('Next event', 'enroute_blocks'), value: 'next' },
                                { label: __('Specific event', 'enroute_blocks'), value: 'specific' },
                            ],
                            onChange: function(v) { setAttrs({ mode: v }); }
                        }),
                        attrs.mode === 'specific' && el(PostSearch, { postType: 'event', value: attrs.postId, onChange: function(v) { setAttrs({ postId: v }); }, placeholder: __('Search events…', 'enroute_blocks') }),
                        el(ColorPicker, { value: attrs.colorIndex, onChange: function(v) { setAttrs({ colorIndex: v }); } })
                    )
                ),
                el('div', { style: { padding: '1rem', background: color, border: '2px solid #ccc', fontFamily: 'Montserrat,sans-serif' } },
                    el('strong', null, '📅 ' + __('Featured Event', 'enroute_blocks')),
                    el('span', { style: { marginLeft: '0.5rem', fontSize: '0.85rem' } },
                        attrs.mode === 'next' ? __('Next event', 'enroute_blocks')
                            : (attrs.postId ? 'ID: ' + attrs.postId : __('— Select in sidebar →', 'enroute_blocks'))
                    )
                )
            );
        },
        save: function() { return null; }
    });

    // ── SLIDESHOW BLOCK ──────────────────────────────────────────────────────
    // Each slide: title, text (WYSIWYG: bold, italic, link), link, colour, image.
    var RichText = wp.blockEditor.RichText;

    registerBlockType('enroute/slideshow', {
        title: __('Slideshow', 'enroute_blocks'),
        icon: 'images-alt2',
        category: 'enroute',
        attributes: {
            slides:   { type: 'array', default: [] },
            interval: { type: 'number', default: 6 },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block enroute-editor-wide' });
            var slides = attrs.slides || [];

            function update(i, patch) {
                var next = slides.map(function(s, j) { return j === i ? Object.assign({}, s, patch) : s; });
                setAttrs({ slides: next });
            }
            function add() {
                setAttrs({ slides: slides.concat([{ title: '', text: '', linkUrl: '', colorIndex: (slides.length % 5) + 1, imageId: 0, imageUrl: '' }]) });
            }
            function remove(i) {
                if (!window.confirm(__('Delete this slide?', 'enroute_blocks'))) return;
                setAttrs({ slides: slides.filter(function(s, j) { return j !== i; }) });
            }
            function move(i, d) {
                var j = i + d; if (j < 0 || j >= slides.length) return;
                var next = slides.slice(); var t = next[i]; next[i] = next[j]; next[j] = t;
                setAttrs({ slides: next });
            }
            function pickImage(i) {
                var frame = wp.media({ title: __('Select Image', 'enroute_blocks'), button: { text: __('Use this image', 'enroute_blocks') }, multiple: false });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    update(i, { imageId: att.id, imageUrl: att.sizes && att.sizes.large ? att.sizes.large.url : att.url });
                });
                frame.open();
            }

            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Slideshow', 'enroute_blocks') },
                        el(TextControl, {
                            label: __('Seconds per slide', 'enroute_blocks'), type: 'number', min: 2, max: 30,
                            value: attrs.interval, onChange: function(v) { setAttrs({ interval: Math.max(2, Math.min(30, parseInt(v, 10) || 6)) }); }
                        })
                    )
                ),
                el('div', { style: { border: '2px solid #ccc', padding: '1rem', fontFamily: 'Montserrat,sans-serif', background: '#fafafa' } },
                    el('strong', { style: { display: 'block', marginBottom: '0.75rem' } }, '🖼 ' + __('Slideshow', 'enroute_blocks') + ' (' + slides.length + ')'),
                    slides.map(function(s, i) {
                        return el('div', { key: i, style: { display: 'flex', gap: '1rem', marginBottom: '1rem', background: '#fff', border: '1px solid #ddd' } },
                            el('div', { style: { flex: '0 0 40%', padding: '0.75rem', background: getColor(s.colorIndex) } },
                                el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' } },
                                    el('strong', null, __('Slide', 'enroute_blocks') + ' ' + (i + 1)),
                                    el('span', null,
                                        el(Button, { isSmall: true, icon: 'arrow-up-alt2', label: __('Move up', 'enroute_blocks'), disabled: i === 0, onClick: function() { move(i, -1); } }),
                                        el(Button, { isSmall: true, icon: 'arrow-down-alt2', label: __('Move down', 'enroute_blocks'), disabled: i === slides.length - 1, onClick: function() { move(i, 1); } }),
                                        el(Button, { isSmall: true, isDestructive: true, icon: 'trash', label: __('Delete', 'enroute_blocks'), onClick: function() { remove(i); } })
                                    )
                                ),
                                el(TextControl, { label: __('Title', 'enroute_blocks'), value: s.title || '', onChange: function(v) { update(i, { title: v }); } }),
                                el('p', { style: { margin: '0.5rem 0 0.25rem', fontSize: '11px', fontWeight: 500, textTransform: 'uppercase' } }, __('Text', 'enroute_blocks')),
                                el(RichText, {
                                    tagName: 'div', value: s.text || '', placeholder: __('Text…', 'enroute_blocks'),
                                    allowedFormats: ['core/bold', 'core/italic', 'core/link'],
                                    onChange: function(v) { update(i, { text: v }); },
                                    style: { background: '#fff', padding: '0.5rem', minHeight: 90, fontSize: '0.85rem' }
                                }),
                                el(TextControl, { label: __('Link URL (optional)', 'enroute_blocks'), value: s.linkUrl || '', onChange: function(v) { update(i, { linkUrl: v }); }, style: { marginTop: '0.5rem' } }),
                                el(ColorPicker, { value: s.colorIndex || 1, onChange: function(v) { update(i, { colorIndex: v }); } })
                            ),
                            el('div', { style: { flex: 1, padding: '0.75rem', display: 'flex', flexDirection: 'column', gap: '0.5rem' } },
                                s.imageUrl
                                    ? el('img', { src: s.imageUrl, style: { width: '100%', aspectRatio: '1264 / 690', objectFit: 'cover', display: 'block' } })
                                    : el('div', { style: { width: '100%', aspectRatio: '1264 / 690', background: '#e5e7eb', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#6b7280' } }, __('No image', 'enroute_blocks')),
                                el('div', null,
                                    el(Button, { isSecondary: true, onClick: function() { pickImage(i); } }, s.imageId ? __('Change Image', 'enroute_blocks') : __('Select Image', 'enroute_blocks')),
                                    s.imageId ? el(Button, { isLink: true, isDestructive: true, style: { marginLeft: '0.75rem' }, onClick: function() { update(i, { imageId: 0, imageUrl: '' }); } }, __('Remove', 'enroute_blocks')) : null
                                )
                            )
                        );
                    }),
                    el(Button, { isPrimary: true, onClick: add }, '+ ' + __('Add slide', 'enroute_blocks'))
                )
            );
        },
        save: function() { return null; }
    });

    // ── LOGO CAROUSEL BLOCK ──────────────────────────────────────────────────
    // The logos themselves are managed in the menu "Logos" (one place for all pages).
    var ToggleControl = wp.components.ToggleControl;

    registerBlockType('enroute/logo-carousel', {
        title: __('Logo Carousel', 'enroute_blocks'),
        icon: 'images-alt',
        category: 'enroute',
        attributes: {
            lines: { type: 'boolean', default: true },
            interval: { type: 'number', default: 3 },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block enroute-editor-wide' });
            var count = data.logoCount || 0;
            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Logo Carousel', 'enroute_blocks') },
                        el(ToggleControl, {
                            label: __('Lines above and below', 'enroute_blocks'),
                            checked: attrs.lines !== false, onChange: function(v) { setAttrs({ lines: v }); }
                        }),
                        el(TextControl, {
                            label: __('Seconds per step', 'enroute_blocks'), type: 'number', min: 1, max: 20, step: 0.5,
                            help: __('The logos move on one at a time. Only used when they do not fit in one row.', 'enroute_blocks'),
                            value: attrs.interval, onChange: function(v) { setAttrs({ interval: Math.max(1, Math.min(20, parseFloat(v) || 3)) }); }
                        })
                    )
                ),
                el('div', { style: { borderTop: attrs.lines !== false ? '1px solid #111' : '2px dashed #ccc', borderBottom: attrs.lines !== false ? '1px solid #111' : '2px dashed #ccc', padding: '1.25rem 1rem', fontFamily: 'Montserrat,sans-serif', background: '#fafafa', textAlign: 'center' } },
                    el('strong', { style: { display: 'block', marginBottom: '0.4rem' } }, '🏷 ' + __('Logo Carousel', 'enroute_blocks')),
                    el('p', { style: { margin: '0 0 0.6rem', fontSize: '0.85rem', color: '#555' } },
                        count + ' ' + __('active logos in total. On the website only the logos of the page language are shown, in the saved order.', 'enroute_blocks')
                    ),
                    el('a', { href: data.logosUrl, target: '_blank', rel: 'noopener', className: 'components-button is-secondary' }, __('Manage logos', 'enroute_blocks'))
                )
            );
        },
        save: function() { return null; }
    });

    // ── BLOG BLOCK ───────────────────────────────────────────────────────────
    registerBlockType('enroute/featured-blog', {
        title: __('Featured Blog Post', 'enroute_blocks'),
        icon: 'admin-post',
        category: 'enroute',
        attributes: {
            postId:     { type: 'number', default: 0 },
            colorIndex: { type: 'number', default: 1 },
            mode:       { type: 'string', default: 'newest' },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block' });
            var color = getColor(attrs.colorIndex);
            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Blog Post', 'enroute_blocks') },
                        el(SelectControl, {
                            label: __('Post selection', 'enroute_blocks'),
                            value: attrs.mode,
                            options: [
                                { label: __('Newest blog post (auto)', 'enroute_blocks'), value: 'newest' },
                                { label: __('Specific post', 'enroute_blocks'), value: 'specific' },
                            ],
                            onChange: function(v) { setAttrs({ mode: v }); }
                        }),
                        attrs.mode === 'specific' && el(PostSearch, { postType: 'post', value: attrs.postId, onChange: function(v) { setAttrs({ postId: v }); }, placeholder: __('Search blog posts…', 'enroute_blocks') }),
                        el(ColorPicker, { value: attrs.colorIndex, onChange: function(v) { setAttrs({ colorIndex: v }); } })
                    )
                ),
                el('div', { style: { padding: '1rem', background: color, border: '2px solid #ccc', fontFamily: 'Montserrat,sans-serif' } },
                    el('strong', null, '📝 ' + __('Featured Blog Post', 'enroute_blocks')),
                    el('span', { style: { marginLeft: '0.5rem', fontSize: '0.85rem', color: '#444' } },
                        attrs.mode === 'newest'
                            ? __('Newest post (auto)', 'enroute_blocks')
                            : (attrs.postId ? 'ID: ' + attrs.postId : __('— Select in sidebar →', 'enroute_blocks'))
                    )
                )
            );
        },
        save: function() { return null; }
    });

    // ── CUSTOM BLOCK ─────────────────────────────────────────────────────────
    registerBlockType('enroute/featured-custom', {
        title: __('Featured Custom (Wide)', 'enroute_blocks'),
        icon: 'align-wide',
        category: 'enroute',
        attributes: {
            title:       { type: 'string', default: '' },
            description: { type: 'string', default: '' },
            imageId:     { type: 'number', default: 0 },
            imageUrl:    { type: 'string', default: '' },
            colorIndex:  { type: 'number', default: 1 },
            linkUrl:     { type: 'string', default: '' },
            linkText:    { type: 'string', default: '' },
        },
        edit: function(props) {
            var attrs = props.attributes; var setAttrs = props.setAttributes;
            var blockProps = useBlockProps({ className: 'enroute-editor-block enroute-editor-wide' });
            var color = getColor(attrs.colorIndex);

            function openMedia() {
                var frame = wp.media({
                    title: __('Select Image', 'enroute_blocks'),
                    button: { text: __('Use this image', 'enroute_blocks') },
                    multiple: false,
                });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    setAttrs({ imageId: att.id, imageUrl: att.sizes && att.sizes.large ? att.sizes.large.url : att.url });
                });
                frame.open();
            }

            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Custom Block', 'enroute_blocks') },
                        el(ColorPicker, { value: attrs.colorIndex, onChange: function(v) { setAttrs({ colorIndex: v }); } }),
                        el(TextControl, { label: __('Link URL (optional)', 'enroute_blocks'), value: attrs.linkUrl, onChange: function(v) { setAttrs({ linkUrl: v }); }, style: { marginTop: '1rem' } }),
                        attrs.linkUrl && el(TextControl, { label: __('Link text', 'enroute_blocks'), value: attrs.linkText, onChange: function(v) { setAttrs({ linkText: v }); } }),
                        el('div', { style: { marginTop: '1rem' } },
                            el(Button, { isPrimary: true, onClick: openMedia },
                                attrs.imageId ? __('Change Image', 'enroute_blocks') : __('Select Image', 'enroute_blocks')
                            ),
                            attrs.imageUrl && el('img', { src: attrs.imageUrl, style: { width: '100%', marginTop: '0.5rem', display: 'block' } })
                        )
                    )
                ),
                el('div', { style: { display: 'flex', minHeight: 220, border: '2px solid #ccc', fontFamily: 'Montserrat,sans-serif' } },
                    el('div', { style: { flex: '0 0 50%', background: '#e5e7eb', position: 'relative', minHeight: 220 } },
                        attrs.imageUrl
                            ? el('img', { src: attrs.imageUrl, style: { width: '100%', height: '100%', objectFit: 'cover', display: 'block', position: 'absolute', top: 0, left: 0 } })
                            : el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%', minHeight: 220, color: '#9ca3af', fontSize: '0.85rem', cursor: 'pointer' }, onClick: openMedia },
                                __('Click to select image', 'enroute_blocks'))
                    ),
                    el('div', { style: { flex: 1, padding: '1.5rem', background: color } },
                        el('input', {
                            type: 'text',
                            value: attrs.title,
                            onChange: function(e) { setAttrs({ title: e.target.value }); },
                            placeholder: __('Title…', 'enroute_blocks'),
                            style: { width: '100%', fontWeight: 700, fontSize: '1.25rem', border: 'none', background: 'transparent', fontFamily: 'Montserrat,sans-serif', marginBottom: '0.75rem', outline: 'none', display: 'block' }
                        }),
                        el('textarea', {
                            value: attrs.description,
                            onChange: function(e) { setAttrs({ description: e.target.value }); },
                            placeholder: __('Description…', 'enroute_blocks'),
                            rows: 5,
                            style: { width: '100%', border: 'none', background: 'transparent', fontFamily: 'Montserrat,sans-serif', fontSize: '0.9rem', lineHeight: 1.65, resize: 'vertical', outline: 'none' }
                        })
                    )
                )
            );
        },
        save: function() { return null; }
    });

})();
