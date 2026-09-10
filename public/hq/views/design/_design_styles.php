<style>
.design-intro { color: var(--hq-muted); margin: 0 0 1.15rem; font-size: 0.95rem; }
.field-hint { font-size: 0.8rem; color: var(--hq-muted); margin: 0.35rem 0 0; }
.color-row { display: flex; align-items: center; gap: 0.5rem; }
.color-row input[type="color"] { width: 3rem; height: 2.25rem; padding: 0.15rem; }
.muted { color: var(--hq-muted); font-size: 0.85rem; }
.checkbox-label { display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 500; cursor: pointer; }
.token-swatch-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.65rem;
}
.token-swatch {
    display: flex; flex-direction: column; gap: 0.35rem;
    padding: 0.55rem; border: 1px solid var(--hq-border); border-radius: 8px;
    background: var(--hq-input-bg); cursor: pointer;
}
.token-swatch-label { font-size: 0.75rem; color: var(--hq-muted); }
.token-swatch input[type="color"] { width: 100%; height: 2.25rem; padding: 0; border: none; background: transparent; cursor: pointer; }
.token-swatch-hex { font-size: 0.7rem; color: var(--hq-muted); font-family: ui-monospace, monospace; }
.icon-mode-tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.5rem 0 0.75rem; }
.icon-mode-tab {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.75rem; border: 1px solid var(--hq-border); border-radius: 6px;
    background: var(--hq-input-bg); cursor: pointer; font-size: 0.85rem; color: var(--hq-muted);
}
.icon-mode-tab:has(input:checked) { border-color: var(--hq-accent); color: var(--hq-accent); }
.icon-mode-tab input { margin: 0; }
.icon-preset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem; }
.icon-preset-option {
    display: flex; flex-direction: column; align-items: center; gap: 0.35rem;
    padding: 0.7rem 0.4rem; border: 1px solid var(--hq-border); border-radius: 8px;
    background: var(--hq-input-bg); cursor: pointer; position: relative;
}
.icon-preset-option input { position: absolute; opacity: 0; pointer-events: none; }
.icon-preset-option.is-selected, .icon-preset-option:has(input:checked) {
    border-color: var(--hq-accent); box-shadow: 0 0 0 1px var(--hq-accent);
}
.icon-preset-preview { font-size: 1.2rem; color: var(--hq-text); }
.icon-preset-label { font-size: 0.7rem; color: var(--hq-muted); }
.variant-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
.variant-grid--hero { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
.variant-option { position: relative; cursor: pointer; }
.variant-option input { position: absolute; opacity: 0; pointer-events: none; }
.variant-preview {
    display: flex; align-items: center; justify-content: center; min-height: 3rem;
    padding: 0.5rem; border: 1px solid var(--hq-border); border-radius: 8px;
    font-size: 0.8rem; font-weight: 500; color: var(--hq-muted); background: var(--hq-input-bg);
}
.variant-option.is-selected .variant-preview,
.variant-option:has(input:checked) .variant-preview {
    border-color: var(--hq-accent); color: var(--hq-accent); box-shadow: 0 0 0 1px var(--hq-accent);
}
.variant-preview--btn-solid { background: var(--hq-accent); color: #fff; border-color: transparent; }
.variant-preview--btn-outline { border-width: 2px; border-color: var(--hq-accent); color: var(--hq-accent); }
.variant-preview--btn-ghost { background: transparent; }
.variant-preview--btn-soft { background: rgba(59, 130, 246, 0.15); color: var(--hq-accent); }
.variant-preview--card-elevated { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.variant-preview--card-outlined { border-width: 2px; }
.variant-preview--card-glass { background: rgba(255,255,255,0.06); }
.variant-preview--hero { min-height: 4rem; }
@media (max-width: 640px) {
    .token-swatch-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
