/* global enrouteBlocksData, wp */
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, TextControl, TextareaControl, Button, Spinner } = wp.components;
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;

const COLORS = (enrouteBlocksData?.colors || []).map(c => ({
    label: `Color ${c.index}`,
    value: String(c.index),
    color: c.color,
}));
const COLOR_OPTIONS = COLORS.map(c => ({ label: `${c.label} (${c.color})`, value: c.value }));

// ── Search component ──────────────────────────────────────────────────────────
function PostSearch({ postType, value, onChange, placeholder }) {
    const [search, setSearch] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(null);

    useEffect(() => {
        if (value && !selected) {
            // Load selected post title
            fetch(`${enrouteBlocksData.ajaxUrl}?action=enroute_blocks_search&nonce=${enrouteBlocksData.nonce}&post_type=${postType}&search=`)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const found = d.data.find(p => p.id === value);
                        if (found) setSelected(found);
                    }
                });
        }
    }, [value]);

    const doSearch = (s) => {
        setSearch(s);
        if (s.length < 2) { setResults([]); return; }
        setLoading(true);
        fetch(`${enrouteBlocksData.ajaxUrl}?action=enroute_blocks_search&nonce=${enrouteBlocksData.nonce}&post_type=${postType}&search=${encodeURIComponent(s)}`)
            .then(r => r.json())
            .then(d => {
                setLoading(false);
                if (d.success) setResults(d.data);
            });
    };

    return (
        <div style={{marginBottom: '1rem'}}>
            {selected && (
                <div style={{display:'flex', alignItems:'center', gap:'0.5rem', marginBottom:'0.5rem', padding:'0.5rem', background:'#f0f0f0'}}>
                    {selected.thumb && <img src={selected.thumb} style={{width:40,height:40,objectFit:'cover'}} />}
                    <span style={{flex:1, fontSize:'0.85rem'}}>{selected.title}</span>
                    <Button isSmall isDestructive onClick={() => { setSelected(null); onChange(0); }}>×</Button>
                </div>
            )}
            <TextControl
                label={placeholder || __('Search…', 'enroute_blocks')}
                value={search}
                onChange={doSearch}
            />
            {loading && <Spinner />}
            {results.length > 0 && (
                <div style={{border:'1px solid #ddd', maxHeight:200, overflowY:'auto', background:'#fff'}}>
                    {results.map(p => (
                        <div key={p.id}
                            style={{display:'flex', alignItems:'center', gap:'0.5rem', padding:'0.4rem 0.6rem', cursor:'pointer', borderBottom:'1px solid #f0f0f0'}}
                            onClick={() => { setSelected(p); onChange(p.id); setResults([]); setSearch(''); }}
                        >
                            {p.thumb && <img src={p.thumb} style={{width:32,height:32,objectFit:'cover'}} />}
                            <span style={{fontSize:'0.85rem'}}>{p.title}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Color picker row ──────────────────────────────────────────────────────────
function ColorPicker({ value, onChange }) {
    return (
        <div>
            <p style={{marginBottom:'0.25rem', fontSize:'0.85rem', fontWeight:600}}>{__('Background Color', 'enroute_blocks')}</p>
            <div style={{display:'flex', gap:'0.4rem', flexWrap:'wrap'}}>
                {COLORS.map(c => (
                    <div key={c.value}
                        onClick={() => onChange(Number(c.value))}
                        style={{
                            width:28, height:28, borderRadius:'50%',
                            background: c.color,
                            border: Number(value) === Number(c.value) ? '3px solid #000' : '2px solid #ccc',
                            cursor:'pointer',
                        }}
                        title={c.label}
                    />
                ))}
            </div>
        </div>
    );
}

// ── OFFER BLOCK ──────────────────────────────────────────────────────────────
registerBlockType('enroute/featured-offer', {
    title: __('Featured Offer', 'enroute_blocks'),
    icon: 'tag',
    category: 'enroute',
    attributes: {
        postId: { type: 'number', default: 0 },
    },
    edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps({ className: 'enroute-editor-block' });
        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Offer', 'enroute_blocks')}>
                        <PostSearch
                            postType="offer"
                            value={attributes.postId}
                            onChange={v => setAttributes({ postId: v })}
                            placeholder={__('Search offers…', 'enroute_blocks')}
                        />
                    </PanelBody>
                </InspectorControls>
                <div style={{padding:'1rem', background:'#f8f8f8', border:'2px solid #EBD84A', fontFamily:'Montserrat,sans-serif'}}>
                    <strong>📋 {__('Featured Offer', 'enroute_blocks')}</strong>
                    {attributes.postId ? <span style={{marginLeft:'0.5rem', fontSize:'0.85rem', color:'#666'}}>ID: {attributes.postId}</span> : <span style={{marginLeft:'0.5rem', fontSize:'0.85rem', color:'#999'}}>{__('— Select in sidebar →', 'enroute_blocks')}</span>}
                </div>
            </div>
        );
    },
    save: () => null,
});

// ── GUIDE BLOCK ──────────────────────────────────────────────────────────────
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
    },
    edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps({ className: 'enroute-editor-block' });
        const color = COLORS.find(c => Number(c.value) === attributes.colorIndex)?.color || '#EBD84A';
        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Guide', 'enroute_blocks')}>
                        <SelectControl
                            label={__('Mode', 'enroute_blocks')}
                            value={attributes.mode}
                            options={[
                                { label: __('Random active guide', 'enroute_blocks'), value: 'random' },
                                { label: __('Specific guide', 'enroute_blocks'), value: 'specific' },
                            ]}
                            onChange={v => setAttributes({ mode: v })}
                        />
                        {attributes.mode === 'specific' && (
                            <PostSearch
                                postType="guide"
                                value={attributes.postId}
                                onChange={v => setAttributes({ postId: v })}
                                placeholder={__('Search guides…', 'enroute_blocks')}
                            />
                        )}
                        <ColorPicker value={attributes.colorIndex} onChange={v => setAttributes({ colorIndex: v })} />
                        <TextControl
                            label={__('Link text', 'enroute_blocks')}
                            value={attributes.linkText}
                            onChange={v => setAttributes({ linkText: v })}
                            style={{marginTop:'1rem'}}
                        />
                        <TextControl
                            label={__('Guides page URL', 'enroute_blocks')}
                            value={attributes.linkUrl || enrouteBlocksData?.guidesUrl || ''}
                            onChange={v => setAttributes({ linkUrl: v })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div style={{padding:'1rem', background: color, border:'2px solid #ccc', fontFamily:'Montserrat,sans-serif'}}>
                    <strong>👤 {__('Featured Guide', 'enroute_blocks')}</strong>
                    <span style={{marginLeft:'0.5rem', fontSize:'0.85rem'}}>
                        {attributes.mode === 'random' ? __('Random', 'enroute_blocks') : `ID: ${attributes.postId}`}
                    </span>
                </div>
            </div>
        );
    },
    save: () => null,
});

// ── BLOG BLOCK ───────────────────────────────────────────────────────────────
registerBlockType('enroute/featured-blog', {
    title: __('Featured Blog Post', 'enroute_blocks'),
    icon: 'admin-post',
    category: 'enroute',
    attributes: {
        postId:     { type: 'number', default: 0 },
        colorIndex: { type: 'number', default: 1 },
    },
    edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps({ className: 'enroute-editor-block' });
        const color = COLORS.find(c => Number(c.value) === attributes.colorIndex)?.color || '#EBD84A';
        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Blog Post', 'enroute_blocks')}>
                        <PostSearch
                            postType="post"
                            value={attributes.postId}
                            onChange={v => setAttributes({ postId: v })}
                            placeholder={__('Search blog posts…', 'enroute_blocks')}
                        />
                        <ColorPicker value={attributes.colorIndex} onChange={v => setAttributes({ colorIndex: v })} />
                    </PanelBody>
                </InspectorControls>
                <div style={{padding:'1rem', background: color, border:'2px solid #ccc', fontFamily:'Montserrat,sans-serif'}}>
                    <strong>📝 {__('Featured Blog Post', 'enroute_blocks')}</strong>
                    {attributes.postId ? <span style={{marginLeft:'0.5rem', fontSize:'0.85rem', color:'#444'}}>ID: {attributes.postId}</span> : <span style={{marginLeft:'0.5rem', fontSize:'0.85rem', color:'#999'}}>{__('— Select in sidebar →', 'enroute_blocks')}</span>}
                </div>
            </div>
        );
    },
    save: () => null,
});

// ── CUSTOM BLOCK ─────────────────────────────────────────────────────────────
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
    },
    edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps({ className: 'enroute-editor-block enroute-editor-wide' });
        const color = COLORS.find(c => Number(c.value) === attributes.colorIndex)?.color || '#EBD84A';

        const openMediaLibrary = () => {
            const frame = wp.media({
                title: __('Select Image', 'enroute_blocks'),
                button: { text: __('Use this image', 'enroute_blocks') },
                multiple: false,
            });
            frame.on('select', () => {
                const att = frame.state().get('selection').first().toJSON();
                setAttributes({ imageId: att.id, imageUrl: att.url });
            });
            frame.open();
        };

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Custom Block', 'enroute_blocks')}>
                        <ColorPicker value={attributes.colorIndex} onChange={v => setAttributes({ colorIndex: v })} />
                        <div style={{marginTop:'1rem'}}>
                            <Button isPrimary onClick={openMediaLibrary}>
                                {attributes.imageId ? __('Change Image', 'enroute_blocks') : __('Select Image', 'enroute_blocks')}
                            </Button>
                            {attributes.imageUrl && (
                                <img src={attributes.imageUrl} style={{width:'100%', marginTop:'0.5rem', display:'block'}} />
                            )}
                        </div>
                    </PanelBody>
                </InspectorControls>
                <div style={{display:'flex', minHeight:200, border:'2px solid #ccc', fontFamily:'Montserrat,sans-serif'}}>
                    <div style={{flex:'0 0 50%', background:'#e5e7eb', position:'relative', minHeight:200}}>
                        {attributes.imageUrl
                            ? <img src={attributes.imageUrl} style={{width:'100%', height:'100%', objectFit:'cover', display:'block', position:'absolute', top:0, left:0}} />
                            : <div style={{display:'flex', alignItems:'center', justifyContent:'center', height:'100%', color:'#9ca3af', fontSize:'0.85rem'}}>{__('Select image →', 'enroute_blocks')}</div>
                        }
                    </div>
                    <div style={{flex:1, padding:'1.5rem', background: color}}>
                        <input
                            type="text"
                            value={attributes.title}
                            onChange={e => setAttributes({ title: e.target.value })}
                            placeholder={__('Title…', 'enroute_blocks')}
                            style={{width:'100%', fontWeight:700, fontSize:'1.25rem', border:'none', background:'transparent', fontFamily:'Montserrat,sans-serif', marginBottom:'0.75rem', outline:'none'}}
                        />
                        <textarea
                            value={attributes.description}
                            onChange={e => setAttributes({ description: e.target.value })}
                            placeholder={__('Description…', 'enroute_blocks')}
                            rows={5}
                            style={{width:'100%', border:'none', background:'transparent', fontFamily:'Montserrat,sans-serif', fontSize:'0.9rem', lineHeight:1.65, resize:'vertical', outline:'none'}}
                        />
                    </div>
                </div>
            </div>
        );
    },
    save: () => null,
});

// ── Register block category ───────────────────────────────────────────────────
wp.hooks.addFilter(
    'blocks.registerBlockType',
    'enroute/add-category',
    (settings, name) => settings
);
