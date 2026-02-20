<?php
declare(strict_types=1);
/**
 * Form Builder — Editor UI
 * Loaded by index.php for GET /form-builder (no preview param)
 */

// If ?edit=id is passed, open that form; otherwise blank
$editId   = isset($_GET['edit']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['edit']) : '';
$initForm = $editId ? (builder_load_form($editId) ?? builder_blank()) : null;
// null means "show the forms list on load, JS will call blank or load"
$initJson = $initForm ? json_encode($initForm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Form Builder</title>
<style>
/* ── reset + tokens ──────────────────────────────────────────────────────── */
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
    --c-red-bg:     #fdf2f2;
    --c-hdr:        #081110;
    --c-hdr-text:   #eaf5f4;
    --sidebar-w:    300px;
    --toolbar-h:    50px;
}
html { font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--c-text); background: var(--c-bg); }
body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
button, input, select, textarea { font-family: inherit; font-size: inherit; }
a { color: var(--c-primary); text-decoration: none; }
a:hover { color: var(--c-accent); }

/* ── toolbar ─────────────────────────────────────────────────────────────── */
.toolbar {
    height: var(--toolbar-h);
    background: var(--c-hdr);
    color: var(--c-hdr-text);
    display: flex;
    align-items: center;
    gap: 0;
    flex-shrink: 0;
    border-bottom: 2px solid var(--c-primary);
    z-index: 50;
}
.toolbar-brand {
    padding: 0 1.1rem;
    font-weight: 700;
    font-size: 0.92rem;
    letter-spacing: 0.03em;
    white-space: nowrap;
    border-right: 1px solid #1e3a38;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.toolbar-brand span { color: var(--c-accent); }
.toolbar-sep { width: 1px; background: #1e3a38; height: 60%; margin: 0 0.25rem; }
.toolbar-btn {
    height: 100%;
    padding: 0 0.9rem;
    background: none;
    border: none;
    color: #9ab8b6;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    transition: color 0.15s;
}
.toolbar-btn:hover { color: var(--c-hdr-text); }
.toolbar-btn.active { color: var(--c-hdr-text); border-bottom-color: var(--c-accent); }
.toolbar-btn.danger { color: #e08080; }
.toolbar-btn.danger:hover { color: #f8b0b0; }
.toolbar-spacer { flex: 1; }
.toolbar-right {
    display: flex;
    align-items: center;
    height: 100%;
    border-left: 1px solid #1e3a38;
}
.toolbar-form-name {
    padding: 0 0.75rem;
    font-size: 0.78rem;
    color: #8ab0ae;
    font-family: monospace;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.toolbar-save-btn {
    height: 100%;
    padding: 0 1.2rem;
    background: var(--c-primary);
    border: none;
    color: var(--c-hdr-text);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: background 0.15s;
}
.toolbar-save-btn:hover { background: var(--c-primary-dk); }
.save-indicator {
    font-size: 0.7rem;
    color: var(--c-accent);
    padding: 0 0.6rem;
    opacity: 0;
    transition: opacity 0.4s;
}
.save-indicator.show { opacity: 1; }

/* ── layout ──────────────────────────────────────────────────────────────── */
.layout {
    flex: 1;
    display: flex;
    overflow: hidden;
}

/* ── left sidebar — forms list + steps ──────────────────────────────────── */
.sidebar-left {
    width: var(--sidebar-w);
    flex-shrink: 0;
    background: #f6faf9;
    border-right: 1px solid var(--c-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sidebar-section {
    border-bottom: 1px solid var(--c-border);
    flex-shrink: 0;
}
.sidebar-section-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.75rem;
    background: var(--c-surface);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--c-muted);
}
.sidebar-icon-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--c-primary);
    font-size: 1rem;
    line-height: 1;
    padding: 0.1rem 0.2rem;
    font-weight: 700;
}
.sidebar-icon-btn:hover { color: var(--c-accent); }
.sidebar-icon-btn.red { color: var(--c-red); }
.sidebar-icon-btn.red:hover { color: #c0392b; }
.forms-list-body { overflow-y: auto; max-height: 180px; }
.form-list-item {
    display: flex;
    align-items: center;
    gap: 0;
    border-bottom: 1px solid var(--c-border);
    cursor: pointer;
    transition: background 0.1s;
}
.form-list-item:last-child { border-bottom: none; }
.form-list-item:hover { background: #e8f2f1; }
.form-list-item.active { background: #d8edeb; }
.form-list-item-name {
    flex: 1;
    padding: 0.5rem 0.75rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--c-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.form-list-item-meta {
    font-size: 0.68rem;
    color: var(--c-muted);
    padding-right: 0.5rem;
    white-space: nowrap;
}
.form-list-actions {
    display: flex;
    flex-shrink: 0;
}
.form-list-action-btn {
    padding: 0.4rem 0.4rem;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--c-muted);
    line-height: 1;
}
.form-list-action-btn:hover { color: var(--c-primary); }
.form-list-action-btn.red:hover { color: var(--c-red); }

/* steps list */
.steps-body { overflow-y: auto; flex: 1; min-height: 0; }
.step-list-item {
    display: flex;
    align-items: center;
    gap: 0;
    border-bottom: 1px solid var(--c-border);
    cursor: pointer;
    transition: background 0.1s;
}
.step-list-item:last-child { border-bottom: none; }
.step-list-item:hover { background: #e8f2f1; }
.step-list-item.active { background: #d8edeb; border-left: 3px solid var(--c-primary); }
.step-num {
    padding: 0 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--c-muted);
    min-width: 30px;
    text-align: center;
}
.step-list-item.active .step-num { color: var(--c-primary); }
.step-list-name {
    flex: 1;
    padding: 0.5rem 0.3rem;
    font-size: 0.82rem;
    color: var(--c-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.step-list-actions { display: flex; flex-shrink: 0; }
.step-list-action {
    padding: 0.4rem 0.35rem;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.78rem;
    color: var(--c-muted);
    line-height: 1;
}
.step-list-action:hover { color: var(--c-primary); }
.step-list-action.red:hover { color: var(--c-red); }

/* ── center canvas ────────────────────────────────────────────────────────── */
.canvas-wrap {
    flex: 1;
    overflow-y: auto;
    background: var(--c-bg);
    padding: 1.5rem;
    min-width: 0;
}
.canvas-step-hdr {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.canvas-step-hdr input[type=text] {
    flex: 1;
    min-width: 140px;
    padding: 0.4rem 0.6rem;
    border: 1px solid var(--c-border);
    background: #fff;
    color: var(--c-text);
    font-size: 1rem;
    font-weight: 700;
}
.canvas-step-hdr input[type=text]:focus { outline: 2px solid var(--c-primary); }
.canvas-step-desc-row { margin-bottom: 1.25rem; }
.canvas-step-desc-row input[type=text] {
    width: 100%;
    padding: 0.35rem 0.55rem;
    border: 1px solid var(--c-border);
    background: #fff;
    color: var(--c-muted);
    font-size: 0.88rem;
}
.canvas-step-desc-row input:focus { outline: 2px solid var(--c-primary); }

.fields-area { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.25rem; }
.field-card {
    background: #fff;
    border: 1px solid var(--c-border);
    padding: 0.75rem 0.9rem;
    cursor: pointer;
    position: relative;
}
.field-card:hover { border-color: var(--c-accent); }
.field-card.selected { border-color: var(--c-primary); border-width: 2px; }
.field-card-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.field-card-drag {
    color: var(--c-border);
    font-size: 1.1rem;
    cursor: grab;
    flex-shrink: 0;
    line-height: 1;
}
.field-card-label {
    flex: 1;
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--c-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.field-card-type {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-muted);
    background: var(--c-surface);
    padding: 0.15rem 0.4rem;
    flex-shrink: 0;
}
.field-card-btns {
    display: flex;
    gap: 0.15rem;
    flex-shrink: 0;
}
.field-card-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--c-muted);
    font-size: 0.85rem;
    padding: 0.1rem 0.25rem;
    line-height: 1;
}
.field-card-btn:hover { color: var(--c-primary); }
.field-card-btn.red:hover { color: var(--c-red); }

.add-field-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.25rem;
}
.add-field-btn {
    padding: 0.35rem 0.7rem;
    background: #fff;
    border: 1px dashed var(--c-accent);
    color: var(--c-accent);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.add-field-btn:hover { background: var(--c-surface); border-color: var(--c-primary); color: var(--c-primary); }

/* empty step placeholder */
.canvas-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--c-muted);
    font-size: 0.88rem;
    border: 2px dashed var(--c-border);
}

/* no-form overlay */
#no-form-view {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    gap: 1rem;
    color: var(--c-muted);
    padding: 2rem;
    text-align: center;
}
#no-form-view h2 { font-size: 1.2rem; color: var(--c-text); }
.big-btn {
    padding: 0.65rem 1.6rem;
    font-size: 0.88rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    cursor: pointer;
    border: 2px solid var(--c-primary);
    background: var(--c-primary);
    color: var(--c-hdr-text);
}
.big-btn:hover { background: var(--c-primary-dk); }
.big-btn.secondary { background: transparent; color: var(--c-primary); }
.big-btn.secondary:hover { background: var(--c-surface); }

/* ── right sidebar — field / design properties ────────────────────────────── */
.sidebar-right {
    width: 270px;
    flex-shrink: 0;
    background: #f6faf9;
    border-left: 1px solid var(--c-border);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.props-tabs {
    display: flex;
    border-bottom: 2px solid var(--c-border);
    flex-shrink: 0;
}
.props-tab {
    flex: 1;
    padding: 0.55rem 0;
    background: none;
    border: none;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    color: var(--c-muted);
    border-bottom: 3px solid transparent;
}
.props-tab.active { color: var(--c-primary); border-bottom-color: var(--c-primary); }
.props-panel { padding: 0.85rem 0.75rem; display: none; }
.props-panel.active { display: block; }

/* right-sidebar form controls */
.prop-row { margin-bottom: 0.75rem; }
.prop-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--c-muted);
    margin-bottom: 0.3rem;
}
.prop-input, .prop-select, .prop-textarea {
    width: 100%;
    padding: 0.4rem 0.55rem;
    border: 1px solid var(--c-border);
    background: #fff;
    color: var(--c-text);
    font-size: 0.82rem;
}
.prop-textarea { resize: vertical; min-height: 60px; font-size: 0.78rem; }
.prop-input:focus, .prop-select:focus, .prop-textarea:focus { outline: 2px solid var(--c-primary); }
.prop-checkbox-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
.prop-checkbox-row input[type=checkbox] { accent-color: var(--c-primary); width: 14px; height: 14px; }
.prop-checkbox-row label { font-size: 0.82rem; color: var(--c-text); cursor: pointer; }
.prop-color-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
.prop-color-row label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--c-muted); flex: 1; }
.prop-color-row input[type=color] { width: 36px; height: 26px; padding: 1px; border: 1px solid var(--c-border); cursor: pointer; background: none; }
.prop-color-hex { width: 72px; padding: 0.3rem 0.4rem; border: 1px solid var(--c-border); font-size: 0.75rem; font-family: monospace; color: var(--c-text); background: #fff; }

/* options list */
.opt-list { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.5rem; }
.opt-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.opt-item input[type=text] {
    flex: 1;
    padding: 0.3rem 0.4rem;
    border: 1px solid var(--c-border);
    font-size: 0.78rem;
    background: #fff;
    color: var(--c-text);
}
.opt-item input:focus { outline: 2px solid var(--c-primary); }
.opt-rm-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--c-muted);
    font-size: 0.9rem;
    padding: 0 0.2rem;
}
.opt-rm-btn:hover { color: var(--c-red); }
.opt-add-btn {
    font-size: 0.72rem;
    padding: 0.25rem 0.6rem;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    cursor: pointer;
    color: var(--c-primary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.opt-add-btn:hover { background: var(--c-border); }

.prop-section-title {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--c-muted);
    margin: 1rem 0 0.5rem;
    padding-bottom: 0.3rem;
    border-bottom: 1px solid var(--c-border);
}
.prop-section-title:first-child { margin-top: 0; }

/* no-selection placeholder */
.no-sel {
    padding: 2rem 0.75rem;
    text-align: center;
    color: var(--c-muted);
    font-size: 0.82rem;
    line-height: 1.55;
}

/* ── notifications ───────────────────────────────────────────────────────── */
.notif {
    position: fixed;
    bottom: 1.25rem;
    right: 1.25rem;
    padding: 0.65rem 1rem;
    font-size: 0.82rem;
    font-weight: 600;
    background: var(--c-hdr);
    color: var(--c-hdr-text);
    border-left: 4px solid var(--c-accent);
    z-index: 999;
    max-width: 320px;
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 0.2s, transform 0.2s;
    pointer-events: none;
}
.notif.show { opacity: 1; transform: translateY(0); }
.notif.err { border-left-color: var(--c-red); }
</style>
</head>
<body>

<!-- TOOLBAR ----------------------------------------------------------------->
<div class="toolbar">
    <div class="toolbar-brand">Form <span>Builder</span></div>

    <button class="toolbar-btn active" id="tbtn-editor" onclick="showEditorView()">Editor</button>
    <button class="toolbar-btn" id="tbtn-forms" onclick="showFormsList()">All Forms</button>
    <div class="toolbar-sep"></div>
    <button class="toolbar-btn" id="tbtn-preview" onclick="openPreview()" title="Open preview in new tab">Preview</button>
    <button class="toolbar-btn danger" id="tbtn-delete" onclick="deleteCurrentForm()" title="Delete this form">Delete</button>

    <div class="toolbar-spacer"></div>
    <div class="toolbar-right">
        <div class="toolbar-form-name" id="toolbar-form-name">—</div>
        <span class="save-indicator" id="save-indicator">Saved</span>
        <button class="toolbar-save-btn" onclick="saveForm()">Save</button>
    </div>
</div>

<!-- LAYOUT ------------------------------------------------------------------>
<div class="layout">

    <!-- LEFT SIDEBAR -->
    <div class="sidebar-left">

        <!-- Forms list -->
        <div class="sidebar-section">
            <div class="sidebar-section-hdr">
                Saved Forms
                <div style="display:flex;gap:0.25rem;">
                    <button class="sidebar-icon-btn" title="New form" onclick="newForm()">+</button>
                    <button class="sidebar-icon-btn" title="Refresh list" onclick="loadFormsList()">&#8635;</button>
                </div>
            </div>
            <div class="forms-list-body" id="forms-list-body">
                <div style="padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--c-muted);">Loading...</div>
            </div>
        </div>

        <!-- Steps list -->
        <div class="sidebar-section" style="flex-shrink:0;">
            <div class="sidebar-section-hdr">
                Steps
                <button class="sidebar-icon-btn" title="Add step" onclick="addStep()">+</button>
            </div>
        </div>
        <div class="steps-body" id="steps-body">
            <div style="padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--c-muted);">Open or create a form.</div>
        </div>
    </div>

    <!-- CANVAS -->
    <div class="canvas-wrap" id="canvas-wrap">
        <div id="no-form-view">
            <h2>Form Builder</h2>
            <p>Create a new form or select one from the list on the left.</p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;margin-top:0.5rem;">
                <button class="big-btn" onclick="newForm()">New Form</button>
                <button class="big-btn secondary" onclick="loadFormsList()">Open Saved</button>
            </div>
        </div>
        <div id="editor-view" style="display:none;">
            <div class="canvas-step-hdr">
                <input type="text" id="step-title-input" placeholder="Step title" oninput="updateStepTitle(this.value)">
            </div>
            <div class="canvas-step-desc-row">
                <input type="text" id="step-desc-input" placeholder="Step description (optional)" oninput="updateStepDesc(this.value)">
            </div>
            <div class="fields-area" id="fields-area"></div>
            <div class="add-field-row" id="add-field-row"></div>
        </div>
    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="sidebar-right">
        <div class="props-tabs">
            <button class="props-tab active" id="ptab-field" onclick="showPropTab('field')">Field</button>
            <button class="props-tab" id="ptab-design" onclick="showPropTab('design')">Design</button>
            <button class="props-tab" id="ptab-form" onclick="showPropTab('form')">Form</button>
        </div>

        <!-- Field properties -->
        <div class="props-panel active" id="ppanel-field">
            <div class="no-sel" id="no-field-sel">Select a field on the canvas to edit its properties.</div>
            <div id="field-props" style="display:none;"></div>
        </div>

        <!-- Design properties -->
        <div class="props-panel" id="ppanel-design">
            <p class="prop-section-title">Colors</p>
            <div class="prop-color-row"><label>Primary</label>    <input type="color" id="dp-primary"   oninput="syncColorHex(this,'dp-primary-hex')">   <input type="text" class="prop-color-hex" id="dp-primary-hex"   oninput="syncHexColor(this,'dp-primary')"></div>
            <div class="prop-color-row"><label>Accent</label>     <input type="color" id="dp-accent"    oninput="syncColorHex(this,'dp-accent-hex')">    <input type="text" class="prop-color-hex" id="dp-accent-hex"    oninput="syncHexColor(this,'dp-accent')"></div>
            <div class="prop-color-row"><label>Background</label> <input type="color" id="dp-bg"        oninput="syncColorHex(this,'dp-bg-hex')">        <input type="text" class="prop-color-hex" id="dp-bg-hex"        oninput="syncHexColor(this,'dp-bg')"></div>
            <div class="prop-color-row"><label>Text</label>       <input type="color" id="dp-text"      oninput="syncColorHex(this,'dp-text-hex')">      <input type="text" class="prop-color-hex" id="dp-text-hex"      oninput="syncHexColor(this,'dp-text')"></div>
            <div class="prop-color-row"><label>Header bg</label>  <input type="color" id="dp-headerbg"  oninput="syncColorHex(this,'dp-headerbg-hex')">  <input type="text" class="prop-color-hex" id="dp-headerbg-hex"  oninput="syncHexColor(this,'dp-headerbg')"></div>
            <div class="prop-color-row"><label>Header text</label><input type="color" id="dp-headertext"oninput="syncColorHex(this,'dp-headertext-hex')"><input type="text" class="prop-color-hex" id="dp-headertext-hex" oninput="syncHexColor(this,'dp-headertext')"></div>

            <p class="prop-section-title">Typography</p>
            <div class="prop-row">
                <label class="prop-label">Font</label>
                <select class="prop-select" id="dp-font" onchange="designChanged()">
                    <option value="system">System UI (default)</option>
                    <option value="inter">Inter / Helvetica</option>
                    <option value="serif">Georgia (serif)</option>
                    <option value="mono">Monospace</option>
                    <option value="trebuchet">Trebuchet</option>
                </select>
            </div>
            <div class="prop-row">
                <label class="prop-label">Base font size (px)</label>
                <input type="number" class="prop-input" id="dp-fontsize" min="12" max="22" step="1" value="16" oninput="designChanged()">
            </div>

            <p class="prop-section-title">Shape</p>
            <div class="prop-row">
                <label class="prop-label">Border radius (px)</label>
                <input type="number" class="prop-input" id="dp-radius" min="0" max="20" step="1" value="0" oninput="designChanged()">
            </div>
        </div>

        <!-- Form meta properties -->
        <div class="props-panel" id="ppanel-form">
            <p class="prop-section-title">Form Info</p>
            <div class="prop-row">
                <label class="prop-label">Form name</label>
                <input type="text" class="prop-input" id="fp-name" placeholder="My Form" oninput="formMetaChanged()">
            </div>
            <div class="prop-row">
                <label class="prop-label">Description</label>
                <textarea class="prop-textarea" id="fp-desc" placeholder="Internal notes..." oninput="formMetaChanged()"></textarea>
            </div>

            <p class="prop-section-title">Header Text</p>
            <div class="prop-row">
                <label class="prop-label">Header title</label>
                <input type="text" class="prop-input" id="fp-header-title" placeholder="Request a Quote" oninput="formMetaChanged()">
            </div>
            <div class="prop-row">
                <label class="prop-label">Header subtitle</label>
                <input type="text" class="prop-input" id="fp-header-sub" placeholder="Optional tagline" oninput="formMetaChanged()">
            </div>

            <p class="prop-section-title">Button Labels</p>
            <div class="prop-row">
                <label class="prop-label">Submit button</label>
                <input type="text" class="prop-input" id="fp-submit-label" placeholder="Submit" oninput="formMetaChanged()">
            </div>
            <div class="prop-row">
                <label class="prop-label">Next button</label>
                <input type="text" class="prop-input" id="fp-next-label" placeholder="Next" oninput="formMetaChanged()">
            </div>
            <div class="prop-row">
                <label class="prop-label">Back button</label>
                <input type="text" class="prop-input" id="fp-back-label" placeholder="Back" oninput="formMetaChanged()">
            </div>
        </div>
    </div>

</div><!-- /layout -->

<!-- NOTIFICATION -->
<div class="notif" id="notif"></div>

<script>
(function () {
'use strict';

// ── state ─────────────────────────────────────────────────────────────────
var form        = null;   // current form object
var curStep     = 0;      // active step index
var curField    = null;   // {si, fi} selected field
var formsList   = [];     // list of saved forms meta

// ── field types catalog ──────────────────────────────────────────────────
var FIELD_TYPES = [
    { type: 'text',          label: '+ Text'         },
    { type: 'email',         label: '+ Email'        },
    { type: 'number',        label: '+ Number'       },
    { type: 'tel',           label: '+ Phone'        },
    { type: 'textarea',      label: '+ Textarea'     },
    { type: 'select',        label: '+ Dropdown'     },
    { type: 'radio',         label: '+ Radio'        },
    { type: 'checkbox_group',label: '+ Checkboxes'   },
    { type: 'heading',       label: '+ Heading'      },
    { type: 'paragraph',     label: '+ Paragraph'    },
    { type: 'divider',       label: '+ Divider'      },
];

function defaultField(type) {
    var base = { type: type, label: '', placeholder: '', required: false };
    if (type === 'heading')       base.label = 'Section Heading';
    if (type === 'paragraph')     base.label = 'Paragraph'; base.placeholder = 'Your text here...';
    if (type === 'divider')       base.label = '';
    if (['select','radio','checkbox_group'].includes(type)) {
        base.options = [{ label: 'Option 1' }, { label: 'Option 2' }];
    }
    return base;
}

// ── API ───────────────────────────────────────────────────────────────────
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

// ── notifications ─────────────────────────────────────────────────────────
var notifTimer = null;
function notify(msg, err) {
    var el = document.getElementById('notif');
    el.textContent = msg;
    el.className   = 'notif show' + (err ? ' err' : '');
    clearTimeout(notifTimer);
    notifTimer = setTimeout(function(){ el.classList.remove('show'); }, 2800);
}

// ── forms list ────────────────────────────────────────────────────────────
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
        var dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        return '<div class="form-list-item' + (isActive ? ' active' : '') + '" data-id="' + esc(f.id) + '">' +
            '<div class="form-list-item-name" onclick="openFormById(\'' + esc(f.id) + '\')" title="' + esc(f.name) + '">' + esc(f.name) + '</div>' +
            '<div class="form-list-item-meta">' + f.step_count + ' step' + (f.step_count!==1?'s':'') + ' &bull; ' + dateStr + '</div>' +
            '<div class="form-list-actions">' +
            '<button class="form-list-action-btn" title="Duplicate" onclick="duplicateForm(\'' + esc(f.id) + '\')">&#9107;</button>' +
            '<button class="form-list-action-btn red" title="Delete" onclick="confirmDeleteForm(\'' + esc(f.id) + '\',\'' + esc(f.name) + '\')">&#10005;</button>' +
            '</div>' +
        '</div>';
    }).join('');
}

function openFormById(id) {
    api('load', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        loadForm(d.form);
        notify('Opened: ' + d.form.name);
    });
}

function duplicateForm(id) {
    api('duplicate', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        loadFormsList();
        loadForm(d.form);
        notify('Duplicated as "' + d.form.name + '"');
    });
}

function confirmDeleteForm(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    api('delete', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        if (form && form.id === id) { form = null; showNoFormView(); }
        loadFormsList();
        notify('Deleted "' + name + '"');
    });
}

// ── load form into editor ─────────────────────────────────────────────────
function loadForm(f) {
    form     = f;
    curStep  = 0;
    curField = null;
    showEditorView();
    renderStepsList();
    renderCanvas();
    populateDesignPanel();
    populateFormPanel();
    document.getElementById('toolbar-form-name').textContent = f.name || 'Untitled';
    loadFormsList(); // refresh list to update active state
}

// ── new / blank ────────────────────────────────────────────────────────────
window.newForm = function() {
    api('blank', null, function(d) {
        loadForm(d.form);
        showPropTab('form');
        notify('New form created. Give it a name and save.');
    });
};

// ── save ──────────────────────────────────────────────────────────────────
window.saveForm = function() {
    if (!form) { notify('No form open.', true); return; }
    api('save', { form: form }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        form = d.form;
        document.getElementById('toolbar-form-name').textContent = form.name;
        loadFormsList();
        // flash indicator
        var ind = document.getElementById('save-indicator');
        ind.classList.add('show');
        setTimeout(function(){ ind.classList.remove('show'); }, 1800);
        notify('Saved "' + form.name + '"');
    });
};

// ── delete current form ───────────────────────────────────────────────────
window.deleteCurrentForm = function() {
    if (!form) return;
    if (!confirm('Delete "' + form.name + '"? This cannot be undone.')) return;
    var id = form.id;
    if (!id) { form = null; showNoFormView(); return; }
    api('delete', { id: id }, function(d) {
        if (d.error) { notify(d.error, true); return; }
        form = null;
        loadFormsList();
        showNoFormView();
        notify('Form deleted.');
    });
};

// ── preview ───────────────────────────────────────────────────────────────
window.openPreview = function() {
    if (!form) { notify('Save the form first, then preview.', true); return; }
    if (!form.id) { saveForm(); return; }
    window.open('/form-builder?preview=1&id=' + encodeURIComponent(form.id), '_blank');
};

// ── view helpers ──────────────────────────────────────────────────────────
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
        '<h2>Saved Forms</h2>' +
        '<p>Select a form from the left panel, or create a new one.</p>' +
        '<div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;margin-top:0.5rem;">' +
        '<button class="big-btn" onclick="newForm()">New Form</button>' +
        '</div>';
    setActive('tbtn-forms', ['tbtn-editor','tbtn-forms']);
};

function showNoFormView() {
    document.getElementById('no-form-view').innerHTML =
        '<h2>Form Builder</h2>' +
        '<p>Create a new form or select one from the list on the left.</p>' +
        '<div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;margin-top:0.5rem;">' +
        '<button class="big-btn" onclick="newForm()">New Form</button>' +
        '<button class="big-btn secondary" onclick="loadFormsList()">Open Saved</button>' +
        '</div>';
    document.getElementById('no-form-view').style.display = '';
    document.getElementById('editor-view').style.display  = 'none';
    document.getElementById('toolbar-form-name').textContent = '—';
    renderStepsList();
}

function setActive(id, group) {
    group.forEach(function(bid){ document.getElementById(bid).classList.remove('active'); });
    document.getElementById(id).classList.add('active');
}

// ── steps ─────────────────────────────────────────────────────────────────
function renderStepsList() {
    var body = document.getElementById('steps-body');
    if (!form) { body.innerHTML = '<div style="padding:0.65rem 0.75rem;font-size:0.78rem;color:var(--c-muted);">Open or create a form.</div>'; return; }
    body.innerHTML = (form.steps || []).map(function(s, i) {
        return '<div class="step-list-item' + (i === curStep ? ' active' : '') + '" data-si="' + i + '">' +
            '<div class="step-num">' + (i+1) + '</div>' +
            '<div class="step-list-name" onclick="selectStep(' + i + ')">' + esc(s.title || ('Step ' + (i+1))) + '</div>' +
            '<div class="step-list-actions">' +
            (i > 0 ? '<button class="step-list-action" title="Move up" onclick="moveStep(' + i + ',-1)">&#9650;</button>' : '') +
            (i < form.steps.length-1 ? '<button class="step-list-action" title="Move down" onclick="moveStep(' + i + ',1)">&#9660;</button>' : '') +
            '<button class="step-list-action red" title="Delete step" onclick="deleteStep(' + i + ')">&#10005;</button>' +
            '</div>' +
        '</div>';
    }).join('');
}

window.selectStep = function(i) {
    curStep  = i;
    curField = null;
    renderStepsList();
    renderCanvas();
    clearFieldProps();
};

window.addStep = function() {
    if (!form) return;
    form.steps.push({ title: 'New Step', description: '', fields: [] });
    curStep = form.steps.length - 1;
    curField = null;
    renderStepsList();
    renderCanvas();
};

window.deleteStep = function(i) {
    if (!form || form.steps.length <= 1) { notify('A form must have at least one step.', true); return; }
    if (!confirm('Delete this step and all its fields?')) return;
    form.steps.splice(i, 1);
    if (curStep >= form.steps.length) curStep = form.steps.length - 1;
    curField = null;
    renderStepsList();
    renderCanvas();
};

window.moveStep = function(i, dir) {
    var j = i + dir;
    if (!form || j < 0 || j >= form.steps.length) return;
    var tmp = form.steps[i]; form.steps[i] = form.steps[j]; form.steps[j] = tmp;
    if (curStep === i) curStep = j;
    else if (curStep === j) curStep = i;
    renderStepsList();
    renderCanvas();
};

window.updateStepTitle = function(v) {
    if (!form) return;
    form.steps[curStep].title = v;
    renderStepsList();
};
window.updateStepDesc = function(v) {
    if (!form) return;
    form.steps[curStep].description = v;
};

// ── canvas ────────────────────────────────────────────────────────────────
function renderCanvas() {
    if (!form) return;
    var step = form.steps[curStep];
    document.getElementById('step-title-input').value = step.title || '';
    document.getElementById('step-desc-input').value  = step.description || '';

    // fields
    var area  = document.getElementById('fields-area');
    var fields = step.fields || [];
    if (!fields.length) {
        area.innerHTML = '<div class="canvas-empty">No fields yet. Use the buttons below to add fields.</div>';
    } else {
        area.innerHTML = fields.map(function(f, fi) {
            var isSel = curField && curField.si === curStep && curField.fi === fi;
            var label = f.label || ('<em style="color:var(--c-muted)">Unlabeled ' + f.type + '</em>');
            return '<div class="field-card' + (isSel ? ' selected' : '') + '" onclick="selectField(' + fi + ')" data-fi="' + fi + '">' +
                '<div class="field-card-top">' +
                '<span class="field-card-drag" title="Drag to reorder">&#8801;</span>' +
                '<span class="field-card-label">' + label + '</span>' +
                '<span class="field-card-type">' + f.type + '</span>' +
                '<div class="field-card-btns">' +
                (fi > 0 ? '<button class="field-card-btn" title="Move up" onclick="event.stopPropagation();moveField(' + fi + ',-1)">&#9650;</button>' : '') +
                (fi < fields.length-1 ? '<button class="field-card-btn" title="Move down" onclick="event.stopPropagation();moveField(' + fi + ',1)">&#9660;</button>' : '') +
                '<button class="field-card-btn red" title="Remove field" onclick="event.stopPropagation();removeField(' + fi + ')">&#10005;</button>' +
                '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    // add field buttons
    var row = document.getElementById('add-field-row');
    row.innerHTML = FIELD_TYPES.map(function(ft) {
        return '<button class="add-field-btn" onclick="addField(\'' + ft.type + '\')">' + ft.label + '</button>';
    }).join('');
}

// ── fields ────────────────────────────────────────────────────────────────
window.addField = function(type) {
    if (!form) return;
    form.steps[curStep].fields.push(defaultField(type));
    var fi = form.steps[curStep].fields.length - 1;
    curField = { si: curStep, fi: fi };
    renderCanvas();
    renderFieldProps();
};

window.selectField = function(fi) {
    curField = { si: curStep, fi: fi };
    renderCanvas();
    renderFieldProps();
    showPropTab('field');
};

window.removeField = function(fi) {
    if (!form) return;
    form.steps[curStep].fields.splice(fi, 1);
    if (curField && curField.fi === fi) curField = null;
    renderCanvas();
    clearFieldProps();
};

window.moveField = function(fi, dir) {
    var fields = form.steps[curStep].fields;
    var j = fi + dir;
    if (j < 0 || j >= fields.length) return;
    var tmp = fields[fi]; fields[fi] = fields[j]; fields[j] = tmp;
    if (curField && curField.fi === fi) curField.fi = j;
    renderCanvas();
    renderFieldProps();
};

// ── field properties panel ────────────────────────────────────────────────
function clearFieldProps() {
    document.getElementById('no-field-sel').style.display = '';
    document.getElementById('field-props').style.display  = 'none';
}

function renderFieldProps() {
    if (!curField || !form) { clearFieldProps(); return; }
    var field = form.steps[curField.si].fields[curField.fi];
    if (!field) { clearFieldProps(); return; }
    document.getElementById('no-field-sel').style.display = 'none';
    var fp = document.getElementById('field-props');
    fp.style.display = '';

    var hasPlaceholder = !['radio','checkbox_group','heading','divider'].includes(field.type);
    var hasOptions     = ['select','radio','checkbox_group'].includes(field.type);
    var hasRequired    = !['heading','paragraph','divider'].includes(field.type);

    var opts = '';
    if (hasOptions) {
        var optRows = (field.options || []).map(function(o, oi) {
            return '<div class="opt-item">' +
                '<input type="text" value="' + esc(o.label || '') + '" placeholder="Option label" oninput="updateOption(' + oi + ',this.value)">' +
                '<button class="opt-rm-btn" onclick="removeOption(' + oi + ')">&#10005;</button>' +
            '</div>';
        }).join('');
        opts = '<p class="prop-section-title">Options</p>' +
            '<div class="opt-list" id="opt-list">' + optRows + '</div>' +
            '<button class="opt-add-btn" onclick="addOption()">+ Add Option</button>';
    }

    fp.innerHTML =
        '<p class="prop-section-title">Field: ' + field.type + '</p>' +
        '<div class="prop-row"><label class="prop-label">Label</label>' +
            '<input type="text" class="prop-input" id="fp-label" value="' + esc(field.label || '') + '" oninput="updateField(\'label\',this.value)"></div>' +
        (hasPlaceholder ? '<div class="prop-row"><label class="prop-label">Placeholder</label>' +
            '<input type="text" class="prop-input" id="fp-ph" value="' + esc(field.placeholder || '') + '" oninput="updateField(\'placeholder\',this.value)"></div>' : '') +
        (hasRequired ? '<div class="prop-checkbox-row">' +
            '<input type="checkbox" id="fp-req" ' + (field.required ? 'checked' : '') + ' onchange="updateField(\'required\',this.checked)">' +
            '<label for="fp-req">Required</label></div>' : '') +
        opts;
}

window.updateField = function(key, val) {
    if (!curField || !form) return;
    form.steps[curField.si].fields[curField.fi][key] = val;
    if (key === 'label') renderCanvas();
};

window.addOption = function() {
    if (!curField || !form) return;
    var field = form.steps[curField.si].fields[curField.fi];
    if (!field.options) field.options = [];
    field.options.push({ label: 'New Option' });
    renderFieldProps();
};

window.removeOption = function(oi) {
    if (!curField || !form) return;
    form.steps[curField.si].fields[curField.fi].options.splice(oi, 1);
    renderFieldProps();
};

window.updateOption = function(oi, val) {
    if (!curField || !form) return;
    form.steps[curField.si].fields[curField.fi].options[oi].label = val;
};

// ── design panel ──────────────────────────────────────────────────────────
var designFieldMap = {
    'dp-primary':    'primaryColor',
    'dp-accent':     'accentColor',
    'dp-bg':         'bgColor',
    'dp-text':       'textColor',
    'dp-headerbg':   'headerBg',
    'dp-headertext': 'headerText',
};

function populateDesignPanel() {
    if (!form) return;
    var d = form.design || {};
    Object.keys(designFieldMap).forEach(function(id) {
        var key = designFieldMap[id];
        var el  = document.getElementById(id);
        if (el) el.value = d[key] || '';
        var hex = document.getElementById(id + '-hex');
        if (hex) hex.value = d[key] || '';
    });
    var fnt = document.getElementById('dp-font');
    if (fnt) fnt.value = d.font || 'system';
    var fs = document.getElementById('dp-fontsize');
    if (fs) fs.value = d.fontSize || '16';
    var rad = document.getElementById('dp-radius');
    if (rad) rad.value = d.borderRadius || '0';
}

window.designChanged = function() {
    if (!form) return;
    if (!form.design) form.design = {};
    Object.keys(designFieldMap).forEach(function(id) {
        var el = document.getElementById(id);
        if (el) form.design[designFieldMap[id]] = el.value;
    });
    form.design.font        = document.getElementById('dp-font').value;
    form.design.fontSize    = document.getElementById('dp-fontsize').value;
    form.design.borderRadius= document.getElementById('dp-radius').value;
};

window.syncColorHex = function(colorEl, hexId) {
    document.getElementById(hexId).value = colorEl.value;
    designChanged();
};

window.syncHexColor = function(hexEl, colorId) {
    var v = hexEl.value.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        document.getElementById(colorId).value = v;
        designChanged();
    }
};

// ── form meta panel ────────────────────────────────────────────────────────
function populateFormPanel() {
    if (!form) return;
    var d = form.design || {};
    setVal('fp-name',         form.name        || '');
    setVal('fp-desc',         form.description || '');
    setVal('fp-header-title', d.headerTitle    || '');
    setVal('fp-header-sub',   d.headerSubtitle || '');
    setVal('fp-submit-label', d.submitLabel    || '');
    setVal('fp-next-label',   d.nextLabel      || '');
    setVal('fp-back-label',   d.backLabel      || '');
}

window.formMetaChanged = function() {
    if (!form) return;
    form.name        = getVal('fp-name');
    form.description = getVal('fp-desc');
    if (!form.design) form.design = {};
    form.design.headerTitle    = getVal('fp-header-title');
    form.design.headerSubtitle = getVal('fp-header-sub');
    form.design.submitLabel    = getVal('fp-submit-label');
    form.design.nextLabel      = getVal('fp-next-label');
    form.design.backLabel      = getVal('fp-back-label');
    document.getElementById('toolbar-form-name').textContent = form.name || 'Untitled';
};

// ── right panel tab switch ────────────────────────────────────────────────
window.showPropTab = function(name) {
    ['field','design','form'].forEach(function(n) {
        document.getElementById('ptab-' + n).classList.toggle('active', n === name);
        document.getElementById('ppanel-' + n).classList.toggle('active', n === name);
    });
};

// ── utils ─────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function setVal(id, v) { var e = document.getElementById(id); if(e) e.value = v; }
function getVal(id)    { var e = document.getElementById(id); return e ? e.value : ''; }

// ── keyboard shortcut ──────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        saveForm();
    }
});

// ── init ──────────────────────────────────────────────────────────────────
loadFormsList();

<?php if ($initForm): ?>
loadForm(<?= $initJson ?>);
<?php endif; ?>

}());
</script>
</body>
</html>
