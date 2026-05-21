/**
 * Email Builder — Enhanced Visual Email Editor
 * Pinpoint CRM
 * Built with SortableJS for drag-and-drop
 */

const EmailBuilder = (function() {
    'use strict';

    /* ─── State ─── */
    let csrfToken = '';
    let sections = [];
    let body = {
        backgroundColor: '#f4f4f4',
        fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif',
        fontSize: '16',
        textColor: '#333333',
        lineHeight: '1.6',
        linkColor: '#D91C48',
        contentWidth: '600',
        paddingTop: '20',
        paddingBottom: '20',
        paddingLeft: '0',
        paddingRight: '0'
    };
    let selectedId = null; // can be 'body', 'sec_xxx', or 'blk_xxx'
    let selectedSectionId = null;
    let sortableSections = null;
    let sortableBlocks = {}; // keyed by column container id

    /* ─── DOM refs ─── */
    let $canvas, $propertiesPanel, $bodyTab, $previewFrame;

    /* ─── Helpers ─── */
    function generateId(prefix) {
        return prefix + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    }

    function deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function px(val) {
        return val + 'px';
    }

    /* ─── Default block data ─── */
    const blockDefaults = {
        text: {
            content: '<p>Click to edit this text...</p>',
            fontSize: '16',
            fontWeight: '400',
            textAlign: 'left',
            color: '#333333',
            lineHeight: '1.6',
            letterSpacing: 'normal',
            textTransform: 'none',
            fontStyle: 'normal',
            textDecoration: 'none',
            paddingTop: '0',
            paddingBottom: '0',
            paddingLeft: '0',
            paddingRight: '0',
            marginTop: '0',
            marginBottom: '0',
            backgroundColor: '',
            borderRadius: '0',
            borderWidth: '0',
            borderColor: '#000000',
            borderStyle: 'solid'
        },
        image: {
            src: 'https://placehold.co/400x200/e2e2e2/999?text=Image',
            alt: 'Image',
            width: '100%',
            height: 'auto',
            alignment: 'center',
            objectFit: 'cover',
            borderRadius: '0',
            borderWidth: '0',
            borderColor: '#000000',
            borderStyle: 'solid',
            linkUrl: '',
            paddingTop: '0',
            paddingBottom: '0',
            backgroundColor: ''
        },
        button: {
            text: 'Click Here',
            url: '#',
            bgColor: '#D91C48',
            textColor: '#ffffff',
            fontSize: '16',
            fontWeight: '600',
            borderRadius: '4',
            borderWidth: '0',
            borderColor: '#000000',
            borderStyle: 'solid',
            paddingVertical: '12',
            paddingHorizontal: '24',
            alignment: 'center',
            width: 'auto',
            letterSpacing: 'normal',
            textTransform: 'none'
        },
        divider: {
            color: '#dddddd',
            thickness: '1',
            width: '100%',
            style: 'solid',
            alignment: 'center',
            marginTop: '16',
            marginBottom: '16'
        },
        spacer: {
            height: '32',
            backgroundColor: '',
            borderRadius: '0',
            borderWidth: '0',
            borderColor: '#000000',
            borderStyle: 'solid'
        },
        html: {
            content: '<!-- Custom HTML -->',
            paddingTop: '0',
            paddingBottom: '0',
            paddingLeft: '0',
            paddingRight: '0',
            backgroundColor: ''
        },
        social: {
            alignment: 'center',
            iconSize: '24',
            iconColor: '#333333',
            spacing: '8',
            shape: 'rounded',
            showLabels: false,
            platforms: {
                facebook: { url: '', active: false },
                twitter: { url: '', active: false },
                linkedin: { url: '', active: false },
                instagram: { url: '', active: false },
                youtube: { url: '', active: false }
            }
        }
    };

    /* ─── Default section data ─── */
    const sectionDefaults = {
        columns: 1,
        bgColor: '#ffffff',
        paddingTop: '20',
        paddingBottom: '20',
        paddingLeft: '24',
        paddingRight: '24',
        borderTopWidth: '0',
        borderTopColor: '#000000',
        borderTopStyle: 'solid',
        borderBottomWidth: '0',
        borderBottomColor: '#000000',
        borderBottomStyle: 'solid',
        verticalAlign: 'top',
        gap: '24'
    };


    /* ─── Init ─── */
    function init(opts) {
        csrfToken = opts.csrfToken || '';
        $canvas = document.getElementById('email-canvas');
        $propertiesPanel = document.getElementById('properties-panel');
        $bodyTab = document.getElementById('tab-body');
        $previewFrame = document.getElementById('preview-frame');

        // Parse existing JSON
        if (opts.existingJson) {
            try {
                const parsed = JSON.parse(opts.existingJson);
                if (parsed.sections) {
                    sections = parsed.sections;
                    if (parsed.body) Object.assign(body, parsed.body);
                } else if (Array.isArray(parsed)) {
                    // Legacy: flat array of blocks → convert to single-column sections
                    sections = migrateLegacyBlocks(parsed);
                }
            } catch (e) {
                console.warn('Failed to parse existing JSON, starting fresh');
                sections = [];
            }
        }

        renderCanvas();
        initDragDrop();
        showBodyProperties();
    }

    /* ─── Legacy migration ─── */
    function migrateLegacyBlocks(blocks) {
        return blocks.map(function(b) {
            const sec = deepClone(sectionDefaults);
            sec.id = generateId('sec');
            sec.columns = 1;
            sec.content = [[{
                id: generateId('blk'),
                type: b.type || 'text',
                data: b.data || (blockDefaults[b.type] || blockDefaults.text)
            }]];
            return sec;
        });
    }

    /* ─── Section creation ─── */
    function createSection(colCount) {
        const sec = deepClone(sectionDefaults);
        sec.id = generateId('sec');
        sec.columns = colCount;
        sec.content = [];
        for (let i = 0; i < colCount; i++) {
            sec.content.push([]);
        }
        return sec;
    }

    function createBlock(type) {
        const defaults = blockDefaults[type] || blockDefaults.text;
        return {
            id: generateId('blk'),
            type: type,
            data: deepClone(defaults)
        };
    }


    /* ─── Canvas rendering ─── */
    function renderCanvas() {
        if (!$canvas) return;
        $canvas.innerHTML = '';

        sections.forEach(function(sec) {
            const $sec = renderSection(sec);
            $canvas.appendChild($sec);
        });

        initDragDrop();
    }

    function renderSection(sec) {
        const $el = document.createElement('div');
        $el.className = 'email-section';
        $el.dataset.sectionId = sec.id;
        $el.style.backgroundColor = sec.bgColor || '#ffffff';
        $el.style.paddingTop = px(sec.paddingTop || '20');
        $el.style.paddingBottom = px(sec.paddingBottom || '20');
        $el.style.paddingLeft = px(sec.paddingLeft || '24');
        $el.style.paddingRight = px(sec.paddingRight || '24');
        $el.style.borderTop = (sec.borderTopWidth || '0') + 'px ' + (sec.borderTopStyle || 'solid') + ' ' + (sec.borderTopColor || '#000');
        $el.style.borderBottom = (sec.borderBottomWidth || '0') + 'px ' + (sec.borderBottomStyle || 'solid') + ' ' + (sec.borderBottomColor || '#000');
        $el.style.position = 'relative';
        $el.style.marginBottom = '8px';
        $el.style.borderRadius = '4px';

        // Section label/header
        const $header = document.createElement('div');
        $header.className = 'section-header';
        $header.innerHTML = '<span style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:1px;">Section (' + sec.columns + ' col)</span>';
        $header.style.padding = '4px 8px';
        $header.style.background = '#f8f9fa';
        $header.style.borderBottom = '1px dashed #ddd';
        $header.style.cursor = 'pointer';
        $header.style.userSelect = 'none';
        $header.style.display = 'none'; // shown on hover via CSS
        $header.addEventListener('click', function(e) {
            e.stopPropagation();
            selectSection(sec.id);
        });
        $el.appendChild($header);

        // Section content wrapper
        const $wrapper = document.createElement('div');
        $wrapper.className = 'section-wrapper';
        $wrapper.style.display = 'grid';
        $wrapper.style.gridTemplateColumns = 'repeat(' + sec.columns + ', 1fr)';
        $wrapper.style.gap = px(sec.gap || '24');
        $wrapper.style.alignItems = sec.verticalAlign || 'top';
        $wrapper.style.maxWidth = px(body.contentWidth || '600');
        $wrapper.style.margin = '0 auto';

        sec.content.forEach(function(colBlocks, colIdx) {
            const $col = document.createElement('div');
            $col.className = 'email-column';
            $col.dataset.sectionId = sec.id;
            $col.dataset.colIndex = colIdx;
            $col.style.minHeight = '40px';
            $col.style.border = '1px dashed transparent';
            $col.style.transition = 'border-color 0.2s';
            $col.dataset.colId = sec.id + '_col_' + colIdx;

            colBlocks.forEach(function(blk) {
                const $blk = renderBlock(blk, sec.id, colIdx);
                $col.appendChild($blk);
            });

            $wrapper.appendChild($col);
        });

        $el.appendChild($wrapper);

        // Hover show header
        $el.addEventListener('mouseenter', function() { $header.style.display = 'block'; });
        $el.addEventListener('mouseleave', function() { if (selectedId !== sec.id) $header.style.display = 'none'; });

        // Click to select section
        $el.addEventListener('click', function(e) {
            if (e.target === $el || e.target === $wrapper) {
                selectSection(sec.id);
            }
        });

        return $el;
    }

    function renderBlock(blk, sectionId, colIdx) {
        const $el = document.createElement('div');
        $el.className = 'email-block email-block-' + blk.type;
        $el.dataset.blockId = blk.id;
        $el.dataset.sectionId = sectionId;
        $el.dataset.colIndex = colIdx;
        $el.style.position = 'relative';
        $el.style.cursor = 'pointer';
        $el.style.transition = 'box-shadow 0.2s';

        const d = blk.data;

        switch (blk.type) {
            case 'text':
                $el.innerHTML = d.content || '<p>Text</p>';
                applyTextStyles($el, d);
                break;
            case 'image':
                const $img = document.createElement('img');
                $img.src = d.src || 'https://placehold.co/400x200/e2e2e2/999?text=Image';
                $img.alt = d.alt || '';
                $img.style.maxWidth = '100%';
                $img.style.width = d.width || '100%';
                $img.style.height = d.height || 'auto';
                $img.style.objectFit = d.objectFit || 'cover';
                $img.style.borderRadius = px(d.borderRadius || '0');
                $img.style.border = (d.borderWidth || '0') + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000');
                $img.style.display = 'block';
                $img.style.margin = (d.alignment === 'center' ? '0 auto' : d.alignment === 'right' ? '0 0 0 auto' : '0');
                if (d.linkUrl) {
                    const $a = document.createElement('a');
                    $a.href = d.linkUrl;
                    $a.appendChild($img);
                    $el.appendChild($a);
                } else {
                    $el.appendChild($img);
                }
                if (d.paddingTop) $el.style.paddingTop = px(d.paddingTop);
                if (d.paddingBottom) $el.style.paddingBottom = px(d.paddingBottom);
                break;
            case 'button':
                const $btn = document.createElement('a');
                $btn.href = d.url || '#';
                $btn.textContent = d.text || 'Button';
                $btn.style.display = 'inline-block';
                $btn.style.backgroundColor = d.bgColor || '#D91C48';
                $btn.style.color = d.textColor || '#fff';
                $btn.style.fontSize = px(d.fontSize || '16');
                $btn.style.fontWeight = d.fontWeight || '600';
                $btn.style.borderRadius = px(d.borderRadius || '4');
                $btn.style.border = (d.borderWidth || '0') + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000');
                $btn.style.padding = px(d.paddingVertical || '12') + ' ' + px(d.paddingHorizontal || '24');
                $btn.style.textDecoration = 'none';
                $btn.style.textTransform = d.textTransform || 'none';
                $btn.style.letterSpacing = d.letterSpacing === 'normal' ? 'normal' : px(d.letterSpacing || '0');
                $btn.style.textAlign = 'center';

                const $btnWrap = document.createElement('div');
                $btnWrap.style.textAlign = d.alignment || 'center';
                if (d.width === '100%') {
                    $btn.style.display = 'block';
                    $btn.style.width = '100%';
                }
                $btnWrap.appendChild($btn);
                $el.appendChild($btnWrap);
                break;
            case 'divider':
                const $hr = document.createElement('hr');
                $hr.style.border = 'none';
                $hr.style.borderTop = (d.thickness || '1') + 'px ' + (d.style || 'solid') + ' ' + (d.color || '#ddd');
                $hr.style.width = d.width || '100%';
                $hr.style.margin = px(d.marginTop || '16') + ' auto';
                $hr.style.marginBottom = px(d.marginBottom || '16');
                if (d.alignment === 'left') $hr.style.marginLeft = '0';
                if (d.alignment === 'right') $hr.style.marginRight = '0';
                $el.appendChild($hr);
                break;
            case 'spacer':
                $el.style.height = px(d.height || '32');
                $el.style.backgroundColor = d.backgroundColor || 'transparent';
                $el.style.borderRadius = px(d.borderRadius || '0');
                $el.style.border = (d.borderWidth || '0') + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000');
                break;
            case 'html':
                $el.innerHTML = d.content || '';
                if (d.paddingTop) $el.style.paddingTop = px(d.paddingTop);
                if (d.paddingBottom) $el.style.paddingBottom = px(d.paddingBottom);
                if (d.paddingLeft) $el.style.paddingLeft = px(d.paddingLeft);
                if (d.paddingRight) $el.style.paddingRight = px(d.paddingRight);
                $el.style.backgroundColor = d.backgroundColor || 'transparent';
                break;
            case 'social':
                renderSocialBlock($el, d);
                break;
            default:
                $el.innerHTML = '<p>Unknown block: ' + escapeHtml(blk.type) + '</p>';
        }

        // Selection styling
        $el.addEventListener('click', function(e) {
            e.stopPropagation();
            selectBlock(blk.id, sectionId);
        });

        // Hover delete
        addBlockHoverActions($el, blk.id, sectionId, colIdx);

        return $el;
    }

    function applyTextStyles($el, d) {
        $el.style.fontSize = px(d.fontSize || '16');
        $el.style.fontWeight = d.fontWeight || '400';
        $el.style.textAlign = d.textAlign || 'left';
        $el.style.color = d.color || '#333';
        $el.style.lineHeight = d.lineHeight || '1.6';
        $el.style.letterSpacing = d.letterSpacing === 'normal' ? 'normal' : px(d.letterSpacing || '0');
        $el.style.textTransform = d.textTransform || 'none';
        $el.style.fontStyle = d.fontStyle || 'normal';
        $el.style.textDecoration = d.textDecoration || 'none';
        $el.style.paddingTop = px(d.paddingTop || '0');
        $el.style.paddingBottom = px(d.paddingBottom || '0');
        $el.style.paddingLeft = px(d.paddingLeft || '0');
        $el.style.paddingRight = px(d.paddingRight || '0');
        $el.style.marginTop = px(d.marginTop || '0');
        $el.style.marginBottom = px(d.marginBottom || '0');
        $el.style.backgroundColor = d.backgroundColor || 'transparent';
        $el.style.borderRadius = px(d.borderRadius || '0');
        $el.style.border = (d.borderWidth || '0') + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000');
        // Make content editable
        $el.contentEditable = true;
        $el.addEventListener('blur', function() {
            const block = findBlock(sectionId, blk.id);
            if (block) {
                block.data.content = $el.innerHTML;
            }
        });
    }

    function renderSocialBlock($el, d) {
        const $wrap = document.createElement('div');
        $wrap.style.textAlign = d.alignment || 'center';
        $wrap.style.display = 'flex';
        $wrap.style.justifyContent = d.alignment === 'left' ? 'flex-start' : d.alignment === 'right' ? 'flex-end' : 'center';
        $wrap.style.gap = px(d.spacing || '8');
        $wrap.style.flexWrap = 'wrap';

        const platforms = d.platforms || {};
        const icons = {
            facebook: 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z',
            twitter: 'M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z',
            linkedin: 'M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2zM4 6a2 2 0 100-4 2 2 0 000 4z',
            instagram: 'M16 4h-8a4 4 0 00-4 4v8a4 4 0 004 4h8a4 4 0 004-4V8a4 4 0 00-4-4zm-4 12a4 4 0 110-8 4 4 0 010 8zm5-8a1 1 0 110-2 1 1 0 010 2z',
            youtube: 'M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 12a29 29 0 00.46 5.58 2.78 2.78 0 001.94 2C5.12 20 12 20 12 20s6.88 0 8.6-.46a2.78 2.78 0 001.94-2A29 29 0 0023 12a29 29 0 00-.46-5.58zM9.75 15V9l5.2 3z'
        };

        Object.keys(platforms).forEach(function(key) {
            const plat = platforms[key];
            if (!plat.active || !plat.url) return;

            const $a = document.createElement('a');
            $a.href = plat.url;
            $a.target = '_blank';
            $a.style.display = 'inline-flex';
            $a.style.alignItems = 'center';
            $a.style.justifyContent = 'center';
            $a.style.width = px(d.iconSize || '24');
            $a.style.height = px(d.iconSize || '24');
            $a.style.backgroundColor = d.iconColor || '#333';
            $a.style.borderRadius = d.shape === 'circle' ? '50%' : d.shape === 'rounded' ? '4px' : '0';

            const $svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            $svg.setAttribute('viewBox', '0 0 24 24');
            $svg.setAttribute('width', '60%');
            $svg.setAttribute('height', '60%');
            $svg.style.fill = '#fff';
            if (icons[key]) {
                const $path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                $path.setAttribute('d', icons[key]);
                $svg.appendChild($path);
            }
            $a.appendChild($svg);
            $wrap.appendChild($a);
        });

        $el.appendChild($wrap);
    }

    function addBlockHoverActions($el, blockId, sectionId, colIdx) {
        // Add a small delete button on hover
        const $actions = document.createElement('div');
        $actions.className = 'block-actions';
        $actions.style.position = 'absolute';
        $actions.style.top = '2px';
        $actions.style.right = '2px';
        $actions.style.display = 'none';
        $actions.style.gap = '4px';
        $actions.style.zIndex = '10';

        const $del = document.createElement('button');
        $del.innerHTML = '×';
        $del.style.background = '#D91C48';
        $del.style.color = '#fff';
        $del.style.border = 'none';
        $del.style.borderRadius = '3px';
        $del.style.width = '20px';
        $del.style.height = '20px';
        $del.style.fontSize = '14px';
        $del.style.cursor = 'pointer';
        $del.style.lineHeight = '1';
        $del.title = 'Delete block';
        $del.addEventListener('click', function(e) {
            e.stopPropagation();
            removeBlock(blockId);
        });
        $actions.appendChild($del);

        const $dup = document.createElement('button');
        $dup.innerHTML = '⎘';
        $dup.style.background = '#28a745';
        $dup.style.color = '#fff';
        $dup.style.border = 'none';
        $dup.style.borderRadius = '3px';
        $dup.style.width = '20px';
        $dup.style.height = '20px';
        $dup.style.fontSize = '12px';
        $dup.style.cursor = 'pointer';
        $dup.style.lineHeight = '1';
        $dup.title = 'Duplicate';
        $dup.addEventListener('click', function(e) {
            e.stopPropagation();
            duplicateBlock(sectionId, blockId);
        });
        $actions.appendChild($dup);

        $el.appendChild($actions);
        $el.addEventListener('mouseenter', function() { $actions.style.display = 'flex'; });
        $el.addEventListener('mouseleave', function() { $actions.style.display = 'none'; });
    }


    /* ─── Selection ─── */
    function selectSection(id) {
        selectedId = id;
        selectedSectionId = id;
        highlightSelection();
        showSectionProperties(id);
    }

    function selectBlock(id, sectionId) {
        selectedId = id;
        selectedSectionId = sectionId;
        highlightSelection();
        showBlockProperties(id, sectionId);
    }

    function selectBody() {
        selectedId = 'body';
        selectedSectionId = null;
        highlightSelection();
        showBodyProperties();
    }

    function highlightSelection() {
        document.querySelectorAll('.email-section').forEach(function(s) {
            s.style.outline = s.dataset.sectionId === selectedId ? '2px solid #D91C48' : 'none';
            s.querySelector('.section-header').style.display = s.dataset.sectionId === selectedId ? 'block' : 'none';
        });
        document.querySelectorAll('.email-block').forEach(function(b) {
            b.style.outline = b.dataset.blockId === selectedId ? '2px solid #D91C48' : 'none';
        });
    }

    /* ─── Drag & Drop (SortableJS) ─── */
    function initDragDrop() {
        if (!window.Sortable) return;

        // Section sorting
        if (sortableSections) sortableSections.destroy();
        sortableSections = new Sortable($canvas, {
            group: 'sections',
            handle: '.section-header',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                if (evt.oldIndex === evt.newIndex) return;
                const item = sections.splice(evt.oldIndex, 1)[0];
                sections.splice(evt.newIndex, 0, item);
                renderCanvas();
            }
        });

        // Block sorting within columns
        Object.keys(sortableBlocks).forEach(function(k) {
            if (sortableBlocks[k]) sortableBlocks[k].destroy();
        });
        sortableBlocks = {};

        document.querySelectorAll('.email-column').forEach(function($col) {
            const sid = $col.dataset.sectionId;
            const cid = parseInt($col.dataset.colIndex);
            sortableBlocks[sid + '_' + cid] = new Sortable($col, {
                group: {
                    name: 'blocks',
                    pull: function(to, from) {
                        // Allow pulling to other columns
                        return true;
                    },
                    put: function(to, from, dragEl) {
                        // Only allow dropping in columns
                        return to.el.classList.contains('email-column');
                    }
                },
                handle: '.email-block',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function(evt) {
                    const fromSid = evt.from.dataset.sectionId;
                    const fromCol = parseInt(evt.from.dataset.colIndex);
                    const toSid = evt.to.dataset.sectionId;
                    const toCol = parseInt(evt.to.dataset.colIndex);
                    const oldIdx = evt.oldIndex;
                    const newIdx = evt.newIndex;

                    // Find and move block in data
                    moveBlockData(fromSid, fromCol, oldIdx, toSid, toCol, newIdx);
                    renderCanvas();
                }
            });
        });

        // Palette → canvas drop
        const $paletteItems = document.getElementById('palette-items');
        if ($paletteItems) {
            document.querySelectorAll('.palette-item').forEach(function($item) {
                $item.draggable = true;
                $item.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('blockType', $item.dataset.type);
                    e.dataTransfer.setData('sectionType', $item.dataset.section);
                });
            });
        }

        // Canvas drop zones
        document.querySelectorAll('.email-column').forEach(function($col) {
            $col.addEventListener('dragover', function(e) { e.preventDefault(); $col.style.borderColor = '#D91C48'; });
            $col.addEventListener('dragleave', function(e) { $col.style.borderColor = 'transparent'; });
            $col.addEventListener('drop', function(e) {
                e.preventDefault();
                $col.style.borderColor = 'transparent';
                const blockType = e.dataTransfer.getData('blockType');
                if (blockType) {
                    const sid = $col.dataset.sectionId;
                    const colIdx = parseInt($col.dataset.colIndex);
                    addBlock(sid, colIdx, blockType);
                }
            });
        });
    }

    function moveBlockData(fromSid, fromCol, fromIdx, toSid, toCol, toIdx) {
        const fromSec = sections.find(function(s) { return s.id === fromSid; });
        if (!fromSec) return;
        const block = fromSec.content[fromCol].splice(fromIdx, 1)[0];
        if (!block) return;

        const toSec = sections.find(function(s) { return s.id === toSid; });
        if (!toSec) return;
        toSec.content[toCol].splice(toIdx, 0, block);
    }

    /* ─── CRUD operations ─── */
    function addSection(colCount) {
        sections.push(createSection(colCount));
        renderCanvas();
        selectSection(sections[sections.length - 1].id);
    }

    function addBlock(sectionId, colIndex, type) {
        const sec = sections.find(function(s) { return s.id === sectionId; });
        if (!sec) return;
        const blk = createBlock(type);
        sec.content[colIndex].push(blk);
        renderCanvas();
        selectBlock(blk.id, sectionId);
    }

    function removeSection(id) {
        sections = sections.filter(function(s) { return s.id !== id; });
        selectedId = null;
        renderCanvas();
        selectBody();
    }

    function removeBlock(id) {
        sections.forEach(function(sec) {
            sec.content.forEach(function(col) {
                for (var i = col.length - 1; i >= 0; i--) {
                    if (col[i].id === id) col.splice(i, 1);
                }
            });
        });
        selectedId = null;
        renderCanvas();
    }

    function duplicateSection(id) {
        const sec = sections.find(function(s) { return s.id === id; });
        if (!sec) return;
        const dup = deepClone(sec);
        dup.id = generateId('sec');
        dup.content.forEach(function(col) {
            col.forEach(function(b) { b.id = generateId('blk'); });
        });
        const idx = sections.findIndex(function(s) { return s.id === id; });
        sections.splice(idx + 1, 0, dup);
        renderCanvas();
        selectSection(dup.id);
    }

    function duplicateBlock(sectionId, blockId) {
        const sec = sections.find(function(s) { return s.id === sectionId; });
        if (!sec) return;
        let found = null, colIdx = 0, blkIdx = 0;
        sec.content.forEach(function(col, cidx) {
            col.forEach(function(b, bidx) {
                if (b.id === blockId) { found = deepClone(b); colIdx = cidx; blkIdx = bidx; }
            });
        });
        if (!found) return;
        found.id = generateId('blk');
        sec.content[colIdx].splice(blkIdx + 1, 0, found);
        renderCanvas();
        selectBlock(found.id, sectionId);
    }


    /* ─── Properties Panel ─── */
    function showBodyProperties() {
        if (!$propertiesPanel) return;
        $propertiesPanel.innerHTML = '<h3>Email Body Settings</h3>';

        const fields = [
            { key: 'backgroundColor', label: 'Background Color', type: 'color' },
            { key: 'fontFamily', label: 'Font Family', type: 'select', options: [
                { value: 'system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif', label: 'System UI' },
                { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
                { value: 'Georgia, serif', label: 'Georgia' },
                { value: 'Helvetica, Arial, sans-serif', label: 'Helvetica' },
                { value: 'Trebuchet MS, sans-serif', label: 'Trebuchet MS' },
                { value: 'Verdana, sans-serif', label: 'Verdana' }
            ]},
            { key: 'fontSize', label: 'Font Size (px)', type: 'range', min: 12, max: 18, step: 1 },
            { key: 'textColor', label: 'Text Color', type: 'color' },
            { key: 'lineHeight', label: 'Line Height', type: 'select', options: [
                { value: '1.2', label: '1.2' }, { value: '1.4', label: '1.4' },
                { value: '1.6', label: '1.6' }, { value: '1.8', label: '1.8' }, { value: '2.0', label: '2.0' }
            ]},
            { key: 'linkColor', label: 'Link Color', type: 'color' },
            { key: 'contentWidth', label: 'Content Width (px)', type: 'select', options: [
                { value: '480', label: '480px' }, { value: '540', label: '540px' },
                { value: '600', label: '600px' }, { value: '640', label: '640px' }
            ]},
            { key: 'paddingTop', label: 'Top Padding (px)', type: 'range', min: 0, max: 60 },
            { key: 'paddingBottom', label: 'Bottom Padding (px)', type: 'range', min: 0, max: 60 }
        ];

        fields.forEach(function(f) {
            const $wrap = document.createElement('div');
            $wrap.className = 'prop-field';
            $wrap.style.marginBottom = '16px';

            const $label = document.createElement('label');
            $label.textContent = f.label;
            $label.style.display = 'block';
            $label.style.fontSize = '13px';
            $label.style.color = '#666';
            $label.style.marginBottom = '4px';
            $wrap.appendChild($label);

            let $input;
            if (f.type === 'color') {
                $input = document.createElement('input');
                $input.type = 'color';
                $input.value = body[f.key] || '#000000';
            } else if (f.type === 'select') {
                $input = document.createElement('select');
                $input.style.width = '100%';
                $input.style.padding = '6px';
                f.options.forEach(function(opt) {
                    const $opt = document.createElement('option');
                    $opt.value = opt.value;
                    $opt.textContent = opt.label;
                    $opt.selected = (body[f.key] === opt.value);
                    $input.appendChild($opt);
                });
            } else if (f.type === 'range') {
                $input = document.createElement('input');
                $input.type = 'range';
                $input.min = f.min;
                $input.max = f.max;
                $input.step = f.step || 1;
                $input.value = body[f.key] || f.min;
                $input.style.width = '100%';
            }

            $input.addEventListener('change', function() {
                body[f.key] = $input.value;
                renderCanvas();
                renderPreview();
            });

            $wrap.appendChild($input);
            $propertiesPanel.appendChild($wrap);
        });
    }

    function showSectionProperties(id) {
        const sec = sections.find(function(s) { return s.id === id; });
        if (!sec || !$propertiesPanel) return;
        $propertiesPanel.innerHTML = '';

        const $title = document.createElement('h3');
        $title.textContent = 'Section Settings';
        $propertiesPanel.appendChild($title);

        // Columns selector
        const $colWrap = document.createElement('div');
        $colWrap.style.marginBottom = '16px';
        const $colLabel = document.createElement('label');
        $colLabel.textContent = 'Columns';
        $colLabel.style.display = 'block';
        $colLabel.style.fontSize = '13px';
        $colLabel.style.color = '#666';
        $colLabel.style.marginBottom = '8px';
        $colWrap.appendChild($colLabel);

        [1, 2, 3, 4].forEach(function(n) {
            const $btn = document.createElement('button');
            $btn.textContent = n + ' Col';
            $btn.style.marginRight = '6px';
            $btn.style.padding = '6px 12px';
            $btn.style.border = sec.columns === n ? '2px solid #D91C48' : '1px solid #ddd';
            $btn.style.background = sec.columns === n ? '#D91C48' : '#fff';
            $btn.style.color = sec.columns === n ? '#fff' : '#333';
            $btn.style.borderRadius = '4px';
            $btn.style.cursor = 'pointer';
            $btn.addEventListener('click', function() {
                changeSectionColumns(id, n);
            });
            $colWrap.appendChild($btn);
        });
        $propertiesPanel.appendChild($colWrap);

        // Section fields
        const fields = [
            { key: 'bgColor', label: 'Background Color', type: 'color' },
            { key: 'paddingTop', label: 'Top Padding (px)', type: 'range', min: 0, max: 60 },
            { key: 'paddingBottom', label: 'Bottom Padding (px)', type: 'range', min: 0, max: 60 },
            { key: 'paddingLeft', label: 'Left Padding (px)', type: 'range', min: 0, max: 60 },
            { key: 'paddingRight', label: 'Right Padding (px)', type: 'range', min: 0, max: 60 },
            { key: 'borderTopWidth', label: 'Border Top Width (px)', type: 'range', min: 0, max: 10 },
            { key: 'borderTopColor', label: 'Border Top Color', type: 'color' },
            { key: 'borderBottomWidth', label: 'Border Bottom Width (px)', type: 'range', min: 0, max: 10 },
            { key: 'borderBottomColor', label: 'Border Bottom Color', type: 'color' },
            { key: 'gap', label: 'Column Gap (px)', type: 'range', min: 0, max: 48 }
        ];

        fields.forEach(function(f) {
            const $wrap = document.createElement('div');
            $wrap.className = 'prop-field';
            $wrap.style.marginBottom = '12px';

            const $label = document.createElement('label');
            $label.textContent = f.label;
            $label.style.display = 'block';
            $label.style.fontSize = '13px';
            $label.style.color = '#666';
            $label.style.marginBottom = '4px';
            $wrap.appendChild($label);

            let $input;
            if (f.type === 'color') {
                $input = document.createElement('input');
                $input.type = 'color';
                $input.value = sec[f.key] || '#000000';
            } else {
                $input = document.createElement('input');
                $input.type = 'range';
                $input.min = f.min;
                $input.max = f.max;
                $input.value = sec[f.key] || f.min;
                $input.style.width = '100%';
            }

            $input.addEventListener('change', function() {
                sec[f.key] = $input.value;
                renderCanvas();
            });

            $wrap.appendChild($input);
            $propertiesPanel.appendChild($wrap);
        });

        // Delete button
        const $del = document.createElement('button');
        $del.textContent = 'Delete Section';
        $del.style.width = '100%';
        $del.style.padding = '10px';
        $del.style.background = '#D91C48';
        $del.style.color = '#fff';
        $del.style.border = 'none';
        $del.style.borderRadius = '4px';
        $del.style.marginTop = '16px';
        $del.style.cursor = 'pointer';
        $del.addEventListener('click', function() { removeSection(id); });
        $propertiesPanel.appendChild($del);
    }

    function showBlockProperties(id, sectionId) {
        const sec = sections.find(function(s) { return s.id === sectionId; });
        if (!sec || !$propertiesPanel) return;
        let block = null;
        sec.content.forEach(function(col) {
            col.forEach(function(b) { if (b.id === id) block = b; });
        });
        if (!block) return;

        $propertiesPanel.innerHTML = '';
        const $title = document.createElement('h3');
        $title.textContent = block.type.charAt(0).toUpperCase() + block.type.slice(1) + ' Properties';
        $propertiesPanel.appendChild($title);

        // Merge tags helper for text blocks
        if (block.type === 'text') {
            const $tags = document.createElement('div');
            $tags.style.marginBottom = '12px';
            $tags.innerHTML = '<span style="font-size:12px;color:#999;">Merge tags: </span>';
            ['{{company_name}}', '{{contact_person}}', '{{email}}', '{{unsubscribe_url}}'].forEach(function(tag) {
                const $tagBtn = document.createElement('button');
                $tagBtn.textContent = tag;
                $tagBtn.style.fontSize = '11px';
                $tagBtn.style.padding = '2px 6px';
                $tagBtn.style.margin = '2px';
                $tagBtn.style.border = '1px solid #ddd';
                $tagBtn.style.background = '#f8f9fa';
                $tagBtn.style.borderRadius = '3px';
                $tagBtn.style.cursor = 'pointer';
                $tagBtn.addEventListener('click', function() {
                    block.data.content = (block.data.content || '') + ' ' + tag + ' ';
                    renderCanvas();
                });
                $tags.appendChild($tagBtn);
            });
            $propertiesPanel.appendChild($tags);
        }

        // Build fields based on block type
        const fieldDefs = getBlockFieldDefinitions(block.type);
        fieldDefs.forEach(function(f) {
            const $wrap = document.createElement('div');
            $wrap.className = 'prop-field';
            $wrap.style.marginBottom = '12px';

            const $label = document.createElement('label');
            $label.textContent = f.label;
            $label.style.display = 'block';
            $label.style.fontSize = '13px';
            $label.style.color = '#666';
            $label.style.marginBottom = '4px';
            $wrap.appendChild($label);

            let $input = buildInput(f, block.data[f.key]);
            $input.addEventListener('change', function() {
                block.data[f.key] = $input.value;
                renderCanvas();
            });
            $wrap.appendChild($input);
            $propertiesPanel.appendChild($wrap);
        });

        // Delete button
        const $del = document.createElement('button');
        $del.textContent = 'Delete Block';
        $del.style.width = '100%';
        $del.style.padding = '10px';
        $del.style.background = '#D91C48';
        $del.style.color = '#fff';
        $del.style.border = 'none';
        $del.style.borderRadius = '4px';
        $del.style.marginTop = '16px';
        $del.style.cursor = 'pointer';
        $del.addEventListener('click', function() { removeBlock(id); });
        $propertiesPanel.appendChild($del);
    }

    function buildInput(f, value) {
        let $input;
        if (f.type === 'color') {
            $input = document.createElement('input');
            $input.type = 'color';
            $input.value = value || '#000000';
        } else if (f.type === 'select') {
            $input = document.createElement('select');
            $input.style.width = '100%';
            $input.style.padding = '6px';
            f.options.forEach(function(opt) {
                const $opt = document.createElement('option');
                $opt.value = opt.value;
                $opt.textContent = opt.label;
                $opt.selected = (String(value) === String(opt.value));
                $input.appendChild($opt);
            });
        } else if (f.type === 'range') {
            $input = document.createElement('input');
            $input.type = 'range';
            $input.min = f.min;
            $input.max = f.max;
            $input.step = f.step || 1;
            $input.value = value || f.min;
            $input.style.width = '100%';
        } else if (f.type === 'textarea') {
            $input = document.createElement('textarea');
            $input.value = value || '';
            $input.rows = 4;
            $input.style.width = '100%';
            $input.style.padding = '6px';
        } else {
            $input = document.createElement('input');
            $input.type = 'text';
            $input.value = value || '';
            $input.style.width = '100%';
            $input.style.padding = '6px';
        }
        return $input;
    }

    function getBlockFieldDefinitions(type) {
        const defs = {
            text: [
                { key: 'content', label: 'Content', type: 'textarea' },
                { key: 'fontSize', label: 'Font Size (px)', type: 'range', min: 10, max: 48 },
                { key: 'fontWeight', label: 'Font Weight', type: 'select', options: [
                    { value: '400', label: 'Normal' }, { value: '500', label: 'Medium' },
                    { value: '600', label: 'Semi-bold' }, { value: '700', label: 'Bold' },
                    { value: '800', label: 'Extra-bold' }
                ]},
                { key: 'textAlign', label: 'Text Align', type: 'select', options: [
                    { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' }, { value: 'justify', label: 'Justify' }
                ]},
                { key: 'color', label: 'Text Color', type: 'color' },
                { key: 'lineHeight', label: 'Line Height', type: 'select', options: [
                    { value: '1.2', label: '1.2' }, { value: '1.4', label: '1.4' },
                    { value: '1.6', label: '1.6' }, { value: '1.8', label: '1.8' }, { value: '2.0', label: '2.0' }
                ]},
                { key: 'backgroundColor', label: 'Background', type: 'color' },
                { key: 'borderRadius', label: 'Border Radius (px)', type: 'range', min: 0, max: 30 },
                { key: 'paddingTop', label: 'Top Padding (px)', type: 'range', min: 0, max: 40 },
                { key: 'paddingBottom', label: 'Bottom Padding (px)', type: 'range', min: 0, max: 40 }
            ],
            image: [
                { key: 'src', label: 'Image URL', type: 'text' },
                { key: 'alt', label: 'Alt Text', type: 'text' },
                { key: 'width', label: 'Width', type: 'select', options: [
                    { value: '100%', label: 'Full Width' }, { value: 'auto', label: 'Auto' }
                ]},
                { key: 'alignment', label: 'Alignment', type: 'select', options: [
                    { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' }
                ]},
                { key: 'objectFit', label: 'Object Fit', type: 'select', options: [
                    { value: 'cover', label: 'Cover' }, { value: 'contain', label: 'Contain' },
                    { value: 'fill', label: 'Fill' }
                ]},
                { key: 'borderRadius', label: 'Border Radius (px)', type: 'range', min: 0, max: 30 },
                { key: 'linkUrl', label: 'Link URL', type: 'text' }
            ],
            button: [
                { key: 'text', label: 'Button Text', type: 'text' },
                { key: 'url', label: 'URL', type: 'text' },
                { key: 'bgColor', label: 'Background Color', type: 'color' },
                { key: 'textColor', label: 'Text Color', type: 'color' },
                { key: 'fontSize', label: 'Font Size (px)', type: 'range', min: 10, max: 24 },
                { key: 'borderRadius', label: 'Border Radius (px)', type: 'range', min: 0, max: 30 },
                { key: 'paddingVertical', label: 'Vertical Padding (px)', type: 'range', min: 4, max: 24 },
                { key: 'paddingHorizontal', label: 'Horizontal Padding (px)', type: 'range', min: 8, max: 48 },
                { key: 'alignment', label: 'Alignment', type: 'select', options: [
                    { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' }
                ]},
                { key: 'width', label: 'Width', type: 'select', options: [
                    { value: 'auto', label: 'Auto' }, { value: '100%', label: 'Full Width' }
                ]}
            ],
            divider: [
                { key: 'color', label: 'Color', type: 'color' },
                { key: 'thickness', label: 'Thickness (px)', type: 'range', min: 1, max: 10 },
                { key: 'width', label: 'Width', type: 'select', options: [
                    { value: '100%', label: 'Full Width' }, { value: '75%', label: '75%' },
                    { value: '50%', label: '50%' }, { value: '25%', label: '25%' }
                ]},
                { key: 'style', label: 'Style', type: 'select', options: [
                    { value: 'solid', label: 'Solid' }, { value: 'dashed', label: 'Dashed' },
                    { value: 'dotted', label: 'Dotted' }, { value: 'double', label: 'Double' }
                ]},
                { key: 'alignment', label: 'Alignment', type: 'select', options: [
                    { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' }
                ]}
            ],
            spacer: [
                { key: 'height', label: 'Height (px)', type: 'range', min: 0, max: 200 },
                { key: 'backgroundColor', label: 'Background', type: 'color' }
            ],
            html: [
                { key: 'content', label: 'HTML Content', type: 'textarea' },
                { key: 'backgroundColor', label: 'Background', type: 'color' }
            ],
            social: [
                { key: 'alignment', label: 'Alignment', type: 'select', options: [
                    { value: 'left', label: 'Left' }, { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' }
                ]},
                { key: 'iconSize', label: 'Icon Size (px)', type: 'range', min: 16, max: 32 },
                { key: 'spacing', label: 'Spacing (px)', type: 'select', options: [
                    { value: '4', label: '4px' }, { value: '8', label: '8px' },
                    { value: '12', label: '12px' }, { value: '16', label: '16px' }
                ]},
                { key: 'shape', label: 'Icon Shape', type: 'select', options: [
                    { value: 'square', label: 'Square' }, { value: 'rounded', label: 'Rounded' },
                    { value: 'circle', label: 'Circle' }
                ]}
            ]
        };
        return defs[type] || [];
    }

    function changeSectionColumns(id, newCount) {
        const sec = sections.find(function(s) { return s.id === id; });
        if (!sec) return;
        if (sec.columns === newCount) return;

        // Flatten existing blocks
        const allBlocks = [];
        sec.content.forEach(function(col) {
            col.forEach(function(b) { allBlocks.push(b); });
        });

        // Rebuild columns
        sec.columns = newCount;
        sec.content = [];
        for (let i = 0; i < newCount; i++) {
            sec.content.push([]);
        }

        // Distribute blocks round-robin
        allBlocks.forEach(function(b, idx) {
            sec.content[idx % newCount].push(b);
        });

        renderCanvas();
        selectSection(id);
    }


    /* ─── HTML Generation (table-based for email clients) ─── */
    function getHTML() {
        var fontFamily = body.fontFamily || 'system-ui, sans-serif';
        var contentWidth = body.contentWidth || '600';
        var bgColor = body.backgroundColor || '#f4f4f4';
        var textColor = body.textColor || '#333333';
        var fontSize = body.fontSize || '16';
        var lineHeight = body.lineHeight || '1.6';
        var linkColor = body.linkColor || '#D91C48';

        var html = '<!DOCTYPE html>\n';
        html += '<html lang="en">\n';
        html += '<head>\n';
        html += '  <meta charset="UTF-8">\n';
        html += '  <meta name="viewport" content="width=device-width, initial-scale=1.0">\n';
        html += '  <title>Email</title>\n';
        html += '  <style>\n';
        html += '    @media only screen and (max-width: 600px) {\n';
        html += '      .email-col { display: block !important; width: 100% !important; }\n';
        html += '      .email-col-inner { width: 100% !important; }\n';
        html += '    }\n';
        html += '  </style>\n';
        html += '</head>\n';
        html += '<body style="margin:0;padding:0;background-color:' + bgColor + ';font-family:' + fontFamily + ';font-size:' + fontSize + 'px;line-height:' + lineHeight + ';color:' + textColor + ';">\n';
        html += '  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">\n';
        html += '    <tr>\n';
        html += '      <td align="center" valign="top" style="padding:' + (body.paddingTop || '20') + 'px 0;' + (body.paddingBottom || '20') + 'px 0;">\n';
        html += '        <table role="presentation" width="' + contentWidth + '" cellspacing="0" cellpadding="0" border="0" style="max-width:' + contentWidth + 'px;width:100%;">\n';

        sections.forEach(function(sec) {
            var secBg = sec.bgColor || '#ffffff';
            var secPadTop = sec.paddingTop || '20';
            var secPadBottom = sec.paddingBottom || '20';
            var secPadLeft = sec.paddingLeft || '24';
            var secPadRight = sec.paddingRight || '24';
            var borderTop = (sec.borderTopWidth || '0') + 'px ' + (sec.borderTopStyle || 'solid') + ' ' + (sec.borderTopColor || 'transparent');
            var borderBottom = (sec.borderBottomWidth || '0') + 'px ' + (sec.borderBottomStyle || 'solid') + ' ' + (sec.borderBottomColor || 'transparent');

            html += '          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:' + secBg + ';border-top:' + borderTop + ';border-bottom:' + borderBottom + ';">\n';
            html += '            <tr>\n';
            html += '              <td style="padding:' + secPadTop + 'px ' + secPadRight + 'px ' + secPadBottom + 'px ' + secPadLeft + 'px;">\n';
            html += '                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">\n';
            html += '                  <tr>\n';

            sec.content.forEach(function(col, colIdx) {
                var colWidth = Math.floor(100 / sec.columns);
                html += '                    <td class="email-col" width="' + colWidth + '%" valign="top" style="padding:0;">\n';
                html += '                      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-col-inner">\n';
                html += '                        <tr>\n';
                html += '                          <td valign="top" style="padding:0;' + (colIdx > 0 ? 'padding-left:' + (sec.gap || '24') + 'px;' : '') + (colIdx < sec.columns - 1 ? 'padding-right:' + (sec.gap || '24') + 'px;' : '') + '">\n';

                col.forEach(function(blk) {
                    html += renderBlockHTML(blk, linkColor);
                });

                html += '                          </td>\n';
                html += '                        </tr>\n';
                html += '                      </table>\n';
                html += '                    </td>\n';
            });

            html += '                  </tr>\n';
            html += '                </table>\n';
            html += '              </td>\n';
            html += '            </tr>\n';
            html += '          </table>\n';
        });

        html += '        </table>\n';
        html += '      </td>\n';
        html += '    </tr>\n';
        html += '  </table>\n';
        html += '</body>\n';
        html += '</html>\n';

        return html;
    }

    function renderBlockHTML(blk, linkColor) {
        var d = blk.data;
        var html = '';

        switch (blk.type) {
            case 'text':
                html += '                            <div style="';
                html += 'font-size:' + (d.fontSize || '16') + 'px;';
                html += 'font-weight:' + (d.fontWeight || '400') + ';';
                html += 'text-align:' + (d.textAlign || 'left') + ';';
                html += 'color:' + (d.color || '#333') + ';';
                html += 'line-height:' + (d.lineHeight || '1.6') + ';';
                html += 'letter-spacing:' + (d.letterSpacing === 'normal' ? 'normal' : (d.letterSpacing || '0') + 'px') + ';';
                html += 'text-transform:' + (d.textTransform || 'none') + ';';
                html += 'font-style:' + (d.fontStyle || 'normal') + ';';
                html += 'text-decoration:' + (d.textDecoration || 'none') + ';';
                html += 'padding:' + (d.paddingTop || '0') + 'px ' + (d.paddingRight || '0') + 'px ' + (d.paddingBottom || '0') + 'px ' + (d.paddingLeft || '0') + 'px;';
                html += 'margin:' + (d.marginTop || '0') + 'px 0 ' + (d.marginBottom || '0') + 'px;';
                if (d.backgroundColor) html += 'background-color:' + d.backgroundColor + ';';
                if (d.borderRadius) html += 'border-radius:' + d.borderRadius + 'px;';
                if (d.borderWidth && parseInt(d.borderWidth) > 0) html += 'border:' + d.borderWidth + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000') + ';';
                html += '"';
                html += '>' + (d.content || '') + '</div>\n';
                break;

            case 'image':
                html += '                            <div style="text-align:' + (d.alignment || 'center') + ';padding:' + (d.paddingTop || '0') + 'px 0 ' + (d.paddingBottom || '0') + 'px;"';
                if (d.backgroundColor) html += ' style="background-color:' + d.backgroundColor + ';"';
                html += '>\n';
                if (d.linkUrl) html += '                              <a href="' + escapeHtml(d.linkUrl) + '" target="_blank">\n';
                html += '                              <img src="' + escapeHtml(d.src) + '" alt="' + escapeHtml(d.alt || '') + '" width="' + (d.width === '100%' ? '100%' : 'auto') + '" height="auto" style="max-width:100%;height:auto;border-radius:' + (d.borderRadius || '0') + 'px;display:block;margin:0 auto;';
                if (d.borderWidth && parseInt(d.borderWidth) > 0) html += 'border:' + d.borderWidth + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000') + ';';
                html += '"/>\n';
                if (d.linkUrl) html += '                              </a>\n';
                html += '                            </div>\n';
                break;

            case 'button':
                html += '                            <div style="text-align:' + (d.alignment || 'center') + ';margin:' + (d.marginTop || '0') + 'px 0 ' + (d.marginBottom || '0') + 'px;">\n';
                html += '                              <a href="' + escapeHtml(d.url || '#') + '" style="';
                html += 'display:' + (d.width === '100%' ? 'block;width:100%;' : 'inline-block;') + ';';
                html += 'background-color:' + (d.bgColor || '#D91C48') + ';';
                html += 'color:' + (d.textColor || '#ffffff') + ';';
                html += 'font-size:' + (d.fontSize || '16') + 'px;';
                html += 'font-weight:' + (d.fontWeight || '600') + ';';
                html += 'border-radius:' + (d.borderRadius || '4') + 'px;';
                html += 'padding:' + (d.paddingVertical || '12') + 'px ' + (d.paddingHorizontal || '24') + 'px;';
                html += 'text-decoration:none;text-transform:' + (d.textTransform || 'none') + ';';
                html += 'letter-spacing:' + (d.letterSpacing === 'normal' ? 'normal' : (d.letterSpacing || '0') + 'px') + ';';
                if (d.borderWidth && parseInt(d.borderWidth) > 0) html += 'border:' + d.borderWidth + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000') + ';';
                html += 'text-align:center;';
                html += '"';
                html += '>' + escapeHtml(d.text || 'Button') + '</a>\n';
                html += '                            </div>\n';
                break;

            case 'divider':
                html += '                            <hr style="';
                html += 'border:none;border-top:' + (d.thickness || '1') + 'px ' + (d.style || 'solid') + ' ' + (d.color || '#ddd') + ';';
                html += 'width:' + (d.width || '100%') + ';';
                html += 'margin:' + (d.marginTop || '16') + 'px auto ' + (d.marginBottom || '16') + 'px;';
                if (d.alignment === 'left') html += 'margin-left:0;margin-right:auto;';
                if (d.alignment === 'right') html += 'margin-left:auto;margin-right:0;';
                html += '"/>\n';
                break;

            case 'spacer':
                html += '                            <div style="height:' + (d.height || '32') + 'px;';
                if (d.backgroundColor) html += 'background-color:' + d.backgroundColor + ';';
                if (d.borderRadius) html += 'border-radius:' + d.borderRadius + 'px;';
                if (d.borderWidth && parseInt(d.borderWidth) > 0) html += 'border:' + d.borderWidth + 'px ' + (d.borderStyle || 'solid') + ' ' + (d.borderColor || '#000') + ';';
                html += '"';
                html += '>&nbsp;</div>\n';
                break;

            case 'html':
                html += '                            <div style="padding:' + (d.paddingTop || '0') + 'px ' + (d.paddingRight || '0') + 'px ' + (d.paddingBottom || '0') + 'px ' + (d.paddingLeft || '0') + 'px;';
                if (d.backgroundColor) html += 'background-color:' + d.backgroundColor + ';';
                html += '"';
                html += '>' + (d.content || '') + '</div>\n';
                break;

            case 'social':
                html += '                            <div style="text-align:' + (d.alignment || 'center') + ';padding:8px 0;">\n';
                var platforms = d.platforms || {};
                Object.keys(platforms).forEach(function(key) {
                    var plat = platforms[key];
                    if (!plat.active || !plat.url) return;
                    var size = d.iconSize || '24';
                    var bg = d.iconColor || '#333';
                    var rad = d.shape === 'circle' ? '50%' : d.shape === 'rounded' ? '4px' : '0';
                    html += '                              <a href="' + escapeHtml(plat.url) + '" target="_blank" style="display:inline-block;width:' + size + 'px;height:' + size + 'px;background-color:' + bg + ';border-radius:' + rad + ';margin:0 ' + (d.spacing || '8') + 'px;text-decoration:none;line-height:' + size + 'px;text-align:center;color:#fff;font-size:' + (size * 0.5) + 'px;">' + key[0].toUpperCase() + '</a>\n';
                });
                html += '                            </div>\n';
                break;
        }

        return html;
    }

    function renderPreview() {
        if (!$previewFrame) return;
        var html = getHTML();
        $previewFrame.srcdoc = html;
    }


    /* ─── Finder helpers ─── */
    function findBlock(sectionId, blockId) {
        var sec = sections.find(function(s) { return s.id === sectionId; });
        if (!sec) return null;
        for (var c = 0; c < sec.content.length; c++) {
            for (var b = 0; b < sec.content[c].length; b++) {
                if (sec.content[c][b].id === blockId) return sec.content[c][b];
            }
        }
        return null;
    }

    function findBlockColumn(sectionId, blockId) {
        var sec = sections.find(function(s) { return s.id === sectionId; });
        if (!sec) return -1;
        for (var c = 0; c < sec.content.length; c++) {
            for (var b = 0; b < sec.content[c].length; b++) {
                if (sec.content[c][b].id === blockId) return c;
            }
        }
        return -1;
    }

    /* ─── Tab switching ─── */
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
        document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); });

        var $content = document.getElementById('tab-' + tab);
        var $btn = document.querySelector('.tab-btn[data-tab="' + tab + '"]');
        if ($content) $content.classList.add('active');
        if ($btn) $btn.classList.add('active');

        if (tab === 'preview') {
            renderPreview();
        } else if (tab === 'body') {
            selectBody();
        }
    }

    /* ─── Preview size toggle ─── */
    function setPreviewSize(size) {
        if (!$previewFrame) return;
        $previewFrame.style.width = size === 'mobile' ? '375px' : '100%';
    }

    /* ─── Save / Export ─── */
    function getJSON() {
        return JSON.stringify({
            body: body,
            sections: sections
        });
    }

    function getSections() {
        return sections;
    }

    function getBody() {
        return body;
    }

    function setSections(newSections) {
        sections = newSections || [];
        renderCanvas();
    }

    function setBody(newBody) {
        body = Object.assign({}, body, newBody || {});
        renderCanvas();
    }

    /* ─── Public API ─── */
    return {
        init: init,
        addSection: addSection,
        addBlock: addBlock,
        removeSection: removeSection,
        removeBlock: removeBlock,
        duplicateSection: duplicateSection,
        duplicateBlock: duplicateBlock,
        getJSON: getJSON,
        getHTML: getHTML,
        renderPreview: renderPreview,
        switchTab: switchTab,
        setPreviewSize: setPreviewSize,
        getSections: getSections,
        getBody: getBody,
        setSections: setSections,
        setBody: setBody,
        selectBody: selectBody
    };

})();

/* ─── Global helpers for onclick handlers ─── */
window.EmailBuilder = EmailBuilder;
