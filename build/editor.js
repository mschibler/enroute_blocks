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
