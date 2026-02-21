<?php
declare(strict_types=1);
/**
 * Quote Form Builder -- Configurator UI
 * Configure services, complexity, add-ons, style, language, and budget tiers
 * to produce accurate quote forms.
 */
$editId   = isset($_GET['edit']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['edit']) : '';
$initForm = $editId ? (builder_load_form($editId) ?? builder_blank()) : null;
$initJson = $initForm ? json_encode($initForm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quote Form Builder</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --c-bg:         #fcfdfd;
    --c-surface:    #f0f5f4;
    --c-border:     #c8dedd;
    --c-primary:    #244c47;
    --c-primary-dk: #1a3835;
    --c-accent:     #459289;
    --c-text:       #182523;
    --c-muted:      #556b69;
    --c-red:        #8b1a1a;
    --c-hdr:        #081110;
    --c-hdr-text:   #eaf5f4;
    --sidebar-w:    260px;
    --toolbar-h:    50px;
}
html { font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--c-text); background: var(--c-bg); }
body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
button, input, select, textarea { font-family: inherit; font-size: inherit; }
a { color: var(--c-primary); text-decoration: none; }
a:hover { color: var(--c-accent); }

/* toolbar */
.toolbar { height: var(--toolbar-h); background: var(--c-hdr); color: var(--c-hdr-text); display: flex; align-items: center; flex-shrink: 0; border-bottom: 2px solid var(--c-primary); z-index: 50; }
.toolbar-brand { padding: 0 1.1rem; font-weight: 700; font-size: 0.92rem; letter-spacing: 0.03em; white-space: nowrap; border-right: 1px solid #1e3a38; height: 100%; display: flex; align-items: center; gap: 0.5rem; }
.toolbar-brand span { color: var(--c-accent); }
.toolbar-sep { width: 1px; background: #1e3a38; height: 60%; margin: 0 0.25rem; }
.toolbar-btn { height: 100%; padding: 0 0.9rem; background: none; border: none; color: #9ab8b6; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent; transition: color 0.15s; }
.toolbar-btn:hover { color: var(--c-hdr-text); }
.toolbar-btn.active { color: var(--c-hdr-text); border-bottom-color: var(--c-accent); }
.toolbar-btn.danger { color: #e08080; }
.toolbar-btn.danger:hover { color: #f8b0b0; }
.toolbar-spacer { flex: 1; }
.toolbar-right { display: flex; align-items: center; height: 100%; border-left: 1px solid #1e3a38; }
.toolbar-form-name { padding: 0 0.75rem; font-size: 0.78rem; color: #8ab0ae; font-family: monospace; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.toolbar-create-btn { height: 100%; padding: 0 1.1rem; background: none; border: none; border-left: 1px solid #1e3a38; color: var(--c-accent); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; white-space: nowrap; transition: background 0.15s, color 0.15s; }
.toolbar-create-btn:hover { background: var(--c-primary); color: var(--c-hdr-text); }
.toolbar-save-btn { height: 100%; padding: 0 1.2rem; background: var(--c-primary); border: none; color: var(--c-hdr-text); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; transition: background 0.15s; }
.toolbar-save-btn:hover { background: var(--c-primary-dk); }
.save-indicator { font-size: 0.7rem; color: var(--c-accent); padding: 0 0.6rem; opacity: 0; transition: opacity 0.4s; }
.save-indicator.show { opacity: 1; }

/* layout */
.layout { flex: 1; display: flex; overflow: hidden; }

/* left sidebar */
.sidebar-left { width: var(--sidebar-w); flex-shrink: 0; background: #f6faf9; border-right: 1px solid var(--c-border); display: flex; flex-direction: column; overflow: hidden; }
.sidebar-section { border-bottom: 1px solid var(--c-border); flex-shrink: 0; }
.sidebar-section-hdr { display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; background: var(--c-surface); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--c-muted); }
.sidebar-icon-btn { background: none; border: none; cursor: pointer; color: var(--c-primary); font-size: 1rem; line-height: 1; padding: 0.1rem 0.2rem; font-weight: 700; }
.sidebar-icon-btn:hover { color: var(--c-accent); }
.forms-list-body { overflow-y: auto; max-height: 180px; }
.form-list-item { display: flex; align-items: center; gap: 0; border-bottom: 1px solid var(--c-border); cursor: pointer; transition: background 0.1s; }
.form-list-item:last-child { border-bottom: none; }
.form-list-item:hover { background: #e8f2f1; }
.form-list-item.active { background: #d8edeb; }
.form-list-item-name { flex: 1; padding: 0.5rem 0.75rem; font-size: 0.82rem; font-weight: 600; color: var(--c-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.form-list-item-meta { font-size: 0.68rem; color: var(--c-muted); padding-right: 0.5rem; white-space: nowrap; }
.form-list-actions { display: flex; flex-shrink: 0; }
.form-list-action-btn { padding: 0.4rem 0.4rem; background: none; border: none; cursor: pointer; font-size: 0.8rem; color: var(--c-muted); line-height: 1; }
.form-list-action-btn:hover { color: var(--c-primary); }
.form-list-action-btn.red:hover { color: var(--c-red); }

/* section nav */
.section-nav { overflow-y: auto; flex: 1; min-height: 0; }
.section-item { display: flex; align-items: center; gap: 0; border-bottom: 1px solid var(--c-border); cursor: pointer; transition: background 0.1s; }
.section-item:hover { background: #e8f2f1; }
.section-item.active { background: #d8edeb; border-left: 3px solid var(--c-primary); }
.section-item .sec-num { padding: 0 0.55rem; font-size: 0.68rem; font-weight: 700; color: var(--c-muted); min-width: 30px; text-align: center; }
.section-item.active .sec-num { color: var(--c-primary); }
.section-item .sec-name { flex: 1; padding: 0.55rem 0.3rem; font-size: 0.82rem; color: var(--c-text); }
.section-item .sec-count { padding: 0 0.55rem; font-size: 0.65rem; color: var(--c-muted); font-family: monospace; }

/* canvas - live preview */
.canvas-wrap { flex: 1; overflow-y: auto; background: var(--c-bg); padding: 1.5rem; min-width: 0; }
#no-form-view { display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; gap: 1rem; color: var(--c-muted); padding: 2rem; text-align: center; min-height: 60vh; }
#no-form-view h2 { font-size: 1.2rem; color: var(--c-text); }
.big-btn { padding: 0.65rem 1.6rem; font-size: 0.88rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; border: 2px solid var(--c-primary); background: var(--c-primary); color: var(--c-hdr-text); }
.big-btn:hover { background: var(--c-primary-dk); }
.big-btn.secondary { background: transparent; color: var(--c-primary); }
.big-btn.secondary:hover { background: var(--c-surface); }

/* live preview */
#live-preview { max-width: 580px; margin: 0 auto; border: 1px solid var(--c-border); }
#live-preview .lp-header { padding: 1rem 1.5rem; border-bottom: 3px solid var(--lp-primary, #244c47); background: var(--lp-header-bg, #244c47); color: var(--lp-header-text, #eaf5f4); }
#live-preview .lp-header-title { font-size: 1.2rem; font-weight: 700; }
#live-preview .lp-header-sub { font-size: 0.78rem; opacity: 0.8; margin-top: 0.15rem; }
#live-preview .lp-body { padding: 1.5rem; background: var(--lp-bg, #fcfdfd); color: var(--lp-text, #182523); }
#live-preview .lp-progress { height: 5px; background: #e8e8e8; margin-bottom: 0.4rem; overflow: hidden; }
#live-preview .lp-progress-bar { height: 100%; background: var(--lp-primary, #244c47); transition: width 0.3s; }
#live-preview .lp-step-ind { font-size: 0.7rem; color: var(--lp-accent, #459289); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; margin-bottom: 1rem; }
#live-preview .lp-step-block { background: #fff; border: 1px solid var(--lp-accent, #459289); padding: 1.5rem; }
#live-preview .lp-step-title { font-size: 1.15rem; font-weight: 700; color: var(--lp-text, #182523); margin-bottom: 0.3rem; }
#live-preview .lp-step-desc { font-size: 0.82rem; color: var(--lp-accent, #459289); margin-bottom: 1.2rem; }
#live-preview .lp-options { display: flex; flex-direction: column; gap: 0.5rem; }
#live-preview .lp-radio-opt,
#live-preview .lp-check-opt { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.55rem 0.7rem; border: 1px solid var(--lp-accent, #459289); cursor: default; }
#live-preview .lp-radio-opt input,
#live-preview .lp-check-opt input { accent-color: var(--lp-primary, #244c47); margin-top: 3px; flex-shrink: 0; }
#live-preview .lp-opt-label { flex: 1; font-size: 0.85rem; color: var(--lp-text, #182523); }
#live-preview .lp-opt-sub { font-size: 0.72rem; color: var(--lp-accent, #459289); margin-top: 0.1rem; }
#live-preview .lp-opt-cost { font-size: 0.78rem; font-weight: 700; color: var(--lp-primary, #244c47); white-space: nowrap; flex-shrink: 0; }
#live-preview .lp-fields { display: flex; flex-direction: column; gap: 1rem; }
#live-preview .lp-field-group { display: flex; flex-direction: column; gap: 0.3rem; }
#live-preview .lp-label { font-size: 0.82rem; font-weight: 600; color: var(--lp-text, #182523); }
#live-preview .lp-req { color: #c0392b; margin-left: 0.15rem; }
#live-preview .lp-input { width: 100%; padding: 0.5rem 0.6rem; border: 1px solid var(--lp-accent, #459289); font-size: 0.88rem; font-family: inherit; background: var(--lp-bg, #fcfdfd); color: var(--lp-text, #182523); }
#live-preview .lp-actions { display: flex; gap: 0.75rem; margin-top: 1.2rem; }
#live-preview .lp-btn { padding: 0.5rem 1.2rem; font-size: 0.82rem; font-weight: 700; border: 2px solid transparent; cursor: default; font-family: inherit; letter-spacing: 0.02em; }
#live-preview .lp-btn-primary { background: var(--lp-primary, #244c47); color: var(--lp-header-text, #eaf5f4); border-color: var(--lp-primary, #244c47); }
#live-preview .lp-btn-secondary { background: transparent; color: var(--lp-primary, #244c47); border-color: var(--lp-primary, #244c47); }
#live-preview .lp-dots { display: flex; gap: 0.4rem; justify-content: center; margin-top: 1rem; }
#live-preview .lp-dot { width: 7px; height: 7px; background: #ccc; }
#live-preview .lp-dot.active { background: var(--lp-primary, #244c47); }
#live-preview .lp-footer { background: var(--lp-header-bg, #244c47); color: var(--lp-header-text, #eaf5f4); padding: 0.75rem 1rem; text-align: center; font-size: 0.72rem; opacity: 0.7; border-top: 2px solid var(--lp-primary, #244c47); }

/* right sidebar */
.sidebar-right { width: 280px; flex-shrink: 0; background: #f6faf9; border-left: 1px solid var(--c-border); overflow-y: auto; display: flex; flex-direction: column; }
.props-tabs { display: flex; border-bottom: 2px solid var(--c-border); flex-shrink: 0; }
.props-tab { flex: 1; padding: 0.55rem 0; background: none; border: none; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; color: var(--c-muted); border-bottom: 3px solid transparent; }
.props-tab.active { color: var(--c-primary); border-bottom-color: var(--c-primary); }
.props-panel { padding: 0.85rem 0.75rem; display: none; }
.props-panel.active { display: block; }
.prop-row { margin-bottom: 0.75rem; }
.prop-label { display: block; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--c-muted); margin-bottom: 0.3rem; }
.prop-input, .prop-select, .prop-textarea { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid var(--c-border); background: #fff; color: var(--c-text); font-size: 0.82rem; }
.prop-textarea { resize: vertical; min-height: 50px; font-size: 0.78rem; }
.prop-input:focus, .prop-select:focus, .prop-textarea:focus { outline: 2px solid var(--c-primary); }
.prop-checkbox-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
.prop-checkbox-row input[type=checkbox] { accent-color: var(--c-primary); width: 14px; height: 14px; }
.prop-checkbox-row label { font-size: 0.82rem; color: var(--c-text); cursor: pointer; }
.prop-color-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.65rem; }
.prop-color-row label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--c-muted); flex: 1; }
.prop-color-row input[type=color] { width: 36px; height: 26px; padding: 1px; border: 1px solid var(--c-border); cursor: pointer; background: none; }
.prop-color-hex { width: 72px; padding: 0.3rem 0.4rem; border: 1px solid var(--c-border); font-size: 0.75rem; font-family: monospace; color: var(--c-text); background: #fff; }
.prop-section-title { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--c-muted); margin: 1rem 0 0.5rem; padding-bottom: 0.3rem; border-bottom: 1px solid var(--c-border); }
.prop-section-title:first-child { margin-top: 0; }
.harmony-controls { margin-bottom: 0.75rem; padding: 0.55rem 0.6rem; border: 1px solid var(--c-border); background: #fff; }
.harmony-controls .prop-checkbox-row { margin-bottom: 0.45rem; }
.harmony-controls .prop-row { margin-bottom: 0; }
.harmony-controls .prop-select { font-size: 0.78rem; }
.harmony-controls.disabled .prop-select { opacity: 0.4; pointer-events: none; }

/* edit items */
.edit-item { border: 1px solid var(--c-border); padding: 0.5rem 0.55rem; margin-bottom: 0.35rem; background: #fff; }
.edit-item-row { display: flex; align-items: center; gap: 0.3rem; }
.edit-item input[type=text],
.edit-item input[type=number] { padding: 0.3rem 0.4rem; border: 1px solid var(--c-border); font-size: 0.78rem; background: #fff; color: var(--c-text); }
.edit-item input[type=text]:focus,
.edit-item input[type=number]:focus { outline: 2px solid var(--c-primary); }
.edit-item input.ei-label { flex: 1; }
.edit-item input.ei-price { width: 75px; text-align: right; }
.edit-item input.ei-mult { width: 55px; text-align: right; }
.edit-item input.ei-desc { width: 100%; margin-top: 0.25rem; font-size: 0.72rem; color: var(--c-muted); }
.edit-item select.ei-type { padding: 0.3rem 0.35rem; border: 1px solid var(--c-border); font-size: 0.75rem; background: #fff; color: var(--c-text); }
.opt-rm-btn { background: none; border: none; cursor: pointer; color: var(--c-muted); font-size: 0.9rem; padding: 0 0.2rem; }
.opt-rm-btn:hover { color: var(--c-red); }
.opt-add-btn { font-size: 0.72rem; padding: 0.25rem 0.6rem; background: var(--c-surface); border: 1px solid var(--c-border); cursor: pointer; color: var(--c-primary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.35rem; }
.opt-add-btn:hover { background: var(--c-border); }

/* notifications */
.notif { position: fixed; bottom: 1.25rem; right: 1.25rem; padding: 0.65rem 1rem; font-size: 0.82rem; font-weight: 600; background: var(--c-hdr); color: var(--c-hdr-text); border-left: 4px solid var(--c-accent); z-index: 999; max-width: 320px; opacity: 0; transform: translateY(8px); transition: opacity 0.2s, transform 0.2s; pointer-events: none; }
.notif.show { opacity: 1; transform: translateY(0); }
.notif.err { border-left-color: var(--c-red); }
</style>
</head>
<body>

<!-- TOOLBAR -->
<div class="toolbar">
    <div class="toolbar-brand">Quote <span>Builder</span></div>
    <button class="toolbar-btn active" id="tbtn-editor" onclick="showEditorView()">Editor</button>
    <button class="toolbar-btn" id="tbtn-forms" onclick="showFormsList()">All Forms</button>
    <div class="toolbar-sep"></div>
    <button class="toolbar-btn" id="tbtn-preview" onclick="openPreview()">Preview</button>
    <button class="toolbar-btn danger" id="tbtn-delete" onclick="deleteCurrentForm()">Delete</button>
    <div class="toolbar-spacer"></div>
    <div class="toolbar-right">
        <button class="toolbar-create-btn" onclick="newForm()">+ Create Form</button>
        <div class="toolbar-form-name" id="toolbar-form-name">--</div>
        <span class="save-indicator" id="save-indicator">Saved</span>
        <button class="toolbar-save-btn" onclick="saveForm()">Save</button>
    </div>
</div>

<!-- LAYOUT -->
<div class="layout">

    <!-- LEFT SIDEBAR -->
    <div class="sidebar-left">
        <div class="sidebar-section">
            <div class="sidebar-section-hdr">
                Saved Forms
                <div style="display:flex;gap:0.25rem;">
                    <button class="sidebar-icon-btn" title="New form" onclick="newForm()">+</button>
                    <button class="sidebar-icon-btn" title="Refresh" onclick="loadFormsList()">&#8635;</button>
                </div>
            </div>
            <div class="forms-list-body" id="forms-list-body">
                <div style="padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--c-muted);">Loading...</div>
            </div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-hdr">Quote Steps</div>
        </div>
        <div class="section-nav" id="section-nav"></div>
    </div>

    <!-- CANVAS -->
    <div class="canvas-wrap" id="canvas-wrap">
        <div id="no-form-view">
            <h2>Quote Form Builder</h2>
            <p>Build quote forms with accurate pricing. Configure services, complexity levels, and add-ons to generate estimates.</p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;margin-top:0.5rem;">
                <button class="big-btn" onclick="newForm()">New Quote Form</button>
                <button class="big-btn secondary" onclick="loadFormsList()">Open Saved</button>
            </div>
        </div>
        <div id="editor-view" style="display:none;">
            <div id="live-preview"></div>
        </div>
    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="sidebar-right">
        <div class="props-tabs">
            <button class="props-tab active" id="ptab-edit" onclick="showPropTab('edit')">Edit</button>
            <button class="props-tab" id="ptab-style" onclick="showPropTab('style')">Style</button>
            <button class="props-tab" id="ptab-lang" onclick="showPropTab('lang')">Language</button>
            <button class="props-tab" id="ptab-tiers" onclick="showPropTab('tiers')">Tiers</button>
        </div>

        <!-- Edit panel (context-sensitive) -->
        <div class="props-panel active" id="ppanel-edit">
            <div id="edit-panel-content">
                <div style="padding:1.5rem 0.5rem;text-align:center;color:var(--c-muted);font-size:0.82rem;">Open or create a quote form to start editing.</div>
            </div>
        </div>

        <!-- Style panel -->
        <div class="props-panel" id="ppanel-style">
            <p class="prop-section-title">Colors</p>
            <div class="prop-color-row"><label>Primary</label>     <input type="color" id="dp-primary"    oninput="styleColorSync(this,'dp-primary-hex')">    <input type="text" class="prop-color-hex" id="dp-primary-hex"    oninput="styleHexSync(this,'dp-primary')"></div>
            <div class="prop-color-row"><label>Accent</label>      <input type="color" id="dp-accent"     oninput="styleColorSync(this,'dp-accent-hex')">     <input type="text" class="prop-color-hex" id="dp-accent-hex"     oninput="styleHexSync(this,'dp-accent')"></div>
            <div class="prop-color-row"><label>Background</label>  <input type="color" id="dp-bg"         oninput="styleColorSync(this,'dp-bg-hex')">         <input type="text" class="prop-color-hex" id="dp-bg-hex"         oninput="styleHexSync(this,'dp-bg')"></div>
            <div class="prop-color-row"><label>Text</label>        <input type="color" id="dp-text"       oninput="styleColorSync(this,'dp-text-hex')">       <input type="text" class="prop-color-hex" id="dp-text-hex"       oninput="styleHexSync(this,'dp-text')"></div>
            <div class="prop-color-row"><label>Header bg</label>   <input type="color" id="dp-headerbg"   oninput="styleColorSync(this,'dp-headerbg-hex')">   <input type="text" class="prop-color-hex" id="dp-headerbg-hex"   oninput="styleHexSync(this,'dp-headerbg')"></div>
            <div class="prop-color-row"><label>Header text</label> <input type="color" id="dp-headertext" oninput="styleColorSync(this,'dp-headertext-hex')"> <input type="text" class="prop-color-hex" id="dp-headertext-hex" oninput="styleHexSync(this,'dp-headertext')"></div>
            <div class="harmony-controls disabled" id="harmony-box">
                <div class="prop-checkbox-row">
                    <input type="checkbox" id="harmony-enable" onchange="toggleHarmony()">
                    <label for="harmony-enable">Use color harmony</label>
                </div>
                <div class="prop-row">
                    <select class="prop-select" id="harmony-mode" onchange="applyHarmony()">
                        <option value="complementary">Complementary</option>
                        <option value="analogous">Analogous</option>
                        <option value="triadic">Triadic</option>
                        <option value="split-complementary">Split-Complementary</option>
                        <option value="monochromatic">Monochromatic</option>
                    </select>
                </div>
            </div>
            <p class="prop-section-title">Typography</p>
            <div class="prop-row">
                <label class="prop-label">Font</label>
                <select class="prop-select" id="dp-font" onchange="styleChanged()">
                    <option value="system">System UI (default)</option>
                    <option value="inter">Inter / Helvetica</option>
                    <option value="serif">Georgia (serif)</option>
                    <option value="mono">Monospace</option>
                    <option value="trebuchet">Trebuchet</option>
                </select>
            </div>
            <div class="prop-row">
                <label class="prop-label">Base font size (px)</label>
                <input type="number" class="prop-input" id="dp-fontsize" min="12" max="22" step="1" value="16" oninput="styleChanged()">
            </div>
        </div>

        <!-- Language panel -->
        <div class="props-panel" id="ppanel-lang">
            <p class="prop-section-title">Form Info</p>
            <div class="prop-row"><label class="prop-label">Form name</label><input type="text" class="prop-input" id="lang-form-name" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Description</label><textarea class="prop-textarea" id="lang-form-desc" oninput="langChanged()"></textarea></div>
            <p class="prop-section-title">Header</p>
            <div class="prop-row"><label class="prop-label">Title</label><input type="text" class="prop-input" id="lang-header-title" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Subtitle</label><input type="text" class="prop-input" id="lang-header-sub" oninput="langChanged()"></div>
            <p class="prop-section-title">Step 1: Services</p>
            <div class="prop-row"><label class="prop-label">Title</label><input type="text" class="prop-input" id="lang-svc-title" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Description</label><textarea class="prop-textarea" id="lang-svc-desc" oninput="langChanged()"></textarea></div>
            <p class="prop-section-title">Step 2: Complexity</p>
            <div class="prop-row"><label class="prop-label">Title</label><input type="text" class="prop-input" id="lang-cplx-title" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Description</label><textarea class="prop-textarea" id="lang-cplx-desc" oninput="langChanged()"></textarea></div>
            <p class="prop-section-title">Step 3: Add-Ons</p>
            <div class="prop-row"><label class="prop-label">Title</label><input type="text" class="prop-input" id="lang-addon-title" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Description</label><textarea class="prop-textarea" id="lang-addon-desc" oninput="langChanged()"></textarea></div>
            <p class="prop-section-title">Step 4: Contact</p>
            <div class="prop-row"><label class="prop-label">Title</label><input type="text" class="prop-input" id="lang-contact-title" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Description</label><textarea class="prop-textarea" id="lang-contact-desc" oninput="langChanged()"></textarea></div>
            <p class="prop-section-title">Buttons</p>
            <div class="prop-row"><label class="prop-label">Next</label><input type="text" class="prop-input" id="lang-next" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Back</label><input type="text" class="prop-input" id="lang-back" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Submit</label><input type="text" class="prop-input" id="lang-submit" oninput="langChanged()"></div>
            <p class="prop-section-title">Results Page</p>
            <div class="prop-row"><label class="prop-label">Heading</label><input type="text" class="prop-input" id="lang-result-heading" oninput="langChanged()"></div>
            <div class="prop-row"><label class="prop-label">Description</label><textarea class="prop-textarea" id="lang-result-desc" oninput="langChanged()"></textarea></div>
            <div class="prop-row"><label class="prop-label">Currency symbol</label><input type="text" class="prop-input" id="lang-currency" maxlength="4" style="width:60px;" oninput="langChanged()"></div>
        </div>

        <!-- Tiers panel -->
        <div class="props-panel" id="ppanel-tiers">
            <p class="prop-section-title">Budget Tiers</p>
            <p style="font-size:0.72rem;color:var(--c-muted);margin-bottom:0.6rem;">Each tier applies a multiplier to the calculated base cost. Users see these options on the results page.</p>
            <div id="tiers-list"></div>
            <button class="opt-add-btn" onclick="addTier()">+ Add Tier</button>
            <p class="prop-section-title">Options</p>
            <div class="prop-checkbox-row">
                <input type="checkbox" id="tier-show-breakdown" onchange="tiersMetaChanged()">
                <label for="tier-show-breakdown">Show cost breakdown</label>
            </div>
        </div>
    </div>

</div>

<div class="notif" id="notif"></div>

<script>
(function () {
'use strict';

var form          = null;
var activeSection = 'services';
var formsList     = [];

var SECTIONS = [
    { key: 'services',   name: 'Services',   step: 1 },
    { key: 'complexity', name: 'Complexity',  step: 2 },
    { key: 'addons',     name: 'Add-Ons',     step: 3 },
    { key: 'contact',    name: 'Contact',     step: 4 }
];

var FONTS = {
    system:    "-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif",
    inter:     "'Inter','Helvetica Neue',Arial,sans-serif",
    serif:     "Georgia,'Times New Roman',serif",
    mono:      "SFMono-Regular,Consolas,'Liberation Mono',monospace",
    trebuchet: "'Trebuchet MS','Lucida Grande',sans-serif"
};

var STYLE_MAP = {
    'dp-primary':    'primaryColor',
    'dp-accent':     'accentColor',
    'dp-bg':         'bgColor',
    'dp-text':       'textColor',
    'dp-headerbg':   'headerBg',
    'dp-headertext': 'headerText'
};

// -- utils --
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function setVal(id, v) { var e = document.getElementById(id); if(e) { if (e.tagName === 'TEXTAREA') e.value = v; else e.value = v; } }
function getVal(id)    { var e = document.getElementById(id); return e ? e.value : ''; }
function fmtCost(n, cur) { return (cur || '$') + Number(n).toLocaleString(); }

// -- notifications --
var notifTimer = null;
function notify(msg, err) {
    var el = document.getElementById('notif');
    el.textContent = msg;
    el.className = 'notif show' + (err ? ' err' : '');
    clearTimeout(notifTimer);
    notifTimer = setTimeout(function(){ el.classList.remove('show'); }, 2800);
}

// -- API --
function api(cmd, extra, cb) {
    var body = Object.assign({ cmd: cmd }, extra || {});
    fetch('/form-builder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'api', cmd: cmd, ...body })
    })
    .then(function(r){ return r.json(); })
    .then(cb)
    .catch(function(e){ notify('API error: ' + e.message, true); });
}

// -- forms list --
function loadFormsList() {
    api('list', null, function(d) {
        formsList = d.forms || [];
        renderFormsList();
    });
}

function renderFormsList() {
    var body = document.getElementById('forms-list-body');
    if (!formsList.length) {
        body.innerHTML = '<div style="padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--c-muted);">No saved forms yet.</div>';
        return;
    }
    body.innerHTML = formsList.map(function(f) {
        var isActive = form && form.id === f.id;
        var d = new Date(f.updated_at * 1000);
        var ds = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        return '<div class="form-list-item' + (isActive ? ' active' : '') + '">' +
            '<div class="form-list-item-name" onclick="openFormById(\'' + esc(f.id) + '\')">' + esc(f.name) + '</div>' +
            '<div class="form-list-item-meta">' + ds + '</div>' +
            '<div class="form-list-actions">' +
            '<button class="form-list-action-btn" title="Duplicate" onclick="duplicateForm(\'' + esc(f.id) + '\')">&#9107;</button>' +
            '<button class="form-list-action-btn red" title="Delete" onclick="confirmDeleteForm(\'' + esc(f.id) + '\',\'' + esc(f.name) + '\')">&#10005;</button>' +
            '</div></div>';
    }).join('');
}

window.openFormById = function(id) {
    api('load', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        loadForm(d.form);
        notify('Opened: ' + d.form.name);
    });
};

window.duplicateForm = function(id) {
    api('duplicate', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        loadFormsList();
        loadForm(d.form);
        notify('Duplicated as "' + d.form.name + '"');
    });
};

window.confirmDeleteForm = function(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    api('delete', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        if (form && form.id === id) { form = null; showNoFormView(); }
        loadFormsList();
        notify('Deleted "' + name + '"');
    });
};

// -- load / save / new / delete --
function loadForm(f) {
    form = f;
    activeSection = 'services';
    showEditorView();
    renderSectionNav();
    renderEditPanel();
    renderLivePreview();
    updatePreviewStyles();
    populateStylePanel();
    populateLangPanel();
    populateTiersPanel();
    document.getElementById('toolbar-form-name').textContent = f.name || 'Untitled';
    loadFormsList();
}

window.newForm = function() {
    api('blank', null, function(d) {
        loadForm(d.form);
        showPropTab('lang');
        notify('New quote form created.');
    });
};

window.saveForm = function() {
    if (!form) { notify('No form open.', true); return; }
    api('save', { form: form }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        form = d.form;
        document.getElementById('toolbar-form-name').textContent = form.name;
        loadFormsList();
        var ind = document.getElementById('save-indicator');
        ind.classList.add('show');
        setTimeout(function(){ ind.classList.remove('show'); }, 1800);
        notify('Saved "' + form.name + '"');
    });
};

window.deleteCurrentForm = function() {
    if (!form) return;
    if (!confirm('Delete "' + form.name + '"? This cannot be undone.')) return;
    var id = form.id;
    if (!id) { form = null; showNoFormView(); return; }
    api('delete', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        form = null; loadFormsList(); showNoFormView();
        notify('Form deleted.');
    });
};

window.openPreview = function() {
    if (!form) { notify('Save the form first, then preview.', true); return; }
    // Always save current state before opening preview so edits are reflected
    api('save', { form: form }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        form = d.form;
        document.getElementById('toolbar-form-name').textContent = form.name;
        loadFormsList();
        window.open('/form-builder?preview=1&id=' + encodeURIComponent(form.id), '_blank');
    });
};

// -- view helpers --
window.showEditorView = function() {
    document.getElementById('no-form-view').style.display  = form ? 'none' : '';
    document.getElementById('editor-view').style.display   = form ? '' : 'none';
    setActive('tbtn-editor', ['tbtn-editor','tbtn-forms']);
};

window.showFormsList = function() {
    loadFormsList();
    document.getElementById('no-form-view').style.display = '';
    document.getElementById('editor-view').style.display  = 'none';
    document.getElementById('no-form-view').innerHTML =
        '<h2>Saved Forms</h2><p>Select a form from the left, or create a new one.</p>' +
        '<div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;margin-top:0.5rem;">' +
        '<button class="big-btn" onclick="newForm()">New Quote Form</button></div>';
    setActive('tbtn-forms', ['tbtn-editor','tbtn-forms']);
};

function showNoFormView() {
    document.getElementById('no-form-view').innerHTML =
        '<h2>Quote Form Builder</h2><p>Build quote forms with accurate pricing.</p>' +
        '<div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;margin-top:0.5rem;">' +
        '<button class="big-btn" onclick="newForm()">New Quote Form</button>' +
        '<button class="big-btn secondary" onclick="loadFormsList()">Open Saved</button></div>';
    document.getElementById('no-form-view').style.display = '';
    document.getElementById('editor-view').style.display  = 'none';
    document.getElementById('toolbar-form-name').textContent = '--';
    renderSectionNav();
}

function setActive(id, group) {
    group.forEach(function(bid){ document.getElementById(bid).classList.remove('active'); });
    document.getElementById(id).classList.add('active');
}

// -- section nav --
function renderSectionNav() {
    var nav = document.getElementById('section-nav');
    if (!form) { nav.innerHTML = '<div style="padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--c-muted);">Open or create a form.</div>'; return; }
    nav.innerHTML = SECTIONS.map(function(s) {
        var count = (form[s.key] || []).length;
        var isActive = activeSection === s.key;
        return '<div class="section-item' + (isActive ? ' active' : '') + '" onclick="goSection(\'' + s.key + '\')">' +
            '<div class="sec-num">' + s.step + '</div>' +
            '<div class="sec-name">' + s.name + '</div>' +
            '<div class="sec-count">' + count + '</div>' +
        '</div>';
    }).join('');
}

window.goSection = function(key) {
    activeSection = key;
    renderSectionNav();
    renderEditPanel();
    renderLivePreview();
    showPropTab('edit');
};

// -- edit panel --
function renderEditPanel() {
    var el = document.getElementById('edit-panel-content');
    if (!form) { el.innerHTML = '<div style="padding:1.5rem 0.5rem;text-align:center;color:var(--c-muted);font-size:0.82rem;">Open or create a quote form.</div>'; return; }

    switch (activeSection) {
        case 'services':   renderServicesEdit(el);   break;
        case 'complexity': renderComplexityEdit(el); break;
        case 'addons':     renderAddonsEdit(el);     break;
        case 'contact':    renderContactEdit(el);    break;
    }
}

function renderServicesEdit(el) {
    var cur = (form.language || {}).currency || '$';
    var html = '<p class="prop-section-title">Service Types</p>' +
        '<p style="font-size:0.72rem;color:var(--c-muted);margin-bottom:0.6rem;">Each service has a base price. Users select one.</p>';
    (form.services || []).forEach(function(svc, i) {
        html += '<div class="edit-item"><div class="edit-item-row">' +
            '<input type="text" class="ei-label" value="' + esc(svc.label||'') + '" placeholder="Service name" oninput="updateItem(\'services\',' + i + ',\'label\',this.value)">' +
            '<input type="number" class="ei-price" value="' + (svc.price||0) + '" placeholder="Price" oninput="updateItem(\'services\',' + i + ',\'price\',parseFloat(this.value)||0)">' +
            '<button class="opt-rm-btn" onclick="removeItem(\'services\',' + i + ')">&#10005;</button>' +
        '</div></div>';
    });
    html += '<button class="opt-add-btn" onclick="addItem(\'services\')">+ Add Service</button>';
    el.innerHTML = html;
}

function renderComplexityEdit(el) {
    var html = '<p class="prop-section-title">Complexity Levels</p>' +
        '<p style="font-size:0.72rem;color:var(--c-muted);margin-bottom:0.6rem;">Each level has a multiplier applied to the base service price.</p>';
    (form.complexity || []).forEach(function(c, i) {
        html += '<div class="edit-item"><div class="edit-item-row">' +
            '<input type="text" class="ei-label" value="' + esc(c.label||'') + '" placeholder="Level name" oninput="updateItem(\'complexity\',' + i + ',\'label\',this.value)">' +
            '<input type="number" class="ei-mult" value="' + (c.multiplier||1) + '" step="0.1" min="0.1" placeholder="x" oninput="updateItem(\'complexity\',' + i + ',\'multiplier\',parseFloat(this.value)||1)">' +
            '<button class="opt-rm-btn" onclick="removeItem(\'complexity\',' + i + ')">&#10005;</button>' +
        '</div>' +
        '<input type="text" class="ei-desc" value="' + esc(c.description||'') + '" placeholder="Description" oninput="updateItem(\'complexity\',' + i + ',\'description\',this.value)">' +
        '</div>';
    });
    html += '<button class="opt-add-btn" onclick="addItem(\'complexity\')">+ Add Level</button>';
    el.innerHTML = html;
}

function renderAddonsEdit(el) {
    var cur = (form.language || {}).currency || '$';
    var html = '<p class="prop-section-title">Add-On Services</p>' +
        '<p style="font-size:0.72rem;color:var(--c-muted);margin-bottom:0.6rem;">Optional extras. Users can select multiple. Costs are added to the total.</p>';
    (form.addons || []).forEach(function(a, i) {
        html += '<div class="edit-item"><div class="edit-item-row">' +
            '<input type="text" class="ei-label" value="' + esc(a.label||'') + '" placeholder="Add-on name" oninput="updateItem(\'addons\',' + i + ',\'label\',this.value)">' +
            '<input type="number" class="ei-price" value="' + (a.price||0) + '" placeholder="Price" oninput="updateItem(\'addons\',' + i + ',\'price\',parseFloat(this.value)||0)">' +
            '<button class="opt-rm-btn" onclick="removeItem(\'addons\',' + i + ')">&#10005;</button>' +
        '</div></div>';
    });
    html += '<button class="opt-add-btn" onclick="addItem(\'addons\')">+ Add Add-On</button>';
    el.innerHTML = html;
}

function renderContactEdit(el) {
    var html = '<p class="prop-section-title">Contact Fields</p>' +
        '<p style="font-size:0.72rem;color:var(--c-muted);margin-bottom:0.6rem;">Fields shown on the final step before the estimate.</p>';
    (form.contact || []).forEach(function(f, i) {
        html += '<div class="edit-item"><div class="edit-item-row">' +
            '<input type="text" class="ei-label" value="' + esc(f.label||'') + '" placeholder="Field label" oninput="updateItem(\'contact\',' + i + ',\'label\',this.value)">' +
            '<select class="ei-type" onchange="updateItem(\'contact\',' + i + ',\'type\',this.value);renderEditPanel()">' +
                '<option value="text"'  + (f.type==='text'  ? ' selected':'') + '>Text</option>' +
                '<option value="email"' + (f.type==='email' ? ' selected':'') + '>Email</option>' +
                '<option value="tel"'   + (f.type==='tel'   ? ' selected':'') + '>Phone</option>' +
                '<option value="number"'+ (f.type==='number'? ' selected':'') + '>Number</option>' +
                '<option value="select"'+ (f.type==='select'? ' selected':'') + '>Dropdown</option>' +
            '</select>' +
            '<label style="display:flex;align-items:center;gap:0.2rem;font-size:0.7rem;color:var(--c-muted);white-space:nowrap;"><input type="checkbox" ' + (f.required ? 'checked' : '') + ' onchange="updateItem(\'contact\',' + i + ',\'required\',this.checked)"> Req</label>' +
            '<button class="opt-rm-btn" onclick="removeItem(\'contact\',' + i + ')">&#10005;</button>' +
        '</div>';
        if (f.type === 'select') {
            var opts = f.options || [];
            html += '<div style="margin-top:0.3rem;padding-left:0.5rem;">';
            opts.forEach(function(o, oi) {
                html += '<div style="display:flex;align-items:center;gap:0.2rem;margin-bottom:0.15rem;">' +
                    '<input type="text" value="' + esc(o) + '" placeholder="Option" style="flex:1;padding:0.2rem 0.35rem;border:1px solid var(--c-border);font-size:0.72rem;background:#fff;color:var(--c-text);" oninput="updateContactOption(' + i + ',' + oi + ',this.value)">' +
                    '<button class="opt-rm-btn" style="font-size:0.75rem;" onclick="removeContactOption(' + i + ',' + oi + ')">&#10005;</button>' +
                '</div>';
            });
            html += '<button class="opt-add-btn" style="font-size:0.65rem;padding:0.15rem 0.4rem;" onclick="addContactOption(' + i + ')">+ Option</button>';
            html += '</div>';
        }
        html += '</div>';
    });
    html += '<button class="opt-add-btn" onclick="addItem(\'contact\')">+ Add Field</button>';
    el.innerHTML = html;
}

// -- generic item CRUD --
window.addItem = function(section) {
    if (!form) return;
    var defaults = {
        services:   { key: 's_' + Date.now(), label: 'New Service', price: 0 },
        complexity: { key: 'c_' + Date.now(), label: 'New Level', description: '', multiplier: 1.0 },
        addons:     { key: 'a_' + Date.now(), label: 'New Add-On', price: 0 },
        contact:    { key: 'f_' + Date.now(), label: 'New Field', type: 'text', required: false }
    };
    if (!form[section]) form[section] = [];
    form[section].push(defaults[section]);
    renderEditPanel();
    renderSectionNav();
    renderLivePreview();
};

window.removeItem = function(section, i) {
    if (!form || !form[section]) return;
    if (form[section].length <= 1 && section !== 'addons' && section !== 'contact') {
        notify('Must have at least one ' + section + ' item.', true);
        return;
    }
    form[section].splice(i, 1);
    renderEditPanel();
    renderSectionNav();
    renderLivePreview();
};

window.updateItem = function(section, i, key, val) {
    if (!form || !form[section] || !form[section][i]) return;
    form[section][i][key] = val;
    renderLivePreview();
};

// contact-specific option management
window.addContactOption = function(fi) {
    if (!form || !form.contact || !form.contact[fi]) return;
    if (!form.contact[fi].options) form.contact[fi].options = [];
    form.contact[fi].options.push('New Option');
    renderEditPanel();
    renderLivePreview();
};

window.removeContactOption = function(fi, oi) {
    if (!form || !form.contact || !form.contact[fi] || !form.contact[fi].options) return;
    form.contact[fi].options.splice(oi, 1);
    renderEditPanel();
    renderLivePreview();
};

window.updateContactOption = function(fi, oi, val) {
    if (!form || !form.contact || !form.contact[fi] || !form.contact[fi].options) return;
    form.contact[fi].options[oi] = val;
    renderLivePreview();
};

// -- live preview --
function renderLivePreview() {
    if (!form) return;
    var el = document.getElementById('live-preview');
    var l  = form.language || {};
    var cur = l.currency || '$';
    var stepMap = { services: 0, complexity: 1, addons: 2, contact: 3 };
    var stepIdx = stepMap[activeSection] || 0;
    var pct = Math.round((stepIdx + 1) / 4 * 100);

    var html = '<div class="lp-header">' +
        '<div class="lp-header-title">' + esc(l.headerTitle || 'Request a Quote') + '</div>' +
        (l.headerSubtitle ? '<div class="lp-header-sub">' + esc(l.headerSubtitle) + '</div>' : '') +
        '</div>';

    html += '<div class="lp-body">';
    html += '<div class="lp-progress"><div class="lp-progress-bar" style="width:' + pct + '%"></div></div>';
    html += '<p class="lp-step-ind">Step ' + (stepIdx + 1) + ' of 4</p>';
    html += '<div class="lp-step-block">';

    if (activeSection === 'services') {
        html += '<h2 class="lp-step-title">' + esc(l.serviceStepTitle || 'Select a Service') + '</h2>';
        html += '<p class="lp-step-desc">' + esc(l.serviceStepDesc || '') + '</p>';
        html += '<div class="lp-options">';
        (form.services || []).forEach(function(svc) {
            html += '<label class="lp-radio-opt"><input type="radio" name="lp-svc" disabled>' +
                '<span class="lp-opt-label">' + esc(svc.label) + '</span>' +
                '<span class="lp-opt-cost">' + fmtCost(svc.price, cur) + '</span></label>';
        });
        html += '</div>';
    }

    if (activeSection === 'complexity') {
        html += '<h2 class="lp-step-title">' + esc(l.complexityStepTitle || 'Complexity') + '</h2>';
        html += '<p class="lp-step-desc">' + esc(l.complexityStepDesc || '') + '</p>';
        html += '<div class="lp-options">';
        (form.complexity || []).forEach(function(c) {
            html += '<label class="lp-radio-opt"><input type="radio" name="lp-cplx" disabled>' +
                '<div style="flex:1;"><div class="lp-opt-label">' + esc(c.label) + '</div>' +
                (c.description ? '<div class="lp-opt-sub">' + esc(c.description) + '</div>' : '') +
                '</div><span class="lp-opt-cost">' + c.multiplier + 'x</span></label>';
        });
        html += '</div>';
    }

    if (activeSection === 'addons') {
        html += '<h2 class="lp-step-title">' + esc(l.addonStepTitle || 'Add-Ons') + '</h2>';
        html += '<p class="lp-step-desc">' + esc(l.addonStepDesc || '') + '</p>';
        html += '<div class="lp-options">';
        (form.addons || []).forEach(function(a) {
            html += '<label class="lp-check-opt"><input type="checkbox" disabled>' +
                '<span class="lp-opt-label">' + esc(a.label) + '</span>' +
                '<span class="lp-opt-cost">+' + fmtCost(a.price, cur) + '</span></label>';
        });
        if (!(form.addons || []).length) {
            html += '<p style="font-size:0.82rem;color:var(--lp-accent);padding:0.5rem 0;">No add-ons configured.</p>';
        }
        html += '</div>';
    }

    if (activeSection === 'contact') {
        html += '<h2 class="lp-step-title">' + esc(l.contactStepTitle || 'Contact') + '</h2>';
        html += '<p class="lp-step-desc">' + esc(l.contactStepDesc || '') + '</p>';
        html += '<div class="lp-fields">';
        (form.contact || []).forEach(function(f) {
            html += '<div class="lp-field-group"><label class="lp-label">' + esc(f.label) +
                (f.required ? '<span class="lp-req">*</span>' : '') + '</label>';
            if (f.type === 'select' && f.options) {
                html += '<select class="lp-input" disabled><option>-- Select --</option>';
                f.options.forEach(function(o) { html += '<option>' + esc(o) + '</option>'; });
                html += '</select>';
            } else {
                html += '<input type="' + esc(f.type || 'text') + '" class="lp-input" disabled placeholder="...">';
            }
            html += '</div>';
        });
        html += '</div>';
    }

    html += '</div>'; // lp-step-block

    html += '<div class="lp-actions">';
    if (stepIdx > 0) html += '<button class="lp-btn lp-btn-secondary" disabled>' + esc(l.backLabel || 'Back') + '</button>';
    if (stepIdx < 3) html += '<button class="lp-btn lp-btn-primary" disabled>' + esc(l.nextLabel || 'Next') + '</button>';
    if (stepIdx === 3) html += '<button class="lp-btn lp-btn-primary" disabled>' + esc(l.submitLabel || 'Get Estimate') + '</button>';
    html += '</div>';

    html += '<div class="lp-dots">';
    for (var d = 0; d < 4; d++) html += '<div class="lp-dot' + (d === stepIdx ? ' active' : '') + '"></div>';
    html += '</div>';

    html += '</div>'; // lp-body
    html += '<div class="lp-footer">Preview -- click Preview in toolbar to test the live form</div>';
    el.innerHTML = html;
}

function updatePreviewStyles() {
    var el = document.getElementById('live-preview');
    if (!el || !form) return;
    var s = form.style || {};
    el.style.setProperty('--lp-primary',     s.primaryColor || '#244c47');
    el.style.setProperty('--lp-accent',      s.accentColor  || '#459289');
    el.style.setProperty('--lp-bg',          s.bgColor      || '#fcfdfd');
    el.style.setProperty('--lp-text',        s.textColor    || '#182523');
    el.style.setProperty('--lp-header-bg',   s.headerBg     || '#244c47');
    el.style.setProperty('--lp-header-text', s.headerText   || '#eaf5f4');
    el.style.fontFamily = FONTS[s.font] || FONTS.system;
    el.style.fontSize   = (s.fontSize || 16) + 'px';
}

// -- style panel --
function populateStylePanel() {
    if (!form) return;
    var s = form.style || {};
    Object.keys(STYLE_MAP).forEach(function(id) {
        var v = s[STYLE_MAP[id]] || '';
        setVal(id, v);
        setVal(id + '-hex', v);
    });
    setVal('dp-font', s.font || 'system');
    setVal('dp-fontsize', s.fontSize || '16');
    var hEnable = document.getElementById('harmony-enable');
    var hBox    = document.getElementById('harmony-box');
    hEnable.checked = !!s.harmonyEnabled;
    setVal('harmony-mode', s.harmonyMode || 'complementary');
    if (s.harmonyEnabled) hBox.classList.remove('disabled');
    else hBox.classList.add('disabled');
}

window.styleChanged = function() {
    if (!form) return;
    if (!form.style) form.style = {};
    Object.keys(STYLE_MAP).forEach(function(id) {
        var el = document.getElementById(id);
        if (el) form.style[STYLE_MAP[id]] = el.value;
    });
    form.style.font     = getVal('dp-font');
    form.style.fontSize = getVal('dp-fontsize');
    form.style.harmonyEnabled = document.getElementById('harmony-enable').checked;
    form.style.harmonyMode    = getVal('harmony-mode');
    updatePreviewStyles();
};

window.styleColorSync = function(colorEl, hexId) {
    document.getElementById(hexId).value = colorEl.value;
    styleChanged();
    if (colorEl.id === 'dp-primary') applyHarmony();
};

window.styleHexSync = function(hexEl, colorId) {
    var v = hexEl.value.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        document.getElementById(colorId).value = v;
        styleChanged();
        if (colorId === 'dp-primary') applyHarmony();
    }
};

// -- color harmony --
function hexToHsl(hex) {
    var r = parseInt(hex.slice(1,3),16)/255;
    var g = parseInt(hex.slice(3,5),16)/255;
    var b = parseInt(hex.slice(5,7),16)/255;
    var max = Math.max(r,g,b), min = Math.min(r,g,b);
    var h = 0, s = 0, l = (max+min)/2;
    if (max !== min) {
        var d = max - min;
        s = l > 0.5 ? d/(2-max-min) : d/(max+min);
        if (max === r) h = ((g-b)/d + (g < b ? 6 : 0)) / 6;
        else if (max === g) h = ((b-r)/d + 2) / 6;
        else h = ((r-g)/d + 4) / 6;
    }
    return [h*360, s*100, l*100];
}

function hslToHex(h, s, l) {
    h = ((h % 360) + 360) % 360;
    s = Math.max(0, Math.min(100, s)) / 100;
    l = Math.max(0, Math.min(100, l)) / 100;
    var c = (1 - Math.abs(2*l - 1)) * s;
    var x = c * (1 - Math.abs((h/60) % 2 - 1));
    var m = l - c/2;
    var r = 0, g = 0, b = 0;
    if (h < 60)       { r=c; g=x; }
    else if (h < 120) { r=x; g=c; }
    else if (h < 180) { g=c; b=x; }
    else if (h < 240) { g=x; b=c; }
    else if (h < 300) { r=x; b=c; }
    else              { r=c; b=x; }
    var toHex = function(v) { var h = Math.round((v+m)*255).toString(16); return h.length < 2 ? '0'+h : h; };
    return '#' + toHex(r) + toHex(g) + toHex(b);
}

function computeHarmony(primaryHex, mode) {
    var hsl = hexToHsl(primaryHex);
    var h = hsl[0], s = hsl[1], l = hsl[2];
    var result = {};
    switch (mode) {
        case 'complementary':
            result.accentColor  = hslToHex((h+180)%360, s, l);
            result.bgColor      = hslToHex(h, Math.max(s-60,5), Math.min(l+42,97));
            result.textColor    = hslToHex(h, Math.min(s,20), Math.max(l-40,5));
            result.headerBg     = hslToHex(h, s, Math.max(l-8,5));
            result.headerText   = hslToHex(h, Math.max(s-55,5), Math.min(l+50,96));
            break;
        case 'analogous':
            result.accentColor  = hslToHex((h+30)%360, s, l);
            result.bgColor      = hslToHex((h-15+360)%360, Math.max(s-55,5), Math.min(l+42,97));
            result.textColor    = hslToHex(h, Math.min(s,20), Math.max(l-40,5));
            result.headerBg     = hslToHex((h-30+360)%360, s, Math.max(l-5,5));
            result.headerText   = hslToHex(h, Math.max(s-55,5), Math.min(l+50,96));
            break;
        case 'triadic':
            result.accentColor  = hslToHex((h+120)%360, s, l);
            result.bgColor      = hslToHex((h+240)%360, Math.max(s-60,5), Math.min(l+42,97));
            result.textColor    = hslToHex(h, Math.min(s,20), Math.max(l-40,5));
            result.headerBg     = hslToHex(h, s, Math.max(l-8,5));
            result.headerText   = hslToHex((h+240)%360, Math.max(s-55,5), Math.min(l+50,96));
            break;
        case 'split-complementary':
            result.accentColor  = hslToHex((h+150)%360, s, l);
            result.bgColor      = hslToHex((h+210)%360, Math.max(s-60,5), Math.min(l+42,97));
            result.textColor    = hslToHex(h, Math.min(s,20), Math.max(l-40,5));
            result.headerBg     = hslToHex(h, s, Math.max(l-8,5));
            result.headerText   = hslToHex(h, Math.max(s-55,5), Math.min(l+50,96));
            break;
        case 'monochromatic':
            result.accentColor  = hslToHex(h, Math.max(s-15,10), Math.min(l+15,65));
            result.bgColor      = hslToHex(h, Math.max(s-65,5), Math.min(l+45,97));
            result.textColor    = hslToHex(h, Math.min(s,20), Math.max(l-40,5));
            result.headerBg     = hslToHex(h, s, Math.max(l-10,5));
            result.headerText   = hslToHex(h, Math.max(s-60,5), Math.min(l+52,96));
            break;
    }
    return result;
}

function setColorField(domId, hex) {
    setVal(domId, hex);
    setVal(domId + '-hex', hex);
}

window.toggleHarmony = function() {
    var on = document.getElementById('harmony-enable').checked;
    var box = document.getElementById('harmony-box');
    if (on) box.classList.remove('disabled');
    else box.classList.add('disabled');
    if (form && form.style) {
        form.style.harmonyEnabled = on;
        form.style.harmonyMode = getVal('harmony-mode');
    }
    if (on) applyHarmony();
};

window.applyHarmony = function() {
    if (!form || !form.style) return;
    var on = document.getElementById('harmony-enable').checked;
    if (!on) return;
    var mode = getVal('harmony-mode');
    form.style.harmonyMode = mode;
    var primary = getVal('dp-primary') || '#244c47';
    var colors = computeHarmony(primary, mode);
    setColorField('dp-accent',     colors.accentColor);
    setColorField('dp-bg',         colors.bgColor);
    setColorField('dp-text',       colors.textColor);
    setColorField('dp-headerbg',   colors.headerBg);
    setColorField('dp-headertext', colors.headerText);
    styleChanged();
};

// -- language panel --
function populateLangPanel() {
    if (!form) return;
    var l = form.language || {};
    setVal('lang-form-name',     form.name        || '');
    setVal('lang-form-desc',     form.description || '');
    setVal('lang-header-title',  l.headerTitle    || '');
    setVal('lang-header-sub',    l.headerSubtitle || '');
    setVal('lang-svc-title',     l.serviceStepTitle    || '');
    setVal('lang-svc-desc',      l.serviceStepDesc     || '');
    setVal('lang-cplx-title',    l.complexityStepTitle || '');
    setVal('lang-cplx-desc',     l.complexityStepDesc  || '');
    setVal('lang-addon-title',   l.addonStepTitle      || '');
    setVal('lang-addon-desc',    l.addonStepDesc       || '');
    setVal('lang-contact-title', l.contactStepTitle    || '');
    setVal('lang-contact-desc',  l.contactStepDesc     || '');
    setVal('lang-next',          l.nextLabel      || '');
    setVal('lang-back',          l.backLabel      || '');
    setVal('lang-submit',        l.submitLabel    || '');
    setVal('lang-result-heading', l.resultHeading || '');
    setVal('lang-result-desc',   l.resultDesc     || '');
    setVal('lang-currency',      l.currency       || '$');
}

window.langChanged = function() {
    if (!form) return;
    form.name        = getVal('lang-form-name');
    form.description = getVal('lang-form-desc');
    if (!form.language) form.language = {};
    form.language.headerTitle        = getVal('lang-header-title');
    form.language.headerSubtitle     = getVal('lang-header-sub');
    form.language.serviceStepTitle   = getVal('lang-svc-title');
    form.language.serviceStepDesc    = getVal('lang-svc-desc');
    form.language.complexityStepTitle= getVal('lang-cplx-title');
    form.language.complexityStepDesc = getVal('lang-cplx-desc');
    form.language.addonStepTitle     = getVal('lang-addon-title');
    form.language.addonStepDesc      = getVal('lang-addon-desc');
    form.language.contactStepTitle   = getVal('lang-contact-title');
    form.language.contactStepDesc    = getVal('lang-contact-desc');
    form.language.nextLabel          = getVal('lang-next');
    form.language.backLabel          = getVal('lang-back');
    form.language.submitLabel        = getVal('lang-submit');
    form.language.resultHeading      = getVal('lang-result-heading');
    form.language.resultDesc         = getVal('lang-result-desc');
    form.language.currency           = getVal('lang-currency');
    document.getElementById('toolbar-form-name').textContent = form.name || 'Untitled';
    renderLivePreview();
};

// -- tiers panel --
function populateTiersPanel() {
    if (!form) return;
    renderTiersList();
    var cb = document.getElementById('tier-show-breakdown');
    if (cb) cb.checked = form.showBreakdown !== false;
}

function renderTiersList() {
    var el = document.getElementById('tiers-list');
    if (!form) { el.innerHTML = ''; return; }
    var tiers = form.tiers || [];
    el.innerHTML = tiers.map(function(t, ti) {
        return '<div class="edit-item">' +
            '<div class="edit-item-row">' +
            '<input type="text" class="ei-label" value="' + esc(t.name||'') + '" placeholder="Tier name" oninput="updateTier(' + ti + ',\'name\',this.value)">' +
            '<input type="number" class="ei-mult" value="' + (t.multiplier||1) + '" step="0.1" min="0.1" oninput="updateTier(' + ti + ',\'multiplier\',parseFloat(this.value)||1)">' +
            '<button class="opt-rm-btn" onclick="removeTier(' + ti + ')">&#10005;</button>' +
            '</div>' +
            '<input type="text" class="ei-desc" value="' + esc(t.description||'') + '" placeholder="Description" oninput="updateTier(' + ti + ',\'description\',this.value)">' +
        '</div>';
    }).join('');
}

window.addTier = function() {
    if (!form) return;
    if (!form.tiers) form.tiers = [];
    form.tiers.push({ name: 'New Tier', multiplier: 1.0, description: '' });
    renderTiersList();
};

window.removeTier = function(ti) {
    if (!form || !form.tiers) return;
    form.tiers.splice(ti, 1);
    renderTiersList();
};

window.updateTier = function(ti, key, val) {
    if (!form || !form.tiers || !form.tiers[ti]) return;
    form.tiers[ti][key] = val;
};

window.tiersMetaChanged = function() {
    if (!form) return;
    form.showBreakdown = document.getElementById('tier-show-breakdown').checked;
};

// -- tab switch --
window.showPropTab = function(name) {
    ['edit','style','lang','tiers'].forEach(function(n) {
        document.getElementById('ptab-' + n).classList.toggle('active', n === name);
        document.getElementById('ppanel-' + n).classList.toggle('active', n === name);
    });
};

// -- keyboard --
document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); saveForm(); }
});

// -- init --
loadFormsList();
<?php if ($initForm): ?>
loadForm(<?= $initJson ?>);
<?php endif; ?>

}());
</script>
</body>
</html>
