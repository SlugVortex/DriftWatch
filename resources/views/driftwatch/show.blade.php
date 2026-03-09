{{-- resources/views/driftwatch/show.blade.php --}}
{{-- PR detail page - consolidated view: summary, impact map, action items --}}
@extends('layouts.app')

@section('title', "PR #{$pullRequest->pr_number}")
@section('heading', "PR #{$pullRequest->pr_number}")

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('driftwatch.pull-requests') }}" class="text-decoration-none">
            <span class="text-secondary fw-medium hover">Pull Requests</span>
        </a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">PR #{{ $pullRequest->pr_number }}</span>
    </li>
@endsection

@push('styles')
<style>
    /* === PR Detail Page — Glow Up Styles === */

    /* Elevated cards with subtle shadow + hover lift */
    .card.dw-card {
        border: 1px solid rgba(0,0,0,0.04) !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,0,0,0.03);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .card.dw-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.06);
    }
    [data-theme=dark] .card.dw-card {
        border-color: rgba(255,255,255,0.06) !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2), 0 4px 16px rgba(0,0,0,0.15);
    }

    /* Section headers with accent underline */
    .dw-section-title {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 8px;
    }
    .dw-section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 32px;
        height: 3px;
        border-radius: 2px;
        background: #605DFF;
    }

    /* Pipeline step badges */
    .pipeline-step {
        padding: 6px 12px;
        border-radius: 8px;
        background: #f8fafc;
        transition: all 0.15s;
    }
    .pipeline-step.done { background: rgba(16,185,129,0.06); }
    [data-theme=dark] .pipeline-step { background: #1e293b; }
    [data-theme=dark] .pipeline-step.done { background: rgba(16,185,129,0.1); }

    /* Deploy decision banner glow */
    .dw-banner {
        position: relative;
        overflow: hidden;
    }
    .dw-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255,255,255,0.08);
        pointer-events: none;
    }

    /* Stat counters */
    .dw-stat {
        padding: 12px 8px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid rgba(0,0,0,0.03);
        transition: all 0.15s;
    }
    .dw-stat:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    [data-theme=dark] .dw-stat { background: #0f172a; border-color: #334155; }

    /* Time bomb cards */
    .dw-bomb-card {
        background: #fff;
        border-radius: 10px;
        padding: 16px;
        border: 1px solid rgba(0,0,0,0.06);
        transition: all 0.15s;
    }
    .dw-bomb-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-1px); }
    [data-theme=dark] .dw-bomb-card { background: #1e293b; border-color: #334155; }

    /* Node hover glow on dag tree */
    #dagTreeSvg .node rect, #dagTreeSvg .node circle, #dagTreeSvg .node polygon {
        cursor: pointer;
        transition: filter 0.15s;
    }
    #dagTreeSvg .node:hover rect, #dagTreeSvg .node:hover polygon {
        filter: brightness(1.15) drop-shadow(0 0 6px rgba(96,93,255,0.3));
    }

    #blastRadiusDynamic {
        border: 1px solid #e2e8f0 !important;
        border-radius: 10px !important;
        background: #f8fafc !important;
    }[data-theme=dark] #blastRadiusDynamic {
        border-color: #334155 !important;
        background: #1e293b !important;
    }
    #blastRadiusStructural {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        overflow: auto;
    }
    [data-theme=dark] #blastRadiusStructural {
        border-color: #334155;
        background: #1e293b;
    }
    div.vis-tooltip {
        background: #1e293b !important;
        color: #f1f5f9 !important;
        border: 1px solid #475569 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
        font-size: 12px !important;
        line-height: 1.5 !important;
        max-width: 320px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    }
    div.vis-navigation div.vis-button {
        background-color: #605DFF !important;
        border-radius: 6px !important;
    }
    .collapse-toggle { cursor: pointer; user-select: none; }
    .collapse-toggle .material-symbols-outlined { transition: transform 0.2s; }
    .collapse-toggle.collapsed .material-symbols-outlined { transform: rotate(-90deg); }
    .legend-btn { border: 1px solid #e2e8f0 !important; background: #fff; transition: all 0.2s; }
    .legend-btn:hover { border-color: #605DFF !important; }
    .legend-btn.dimmed { opacity: 0.35; background: #f8fafc; }
    [data-theme=dark] .legend-btn { border-color: #334155 !important; background: #1e293b; color: #e2e8f0; }[data-theme=dark] .legend-btn.dimmed { background: #0f172a; }[data-theme=dark] #graphHoverCard .card { background: rgba(30,41,59,0.97) !important; }

    /* === Blast Map — animated concentric radius === */
    @keyframes blastPulse { 0% { opacity: 0.6; transform: scale(0.95); } 50% { opacity: 1; transform: scale(1.02); } 100% { opacity: 0.6; transform: scale(0.95); } }
    @keyframes blastRingExpand { 0% { r: 0; opacity: 0.8; stroke-width: 3; } 100% { r: 300; opacity: 0; stroke-width: 0.5; } }
    @keyframes blastNodeAppear { 0% { opacity: 0; transform: scale(0); } 60% { opacity: 1; transform: scale(1.15); } 100% { opacity: 1; transform: scale(1); } }
    @keyframes blastOrbit { 0% { transform: rotate(0deg) translateX(var(--orbit-r)) rotate(0deg); } 100% { transform: rotate(360deg) translateX(var(--orbit-r)) rotate(-360deg); } }
    .blast-node { cursor: pointer; transition: filter 0.2s, opacity 0.2s; }
    .blast-node:hover { filter: url(#glowStrong) brightness(1.3); }
    .blast-node-label { fill: #e2e8f0; font-size: 11px; pointer-events: none; text-anchor: middle; dominant-baseline: central; font-weight: 500; text-shadow: 0 1px 4px rgba(0,0,0,0.8); }
    .blast-ring-static { fill: none; stroke-dasharray: 4 6; }
    .blast-ring-label { font-size: 11px; text-anchor: middle; letter-spacing: 3px; text-transform: uppercase; font-weight: 700; }
    .blast-connection { stroke-linecap: round; transition: opacity 0.2s; }
    [data-theme=dark] #graphHoverCard .fw-bold { color: #f1f5f9 !important; }[data-theme=dark] #graphHoverCard .text-secondary { color: #94a3b8 !important; }
    #blastRadiusDynamic canvas { border-radius: 10px; }

    /* Dominant Risk Score Circle */
    .risk-score-circle {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        position: relative;
        background: rgba(255,255,255,0.6);
        backdrop-filter: blur(4px);
    }
    [data-theme=dark] .risk-score-circle { background: rgba(15,23,42,0.4); }
    .risk-score-circle .score-number {
        font-size: 48px;
        font-weight: 800;
        line-height: 1;
    }
    .risk-score-circle .score-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .risk-score-ring-green { border: 6px solid #10B981; box-shadow: 0 0 20px rgba(16,185,129,0.15); }
    .risk-score-ring-yellow { border: 6px solid #F59E0B; box-shadow: 0 0 20px rgba(245,158,11,0.15); }
    .risk-score-ring-orange { border: 6px solid #F97316; box-shadow: 0 0 20px rgba(249,115,22,0.15); }
    .risk-score-ring-red { border: 6px solid #EF4444; box-shadow: 0 0 20px rgba(239,68,68,0.15); }
    .risk-score-ring-darkred { border: 6px solid #991B1B; animation: pulse-risk 2s ease-in-out infinite; }
    @keyframes pulse-risk {
        0%, 100% { box-shadow: 0 0 0 0 rgba(153,27,27,0.4); }
        50% { box-shadow: 0 0 0 12px rgba(153,27,27,0); }
    }

    /* Dependency Tree DAG */
    #dagTreeContainer { position: relative; }
    [data-theme=dark] #dagTreeContainer { background: #1e293b !important; border-color: #334155 !important; }[data-theme=dark] #dagTreeSvg text { fill: #e2e8f0 !important; }
    #dagTreeSvg .edgePath path { fill: none; }

    /* Tree layout: graph + chat panel */
    .tree-layout { display: flex; gap: 0; position: relative; height: 600px; }
    .tree-graph-area { flex: 1; min-width: 0; transition: flex 0.3s ease; overflow: auto; }
    .tree-side-panel { width: 0; overflow: hidden; transition: width 0.3s ease, opacity 0.2s ease; opacity: 0; border-left: 2px solid #e2e8f0; background: #fff; display: flex; flex-direction: column; height: 100%; flex-shrink: 0; position: relative; }
    .tree-side-panel.open { width: 480px; opacity: 1; overflow: visible; }
    .tree-side-panel .resize-handle { position: absolute; left: -4px; top: 0; bottom: 0; width: 8px; cursor: col-resize; z-index: 20; background: transparent; }
    .tree-side-panel .resize-handle:hover, .tree-side-panel .resize-handle.dragging { background: rgba(96,93,255,0.3); }
    .tree-side-panel .resize-handle::after { content: ''; position: absolute; left: 3px; top: 50%; transform: translateY(-50%); width: 2px; height: 32px; background: #cbd5e1; border-radius: 2px; transition: background 0.2s; }
    .tree-side-panel .resize-handle:hover::after, .tree-side-panel .resize-handle.dragging::after { background: #605DFF; }
    [data-theme=dark] .tree-side-panel { border-color: #334155; background: #1e293b; }
    .tree-side-panel .sp-header { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; background: #fff; z-index: 2; }
    [data-theme=dark] .tree-side-panel .sp-header { background: #1e293b; border-color: #334155; }
    .tree-side-panel .sp-close { cursor: pointer; border: none; background: none; color: #94a3b8; padding: 4px; border-radius: 6px; line-height: 1; }
    .tree-side-panel .sp-close:hover { background: #f1f5f9; color: #1e293b; }
    [data-theme=dark] .tree-side-panel .sp-close:hover { background: #334155; color: #e2e8f0; }
    .tree-side-panel .chat-input-area { padding: 10px 14px; border-top: 1px solid #e2e8f0; background: #fff; flex-shrink: 0; }
    [data-theme=dark] .tree-side-panel .chat-input-area { border-color: #334155; background: #1e293b; }
    [data-theme=dark] #chatMessages { background: #0f172a; }
    [data-theme=dark] #chatInput { background: #0f172a; border-color: #334155; color: #e2e8f0; }

    /* Chat bubbles */
    .chat-msg { display: flex; }
    .chat-msg.chat-bot { justify-content: flex-start; }
    .chat-msg.chat-user { justify-content: flex-end; }
    .chat-bubble { max-width: 95%; padding: 10px 14px; border-radius: 14px; font-size: 12px; line-height: 1.6; }
    .chat-bot .chat-bubble { background: #f1f5f9; color: #334155; border-bottom-left-radius: 4px; }
    .chat-user .chat-bubble { background: #605DFF; color: #fff; border-bottom-right-radius: 4px; }
    [data-theme=dark] .chat-bot .chat-bubble { background: #1e293b; color: #e2e8f0; }
    .chat-suggestions { display: flex; flex-wrap: wrap; gap: 6px; }
    .chat-suggest-btn { font-size: 11px; padding: 4px 10px; border-radius: 14px; border: 1px solid #CBD5E1; background: #fff; color: #475569; cursor: pointer; transition: all 0.15s; }
    .chat-suggest-btn:hover { border-color: #605DFF; background: #EEF2FF; color: #605DFF; }
    [data-theme=dark] .chat-suggest-btn { background: #0f172a; color: #94a3b8; border-color: #334155; }
    [data-theme=dark] .chat-suggest-btn:hover { border-color: #605DFF; background: #1e1b4b; color: #a5b4fc; }
    .chat-file-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin: 6px 0; cursor: pointer; transition: all 0.15s; }
    .chat-file-card:hover { border-color: #605DFF; box-shadow: 0 2px 8px rgba(96,93,255,0.1); }
    [data-theme=dark] .chat-file-card { background: #0f172a; border-color: #334155; }
    .chat-risk-bar { height: 4px; border-radius: 2px; background: #f1f5f9; overflow: hidden; margin-top: 4px; }
    [data-theme=dark] .chat-risk-bar { background: #334155; }
    .chat-risk-fill { height: 100%; border-radius: 2px; }

    /* Collapsible summary */
    .tree-summary-collapse { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
    .tree-summary-collapse.open { max-height: 600px; }

    /* Custom scrollbars */
    #dagTreeContainer::-webkit-scrollbar, #chatMessages::-webkit-scrollbar { width: 6px; height: 6px; }
    #dagTreeContainer::-webkit-scrollbar-track, #chatMessages::-webkit-scrollbar-track { background: transparent; }
    #dagTreeContainer::-webkit-scrollbar-thumb, #chatMessages::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
    #dagTreeContainer::-webkit-scrollbar-thumb:hover, #chatMessages::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    [data-theme=dark] #dagTreeContainer::-webkit-scrollbar-thumb, [data-theme=dark] #chatMessages::-webkit-scrollbar-thumb { background: #475569; }
    #dagTreeContainer, #chatMessages { scrollbar-width: thin; scrollbar-color: #CBD5E1 transparent; }
    [data-theme=dark] #dagTreeContainer, [data-theme=dark] #chatMessages { scrollbar-color: #475569 transparent; }

    /* Quick action buttons shown under file card */
    .chat-quick-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .chat-quick-btn { font-size: 10px; padding: 3px 10px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; color: #605DFF; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 3px; }
    .chat-quick-btn:hover { background: #EEF2FF; border-color: #605DFF; }
    [data-theme=dark] .chat-quick-btn { background: #0f172a; color: #a5b4fc; border-color: #334155; }
    [data-theme=dark] .chat-quick-btn:hover { background: #1e1b4b; border-color: #605DFF; }

    /* AI confidence badge */
    .chat-confidence { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; color: #94a3b8; margin-top: 4px; }
    .chat-confidence-dot { width: 6px; height: 6px; border-radius: 50%; }

    /* Typing animation */
    .chat-typing-dots span { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #605DFF; margin: 0 2px; animation: chat-dot-bounce 1.4s infinite ease-in-out both; }
    .chat-typing-dots span:nth-child(1) { animation-delay: -0.32s; }
    .chat-typing-dots span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes chat-dot-bounce { 0%,80%,100% { transform: scale(0); } 40% { transform: scale(1); } }

    /* Export button */
    .chat-export-btn { font-size: 10px; color: #94a3b8; cursor: pointer; border: none; background: none; padding: 2px; }
    .chat-export-btn:hover { color: #605DFF; }

    /* Inline code viewer in chat */
    .chat-code-viewer { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin: 6px 0; font-size: 11px; }
    [data-theme=dark] .chat-code-viewer { border-color: #334155; }
    .chat-code-header { display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
    [data-theme=dark] .chat-code-header { background: #1e293b; border-color: #334155; }
    .chat-code-header .file-name { font-family: monospace; font-size: 10px; font-weight: 600; color: #605DFF; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .chat-code-header .code-tab { font-size: 10px; padding: 2px 8px; border-radius: 4px; border: 1px solid transparent; background: none; color: #64748b; cursor: pointer; }
    .chat-code-header .code-tab.active { background: #fff; border-color: #e2e8f0; color: #1e293b; font-weight: 600; }
    [data-theme=dark] .chat-code-header .code-tab.active { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    .chat-code-body { max-height: 360px; overflow: auto; background: #1e1e2e; color: #cdd6f4; padding: 10px 12px; font-family: 'Cascadia Code', 'Fira Code', Consolas, monospace; font-size: 11px; line-height: 1.6; white-space: pre; tab-size: 4; }
    .chat-code-body::-webkit-scrollbar { width: 5px; height: 5px; }
    .chat-code-body::-webkit-scrollbar-thumb { background: #45475a; border-radius: 3px; }
    .chat-code-body .line-num { display: inline-block; min-width: 32px; color: #585b70; text-align: right; padding-right: 12px; user-select: none; }
    .chat-code-body .diff-add { background: rgba(16,185,129,0.15); display: block; }
    .chat-code-body .diff-del { background: rgba(239,68,68,0.15); display: block; }
    .chat-code-body .diff-hdr { color: #89b4fa; font-weight: bold; }
    .chat-code-stats { display: flex; gap: 8px; font-size: 10px; color: #94a3b8; padding: 4px 10px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    [data-theme=dark] .chat-code-stats { background: #0f172a; border-color: #334155; }

    /* Full-screen code preview modal */
    .code-preview-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: none; backdrop-filter: blur(4px); }
    .code-preview-overlay.show { display: flex; align-items: center; justify-content: center; }
    .code-preview-modal { width: 92vw; max-width: 1200px; height: 85vh; background: #1e1e2e; border-radius: 14px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 48px rgba(0,0,0,0.4); }
    .code-preview-modal .cpm-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #181825; border-bottom: 1px solid #313244; flex-shrink: 0; }
    .code-preview-modal .cpm-header .file-info { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .code-preview-modal .cpm-header .file-name { font-family: 'Cascadia Code', 'Fira Code', Consolas, monospace; font-size: 13px; color: #cba6f7; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .code-preview-modal .cpm-header .file-path { font-family: monospace; font-size: 11px; color: #6c7086; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .code-preview-modal .cpm-toolbar { display: flex; align-items: center; gap: 6px; padding: 8px 20px; background: #11111b; border-bottom: 1px solid #313244; flex-shrink: 0; flex-wrap: wrap; }
    .code-preview-modal .cpm-tab { font-size: 12px; padding: 5px 14px; border-radius: 6px; border: 1px solid transparent; background: none; color: #a6adc8; cursor: pointer; font-family: inherit; transition: all 0.15s; }
    .code-preview-modal .cpm-tab.active { background: #313244; color: #cdd6f4; border-color: #45475a; font-weight: 600; }
    .code-preview-modal .cpm-tab:hover:not(.active) { background: #1e1e2e; color: #cdd6f4; }
    .code-preview-modal .cpm-stats { display: flex; gap: 14px; margin-left: auto; font-size: 11px; color: #6c7086; }
    .code-preview-modal .cpm-stats .stat-badge { display: flex; align-items: center; gap: 4px; }
    .code-preview-modal .cpm-stats .stat-add { color: #a6e3a1; }
    .code-preview-modal .cpm-stats .stat-del { color: #f38ba8; }
    .code-preview-modal .cpm-body { flex: 1; overflow: auto; padding: 16px 20px; font-family: 'Cascadia Code', 'Fira Code', Consolas, monospace; font-size: 13px; line-height: 1.7; color: #cdd6f4; white-space: pre; tab-size: 4; }
    .code-preview-modal .cpm-body::-webkit-scrollbar { width: 8px; height: 8px; }
    .code-preview-modal .cpm-body::-webkit-scrollbar-thumb { background: #45475a; border-radius: 4px; }
    .code-preview-modal .cpm-body::-webkit-scrollbar-track { background: #1e1e2e; }
    .code-preview-modal .cpm-body .line-num { display: inline-block; min-width: 48px; color: #45475a; text-align: right; padding-right: 16px; user-select: none; border-right: 1px solid #313244; margin-right: 16px; }
    .code-preview-modal .cpm-body .diff-add { background: rgba(166,227,161,0.1); display: block; }
    .code-preview-modal .cpm-body .diff-del { background: rgba(243,139,168,0.1); display: block; }
    .code-preview-modal .cpm-body .diff-hdr { color: #89b4fa; font-weight: bold; }
    .code-preview-modal .cpm-close { background: none; border: none; color: #6c7086; cursor: pointer; padding: 6px; border-radius: 8px; line-height: 1; transition: all 0.15s; }
    .code-preview-modal .cpm-close:hover { background: #313244; color: #f38ba8; }
    .code-preview-modal .cpm-action-bar { display: flex; align-items: center; gap: 6px; }
    .code-preview-modal .cpm-action { font-size: 11px; padding: 4px 10px; border-radius: 5px; border: 1px solid #313244; background: #181825; color: #a6adc8; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s; }
    .code-preview-modal .cpm-action:hover { background: #313244; color: #cdd6f4; border-color: #45475a; }

    /* Tree legend — floating bottom-left */
    .tree-legend { position: absolute; bottom: 12px; left: 12px; background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; z-index: 10; backdrop-filter: blur(8px); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    [data-theme=dark] .tree-legend { background: rgba(30,41,59,0.95); border-color: #334155; }
    .tree-legend-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 6px; }
    .tree-legend-item { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #475569; line-height: 1.8; }
    [data-theme=dark] .tree-legend-item { color: #94a3b8; }
    .tree-legend-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; display: inline-block; }

    /* Speech-to-text button */
    .chat-mic-btn { border: none; background: none; color: #94a3b8; padding: 4px; border-radius: 50%; cursor: pointer; transition: all 0.15s; }
    .chat-mic-btn:hover { color: #605DFF; background: rgba(96,93,255,0.1); }
    .chat-mic-btn.recording { color: #EF4444; animation: mic-pulse 1s infinite; }
    @keyframes mic-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.15); } }

    /* Highlighted nodes/edges */
    #dagTreeSvg .node-highlighted rect, #dagTreeSvg .node-highlighted circle { filter: drop-shadow(0 0 8px rgba(96,93,255,0.6)); }
    #dagTreeSvg .node-dimmed { opacity: 0.2; }
    #dagTreeSvg .edge-highlighted path { stroke: #605DFF !important; stroke-width: 3px !important; filter: drop-shadow(0 0 4px rgba(96,93,255,0.4)); }
    #dagTreeSvg .edge-dimmed path { opacity: 0.1; }

    /* Review item tooltip — positioned below the item, always on-screen */
    .review-item { position: relative; transition: border-color 0.15s, box-shadow 0.15s; }
    .review-item:hover { border-color: #CBD5E1 !important; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .review-tooltip { display: none; position: absolute; z-index: 50; left: 0; top: 100%; margin-top: 6px; width: 340px; max-width: calc(100vw - 40px); background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); padding: 14px; pointer-events: none; }
    .review-item:hover .review-tooltip { display: block; }
    .review-tooltip::before { content: ''; position: absolute; top: -8px; left: 24px; border: 8px solid transparent; border-bottom-color: #e2e8f0; border-top: 0; }
    .review-tooltip::after { content: ''; position: absolute; top: -6px; left: 25px; border: 7px solid transparent; border-bottom-color: #fff; border-top: 0; }
    [data-theme=dark] .review-tooltip { background: #1e293b; border-color: #334155; }
    [data-theme=dark] .review-tooltip::after { border-bottom-color: #1e293b; }
    [data-theme=dark] .review-tooltip::before { border-bottom-color: #334155; }

    /* Review filter buttons */
    #reviewFilterBtns .btn.active { font-weight: 600; }
    #reviewFilterBtns .btn { padding: 2px 8px; }

    /* Score Factor Accordion */
    .score-factor-row { cursor: pointer; transition: background 0.15s; border-radius: 6px; }
    .score-factor-row:hover { background: rgba(96,93,255,0.04); }
    .score-factor-detail { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
    .score-factor-detail.show { max-height: 500px; }

    /* File Detail Modal */
    #fileDetailModal .modal-content { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05); }
    #fileDetailModal .modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px 24px 16px; background: linear-gradient(135deg, #fafbfc, #f8fafc); }
    #fileDetailModal .modal-body { padding: 0; }
    #fileDetailModal .modal-footer { border-top: 1px solid #f1f5f9; padding: 12px 24px; background: #fafbfc; }
    #fileDetailModal .fd-section { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; }
    #fileDetailModal .fd-section:last-child { border-bottom: 0; }
    #fileDetailModal .fd-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #94a3b8; margin-bottom: 6px; }
    #fileDetailModal .fd-summary { font-size: 14px; line-height: 1.7; color: #334155; }
    #fileDetailModal .fd-risk-bar { height: 6px; border-radius: 3px; background: #f1f5f9; overflow: hidden; }
    #fileDetailModal .fd-risk-fill { height: 100%; border-radius: 3px; transition: width 0.4s ease; }
    #fileDetailModal .fd-dep-item { padding: 10px 14px; background: #f8fafc; border-radius: 10px; margin-bottom: 8px; border: 1px solid rgba(0,0,0,0.03); }
    [data-theme=dark] #fileDetailModal .modal-content { background: #1e293b; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
    [data-theme=dark] #fileDetailModal .modal-header { border-color: #334155; background: linear-gradient(135deg, #1e293b, #0f172a); }
    [data-theme=dark] #fileDetailModal .fd-section { border-color: #334155; }
    [data-theme=dark] #fileDetailModal .fd-summary { color: #e2e8f0; }
    [data-theme=dark] #fileDetailModal .fd-dep-item { background: #0f172a; border-color: #334155; }
    [data-theme=dark] #fileDetailModal .fd-risk-bar { background: #334155; }
    [data-theme=dark] #fileDetailModal .modal-footer { border-color: #334155; background: #0f172a; }

    /* Edge hover tooltip */
    #dagEdgeTooltip { display: none; position: fixed; z-index: 101; padding: 8px 14px; border-radius: 8px; background: rgba(30,41,59,0.95); color: #f1f5f9; font-size: 12px; max-width: 320px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); pointer-events: none; }

    /* Review Checklist */
    .review-item { transition: all 0.15s; border-radius: 8px; border-color: #f1f5f9 !important; }
    .review-item:hover { border-color: #605DFF !important; box-shadow: 0 2px 8px rgba(96,93,255,0.08); }
    .review-item .review-file-link { color: #605DFF; text-decoration: none; font-weight: 500; font-size: 12px; }
    .review-item .review-file-link:hover { text-decoration: underline; }
    [data-theme=dark] .review-item { border-color: #334155 !important; }[data-theme=dark] .review-item:hover { border-color: #605DFF !important; }

    /* Collapse icon rotation */
    #reviewCollapseBody.show ~ .card-body #reviewCollapseIcon,
    .collapsed #reviewCollapseIcon { transform: rotate(180deg); }

    /* Floating scroll nav arrows */
    .scroll-nav { position: fixed; bottom: 24px; right: 24px; z-index: 100; display: flex; flex-direction: column; gap: 6px; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    .scroll-nav.visible { opacity: 1; pointer-events: auto; }
    .scroll-nav-btn { width: 38px; height: 38px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.15s; }
    .scroll-nav-btn:hover { background: #605DFF; color: #fff; border-color: #605DFF; }
    [data-theme=dark] .scroll-nav-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
    [data-theme=dark] .scroll-nav-btn:hover { background: #605DFF; color: #fff; border-color: #605DFF; }

    /* Full-screen modal code highlight selection */
    .code-preview-modal .cpm-body ::selection { background: rgba(249,115,22,0.35); color: inherit; }
    .code-preview-modal .cpm-body .code-highlight { background: rgba(249,115,22,0.25); border-left: 3px solid #F97316; padding-left: 8px; display: block; }
    .code-preview-modal .cpm-body .code-highlight.selected { background: rgba(249,115,22,0.4); }

    /* Code attachment in chat */
    .chat-code-attachment { background: #FFF7ED; border: 1px solid #FDBA74; border-radius: 8px; padding: 8px 12px; margin: 6px 0; font-size: 11px; }
    [data-theme=dark] .chat-code-attachment { background: #431407; border-color: #9A3412; }
    .chat-code-attachment .att-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #EA580C; display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
    .chat-code-attachment pre { margin: 0; font-family: 'Cascadia Code', 'Fira Code', Consolas, monospace; font-size: 11px; line-height: 1.5; color: #1e293b; white-space: pre-wrap; word-break: break-all; max-height: 120px; overflow: auto; }
    [data-theme=dark] .chat-code-attachment pre { color: #fed7aa; }

    /* Multi-file panel in full-screen modal */
    .cpm-file-panel { width: 0; overflow: hidden; border-right: 1px solid #313244; background: #11111b; transition: width 0.3s; flex-shrink: 0; display: flex; flex-direction: column; }
    .cpm-file-panel.open { width: 220px; }
    .cpm-file-panel .fp-header { padding: 10px 14px; border-bottom: 1px solid #313244; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .cpm-file-panel .fp-header span { font-size: 12px; font-weight: 600; color: #a6adc8; }
    .cpm-file-panel .fp-list { flex: 1; overflow-y: auto; padding: 6px 0; }
    .cpm-file-item { padding: 6px 14px; font-size: 11px; color: #6c7086; cursor: pointer; display: flex; align-items: center; gap: 6px; border-left: 2px solid transparent; transition: all 0.15s; }
    .cpm-file-item:hover { background: #1e1e2e; color: #cdd6f4; }
    .cpm-file-item.active { background: #1e1e2e; color: #cba6f7; border-left-color: #cba6f7; font-weight: 600; }

    /* TTS speaker icon */
    .dw-tts-btn { border: none; background: none; color: #94a3b8; cursor: pointer; padding: 2px; border-radius: 4px; line-height: 1; transition: all 0.15s; }
    .dw-tts-btn:hover { color: #605DFF; background: rgba(96,93,255,0.08); }
    .dw-tts-btn.playing { color: #605DFF; animation: tts-pulse 1.5s infinite; }
    @keyframes tts-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>
@endpush

@section('content')

    {{-- APPROVAL GATE BANNER — shows when pipeline is paused awaiting human review --}}
    @if($pullRequest->pipeline_paused)
        <div class="bg-warning bg-opacity-15 border border-warning border-opacity-25 rounded-3 mb-4 p-4 dw-banner">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="wh-48 bg-warning bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                        <span class="material-symbols-outlined text-warning" style="font-size:28px;">front_hand</span>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Pipeline Paused — Awaiting Approval</h6>
                        <p class="text-secondary fs-13 mb-0">
                            {{ $pullRequest->paused_reason ?? 'Manual approval required before the Negotiator agent makes its decision.' }}
                        </p>
                        @if($pullRequest->paused_at)
                            <span class="fs-11 text-secondary">Paused {{ $pullRequest->paused_at->diffForHumans() }} at the <strong>{{ ucfirst($pullRequest->paused_at_stage ?? 'negotiator') }}</strong> stage</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <form action="{{ route('driftwatch.resume-pipeline', $pullRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success" onclick="return confirm('Resume the pipeline? The Negotiator will make its deploy decision.')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">play_arrow</span>
                            Resume Pipeline
                        </button>
                    </form>
                    <form action="{{ route('driftwatch.block', $pullRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Block this deployment?')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">block</span>
                            Block
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- DEPLOY DECISION BANNER — full-width, top of page, impossible to miss --}}
    @if($pullRequest->deploymentDecision)
        @php
            $bannerDecision = $pullRequest->deploymentDecision->decision;
            $bannerConfig = match($bannerDecision) {
                'approved' =>['bg' => 'bg-success', 'label' => 'SAFE TO DEPLOY', 'icon' => 'verified'],
                'blocked' =>['bg' => 'bg-danger', 'label' => 'DEPLOYMENT BLOCKED', 'icon' => 'block'],
                default =>['bg' => 'bg-warning text-dark', 'label' => 'AWAITING HUMAN DECISION', 'icon' => 'hourglass_top'],
            };
        @endphp
        <div class="{{ $bannerConfig['bg'] }} rounded-3 mb-4 dw-banner shadow-sm" style="min-height: 80px; display: flex; align-items: center;">
            <div class="d-flex align-items-center justify-content-center gap-3 p-4 w-100">
                <span class="material-symbols-outlined @if($bannerDecision == 'pending_review') text-dark @else text-white @endif" style="font-size: 36px;">{{ $bannerConfig['icon'] }}</span>
                <span class="fw-bold fs-3 text-uppercase letter-spacing-1 @if($bannerDecision == 'pending_review') text-dark @else text-white @endif">
                    {{ $bannerConfig['label'] }}
                </span>
                @if($pullRequest->deploymentDecision->decided_by)
                    <span class="badge bg-white bg-opacity-25 fs-14 ms-3 p-2 shadow-sm @if($bannerDecision == 'pending_review') text-dark @else text-white @endif">
                        Decision by: {{ $pullRequest->deploymentDecision->decided_by }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- PR Header + Pipeline --}}
    <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
        <div class="card-body p-4">
            {{-- Title row --}}
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-{{ $pullRequest->status_color }} bg-opacity-10 text-{{ $pullRequest->status_color }} px-2 py-1 text-capitalize fs-13">
                            {{ str_replace('_', ' ', $pullRequest->status) }}
                        </span>
                        <h5 class="fw-bold mb-0">{{ $pullRequest->pr_title }}</h5>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap text-secondary fs-13">
                        <span>
                            <span class="material-symbols-outlined align-middle fs-16">person</span>
                            {{ $pullRequest->pr_author }}
                        </span>
                        <span>
                            <span class="material-symbols-outlined align-middle fs-16">account_tree</span>
                            {{ $pullRequest->head_branch }} → {{ $pullRequest->base_branch }}
                        </span>
                        <span>
                            <span class="material-symbols-outlined align-middle fs-16">folder</span>
                            {{ $pullRequest->repo_full_name }}
                        </span>
                        <span>
                            <span class="fw-medium text-success">+{{ $pullRequest->additions }}</span> /
                            <span class="fw-medium text-danger">-{{ $pullRequest->deletions }}</span>
                            in {{ $pullRequest->files_changed }} files
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    @if($pullRequest->deploymentDecision && $pullRequest->deploymentDecision->decision === 'pending_review')
                        <form action="{{ route('driftwatch.approve', $pullRequest) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success shadow-sm" onclick="return confirm('Approve this deployment?')">
                                <span class="material-symbols-outlined align-middle me-1 fs-16">check_circle</span> Approve
                            </button>
                        </form>
                        <form action="{{ route('driftwatch.block', $pullRequest) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger shadow-sm" onclick="return confirm('Block this deployment?')">
                                <span class="material-symbols-outlined align-middle me-1 fs-16">block</span> Block
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('driftwatch.reanalyze', $pullRequest) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning shadow-sm" onclick="return confirm('Re-run all agents on this PR?')">
                            <span class="material-symbols-outlined align-middle me-1 fs-16">refresh</span> Re-analyze
                        </button>
                    </form>
                    <a href="{{ $pullRequest->pr_url }}" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm">
                        <span class="material-symbols-outlined align-middle me-1 fs-16">open_in_new</span> GitHub
                    </a>
                </div>
            </div>

            {{-- Agent Pipeline (compact inline) --}}
            @php
                $stepsComplete = 0;
                if ($pullRequest->blastRadius) $stepsComplete++;
                if ($pullRequest->riskAssessment) $stepsComplete++;
                if ($pullRequest->deploymentDecision) $stepsComplete++;
                if ($pullRequest->deploymentOutcome) $stepsComplete++;

                $agents = [['name' => 'Archaeologist', 'icon' => 'explore', 'color' => 'primary', 'done' => (bool) $pullRequest->blastRadius],['name' => 'Historian', 'icon' => 'history', 'color' => 'warning', 'done' => (bool) $pullRequest->riskAssessment],['name' => 'Negotiator', 'icon' => 'gavel', 'color' => 'danger', 'done' => (bool) $pullRequest->deploymentDecision],['name' => 'Chronicler', 'icon' => 'auto_stories', 'color' => 'success', 'done' => (bool) $pullRequest->deploymentOutcome],
                ];
            @endphp
            <div class="d-flex align-items-center gap-2 pt-3 border-top flex-wrap">
                <span class="fs-13 text-secondary fw-medium me-1">Pipeline:</span>
                @foreach($agents as $i => $agent)
                    <div class="pipeline-step {{ $agent['done'] ? 'done' : '' }} d-flex align-items-center gap-1">
                        @if($agent['done'])
                            <span class="material-symbols-outlined text-success" style="font-size: 16px;">check_circle</span>
                        @else
                            <span class="material-symbols-outlined text-secondary" style="font-size: 16px;">radio_button_unchecked</span>
                        @endif
                        <span class="fs-12 {{ $agent['done'] ? 'fw-medium' : 'text-secondary' }}">{{ $agent['name'] }}</span>
                    </div>
                    @if($i < 3)
                        <span class="material-symbols-outlined text-secondary" style="font-size: 14px;">chevron_right</span>
                    @endif
                @endforeach
                <span class="ms-auto badge bg-secondary bg-opacity-10 text-secondary fs-12">{{ $stepsComplete }}/4</span>
            </div>

            {{-- Pipeline Controls: Environment + Template --}}
            <div class="d-flex align-items-center gap-3 pt-3 border-top flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined text-secondary" style="font-size:16px;">dns</span>
                    <span class="fs-12 text-secondary fw-medium">Environment:</span>
                    <form action="{{ route('driftwatch.update-environment', $pullRequest) }}" method="POST" class="d-inline">
                        @csrf
                        <select name="target_environment" class="form-select form-select-sm" style="width:auto;font-size:12px;" onchange="this.form.submit()">
                            @foreach(['production', 'staging', 'development'] as $env)
                                <option value="{{ $env }}" {{ ($pullRequest->target_environment ?? 'production') === $env ? 'selected' : '' }}>
                                    {{ ucfirst($env) }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined text-secondary" style="font-size:16px;">tune</span>
                    <span class="fs-12 text-secondary fw-medium">Template:</span>
                    <form action="{{ route('driftwatch.update-template', $pullRequest) }}" method="POST" class="d-inline">
                        @csrf
                        <select name="pipeline_template" class="form-select form-select-sm" style="width:auto;font-size:12px;" onchange="this.form.submit()">
                            @php $templates = \App\Models\PipelineConfig::all(); @endphp
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->name }}" {{ ($pullRequest->pipeline_template ?? 'full') === $tpl->name ? 'selected' : '' }}>
                                    {{ $tpl->label }}
                                </option>
                            @endforeach
                            @if($templates->isEmpty())
                                <option value="full" selected>Full Analysis</option>
                            @endif
                        </select>
                    </form>
                </div>
                @if($pullRequest->pipeline_paused)
                    <span class="badge bg-warning bg-opacity-10 text-warning ms-auto">
                        <span class="material-symbols-outlined align-middle" style="font-size:14px;">pause_circle</span>
                        Paused at {{ ucfirst($pullRequest->paused_at_stage ?? 'unknown') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Deployment Weather Forecast — Risk Score Section --}}
    @if($pullRequest->riskAssessment)
        @php
            $score = $pullRequest->riskAssessment->risk_score;
            $color = $pullRequest->riskAssessment->risk_color;
            $factors = $pullRequest->riskAssessment->contributing_factors ??[];
            $hasHistory = count($pullRequest->riskAssessment->historical_incidents ??[]) > 0;
            $historyCount = count($pullRequest->riskAssessment->historical_incidents ??[]);
            $blastFiles = $pullRequest->blastRadius->total_affected_files ?? 0;
            $blastServices = $pullRequest->blastRadius->total_affected_services ?? 0;
            $blastEndpoints = count($pullRequest->blastRadius->affected_endpoints ??[]);

            // Weather system
            if ($score <= 20) {
                $weather = 'Clear Skies'; $weatherIcon = 'wb_sunny'; $weatherColor = 'success';
                $forecast = "Conditions are favorable for deployment. Low incident history, isolated blast radius across {$blastFiles} files. The Historian agent found no significant risk patterns in your codebase history.";
                $incidentChance = rand(3, 8);
            } elseif ($score <= 45) {
                $weather = 'Partly Cloudy'; $weatherIcon = 'partly_cloudy_day'; $weatherColor = 'primary';
                $forecast = "Moderate conditions. This PR touches {$blastFiles} files across {$blastServices} service(s). Some risk factors are present but manageable with standard review. " . ($hasHistory ? "The Historian found {$historyCount} related past incident(s) — review them below." : "No related incidents found in history.");
                $incidentChance = rand(10, 22);
            } elseif ($score <= 70) {
                $weather = 'Storm Warning'; $weatherIcon = 'thunderstorm'; $weatherColor = 'warning';
                $forecast = "Elevated risk detected. This PR has a wide blast radius ({$blastFiles} files, {$blastServices} services, {$blastEndpoints} endpoints). " . ($hasHistory ? "WARNING: {$historyCount} related past incident(s) found — similar changes have caused production issues before." : "Multiple risk factors suggest caution.") . " Consider canary deployment or staging validation first.";
                $incidentChance = rand(25, 45);
            } else {
                $weather = 'Severe Storm'; $weatherIcon = 'severe_cold'; $weatherColor = 'danger';
                $forecast = "CRITICAL RISK. This PR scores {$score}/100 — the Negotiator agent has flagged this for review or blocking. Blast radius spans {$blastFiles} files across {$blastServices} services with {$blastEndpoints} exposed endpoints. " . ($hasHistory ? "{$historyCount} related past incident(s) strongly correlate with this change pattern." : "The code complexity and blast radius alone warrant extreme caution.") . " Do NOT deploy without thorough review and a rollback plan.";
                $incidentChance = rand(50, 75);
            }

            // Score breakdown weights
            $blastWeight = min(40, $blastFiles * 2 + $blastServices * 5 + $blastEndpoints * 3);
            $historyWeight = $hasHistory ? min(30, 15 + $historyCount * 5) : 0;
            $codeWeight = max(0, $score - $blastWeight - $historyWeight);
            $totalWeight = max(1, $blastWeight + $historyWeight + $codeWeight);
            $blastPct = round(($blastWeight / $totalWeight) * 100);
            $historyPct = round(($historyWeight / $totalWeight) * 100);
            $codePct = round(($codeWeight / $totalWeight) * 100);
        @endphp
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card" style="border-left: 4px solid var(--bs-{{ $weatherColor }}) !important;">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    {{-- Dominant Risk Score Circle --}}
                    <div class="col-lg-3 text-center mb-3 mb-lg-0">
                        @php
                            $ringClass = match(true) {
                                $score <= 20 => 'risk-score-ring-green',
                                $score <= 40 => 'risk-score-ring-yellow',
                                $score <= 60 => 'risk-score-ring-orange',
                                $score <= 80 => 'risk-score-ring-red',
                                default => 'risk-score-ring-darkred',
                            };
                            $scoreTextColor = match(true) {
                                $score <= 20 => 'text-success',
                                $score <= 40 => 'text-warning',
                                $score <= 60 => 'text-warning',
                                $score <= 80 => 'text-danger',
                                default => 'text-danger',
                            };
                        @endphp
                        <div class="risk-score-circle {{ $ringClass }} mb-2">
                            <span class="score-number {{ $scoreTextColor }}">{{ $score }}</span>
                            <span class="score-label {{ $scoreTextColor }}">/ 100</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center gap-2 mt-2">
                            <span class="material-symbols-outlined text-{{ $weatherColor }}" style="font-size: 24px;">{{ $weatherIcon }}</span>
                            <div class="text-start">
                                <span class="d-block fw-bold fs-14 text-{{ $weatherColor }}">{{ $weather }}</span>
                                <span class="fs-12 text-secondary">{{ $incidentChance }}% chance of incident</span>
                            </div>
                        </div>
                    </div>

                    {{-- Forecast Narrative --}}
                    <div class="col-lg-5 mb-3 mb-lg-0">
                        <h6 class="fw-bold mb-2">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">{{ $weatherIcon }}</span>
                            Deployment Forecast
                        </h6>
                        <p class="fs-13 text-secondary lh-lg mb-3">{{ $forecast }}</p>

                        {{-- Why this score — key contributing factors --}}
                        @if(count($factors) > 0)
                            <div class="border-top pt-2">
                                <span class="fs-12 fw-bold text-uppercase text-secondary d-block mb-1">Why this score:</span>
                                @foreach(array_slice($factors, 0, 4) as $factor)
                                    <div class="d-flex align-items-start gap-1 mb-1">
                                        <span class="material-symbols-outlined text-{{ $weatherColor }} flex-shrink-0" style="font-size:14px; margin-top:2px;">{{ $score >= 50 ? 'warning' : 'info' }}</span>
                                        <span class="fs-12">{{ $factor }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Score Breakdown — Expandable Accordions --}}
                    <div class="col-lg-4">
                        <span class="fs-12 fw-bold text-uppercase text-secondary d-block mb-2">Score Composition</span>
                        @php
                            $blastPts = round($score * $blastPct / 100);
                            $historyPts = round($score * $historyPct / 100);
                            $codePts = round($score * $codePct / 100);
                            $archaeologistRun = $pullRequest->agentRuns?->firstWhere('agent_name', 'archaeologist');
                            $historianRun = $pullRequest->agentRuns?->firstWhere('agent_name', 'historian');
                            $confidenceLabel = fn($pts) => $pts > 20 ? 'High' : ($pts >= 10 ? 'Medium' : 'Low');
                            $confidenceColor = fn($pts) => $pts > 20 ? 'danger' : ($pts >= 10 ? 'warning' : 'success');
                        @endphp
                        <div id="riskNeedleBars">
                            {{-- Blast Radius Factor --}}
                            <div class="mb-2 score-factor-row p-2" data-bs-toggle="collapse" data-bs-target="#factorBlast" aria-expanded="false" aria-controls="factorBlast">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fs-12 fw-medium"><span class="material-symbols-outlined align-middle text-danger" style="font-size:14px;">hub</span> Blast Radius</span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="badge bg-{{ $confidenceColor($blastPts) }} bg-opacity-10 text-{{ $confidenceColor($blastPts) }} fs-10">{{ $confidenceLabel($blastPts) }}</span>
                                        <span class="fs-12 text-secondary fw-bold">+{{ $blastPts }} pts</span>
                                        <span class="material-symbols-outlined text-secondary collapse-toggle" style="font-size:14px;">expand_more</span>
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-danger" style="width: 0%; transition: width 1.5s ease;" data-target-width="{{ $blastPct }}%"></div>
                                </div>
                                <span class="fs-11 text-secondary">{{ $blastFiles }} files, {{ $blastServices }} services, {{ $blastEndpoints }} endpoints</span>
                            </div>
                            <div class="collapse mb-2" id="factorBlast">
                                <div class="bg-light rounded-2 p-3 fs-12 border">
                                    <strong class="text-dark d-block mb-1">Agent Reasoning:</strong>
                                    @if($archaeologistRun && $archaeologistRun->reasoning)
                                        <p class="mb-2 text-secondary">{{ $archaeologistRun->reasoning }}</p>
                                    @else
                                        <p class="mb-2 text-secondary">The Archaeologist mapped the upstream and downstream dependencies for this change.</p>
                                    @endif
                                    <div class="d-flex align-items-center gap-1 mb-2">
                                        <span class="material-symbols-outlined text-warning" style="font-size: 14px;">lightbulb</span>
                                        <span class="text-secondary fw-medium">What would raise this score?</span>
                                    </div>
                                    <span class="text-secondary d-block mb-2">More downstream dependencies or modifying core shared utilities directly increases the calculated blast radius risk.</span>
                                    <a href="{{ $pullRequest->pr_url }}/files" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 mt-1">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">code</span> View diff on GitHub
                                    </a>
                                </div>
                            </div>

                            {{-- Incident History Factor --}}
                            <div class="mb-2 score-factor-row p-2" data-bs-toggle="collapse" data-bs-target="#factorHistory" aria-expanded="false" aria-controls="factorHistory">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fs-12 fw-medium"><span class="material-symbols-outlined align-middle text-warning" style="font-size:14px;">history</span> Incident History</span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="badge bg-{{ $confidenceColor($historyPts) }} bg-opacity-10 text-{{ $confidenceColor($historyPts) }} fs-10">{{ $confidenceLabel($historyPts) }}</span>
                                        <span class="fs-12 text-secondary fw-bold">+{{ $historyPts }} pts</span>
                                        <span class="material-symbols-outlined text-secondary collapse-toggle" style="font-size:14px;">expand_more</span>
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" style="width: 0%; transition: width 1.5s ease;" data-target-width="{{ $historyPct }}%"></div>
                                </div>
                                <span class="fs-11 text-secondary">{{ $hasHistory ? $historyCount . ' related past incidents' : 'No related incidents found' }}</span>
                            </div>
                            <div class="collapse mb-2" id="factorHistory">
                                <div class="bg-light rounded-2 p-3 fs-12 border">
                                    <strong class="text-dark d-block mb-1">Agent Reasoning:</strong>
                                    @if($historianRun && $historianRun->reasoning)
                                        <p class="mb-2 text-secondary">{{ $historianRun->reasoning }}</p>
                                    @else
                                        <p class="mb-2 text-secondary">The Historian found {{ $historyCount }} historical incidents strongly correlated with the current blast radius profile.</p>
                                    @endif
                                    <div class="d-flex align-items-center gap-1 mb-2">
                                        <span class="material-symbols-outlined text-warning" style="font-size: 14px;">lightbulb</span>
                                        <span class="text-secondary fw-medium">What would raise this score?</span>
                                    </div>
                                    <span class="text-secondary d-block">More matching incidents in the exact same service area or file paths over the last 90 days.</span>
                                </div>
                            </div>

                            {{-- Code Complexity Factor --}}
                            <div class="mb-2 score-factor-row p-2" data-bs-toggle="collapse" data-bs-target="#factorCode" aria-expanded="false" aria-controls="factorCode">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fs-12 fw-medium"><span class="material-symbols-outlined align-middle text-primary" style="font-size:14px;">code</span> Code Complexity</span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="badge bg-{{ $confidenceColor($codePts) }} bg-opacity-10 text-{{ $confidenceColor($codePts) }} fs-10">{{ $confidenceLabel($codePts) }}</span>
                                        <span class="fs-12 text-secondary fw-bold">+{{ $codePts }} pts</span>
                                        <span class="material-symbols-outlined text-secondary collapse-toggle" style="font-size:14px;">expand_more</span>
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: 0%; transition: width 1.5s ease;" data-target-width="{{ $codePct }}%"></div>
                                </div>
                                <span class="fs-11 text-secondary">{{ $pullRequest->files_changed }} files, +{{ $pullRequest->additions }}/-{{ $pullRequest->deletions }} lines</span>
                            </div>
                            <div class="collapse mb-2" id="factorCode">
                                <div class="bg-light rounded-2 p-3 fs-12 border">
                                    <strong class="text-dark d-block mb-1">Code Metrics Insight:</strong>
                                    <p class="mb-2 text-secondary">Analysis of the sheer volume and complexity of the modified code chunks in this PR.</p>
                                    <div class="d-flex align-items-center gap-1 mb-2">
                                        <span class="material-symbols-outlined text-warning" style="font-size: 14px;">lightbulb</span>
                                        <span class="text-secondary fw-medium">What would raise this score?</span>
                                    </div>
                                    <span class="text-secondary d-block mb-2">More changed files, larger raw diffs, deep cyclomatic complexity, or breaking function signatures.</span>
                                    <a href="{{ $pullRequest->pr_url }}/files" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 mt-1">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">code</span> View diff on GitHub
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Deployment Weather — Environmental Risk at Deploy Time --}}
    @if($pullRequest->deploymentDecision && $pullRequest->deploymentDecision->weather_checks)
        @php
            $weatherScore = $pullRequest->deploymentDecision->weather_score ?? 0;
            $weatherChecks = $pullRequest->deploymentDecision->weather_checks ?? [];
            $firedChecks = collect($weatherChecks)->where('fired', true);
            $clearChecks = collect($weatherChecks)->where('fired', false);

            // Weather system based on environmental score
            if ($weatherScore <= 10) {
                $envWeather = 'Clear Skies'; $envWeatherIcon = 'wb_sunny'; $envWeatherColor = 'success';
                $envRecommendation = 'Now is a good time to deploy. All environmental checks are clear.';
            } elseif ($weatherScore <= 30) {
                $envWeather = 'Partly Cloudy'; $envWeatherIcon = 'partly_cloudy_day'; $envWeatherColor = 'primary';
                $envRecommendation = 'Conditions are acceptable but not ideal. Proceed with monitoring.';
            } elseif ($weatherScore <= 50) {
                $envWeather = 'Storm Warning'; $envWeatherIcon = 'thunderstorm'; $envWeatherColor = 'warning';
                $envRecommendation = 'Conditions are unfavorable — consider waiting for a better window.';
            } else {
                $envWeather = 'Severe Storm'; $envWeatherIcon = 'severe_cold'; $envWeatherColor = 'danger';
                $envRecommendation = 'Conditions are dangerous. Strongly recommend delaying this deployment.';
            }
        @endphp
        <div class="card border-0 rounded-3 mb-4 dw-card" style="border-left: 4px solid var(--bs-{{ $envWeatherColor }}) !important;">
            <div class="card-body p-4">
                <div class="row">
                    {{-- Weather Score Circle --}}
                    <div class="col-lg-3 text-center mb-3 mb-lg-0">
                        <div class="d-flex flex-column align-items-center">
                            <div class="position-relative d-inline-flex align-items-center justify-content-center mb-2" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--bs-{{ $envWeatherColor }}); box-shadow: 0 0 16px rgba(var(--bs-{{ $envWeatherColor }}-rgb), 0.25);">
                                <span class="fw-bold text-{{ $envWeatherColor }}" style="font-size: 32px;">{{ $weatherScore }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="material-symbols-outlined text-{{ $envWeatherColor }}" style="font-size: 22px;">{{ $envWeatherIcon }}</span>
                                <span class="fw-bold fs-14 text-{{ $envWeatherColor }}">{{ $envWeather }}</span>
                            </div>
                            <span class="fs-11 text-secondary mt-1">Environmental Risk Score</span>
                        </div>
                    </div>

                    {{-- Weather Details --}}
                    <div class="col-lg-9">
                        <h6 class="fw-bold mb-2 dw-section-title">
                            <span class="material-symbols-outlined" style="font-size: 20px;">cloud</span>
                            Deployment Weather
                        </h6>
                        <p class="fs-13 text-secondary mb-3">{{ $envRecommendation }}</p>

                        {{-- Individual Weather Checks --}}
                        <div class="row g-2">
                            @foreach($weatherChecks as $check)
                                @php
                                    $checkColor = $check['fired'] ? 'danger' : 'success';
                                    $checkBg = $check['fired'] ? 'rgba(220,53,69,0.06)' : 'rgba(25,135,84,0.04)';
                                @endphp
                                <div class="col-md-6">
                                    <div class="d-flex gap-2 p-2 rounded-2" style="background: {{ $checkBg }}; border: 1px solid rgba({{ $check['fired'] ? '220,53,69' : '25,135,84' }},0.1);">
                                        <div class="flex-shrink-0 d-flex align-items-center">
                                            <span class="material-symbols-outlined text-{{ $checkColor }}" style="font-size: 20px;">{{ $check['icon'] ?? 'check_circle' }}</span>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="fs-12 fw-bold">{{ $check['label'] }}</span>
                                                @if($check['fired'])
                                                    <span class="badge bg-danger bg-opacity-10 text-danger fs-10">+{{ $check['points'] }} pts</span>
                                                @else
                                                    <span class="badge bg-success bg-opacity-10 text-success fs-10">Clear</span>
                                                @endif
                                            </div>
                                            <span class="fs-11 text-secondary d-block" style="line-height: 1.3;">{{ $check['detail'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Summary bar --}}
                        <div class="mt-3 pt-2 border-top d-flex align-items-center gap-3">
                            <span class="fs-12 text-secondary">
                                <span class="material-symbols-outlined align-middle text-success" style="font-size: 14px;">check_circle</span>
                                {{ $clearChecks->count() }} clear
                            </span>
                            <span class="fs-12 text-secondary">
                                <span class="material-symbols-outlined align-middle text-danger" style="font-size: 14px;">warning</span>
                                {{ $firedChecks->count() }} flagged
                            </span>
                            <span class="fs-11 text-secondary ms-auto">
                                Checked at {{ $pullRequest->deploymentDecision->updated_at->format('H:i') }} · Max possible: 95 pts
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Time Bomb Detection --}}
    @if($pullRequest->blastRadius)
        @php
            $timeBombs =[];
            $depGraphData = $pullRequest->blastRadius->dependency_graph ??[];
            foreach ($depGraphData as $sourceFile => $deps) {
                $depCount = is_array($deps) ? count($deps) : 0;
                $isConfig = preg_match('/\.(json|yaml|yml|env|toml|ini|xml)$/i', $sourceFile) || str_contains($sourceFile, 'config');
                $isMigration = str_contains($sourceFile, 'migration') || str_contains($sourceFile, 'schema');
                $isCore = str_contains($sourceFile, 'auth') || str_contains($sourceFile, 'core') || str_contains($sourceFile, 'base') || str_contains($sourceFile, 'main');
                $risk = 0;
                $reasons =[];
                if ($depCount >= 5) { $risk += 40; $reasons[] = "High fan-out: {$depCount} downstream files depend on this"; }
                elseif ($depCount >= 3) { $risk += 20; $reasons[] = "{$depCount} downstream dependencies"; }
                if ($isConfig) { $risk += 25; $reasons[] = "Configuration file — affects all environments"; }
                if ($isMigration) { $risk += 30; $reasons[] = "Database migration — irreversible in production"; }
                if ($isCore) { $risk += 20; $reasons[] = "Core module — foundational to the system"; }
                if ($risk >= 20) {
                    $timeBombs[] =['file' => $sourceFile, 'risk' => $risk, 'deps' => $depCount, 'reasons' => $reasons];
                }
            }
            usort($timeBombs, function($a, $b) { return $b['risk'] - $a['risk']; });
        @endphp
        @if(count($timeBombs) > 0)
            <div class="card border-0 rounded-3 mb-4 shadow-sm" style="background: linear-gradient(135deg, #FEF3C7, #FDE68A); border-left: 5px solid #F59E0B !important;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-warning" style="font-size: 28px;">timer</span>
                        <span style="font-size: 18px;">Time Bomb Detection</span>
                        <span class="badge bg-danger text-white ms-2">{{ count($timeBombs) }} flagged</span>
                    </h6>
                    <div class="row g-3">
                        @foreach(array_slice($timeBombs, 0, 6) as $bomb)
                            @php
                                $bombColor = $bomb['risk'] >= 50 ? 'danger' : ($bomb['risk'] >= 30 ? 'warning' : 'info');
                            @endphp
                            <div class="col-md-6">
                                <div class="d-flex gap-3 h-100 dw-bomb-card border-start border-3 border-{{ $bombColor }}" style="border-top:0!important;border-right:0!important;border-bottom:0!important;">
                                    <div class="flex-shrink-0">
                                        <div class="wh-40 bg-{{ $bombColor }} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                            <span class="fw-bold fs-14 text-{{ $bombColor }}">{{ $bomb['risk'] }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <code class="fs-13 fw-bold">{{ basename($bomb['file']) }}</code>
                                            @if($bomb['deps'] > 0)
                                                <span class="badge bg-warning bg-opacity-10 text-warning fs-11">{{ $bomb['deps'] }} deps</span>
                                            @endif
                                        </div>
                                        <div class="fs-12 text-secondary mb-2 text-truncate" style="max-width: 300px;">{{ $bomb['file'] }}</div>
                                        @foreach($bomb['reasons'] as $reason)
                                            <div class="d-flex align-items-start gap-1">
                                                <span class="material-symbols-outlined text-{{ $bombColor }} flex-shrink-0" style="font-size:14px; margin-top:2px;">warning</span>
                                                <span class="fs-12 text-secondary fw-medium">{{ $reason }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- DevOps Action Items (MOVED UP FOR VISIBILITY) --}}
    @if($pullRequest->blastRadius)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-2 dw-section-title">
                    <span class="material-symbols-outlined align-middle text-warning" style="font-size: 18px;">checklist</span>
                    Action Items
                </h6>
                <p class="text-secondary fs-13 mb-3">Priority tasks your team should complete before deploying this PR.</p>
                <div id="devopsActionItems"></div>
            </div>
        </div>

        {{-- What to Review — AI-powered review checklist (collapsible) --}}
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h6 class="fw-bold mb-0 dw-section-title" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#reviewCollapseBody">
                        <span class="material-symbols-outlined align-middle text-primary" style="font-size: 18px;">rate_review</span>
                        What to Review
                        <span class="fs-12 fw-normal text-secondary ms-2">Prioritized checklist for this PR</span>
                        <span class="material-symbols-outlined align-middle text-secondary ms-1" style="font-size: 16px; transition: transform 0.3s;" id="reviewCollapseIcon">expand_more</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text border-end-0 bg-transparent" style="border-radius: 20px 0 0 20px;">
                                <span class="material-symbols-outlined" style="font-size: 14px; color: #94a3b8;">search</span>
                            </span>
                            <input type="text" class="form-control border-start-0" id="reviewFilterSearch"
                                   placeholder="Filter files..." autocomplete="off"
                                   style="font-size: 12px; border-radius: 0 20px 20px 0;">
                        </div>
                        <div class="btn-group btn-group-sm" id="reviewFilterBtns">
                            <button class="btn btn-outline-secondary fs-11 active" data-filter="all">All</button>
                            <button class="btn btn-outline-danger fs-11" data-filter="critical">Critical</button>
                            <button class="btn btn-outline-warning fs-11" data-filter="high">High</button>
                            <button class="btn btn-outline-secondary fs-11" data-filter="medium">Medium</button>
                            <button class="btn btn-outline-success fs-11" data-filter="low">Low</button>
                        </div>
                    </div>
                </div>
                <div class="collapse show" id="reviewCollapseBody">
                    <div id="reviewChecklist" class="mt-2" style="max-height: 600px; overflow-y: auto;"></div>
                    <div id="reviewEmpty" class="text-center py-3 text-secondary fs-13" style="display: none;">
                        <span class="material-symbols-outlined d-block mb-1" style="font-size: 28px;">filter_list_off</span>
                        No files match the current filter.
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Summary Row: Blast Radius | Decision Audit Log | DriftWatch Recommendation --}}
    <div class="row">
        {{-- Blast Radius Summary --}}
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle text-danger" style="font-size: 18px;">hub</span>
                        Blast Radius
                    </h6>
                    @if($pullRequest->blastRadius)
                        <div class="d-flex gap-2 mb-3">
                            <div class="text-center flex-fill dw-stat">
                                <span class="d-block fs-4 fw-bold text-primary">{{ $pullRequest->blastRadius->total_affected_files }}</span>
                                <span class="fs-12 text-secondary">Files</span>
                            </div>
                            <div class="text-center flex-fill dw-stat">
                                <span class="d-block fs-4 fw-bold text-warning">{{ $pullRequest->blastRadius->total_affected_services }}</span>
                                <span class="fs-12 text-secondary">Services</span>
                            </div>
                            <div class="text-center flex-fill dw-stat">
                                <span class="d-block fs-4 fw-bold text-info">{{ count($pullRequest->blastRadius->affected_endpoints ??[]) }}</span>
                                <span class="fs-12 text-secondary">Endpoints</span>
                            </div>
                        </div>
                        <p class="text-secondary fs-13 mb-0">{{ $pullRequest->blastRadius->summary }}</p>
                    @else
                        <div class="py-4 text-center text-secondary">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">explore</span>
                            <span class="fs-13">Awaiting Archaeologist agent</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Decision Audit Log (Replaces redundant top banner decision duplicate) --}}
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle text-primary" style="font-size: 18px;">gavel</span>
                        Decision Audit Log
                    </h6>
                    @if($pullRequest->deploymentDecision)
                        @php $decision = $pullRequest->deploymentDecision; @endphp
                        
                        <div class="bg-light rounded-3 p-3 mb-3 border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fs-13 fw-bold text-dark">Current State:</span>
                                <span class="badge bg-{{ $decision->decision_color }} text-{{ $decision->decision_color }} bg-opacity-10 px-2 py-1 fs-12 text-uppercase fw-bold">
                                    {{ str_replace('_', ' ', $decision->decision) }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fs-13 text-secondary">Determined By:</span>
                                <span class="fs-13 fw-medium text-dark">{{ $decision->decided_by ?: 'Negotiator Agent' }}</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fs-13 text-secondary">Timestamp:</span>
                                <span class="fs-13 fw-medium text-dark">{{ $decision->decided_at ? $decision->decided_at->format('M j, g:i A') : now()->format('M j, g:i A') }}</span>
                            </div>
                        </div>

                        @if($decision->notification_message)
                            <div class="p-2 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 mb-2">
                                <p class="text-dark fs-12 mb-0 d-flex gap-2 align-items-start">
                                    <span class="material-symbols-outlined text-primary fs-16">chat</span>
                                    <span>{{ $decision->notification_message }}</span>
                                </p>
                            </div>
                        @endif
                        
                        @if($decision->notified_oncall)
                            <div class="mt-2">
                                <span class="badge bg-warning bg-opacity-10 text-warning fs-12 d-flex align-items-center gap-1 w-fit-content">
                                    <span class="material-symbols-outlined fs-14">notifications_active</span>
                                    On-call engineer notified via Teams
                                </span>
                            </div>
                        @endif
                    @else
                        <div class="py-4 text-center text-secondary">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">gavel</span>
                            <span class="fs-13">Awaiting Negotiator agent</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- DriftWatch Recommendation --}}
        <div class="col-xl-4 col-lg-12 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle text-primary" style="font-size: 18px;">smart_toy</span>
                        DriftWatch Recommendation
                    </h6>
                    @if($pullRequest->riskAssessment && $pullRequest->riskAssessment->recommendation)
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <p class="mb-0 fs-13 lh-lg">{{ $pullRequest->riskAssessment->recommendation }}</p>
                        </div>
                        @if($pullRequest->riskAssessment)
                            <span class="fs-12 text-secondary d-flex gap-2">
                                <span class="material-symbols-outlined text-primary" style="font-size:16px;">smart_toy</span>
                                <span>Generated by the Historian agent (GPT-4.1-mini) based on blast radius analysis, {{ count($pullRequest->riskAssessment->historical_incidents ??[]) }} historical incident(s), and code complexity patterns.</span>
                            </span>
                        @endif
                    @else
                        <div class="py-4 text-center text-secondary">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">smart_toy</span>
                            <span class="fs-13">Awaiting agent analysis</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Merge-Readiness Pack (MRP) --}}
    @if($pullRequest->deploymentDecision && $pullRequest->deploymentDecision->mrp_payload)
        @php $mrp = $pullRequest->deploymentDecision->mrp_payload; @endphp
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h6 class="fw-bold mb-0">
                        <span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size: 18px;">verified</span>
                        Merge-Readiness Pack
                        <span class="badge bg-primary bg-opacity-10 text-primary fs-11 ms-2">{{ $mrp['mrp_id'] ?? 'MRP' }}</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        @if(($mrp['version'] ?? 1) > 1)
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fs-11">v{{ $mrp['version'] }} ({{ ($mrp['version'] ?? 1) - 1 }} previous)</span>
                        @endif
                        <span class="fs-11 text-secondary">{{ \Carbon\Carbon::parse($mrp['generated_at'] ?? now())->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    {{-- Evidence cards --}}
                    @php $evidence = $mrp['evidence'] ??[]; @endphp
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-danger" style="font-size:16px;">hub</span>
                                <span class="fw-bold fs-13">Blast Radius</span>
                                <span class="badge bg-danger bg-opacity-10 text-danger fs-11 ms-auto">{{ $evidence['blast_radius']['score'] ?? 0 }} pts</span>
                            </div>
                            <p class="text-secondary fs-12 mb-1">{{ \Illuminate\Support\Str::limit($evidence['blast_radius']['summary'] ?? 'N/A', 120) }}</p>
                            @if(!empty($evidence['blast_radius']['affected_services']))
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach(array_slice($evidence['blast_radius']['affected_services'], 0, 3) as $svc)
                                        <code class="fs-11 px-1 bg-danger bg-opacity-10 rounded">{{ $svc }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-warning" style="font-size:16px;">history</span>
                                <span class="fw-bold fs-13">Incident History</span>
                                <span class="badge bg-warning bg-opacity-10 text-warning fs-11 ms-auto">{{ $evidence['incident_history']['score'] ?? 0 }} pts</span>
                            </div>
                            <p class="text-secondary fs-12 mb-1">{{ \Illuminate\Support\Str::limit($evidence['incident_history']['summary'] ?? 'No incident history available.', 120) }}</p>
                            @if(!empty($evidence['incident_history']['matching_incidents']))
                                <span class="fs-11 text-secondary">{{ count($evidence['incident_history']['matching_incidents']) }} matching incident(s)</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-info" style="font-size:16px;">security</span>
                                <span class="fw-bold fs-13">CI & Bots</span>
                                @php
                                    $ciScore = ($evidence['ci_status']['ci_risk_addition'] ?? 0);
                                    $botCount = count($evidence['bot_findings'] ??[]);
                                @endphp
                                <span class="badge bg-{{ $ciScore > 0 ? 'danger' : 'success' }} bg-opacity-10 text-{{ $ciScore > 0 ? 'danger' : 'success' }} fs-11 ms-auto">
                                    {{ ($evidence['ci_status']['status'] ?? 'unknown') === 'failing' ? 'Failing' : 'Passing' }}
                                </span>
                            </div>
                            @if(!empty($evidence['ci_status']['failing_checks']))
                                <p class="text-danger fs-12 mb-1">Failing: {{ implode(', ', $evidence['ci_status']['failing_checks']) }}</p>
                            @else
                                <p class="text-secondary fs-12 mb-1">All CI checks passing.</p>
                            @endif
                            @if($botCount > 0)
                                <span class="fs-11 text-warning">{{ $botCount }} bot finding(s) detected</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Conditions for approval --}}
                @if(!empty($mrp['conditions_for_approval']))
                    <div class="mb-2">
                        <span class="fs-12 fw-bold text-uppercase text-secondary d-block mb-2">Conditions for Approval</span>
                        @foreach(array_slice($mrp['conditions_for_approval'], 0, 5) as $condition)
                            <div class="d-flex align-items-start gap-2 mb-1">
                                <span class="material-symbols-outlined text-warning" style="font-size:14px;margin-top:2px;">task_alt</span>
                                <span class="fs-12">{{ $condition }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Audit trail --}}
                @if(!empty($mrp['audit_trail']))
                    <div class="border-top pt-2 mt-2">
                        <span class="fs-11 text-secondary">
                            <span class="material-symbols-outlined align-middle" style="font-size:13px;">schedule</span>
                            {{ $mrp['audit_trail'][count($mrp['audit_trail']) - 1]['details'] ?? '' }}
                            &mdash; {{ $mrp['audit_trail'][count($mrp['audit_trail']) - 1]['by'] ?? '' }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Impact Analysis --}}
    @if($pullRequest->blastRadius)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h6 class="fw-bold mb-0">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">hub</span>
                        Impact Analysis
                    </h6>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary active" id="btnTreeView" onclick="toggleBlastView('tree')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">account_tree</span> Dependency Tree
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnSummaryView" onclick="toggleBlastView('summary')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">view_list</span> Summary
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnDynamicView" onclick="toggleBlastView('dynamic')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">bubble_chart</span> Blast Map
                        </button>
                    </div>
                </div>

                {{-- Dependency Tree — hierarchical LR DAG (default tab) --}}
                <div id="blastDependencyTree">

                    {{-- Collapsible Impact Summary --}}
                    <div class="mb-3">
                        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 w-100 justify-content-between py-2 px-3"
                                onclick="toggleTreeSummary()" id="treeSummaryToggle" style="border-radius: 10px; text-align: left;">
                            <span class="d-flex align-items-center gap-2">
                                <span class="material-symbols-outlined" style="font-size: 18px;">summarize</span>
                                <span class="fw-medium">Impact Summary</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-1" id="treeSummaryBadge"></span>
                            </span>
                            <span class="material-symbols-outlined" style="font-size: 18px; transition: transform 0.3s;" id="treeSummaryChevron">expand_more</span>
                        </button>
                        <div class="tree-summary-collapse" id="treeSummaryBody">
                            <div class="p-3 mt-2 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="fw-bold fs-20" id="tsSummaryFiles">0</div>
                                            <div class="fs-11 text-secondary">Files Changed</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="fw-bold fs-20" id="tsSummaryServices">0</div>
                                            <div class="fs-11 text-secondary">Services Affected</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="fw-bold fs-20" id="tsSummaryDeps">0</div>
                                            <div class="fs-11 text-secondary">Dependencies</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="fw-bold fs-20" id="tsSummaryEndpoints">0</div>
                                            <div class="fs-11 text-secondary">Endpoints</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fs-13 mb-3" id="tsSummaryText" style="line-height: 1.7;"></div>
                                <div id="tsSummaryHighRisk" style="display: none;">
                                    <div class="fw-bold fs-12 text-uppercase text-secondary mb-2">Highest Risk Files</div>
                                    <div id="tsSummaryHighRiskList"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Graph + Chat Panel Layout --}}
                    <div class="tree-layout" id="treeLayout">
                        <div class="tree-graph-area" style="position: relative;">
                            <div id="dagTreeContainer" style="width: 100%; height: 100%; overflow: auto; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
                                <svg id="dagTreeSvg" width="100%" height="100%"></svg>
                            </div>
                            {{-- Floating legend --}}
                            <div class="tree-legend" id="treeLegend">
                                <div class="tree-legend-title">Legend</div>
                                <div class="tree-legend-item"><span class="tree-legend-dot" style="background: #605DFF;"></span> PR Origin</div>
                                <div class="tree-legend-item"><span class="tree-legend-dot" style="background: #FEE2E2; border: 2px solid #EF4444;"></span> Changed File</div>
                                <div class="tree-legend-item"><span class="tree-legend-dot" style="background: #DBEAFE; border: 2px solid #3B82F6;"></span> Dependency</div>
                                <div class="tree-legend-item"><span class="tree-legend-dot" style="background: #D1FAE5; border: 2px solid #10B981; border-radius: 3px; transform: rotate(45deg);"></span> Service</div>
                                <div class="tree-legend-item"><span class="tree-legend-dot" style="background: #FEF9C3; border: 2px solid #F59E0B;"></span> Endpoint</div>
                                <div class="tree-legend-item"><span class="tree-legend-dot" style="background: rgba(96,93,255,0.2); border: 2px solid #605DFF;"></span> Highlighted</div>
                            </div>
                        </div>

                        {{-- Chat Side Panel --}}
                        <div class="tree-side-panel" id="treeSidePanel">
                            <div class="resize-handle" id="panelResizeHandle"></div>
                            {{-- Chat header --}}
                            <div class="sp-header" style="padding: 12px 16px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="material-symbols-outlined" style="font-size: 20px; color: #605DFF;">forum</span>
                                        <span class="fw-bold fs-13">Impact Chat</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="chat-export-btn" id="chatExportBtn" title="Export conversation">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                                        </button>
                                        <button class="sp-close" onclick="clearChatHighlights()" title="Clear highlights">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">filter_alt_off</span>
                                        </button>
                                        <button class="sp-close" onclick="closeSidePanel()" title="Close panel">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Chat messages area --}}
                            <div id="chatMessages" style="flex: 1; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; min-height: 0;">
                                {{-- Welcome message --}}
                                <div class="chat-msg chat-bot">
                                    <div class="chat-bubble">
                                        <div class="fw-medium fs-12 mb-1">DriftWatch Assistant</div>
                                        <div class="fs-12">Click a node or ask me about the impact. Try:</div>
                                        <div class="chat-suggestions mt-2">
                                            <button class="chat-suggest-btn" data-query="Show me the highest risk files">highest risk files</button>
                                            <button class="chat-suggest-btn" data-query="What services are affected?">affected services</button>
                                            <button class="chat-suggest-btn" data-query="Show auth and middleware changes">auth changes</button>
                                            <button class="chat-suggest-btn" data-query="What depends on the most-changed file?">dependency chain</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Chat input — sticky at bottom --}}
                            <div class="chat-input-area">
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="text" class="form-control form-control-sm" id="chatInput"
                                           placeholder="Ask about files, risk, dependencies..."
                                           autocomplete="off" style="font-size: 12px; border-radius: 20px; padding: 8px 16px;">
                                    <button class="chat-mic-btn" id="chatMicBtn" type="button" title="Voice input" style="display: none;">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">mic</span>
                                    </button>
                                    <button class="btn btn-sm btn-primary d-flex align-items-center justify-content-center"
                                            id="chatSendBtn" type="button"
                                            style="border-radius: 50%; width: 34px; height: 34px; min-width: 34px; flex-shrink: 0;">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">send</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Edge hover tooltip --}}
                    <div id="dagEdgeTooltip"></div>
                </div>

                {{-- Summary View --}}
                <div id="blastSummaryView" style="display: none;">
                    <div id="impactSummaryContent"></div>
                </div>

                {{-- Blast Map — animated concentric radius visualization --}}
                <div id="blastRadiusDynamic" style="height: 650px; display: none; position: relative; overflow: hidden; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #0f172a 100%) !important; border-radius: 12px;">
                    <svg id="blastRadiusSvg" width="100%" height="100%" style="position:absolute; top:0; left:0;">
                        <defs>
                            <radialGradient id="pulseGrad1" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#605DFF" stop-opacity="0.15"/><stop offset="100%" stop-color="#605DFF" stop-opacity="0"/></radialGradient>
                            <radialGradient id="pulseGrad2" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#EF4444" stop-opacity="0.1"/><stop offset="100%" stop-color="#EF4444" stop-opacity="0"/></radialGradient>
                            <radialGradient id="pulseGrad3" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#F59E0B" stop-opacity="0.08"/><stop offset="100%" stop-color="#F59E0B" stop-opacity="0"/></radialGradient>
                            <radialGradient id="pulseGrad4" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#06B6D4" stop-opacity="0.06"/><stop offset="100%" stop-color="#06B6D4" stop-opacity="0"/></radialGradient>
                            <filter id="glow"><feGaussianBlur stdDeviation="3" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                            <filter id="glowStrong"><feGaussianBlur stdDeviation="6" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                        </defs>
                    </svg>
                    {{-- Floating info card on hover --}}
                    <div id="graphHoverCard" style="display:none; position:absolute; z-index:20; pointer-events:none; min-width:260px; max-width:340px;">
                        <div class="card border-0 shadow-lg rounded-3" style="backdrop-filter:blur(12px); background:rgba(15,23,42,0.95); border: 1px solid rgba(96,93,255,0.3);">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div id="hoverBadge"></div>
                                    <span class="fw-bold fs-13 text-white" id="hoverTitle"></span>
                                </div>
                                <div class="fs-12 text-secondary mb-2" id="hoverPath"></div>
                                <div id="hoverDescription" class="fs-12 mb-2" style="color:#cbd5e1;"></div>
                                <div id="hoverDeps" class="fs-12"></div>
                            </div>
                        </div>
                    </div>
                    {{-- Stats overlay --}}
                    <div id="blastMapStats" style="position:absolute; top:16px; left:16px; z-index:10; pointer-events:none;">
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex align-items-center gap-2"><div style="width:10px;height:10px;border-radius:50%;background:#EF4444;box-shadow:0 0 8px #EF4444;"></div><span class="fs-11 text-white fw-medium" id="bmStatChanged">0 changed</span></div>
                            <div class="d-flex align-items-center gap-2"><div style="width:10px;height:10px;border-radius:50%;background:#F59E0B;box-shadow:0 0 8px #F59E0B;"></div><span class="fs-11 text-white fw-medium" id="bmStatAffected">0 affected</span></div>
                            <div class="d-flex align-items-center gap-2"><div style="width:10px;height:10px;border-radius:50%;background:#3B82F6;box-shadow:0 0 8px #3B82F6;"></div><span class="fs-11 text-white fw-medium" id="bmStatServices">0 services</span></div>
                            <div class="d-flex align-items-center gap-2"><div style="width:10px;height:10px;border-radius:50%;background:#06B6D4;box-shadow:0 0 8px #06B6D4;"></div><span class="fs-11 text-white fw-medium" id="bmStatEndpoints">0 endpoints</span></div>
                        </div>
                    </div>
                    {{-- Risk score overlay --}}
                    <div id="blastMapRisk" style="position:absolute; top:16px; right:16px; z-index:10; pointer-events:none;">
                        <div class="text-end">
                            <div class="fs-11 text-secondary text-uppercase fw-bold mb-1" style="letter-spacing:1px;">Blast Radius</div>
                            <div class="fs-28 fw-bold" id="bmRiskScore" style="color:#605DFF; text-shadow: 0 0 20px rgba(96,93,255,0.5);">—</div>
                        </div>
                    </div>
                </div>

                {{-- Persistent detail panel below graph --}}
                <div id="blastInfoPanel" class="mt-3 border rounded-3 overflow-hidden" style="display: none;">
                    <div class="p-3 border-bottom d-flex align-items-center gap-2" id="blastInfoHeader" style="background: #f8fafc;"></div>
                    <div class="p-3" id="blastInfoBody"></div>
                </div>

                {{-- Structural Flow Diagram (Mermaid) --}}
                <div id="blastRadiusStructural" style="min-height: 500px; display: none; padding: 20px; position: relative;">
                    <div class="d-flex gap-2 mb-3" style="position: sticky; top: 0; z-index: 5;">
                        <button class="btn btn-sm btn-outline-secondary" onclick="zoomMermaid(1.2)">
                            <span class="material-symbols-outlined" style="font-size: 16px;">zoom_in</span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="zoomMermaid(0.8)">
                            <span class="material-symbols-outlined" style="font-size: 16px;">zoom_out</span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="zoomMermaid(0)">
                            <span class="material-symbols-outlined" style="font-size: 16px;">fit_screen</span>
                        </button>
                    </div>
                    <div id="mermaidZoomWrapper" style="transform-origin: top left; transition: transform 0.2s ease;">
                        <div class="mermaid" id="blastMermaidDiagram"></div>
                    </div>
                </div>

                {{-- Interactive Legend --}}
                <div id="graphLegend" class="d-flex gap-3 mt-3 pt-3 border-top flex-wrap align-items-center" style="display: none !important;">
                    <span class="fs-12 fw-bold text-uppercase text-secondary me-1">Legend:</span>
                    <button class="btn btn-sm px-2 py-1 d-flex align-items-center gap-1 legend-btn active" data-legend="pr" onclick="filterGraphType(this, 'pr')">
                        <div style="width:14px; height:14px; background:#605DFF; border-radius:4px; border:2px solid #4b49cc;"></div>
                        <span class="fs-12">PR Origin</span>
                    </button>
                    <button class="btn btn-sm px-2 py-1 d-flex align-items-center gap-1 legend-btn active" data-legend="changed" onclick="filterGraphType(this, 'changed')">
                        <div style="width:14px; height:14px; background:#EF4444; border-radius:50%;"></div>
                        <span class="fs-12">Changed Files</span>
                    </button>
                    <button class="btn btn-sm px-2 py-1 d-flex align-items-center gap-1 legend-btn active" data-legend="affected" onclick="filterGraphType(this, 'affected')">
                        <div style="width:14px; height:14px; background:#F59E0B; border-radius:50%;"></div>
                        <span class="fs-12">Affected Files</span>
                    </button>
                    <button class="btn btn-sm px-2 py-1 d-flex align-items-center gap-1 legend-btn active" data-legend="downstream" onclick="filterGraphType(this, 'downstream')">
                        <div style="width:14px; height:14px; background:#FBBF24; border-radius:50%; border:2px dashed #F59E0B;"></div>
                        <span class="fs-12">Downstream</span>
                    </button>
                    <button class="btn btn-sm px-2 py-1 d-flex align-items-center gap-1 legend-btn active" data-legend="service" onclick="filterGraphType(this, 'service')">
                        <div style="width:14px; height:14px; background:#3B82F6; border-radius:4px;"></div>
                        <span class="fs-12">Services</span>
                    </button>
                    <button class="btn btn-sm px-2 py-1 d-flex align-items-center gap-1 legend-btn active" data-legend="endpoint" onclick="filterGraphType(this, 'endpoint')">
                        <div style="width:14px; height:14px; background:#06B6D4; border-radius:50%;"></div>
                        <span class="fs-12">Endpoints</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Impact Treemap --}}
    @if($pullRequest->blastRadius && count($pullRequest->blastRadius->affected_files ??[]) > 0)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 dw-section-title">
                    <span class="material-symbols-outlined align-middle text-primary" style="font-size: 18px;">grid_view</span>
                    Impact Heatmap
                    <span class="fs-12 fw-normal text-secondary ms-2">File impact weighted by downstream dependencies</span>
                </h6>
                <div id="impactTreemap" style="min-height: 320px;"></div>
            </div>
        </div>
    @endif

    {{-- Conflicting PR Detection --}}
    @if($pullRequest->blastRadius)
        @php
            // Find other open PRs that touch the same files
            $conflictingPRs = \App\Models\PullRequest::where('id', '!=', $pullRequest->id)
                ->whereIn('status',['open', 'pending_review', 'analyzing'])
                ->whereHas('blastRadius')
                ->with('blastRadius', 'riskAssessment')
                ->get()
                ->filter(function($otherPR) use ($pullRequest) {
                    $myFiles = $pullRequest->blastRadius->affected_files ??[];
                    $theirFiles = $otherPR->blastRadius->affected_files ??[];
                    $overlap = array_intersect($myFiles, $theirFiles);
                    return count($overlap) > 0;
                })
                ->map(function($otherPR) use ($pullRequest) {
                    $myFiles = $pullRequest->blastRadius->affected_files ??[];
                    $theirFiles = $otherPR->blastRadius->affected_files ??[];
                    $overlap = array_intersect($myFiles, $theirFiles);
                    $otherPR->overlap_files = array_values($overlap);
                    $otherPR->overlap_count = count($overlap);
                    $overlapPct = count($myFiles) > 0 ? round((count($overlap) / count($myFiles)) * 100) : 0;
                    $otherPR->overlap_pct = $overlapPct;
                    $otherPR->conflict_severity = $overlapPct >= 50 ? 'danger' : ($overlapPct >= 20 ? 'warning' : 'info');
                    return $otherPR;
                })
                ->sortByDesc('overlap_count');
        @endphp
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 dw-section-title">
                    <span class="material-symbols-outlined align-middle text-warning" style="font-size: 18px;">merge_type</span>
                    Conflicting PR Detection
                    <span class="fs-12 fw-normal text-secondary ms-2">Other open PRs touching the same files</span>
                </h6>
                @if($conflictingPRs->count() > 0)
                    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 d-flex align-items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-warning">warning</span>
                        <span class="fs-13"><strong>{{ $conflictingPRs->count() }} conflicting PR{{ $conflictingPRs->count() > 1 ? 's' : '' }}</strong> found — merging these PRs together could cause integration issues or merge conflicts.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="fw-medium text-secondary fs-13">PR</th>
                                    <th class="fw-medium text-secondary fs-13">Author</th>
                                    <th class="fw-medium text-secondary fs-13">Overlap</th>
                                    <th class="fw-medium text-secondary fs-13">Risk</th>
                                    <th class="fw-medium text-secondary fs-13">Conflicting Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($conflictingPRs as $cpr)
                                    <tr>
                                        <td>
                                            <a href="{{ route('driftwatch.show', $cpr) }}" class="fw-medium text-primary text-decoration-none">
                                                #{{ $cpr->pr_number }}
                                            </a>
                                            <div class="fs-12 text-secondary text-truncate" style="max-width: 200px;">{{ $cpr->pr_title }}</div>
                                        </td>
                                        <td class="fs-13">{{ $cpr->pr_author }}</td>
                                        <td>
                                            <span class="badge bg-{{ $cpr->conflict_severity }} bg-opacity-10 text-{{ $cpr->conflict_severity }} fw-bold">
                                                {{ $cpr->overlap_count }} file{{ $cpr->overlap_count > 1 ? 's' : '' }} ({{ $cpr->overlap_pct }}%)
                                            </span>
                                        </td>
                                        <td>
                                            @if($cpr->riskAssessment)
                                                <span class="badge bg-{{ $cpr->riskAssessment->risk_color }} bg-opacity-10 text-{{ $cpr->riskAssessment->risk_color }}">
                                                    {{ $cpr->riskAssessment->risk_score }}/100
                                                </span>
                                            @else
                                                <span class="text-secondary fs-12">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach(array_slice($cpr->overlap_files, 0, 3) as $of)
                                                    <code class="fs-11 px-1 bg-warning bg-opacity-10 rounded">{{ basename($of) }}</code>
                                                @endforeach
                                                @if(count($cpr->overlap_files) > 3)
                                                    <span class="fs-11 text-secondary">+{{ count($cpr->overlap_files) - 3 }} more</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="d-flex align-items-center gap-3 p-3 bg-success bg-opacity-10 rounded-3">
                        <span class="material-symbols-outlined text-success" style="font-size: 28px;">check_circle</span>
                        <div>
                            <span class="fw-bold fs-14 text-success">No Conflicts Detected</span>
                            <p class="mb-0 fs-13 text-secondary">No other open PRs touch the same files as this PR. Safe to merge independently.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Historical Incidents --}}
    @if($pullRequest->riskAssessment && count($pullRequest->riskAssessment->historical_incidents ??[]) > 0)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 dw-section-title">
                    <span class="material-symbols-outlined align-middle" style="font-size: 18px;">history</span>
                    Related Incidents
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="fw-medium text-secondary fs-13">ID</th>
                                <th class="fw-medium text-secondary fs-13">Incident</th>
                                <th class="fw-medium text-secondary fs-13">Severity</th>
                                <th class="fw-medium text-secondary fs-13">When</th>
                                <th class="fw-medium text-secondary fs-13">Relevance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pullRequest->riskAssessment->historical_incidents as $incident)
                                <tr>
                                    <td><code class="fs-12">{{ $incident['id'] ?? '—' }}</code></td>
                                    <td class="fw-medium fs-13">{{ $incident['title'] ?? 'Unknown' }}</td>
                                    <td>
                                        @php $sev = $incident['severity'] ?? 3; @endphp
                                        <span class="badge bg-{{ $sev <= 1 ? 'danger' : ($sev <= 2 ? 'warning' : 'info') }} bg-opacity-10 text-{{ $sev <= 1 ? 'danger' : ($sev <= 2 ? 'warning' : 'info') }}">
                                            P{{ $sev }}
                                        </span>
                                    </td>
                                    <td class="text-secondary fs-13">{{ $incident['days_ago'] ?? '?' }}d ago</td>
                                    <td class="text-secondary fs-13">{{ $incident['relevance'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Post-Deployment Outcome --}}
    @if($pullRequest->deploymentOutcome)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 dw-section-title">
                    <span class="material-symbols-outlined align-middle" style="font-size: 18px;">auto_stories</span>
                    Post-Deployment Outcome
                </h6>
                <div class="row text-center">
                    <div class="col-md-3">
                        <span class="d-block fs-12 text-secondary mb-1">Predicted</span>
                        <span class="fs-4 fw-bold">{{ $pullRequest->deploymentOutcome->predicted_risk_score }}/100</span>
                    </div>
                    <div class="col-md-3">
                        <span class="d-block fs-12 text-secondary mb-1">Incident?</span>
                        @if($pullRequest->deploymentOutcome->incident_occurred)
                            <span class="badge bg-danger px-3 py-1">Yes</span>
                        @else
                            <span class="badge bg-success px-3 py-1">No</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <span class="d-block fs-12 text-secondary mb-1">Prediction</span>
                        @if($pullRequest->deploymentOutcome->prediction_accurate)
                            <span class="badge bg-success px-3 py-1">Accurate</span>
                        @else
                            <span class="badge bg-warning px-3 py-1">Inaccurate</span>
                        @endif
                    </div>
                    <div class="col-md-3 text-start">
                        <span class="d-block fs-12 text-secondary mb-1">Notes</span>
                        <p class="fs-13 mb-0">{{ $pullRequest->deploymentOutcome->post_mortem_notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Stacked / Related PRs --}}
    @if($pullRequest->deploymentDecision && !empty($pullRequest->deploymentDecision->stacked_pr_ids))
        @php
            $stackedIds = $pullRequest->deploymentDecision->stacked_pr_ids;
            $stackedPRs = \App\Models\PullRequest::whereIn('id', $stackedIds)
                ->with(['riskAssessment', 'blastRadius'])
                ->get();
            $combinedScore = $pullRequest->deploymentDecision->combined_blast_radius_score;
        @endphp
        @if($stackedPRs->count() > 0)
            <div class="card bg-white border-0 rounded-3 mb-4 dw-card" style="border-left: 4px solid #8B5CF6 !important;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <h6 class="fw-bold mb-0 dw-section-title">
                            <span class="material-symbols-outlined align-middle text-purple" style="font-size: 18px; color: #8B5CF6;">stacks</span>
                            Stacked / Related PRs
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fs-11 ms-2">{{ $stackedPRs->count() }} related</span>
                        </h6>
                        @if($combinedScore)
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-12 text-secondary">Combined blast radius:</span>
                                <span class="badge bg-{{ $combinedScore >= 80 ? 'danger' : ($combinedScore >= 40 ? 'warning' : 'success') }} bg-opacity-10 text-{{ $combinedScore >= 80 ? 'danger' : ($combinedScore >= 40 ? 'warning' : 'success') }} px-3 py-1 fw-bold">
                                    {{ $combinedScore }} pts
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 d-flex align-items-center gap-2 mb-3 fs-13">
                        <span class="material-symbols-outlined text-warning">warning</span>
                        <span>These PRs share files or branch dependencies — merging them together increases blast radius. Review combined impact carefully.</span>
                    </div>
                    <div class="row g-3">
                        @foreach($stackedPRs as $stacked)
                            <div class="col-md-6">
                                <a href="{{ route('driftwatch.show', $stacked) }}" class="text-decoration-none">
                                    <div class="p-3 rounded-3 border h-100" style="transition: all 0.15s;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-{{ $stacked->status_color }} bg-opacity-10 text-{{ $stacked->status_color }} fs-11">{{ $stacked->status }}</span>
                                            <span class="fw-bold fs-13 text-dark">PR #{{ $stacked->pr_number }}</span>
                                            @if($stacked->riskAssessment)
                                                <span class="badge bg-{{ $stacked->risk_color }} bg-opacity-10 text-{{ $stacked->risk_color }} fs-11 ms-auto">{{ $stacked->riskAssessment->risk_score }}/100</span>
                                            @endif
                                        </div>
                                        <p class="text-secondary fs-12 mb-1 text-truncate">{{ $stacked->pr_title }}</p>
                                        <div class="d-flex gap-2 fs-11 text-secondary">
                                            <span>{{ $stacked->pr_author }}</span>
                                            <span>{{ $stacked->head_branch }} &rarr; {{ $stacked->base_branch }}</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Pipeline Timeline & Artifacts --}}
    @if($pullRequest->agentRuns->count() > 0)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 dw-section-title">
                    <span class="material-symbols-outlined align-middle" style="font-size: 18px;">timeline</span>
                    Pipeline Timeline
                    <span class="fs-12 fw-normal text-secondary ms-2">Agent execution trace with artifacts</span>
                </h6>

                {{-- Timeline visualization --}}
                @php
                    $totalDuration = $pullRequest->agentRuns->sum('duration_ms');
                    $runOrder = ['archaeologist', 'historian', 'negotiator', 'chronicler'];
                    $sortedRuns = $pullRequest->agentRuns->sortBy(function ($run) use ($runOrder) {
                        return array_search($run->agent_name, $runOrder);
                    });
                    $agentColors = ['archaeologist' => 'primary', 'historian' => 'warning', 'negotiator' => 'danger', 'chronicler' => 'success'];
                    $agentIcons = ['archaeologist' => 'explore', 'historian' => 'history', 'negotiator' => 'gavel', 'chronicler' => 'auto_stories'];
                @endphp

                {{-- Duration bar --}}
                <div class="d-flex align-items-center gap-1 mb-4" style="height:28px;">
                    @foreach($sortedRuns as $run)
                        @php
                            $pct = $totalDuration > 0 ? max(($run->duration_ms / $totalDuration) * 100, 8) : 25;
                            $color = $agentColors[$run->agent_name] ?? 'secondary';
                        @endphp
                        <div class="bg-{{ $color }} bg-opacity-75 rounded-2 d-flex align-items-center justify-content-center text-white"
                             style="width:{{ $pct }}%; height:100%; min-width:60px; font-size:11px; font-weight:600; cursor:pointer;"
                             data-bs-toggle="collapse" data-bs-target="#artifact_{{ $run->id }}"
                             title="{{ ucfirst($run->agent_name) }}: {{ number_format($run->duration_ms) }}ms">
                            {{ ucfirst(substr($run->agent_name, 0, 4)) }}
                            <span class="ms-1 opacity-75">{{ $run->duration_ms }}ms</span>
                        </div>
                    @endforeach
                    <span class="fs-11 text-secondary ms-2 flex-shrink-0">Total: {{ number_format($totalDuration) }}ms</span>
                </div>

                {{-- Agent run cards --}}
                @foreach($sortedRuns as $run)
                    @php
                        $color = $agentColors[$run->agent_name] ?? 'secondary';
                        $icon = $agentIcons[$run->agent_name] ?? 'smart_toy';
                    @endphp
                    <div class="border rounded-3 mb-2 overflow-hidden">
                        <div class="d-flex align-items-center gap-3 p-3" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#artifact_{{ $run->id }}">
                            <div class="wh-36 bg-{{ $color }} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                <span class="material-symbols-outlined text-{{ $color }}" style="font-size:18px;">{{ $icon }}</span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold fs-13">{{ ucfirst($run->agent_name) }}</span>
                                    <span class="badge bg-{{ $run->status_color }} bg-opacity-10 text-{{ $run->status_color }} fs-10">{{ $run->status }}</span>
                                    @if($run->score_contribution > 0)
                                        <span class="fs-11 text-secondary">+{{ $run->score_contribution }} pts</span>
                                    @endif
                                </div>
                                <span class="fs-11 text-secondary">{{ $run->duration_ms }}ms | {{ $run->tokens_used > 0 ? number_format($run->tokens_used) . ' tokens' : '~' . number_format(max(150, strlen(json_encode($run->output_payload ?? [])) / 4)) . ' est. tokens' }} | {{ $run->model_used }}</span>
                            </div>
                            <span class="material-symbols-outlined text-secondary" style="font-size:18px;">expand_more</span>
                        </div>

                        {{-- Collapsible artifact inspector --}}
                        <div class="collapse" id="artifact_{{ $run->id }}">
                            <div class="border-top p-3" style="background: #fafbfc;">
                                @if($run->reasoning)
                                    <div class="mb-3">
                                        <span class="fs-11 fw-bold text-uppercase text-secondary d-block mb-1">Reasoning</span>
                                        <p class="fs-12 text-secondary mb-0">{{ \Illuminate\Support\Str::limit($run->reasoning, 500) }}</p>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <span class="fs-11 fw-bold text-uppercase text-secondary d-block mb-1">Input Payload (Briefing Pack)</span>
                                        <div style="max-height:200px;overflow:auto;">
                                            <pre class="fs-11 bg-white border rounded-2 p-2 mb-0" style="white-space:pre-wrap;word-break:break-word;">{{ json_encode($run->input_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fs-11 fw-bold text-uppercase text-secondary d-block mb-1">Output Payload</span>
                                        <div style="max-height:200px;overflow:auto;">
                                            <pre class="fs-11 bg-white border rounded-2 p-2 mb-0" style="white-space:pre-wrap;word-break:break-word;">{{ json_encode($run->output_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-3 mt-2 fs-11 text-secondary">
                                    <span>Attempt: {{ $run->output_payload['attempt'] ?? 1 }}</span>
                                    <span>Cost: ${{ number_format($run->cost_usd, 6) }}</span>
                                    <span>Hash: <code>{{ substr($run->input_hash, 0, 8) }}</code></span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Agent Runs Debug Panel (only visible with ?debug=1) --}}
    @if(request()->get('debug') == '1' && $pullRequest->agentRuns->count() > 0)
        <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined text-warning fs-20">bug_report</span>
                    Agent Debug Panel
                    <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2">{{ $pullRequest->agentRuns->count() }} runs</span>
                </h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Agent</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Tokens</th>
                                <th>Cost (USD)</th>
                                <th>Score Contribution</th>
                                <th>Model</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pullRequest->agentRuns as $run)
                                <tr>
                                    <td>
                                        <span class="fw-bold">{{ $run->agent_display_name }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $run->status_color }}">{{ $run->status }}</span>
                                    </td>
                                    <td>{{ number_format($run->duration_ms) }}ms</td>
                                    <td>{!! $run->tokens_used > 0 ? number_format($run->tokens_used) : '~' . number_format(max(150, strlen(json_encode($run->output_payload ?? [])) / 4)) !!}</td>
                                    <td>${{ number_format($run->cost_usd, 6) }}</td>
                                    <td>
                                        <span class="fw-bold">{{ $run->score_contribution }}</span>
                                    </td>
                                    <td><code class="fs-12">{{ $run->model_used ?? 'N/A' }}</code></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#agentRun{{ $run->id }}">
                                            <span class="material-symbols-outlined fs-16 align-middle">expand_more</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="agentRun{{ $run->id }}">
                                    <td colspan="8" class="bg-light">
                                        @if($run->reasoning)
                                            <div class="mb-2">
                                                <strong class="fs-12 text-secondary">Reasoning:</strong>
                                                <p class="mb-1 fs-13">{{ $run->reasoning }}</p>
                                            </div>
                                        @endif
                                        <div>
                                            <strong class="fs-12 text-secondary">Raw Output JSON:</strong>
                                            <pre class="bg-dark text-light p-3 rounded-2 fs-12 mb-0" style="max-height:300px; overflow:auto;">{{ json_encode($run->output_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-3 mt-3 text-secondary fs-12">
                    <span>Total Duration: <strong>{{ number_format($pullRequest->agentRuns->sum('duration_ms')) }}ms</strong></span>
                    @php
                        $totalTokens = $pullRequest->agentRuns->sum('tokens_used');
                        $estTokens = $totalTokens > 0 ? $totalTokens : (int) $pullRequest->agentRuns->sum(fn($r) => max(150, strlen(json_encode($r->output_payload ?? [])) / 4));
                    @endphp
                    <span>Total Tokens: <strong>{{ $totalTokens > 0 ? number_format($totalTokens) : '~' . number_format($estTokens) . ' est.' }}</strong></span>
                    <span>Total Cost: <strong>${{ number_format($pullRequest->agentRuns->sum('cost_usd'), 6) }}</strong></span>
                </div>
            </div>
        </div>
    @endif

    {{-- Re-analyze Loading Overlay --}}
    {{-- Floating scroll nav arrows --}}
    <div class="scroll-nav" id="scrollNav">
        <button class="scroll-nav-btn" id="scrollToTopBtn" title="Scroll to top">
            <span class="material-symbols-outlined" style="font-size: 20px;">keyboard_arrow_up</span>
        </button>
        <button class="scroll-nav-btn" id="scrollToBottomBtn" title="Scroll to bottom">
            <span class="material-symbols-outlined" style="font-size: 20px;">keyboard_arrow_down</span>
        </button>
    </div>

    <div id="agentLoadingOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(10,15,30,0.92); backdrop-filter:blur(8px);">
        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white px-4">
            <div class="mb-4">
                <span class="material-symbols-outlined" style="font-size:48px; color:#605DFF;" id="loadingMainIcon">science</span>
            </div>
            <h3 class="fw-bold mb-2" id="loadingTitle">Re-analyzing PR #{{ $pullRequest->pr_number }}</h3>
            <p class="text-white-50 fs-14 mb-4" id="loadingSubtitle">Connecting to Azure Functions...</p>
            <div style="width:100%; max-width:520px; margin-bottom:32px;">
                <div style="height:6px; border-radius:3px; background:rgba(255,255,255,0.08); overflow:hidden;">
                    <div id="agentProgressBar" style="height:100%; width:0%; border-radius:3px; background:linear-gradient(90deg, #605DFF, #3B82F6, #06B6D4, #10B981); transition: width 1.2s cubic-bezier(0.4,0,0.2,1);"></div>
                </div>
            </div>
            <div style="width:100%; max-width:520px;">
                @foreach(['archaeologist' => ['Archaeologist', '#605DFF'], 'historian' =>['Historian', '#F59E0B'], 'negotiator' =>['Negotiator', '#EF4444'], 'chronicler' => ['Chronicler', '#10B981']] as $key => $info)
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="flex-shrink-0" style="width:40px; height:40px;">
                        <img src="{{ url('/assets/images/agents/' . ($key === 'negotiator' ? 'negotiation' : ($key === 'chronicler' ? 'chronicle' : $key)) . '.png') }}" alt="" class="rounded-circle" style="width:40px; height:40px; object-fit:cover; opacity:0.3; transition:all 0.5s;" id="img-{{ $key }}">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-14" style="opacity:0.4; transition:opacity 0.5s;" id="label-{{ $key }}">{{ $info[0] }}</span>
                            <span class="fs-12 text-white-50" id="status-{{ $key }}">Waiting...</span>
                        </div>
                        <div style="height:3px; border-radius:2px; background:rgba(255,255,255,0.06); margin-top:6px; overflow:hidden;">
                            <div id="bar-{{ $key }}" style="height:100%; width:0%; border-radius:2px; background:{{ $info[1] }}; transition:width 2s cubic-bezier(0.4,0,0.2,1);"></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <p class="text-white-50 fs-13 mt-4" id="loadingElapsed">0s elapsed</p>
        </div>
    </div>
    {{-- Full-screen Code Preview Modal --}}
    <div class="code-preview-overlay" id="codePreviewOverlay">
        <div class="code-preview-modal" style="display: flex; flex-direction: row;">
            {{-- Side file panel --}}
            <div class="cpm-file-panel" id="cpmFilePanel">
                <div class="fp-header">
                    <span>Open Files</span>
                    <button class="cpm-close" id="cpmFilePanelClose" title="Close panel" style="padding:2px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                    </button>
                </div>
                <div class="fp-list" id="cpmFileList"></div>
            </div>
            {{-- Main content --}}
            <div style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
                <div class="cpm-header">
                    <div class="file-info">
                        <span class="material-symbols-outlined" style="font-size: 20px; color: #cba6f7;">code</span>
                        <div>
                            <div class="file-name" id="cpmFileName"></div>
                            <div class="file-path" id="cpmFilePath"></div>
                        </div>
                    </div>
                    <div class="cpm-action-bar">
                        <button class="cpm-action" id="cpmAddFileBtn" title="Open another file side-by-side">
                            <span class="material-symbols-outlined" style="font-size: 14px;">add</span> Add File
                        </button>
                        <button class="cpm-action" id="cpmSendToChat" title="Send selected code to chat" style="display:none;">
                            <span class="material-symbols-outlined" style="font-size: 14px; color: #F97316;">chat</span> Send to Chat
                        </button>
                        <button class="cpm-action" id="cpmCopyBtn" title="Copy file contents">
                            <span class="material-symbols-outlined" style="font-size: 14px;">content_copy</span> Copy
                        </button>
                        <button class="cpm-action" id="cpmGithubLink" title="View on GitHub" style="display:none;">
                            <span class="material-symbols-outlined" style="font-size: 14px;">open_in_new</span> GitHub
                        </button>
                        <button class="cpm-close" id="cpmCloseBtn" title="Close">
                            <span class="material-symbols-outlined" style="font-size: 22px;">close</span>
                        </button>
                    </div>
                </div>
                <div class="cpm-toolbar">
                    <button class="cpm-tab active" data-cpm-tab="diff" id="cpmDiffTab">
                        <span class="material-symbols-outlined" style="font-size: 13px; vertical-align: -2px;">difference</span> Changes
                    </button>
                    <button class="cpm-tab" data-cpm-tab="source" id="cpmSourceTab">
                        <span class="material-symbols-outlined" style="font-size: 13px; vertical-align: -2px;">code</span> Full Source
                    </button>
                    <div class="cpm-stats" id="cpmStats"></div>
                </div>
                <div class="cpm-body" id="cpmBody"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dagre-d3/0.6.4/dagre-d3.min.js"></script>
<script>
var mermaidScale = 1;
function zoomMermaid(factor) {
    var wrapper = document.getElementById('mermaidZoomWrapper');
    if (!wrapper) return;
    if (factor === 0) { mermaidScale = 1; }
    else { mermaidScale = Math.max(0.3, Math.min(3, mermaidScale * factor)); }
    wrapper.style.transform = 'scale(' + mermaidScale + ')';
}

function toggleBlastView(view) {
    var treeEl = document.getElementById('blastDependencyTree');
    var summaryEl = document.getElementById('blastSummaryView');
    var dynamicEl = document.getElementById('blastRadiusDynamic');
    var structuralEl = document.getElementById('blastRadiusStructural');
    var infoPanel = document.getElementById('blastInfoPanel');
    var legend = document.getElementById('graphLegend');
    var btnTree = document.getElementById('btnTreeView');
    var btnSummary = document.getElementById('btnSummaryView');
    var btnDynamic = document.getElementById('btnDynamicView');

    // Hide all
    if (treeEl) treeEl.style.display = 'none';
    if (summaryEl) summaryEl.style.display = 'none';
    if (dynamicEl) dynamicEl.style.display = 'none';
    if (structuralEl) structuralEl.style.display = 'none';
    if (infoPanel) infoPanel.style.display = 'none';
    if (legend) legend.style.cssText = 'display: none !important;';
    if (btnTree) btnTree.className = 'btn btn-sm btn-outline-primary';
    if (btnSummary) btnSummary.className = 'btn btn-sm btn-outline-primary';
    if (btnDynamic) btnDynamic.className = 'btn btn-sm btn-outline-primary';

    if (view === 'tree') {
        if (treeEl) treeEl.style.display = 'block';
        if (btnTree) btnTree.className = 'btn btn-sm btn-primary active';
        // Lazy init the dependency tree
        if (treeEl && !treeEl.dataset.rendered && window._initDagTree) {
            window._initDagTree();
            treeEl.dataset.rendered = 'true';
        }
    } else if (view === 'summary') {
        if (summaryEl) summaryEl.style.display = 'block';
        if (btnSummary) btnSummary.className = 'btn btn-sm btn-primary active';
    } else if (view === 'dynamic') {
        if (dynamicEl) dynamicEl.style.display = 'block';
        if (btnDynamic) btnDynamic.className = 'btn btn-sm btn-primary active';
        if (legend) legend.style.cssText = 'display: flex !important;';
        if (!dynamicEl.dataset.rendered && window._initVisGraph) {
            window._initVisGraph();
            dynamicEl.dataset.rendered = 'true';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    mermaid.initialize({ startOnLoad: false, theme: 'base', securityLevel: 'loose', themeVariables: {
        primaryColor: '#e8f0fe', primaryBorderColor: '#605DFF', primaryTextColor: '#1a1a2e',
        lineColor: '#94a3b8', secondaryColor: '#fef3c7', tertiaryColor: '#fce7f3',
        fontSize: '13px', fontFamily: 'inherit'
    }});

    @if($pullRequest->riskAssessment)
    // Animate score composition progress bars
    setTimeout(function() {
        document.querySelectorAll('#riskNeedleBars .progress-bar').forEach(function(bar) {
            var target = bar.getAttribute('data-target-width');
            if (target) bar.style.width = target;
        });
    }, 600);
    @endif

    @if($pullRequest->blastRadius)
    var services = @json($pullRequest->blastRadius->affected_services ??[]);
    var files = @json($pullRequest->blastRadius->affected_files ??[]);
    var endpoints = @json($pullRequest->blastRadius->affected_endpoints ??[]);
    var depGraph = @json($pullRequest->blastRadius->dependency_graph ??[]);
    var blastSummary = @json($pullRequest->blastRadius->summary ?? '');
    var fileDescriptions = @json($pullRequest->blastRadius->file_descriptions ??[]);
    var changeClassifications = @json(
        collect($pullRequest->agentRuns ??[])
            ->where('agent_name', 'archaeologist')
            ->pluck('output_payload')
            ->first()['change_classifications'] ?? ($pullRequest->blastRadius->dependency_graph ? [] :[])
    );
    var prUrl = @json($pullRequest->pr_url ?? '');
    var repoFullName = @json($pullRequest->repo_full_name ?? '');
    var negotiatorComment = @json(
        collect($pullRequest->agentRuns ?? [])
            ->where('agent_name', 'negotiator')
            ->pluck('output_payload')
            ->first()['pr_comment'] ?? ''
    );
    var negotiatorReasoning = @json(
        collect($pullRequest->agentRuns ?? [])
            ->where('agent_name', 'negotiator')
            ->pluck('reasoning')
            ->first() ?? ''
    );
    var riskScore = @json($pullRequest->riskAssessment->risk_score ?? 0);
    var riskLevel = @json($pullRequest->riskAssessment->risk_level ?? 'unknown');
    var decisionText = @json($pullRequest->deploymentDecision->decision ?? 'pending');

    // Build lookup maps for quick access
    var fileDescMap = {};
    if (fileDescriptions && typeof fileDescriptions === 'object') {
        Object.keys(fileDescriptions).forEach(function(k) { fileDescMap[k] = fileDescriptions[k]; });
    }
    var changeClassMap = {};
    if (Array.isArray(changeClassifications)) {
        changeClassifications.forEach(function(c) { if (c.file) changeClassMap[c.file] = c; });
    }

    // === Shared helpers ===
    var depKeys = Object.keys(depGraph);
    var sourceFiles = {};
    depKeys.forEach(function(k) { sourceFiles[k] = true; });

    function getFileType(filePath) {
        var ext = filePath.split('.').pop().toLowerCase();
        var typeMap = { 'py': 'Python', 'js': 'JavaScript', 'ts': 'TypeScript', 'jsx': 'React', 'tsx': 'React', 'php': 'PHP', 'vue': 'Vue', 'css': 'CSS', 'scss': 'SCSS', 'html': 'Template', 'md': 'Docs', 'json': 'Config', 'yaml': 'Config', 'yml': 'Config', 'sql': 'SQL', 'go': 'Go', 'rb': 'Ruby', 'java': 'Java', 'rs': 'Rust' };
        var name = filePath.split('/').pop();
        if (name.includes('test') || name.includes('spec')) return 'Test';
        return typeMap[ext] || ext.toUpperCase();
    }

    function getFileIcon(filePath) {
        var type = getFileType(filePath);
        var icons = { 'Python': 'code', 'JavaScript': 'javascript', 'TypeScript': 'code', 'React': 'web', 'PHP': 'php', 'Vue': 'web', 'CSS': 'palette', 'SCSS': 'palette', 'Template': 'web', 'Docs': 'description', 'Config': 'settings', 'SQL': 'storage', 'Test': 'science', 'Go': 'code', 'Ruby': 'code', 'Java': 'code', 'Rust': 'code' };
        return icons[type] || 'insert_drive_file';
    }

    // Describe what a file likely does based on path and name
    function describeFile(filePath, isSource) {
        var name = filePath.split('/').pop().toLowerCase();
        var dir = filePath.split('/').slice(0, -1).join('/');
        var depCount = (depGraph[filePath] && Array.isArray(depGraph[filePath])) ? depGraph[filePath].length : 0;
        var desc =[];

        // What it is
        if (name.includes('test') || name.includes('spec')) desc.push('Test file');
        else if (name.includes('route') || name.includes('router')) desc.push('Routing definitions');
        else if (name.includes('controller')) desc.push('Request handler');
        else if (name.includes('model') || name.includes('schema')) desc.push('Data model');
        else if (name.includes('middleware')) desc.push('Middleware layer');
        else if (name.includes('migration')) desc.push('DB migration');
        else if (name.includes('config') || name.match(/\.(json|yaml|yml|toml|ini)$/)) desc.push('Configuration');
        else if (name.includes('service') || name.includes('provider')) desc.push('Service layer');
        else if (name.includes('util') || name.includes('helper')) desc.push('Utility functions');
        else if (name.includes('component') || dir.includes('component')) desc.push('UI component');
        else if (name.includes('view') || dir.includes('view')) desc.push('View template');
        else if (dir.includes('api') || dir.includes('endpoint')) desc.push('API layer');
        else if (dir.includes('worker') || dir.includes('job') || dir.includes('queue')) desc.push('Background worker');
        else desc.push(getFileType(filePath) + ' module');

        // Impact
        if (isSource && depCount > 0) desc.push('impacts ' + depCount + ' downstream file' + (depCount > 1 ? 's' : ''));
        else if (isSource) desc.push('directly changed');
        else desc.push('in blast radius');

        return desc.join(' — ');
    }

    // Group files by directory
    function groupByDirectory(fileList) {
        var groups = {};
        fileList.forEach(function(f) {
            var parts = f.split('/');
            var dir = parts.length > 1 ? parts.slice(0, -1).join('/') : '(root)';
            if (!groups[dir]) groups[dir] = [];
            groups[dir].push(f);
        });
        return groups;
    }

    // Helper: strip emoji characters from text
    function stripEmojis(text) {
        if (!text) return '';
        return text.replace(/[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{FE00}-\u{FE0F}\u{200D}\u{20E3}\u{E0020}-\u{E007F}]/gu, '').replace(/\s{2,}/g, ' ').trim();
    }

    // Helper: convert markdown-ish text to simple HTML
    function renderNegotiatorComment(text) {
        if (!text) return '';
        text = stripEmojis(text);
        // Bold
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Headers (### -> h6, ## -> h6)
        text = text.replace(/^###\s+(.+)$/gm, '<h6 class="fw-bold fs-14 mt-3 mb-1">$1</h6>');
        text = text.replace(/^##\s+(.+)$/gm, '<h6 class="fw-bold fs-14 mt-3 mb-1">$1</h6>');
        // Horizontal rules
        text = text.replace(/^---+$/gm, '<hr class="my-2 opacity-25">');
        // List items
        text = text.replace(/^- (.+)$/gm, '<div class="d-flex align-items-start gap-2 mb-1"><span class="material-symbols-outlined text-secondary flex-shrink-0" style="font-size:14px;margin-top:2px;">chevron_right</span><span class="fs-12">$1</span></div>');
        // Paragraphs (double newlines)
        text = text.replace(/\n\n/g, '</p><p class="fs-13 text-secondary mb-2">');
        // Single newlines
        text = text.replace(/\n/g, '<br>');
        return '<p class="fs-13 text-secondary mb-2">' + text + '</p>';
    }

    // === SUMMARY VIEW (default) — directory-grouped, readable ===
    (function() {
        var el = document.getElementById('impactSummaryContent');
        if (!el) return;

        var html = '';

        // Negotiator Risk Summary (if available)
        var commentText = negotiatorComment || negotiatorReasoning || '';
        if (commentText.length > 20) {
            var decisionColors = { 'approved': 'success', 'blocked': 'danger', 'pending_review': 'warning' };
            var decisionIcons = { 'approved': 'verified', 'blocked': 'block', 'pending_review': 'hourglass_top' };
            var dColor = decisionColors[decisionText] || 'secondary';
            var dIcon = decisionIcons[decisionText] || 'gavel';
            var scoreColor = riskScore <= 20 ? 'success' : (riskScore <= 45 ? 'primary' : (riskScore <= 70 ? 'warning' : 'danger'));

            html += '<div class="mb-4 p-3 rounded-3 border" style="border-left: 4px solid var(--bs-' + dColor + ') !important;">';
            html += '<div class="d-flex align-items-center gap-3 mb-3">';
            html += '<div class="d-flex align-items-center justify-content-center rounded-circle bg-' + dColor + ' bg-opacity-10" style="width:44px;height:44px;flex-shrink:0;">';
            html += '<span class="material-symbols-outlined text-' + dColor + '" style="font-size:22px;">' + dIcon + '</span>';
            html += '</div>';
            html += '<div class="flex-grow-1">';
            html += '<div class="d-flex align-items-center gap-2">';
            html += '<span class="fw-bold fs-14">Negotiator Risk Assessment</span>';
            html += '<span class="badge bg-' + dColor + ' bg-opacity-10 text-' + dColor + ' fs-11 text-uppercase">' + decisionText.replace('_', ' ') + '</span>';
            html += '</div>';
            html += '<span class="fs-12 text-secondary">Risk Score: <strong class="text-' + scoreColor + '">' + riskScore + '/100</strong> (' + riskLevel + ')</span>';
            html += '</div>';
            html += '</div>';
            html += renderNegotiatorComment(commentText);
            html += '</div>';
        }

        // Quick stats bar
        var changedCount = depKeys.length;
        var affectedCount = files.length - changedCount;
        var downstreamTotal = 0;
        depKeys.forEach(function(k) { if (Array.isArray(depGraph[k])) downstreamTotal += depGraph[k].length; });

        html += '<div class="d-flex gap-3 flex-wrap mb-4">';
        html += '<div class="d-flex align-items-center gap-2 px-3 py-2 bg-danger bg-opacity-10 rounded-3"><span class="material-symbols-outlined text-danger" style="font-size:18px;">edit_document</span><span class="fs-13"><strong>' + changedCount + '</strong> changed</span></div>';
        html += '<div class="d-flex align-items-center gap-2 px-3 py-2 bg-warning bg-opacity-10 rounded-3"><span class="material-symbols-outlined text-warning" style="font-size:18px;">share</span><span class="fs-13"><strong>' + affectedCount + '</strong> affected</span></div>';
        if (downstreamTotal > 0) html += '<div class="d-flex align-items-center gap-2 px-3 py-2 bg-warning bg-opacity-10 rounded-3"><span class="material-symbols-outlined text-warning" style="font-size:18px;">arrow_downward</span><span class="fs-13"><strong>' + downstreamTotal + '</strong> downstream deps</span></div>';
        if (services.length > 0) html += '<div class="d-flex align-items-center gap-2 px-3 py-2 bg-primary bg-opacity-10 rounded-3"><span class="material-symbols-outlined text-primary" style="font-size:18px;">dns</span><span class="fs-13"><strong>' + services.length + '</strong> service' + (services.length > 1 ? 's' : '') + '</span></div>';
        if (endpoints.length > 0) html += '<div class="d-flex align-items-center gap-2 px-3 py-2 bg-info bg-opacity-10 rounded-3"><span class="material-symbols-outlined text-info" style="font-size:18px;">api</span><span class="fs-13"><strong>' + endpoints.length + '</strong> endpoint' + (endpoints.length > 1 ? 's' : '') + '</span></div>';
        html += '</div>';

        // Services section
        if (services.length > 0) {
            html += '<div class="mb-4">';
            html += '<h6 class="fw-bold fs-14 mb-2"><span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size:16px;">dns</span> Affected Services</h6>';
            html += '<div class="d-flex flex-wrap gap-2">';
            services.forEach(function(s) {
                html += '<div class="d-flex align-items-center gap-2 px-3 py-2 border rounded-3"><span class="material-symbols-outlined text-primary" style="font-size:16px;">dns</span><span class="fs-13 fw-medium">' + s + '</span></div>';
            });
            html += '</div></div>';
        }

        // Changed Files — grouped by directory
        if (changedCount > 0) {
            var changedGroups = groupByDirectory(depKeys);
            html += '<div class="mb-4">';
            html += '<h6 class="fw-bold fs-14 mb-2"><span class="material-symbols-outlined align-middle me-1 text-danger" style="font-size:16px;">edit_document</span> Changed Files</h6>';

            Object.keys(changedGroups).sort().forEach(function(dir) {
                var dirFiles = changedGroups[dir];
                html += '<div class="mb-2">';
                html += '<div class="d-flex align-items-center gap-1 mb-1"><span class="material-symbols-outlined text-secondary" style="font-size:14px;">folder</span><span class="fs-12 fw-medium text-secondary">' + dir + '/</span><span class="badge bg-danger bg-opacity-10 text-danger fs-11">' + dirFiles.length + '</span></div>';
                dirFiles.forEach(function(f) {
                    var name = f.split('/').pop();
                    var depCount = (depGraph[f] && Array.isArray(depGraph[f])) ? depGraph[f].length : 0;
                    html += '<div class="d-flex align-items-center gap-2 ps-4 py-1">';
                    html += '<span class="material-symbols-outlined text-danger" style="font-size:14px;">' + getFileIcon(f) + '</span>';
                    html += '<code class="fs-12 fw-medium">' + name + '</code>';
                    html += '<span class="fs-11 text-secondary">' + describeFile(f, true) + '</span>';
                    if (depCount > 0) html += '<span class="badge bg-warning bg-opacity-10 text-warning fs-11">' + depCount + ' deps</span>';
                    html += '</div>';
                });
                html += '</div>';
            });
            html += '</div>';
        }

        // Affected Files (not source) — grouped by directory
        var nonSourceFiles = files.filter(function(f) { return !sourceFiles[f]; });
        if (nonSourceFiles.length > 0) {
            var affGroups = groupByDirectory(nonSourceFiles);
            html += '<div class="mb-4">';
            html += '<h6 class="fw-bold fs-14 mb-2"><span class="material-symbols-outlined align-middle me-1 text-warning" style="font-size:16px;">share</span> Affected Files</h6>';

            Object.keys(affGroups).sort().forEach(function(dir) {
                var dirFiles = affGroups[dir];
                html += '<div class="mb-2">';
                html += '<div class="d-flex align-items-center gap-1 mb-1"><span class="material-symbols-outlined text-secondary" style="font-size:14px;">folder</span><span class="fs-12 fw-medium text-secondary">' + dir + '/</span><span class="badge bg-warning bg-opacity-10 text-warning fs-11">' + dirFiles.length + '</span></div>';
                dirFiles.forEach(function(f) {
                    var name = f.split('/').pop();
                    html += '<div class="d-flex align-items-center gap-2 ps-4 py-1">';
                    html += '<span class="material-symbols-outlined text-warning" style="font-size:14px;">' + getFileIcon(f) + '</span>';
                    html += '<code class="fs-12">' + name + '</code>';
                    html += '<span class="fs-11 text-secondary">' + describeFile(f, false) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            });
            html += '</div>';
        }

        // Downstream Dependencies
        var allDownstream =[];
        depKeys.forEach(function(k) {
            if (Array.isArray(depGraph[k])) {
                depGraph[k].forEach(function(d) {
                    if (files.indexOf(d) < 0 && allDownstream.indexOf(d) < 0) allDownstream.push(d);
                });
            }
        });
        if (allDownstream.length > 0) {
            var dsGroups = groupByDirectory(allDownstream);
            html += '<div class="mb-4">';
            html += '<h6 class="fw-bold fs-14 mb-2"><span class="material-symbols-outlined align-middle me-1 text-warning" style="font-size:16px;">arrow_downward</span> Downstream Dependencies <span class="fs-12 text-secondary fw-normal">— may break if APIs change</span></h6>';

            Object.keys(dsGroups).sort().forEach(function(dir) {
                var dirFiles = dsGroups[dir];
                html += '<div class="mb-2">';
                html += '<div class="d-flex align-items-center gap-1 mb-1"><span class="material-symbols-outlined text-secondary" style="font-size:14px;">folder</span><span class="fs-12 fw-medium text-secondary">' + dir + '/</span><span class="badge bg-warning bg-opacity-10 text-warning fs-11">' + dirFiles.length + '</span></div>';
                dirFiles.slice(0, 15).forEach(function(f) {
                    var name = f.split('/').pop();
                    // Find which source file this depends on
                    var dependsOn = '';
                    depKeys.forEach(function(src) {
                        if (Array.isArray(depGraph[src]) && depGraph[src].indexOf(f) >= 0) dependsOn = src.split('/').pop();
                    });
                    html += '<div class="d-flex align-items-center gap-2 ps-4 py-1">';
                    html += '<span class="material-symbols-outlined text-warning" style="font-size:14px;">link</span>';
                    html += '<code class="fs-12">' + name + '</code>';
                    if (dependsOn) html += '<span class="fs-11 text-secondary">depends on <strong>' + dependsOn + '</strong></span>';
                    html += '</div>';
                });
                if (dirFiles.length > 15) html += '<div class="ps-4 fs-12 text-secondary">+ ' + (dirFiles.length - 15) + ' more files</div>';
                html += '</div>';
            });
            html += '</div>';
        }

        // Endpoints
        if (endpoints.length > 0) {
            html += '<div class="mb-2">';
            html += '<h6 class="fw-bold fs-14 mb-2"><span class="material-symbols-outlined align-middle me-1 text-info" style="font-size:16px;">api</span> Exposed Endpoints</h6>';
            html += '<div class="d-flex flex-wrap gap-2">';
            endpoints.forEach(function(ep) {
                html += '<div class="d-flex align-items-center gap-2 px-3 py-2 border rounded-3"><span class="badge bg-info bg-opacity-10 text-info">API</span><code class="fs-12">' + ep + '</code></div>';
            });
            html += '</div></div>';
        }

        el.innerHTML = html;
    })();

    // === Dependency Tree — Hierarchical LR DAG using dagre-d3 ===
    window._initDagTree = function() {
        var g = new dagreD3.graphlib.Graph().setGraph({
            rankdir: 'LR',
            marginx: 30,
            marginy: 30,
            ranksep: 80,
            nodesep: 20,
            edgesep: 10
        }).setDefaultEdgeLabel(function() { return {}; });

        var prLabel = @json($pullRequest->repo_full_name . ' #' . $pullRequest->pr_number);

        // Node color helpers
        function scoreColor(score) {
            if (score > 20) return '#EF4444';
            if (score > 10) return '#F97316';
            return '#94a3b8';
        }

        // PR origin node
        g.setNode('pr_origin', {
            label: prLabel,
            style: 'fill: #605DFF; stroke: #4b49cc; stroke-width: 2px;',
            labelStyle: 'fill: #fff; font-weight: bold; font-size: 13px;',
            shape: 'rect',
            rx: 6, ry: 6,
            width: Math.max(180, prLabel.length * 8),
            height: 40
        });

        // Track all nodes to fix orphans
        var addedNodes = { 'pr_origin': true };
        var hasEdge = {};

        // Changed files
        files.forEach(function(f) {
            var fname = (typeof f === 'string') ? f : (f.filename || f);
            var short = fname.split('/').pop();
            var depCount = (depGraph[fname] && Array.isArray(depGraph[fname])) ? depGraph[fname].length : 0;
            var nodeLabel = short + (depCount > 0 ? '\n' + depCount + ' deps' : '');

            // Determine score from change_classifications if available
            var fileScore = 0;
            var classifications = @json($pullRequest->blastRadius->dependency_graph ??[]);
            var fillColor = '#FEE2E2';
            var strokeColor = '#EF4444';

            g.setNode('file_' + fname, {
                label: nodeLabel,
                style: 'fill: ' + fillColor + '; stroke: ' + strokeColor + '; stroke-width: 2px;',
                labelStyle: 'fill: #1a1a2e; font-size: 12px;',
                shape: 'rect',
                rx: 4, ry: 4,
                width: Math.max(160, short.length * 8),
                height: depCount > 0 ? 44 : 36
            });
            addedNodes['file_' + fname] = true;

            // Edge from PR to changed file
            g.setEdge('pr_origin', 'file_' + fname, {
                style: 'stroke: #605DFF; stroke-width: 2px;',
                arrowheadStyle: 'fill: #605DFF;',
                curve: d3.curveBasis
            });
            hasEdge['file_' + fname] = true;
        });

        // Downstream dependencies from dependency_graph
        depKeys.forEach(function(srcFile) {
            if (!Array.isArray(depGraph[srcFile])) return;
            var srcNodeId = 'file_' + srcFile;
            if (!addedNodes[srcNodeId]) {
                var srcShort = srcFile.split('/').pop();
                g.setNode(srcNodeId, {
                    label: srcShort,
                    style: 'fill: #FEE2E2; stroke: #EF4444; stroke-width: 2px;',
                    labelStyle: 'fill: #1a1a2e; font-size: 12px;',
                    shape: 'rect', rx: 4, ry: 4,
                    width: Math.max(160, srcShort.length * 8), height: 36
                });
                addedNodes[srcNodeId] = true;
            }

            depGraph[srcFile].forEach(function(dep) {
                var depNodeId = 'dep_' + dep;
                if (!addedNodes[depNodeId]) {
                    var depShort = dep.split('/').pop();
                    g.setNode(depNodeId, {
                        label: depShort,
                        style: 'fill: #FEF3C7; stroke: #F59E0B; stroke-width: 1.5px;',
                        labelStyle: 'fill: #1a1a2e; font-size: 11px;',
                        shape: 'rect', rx: 8, ry: 8,
                        width: Math.max(160, depShort.length * 8), height: 34
                    });
                    addedNodes[depNodeId] = true;
                }
                g.setEdge(srcNodeId, depNodeId, {
                    style: 'stroke: #F59E0B; stroke-width: 1.5px;',
                    arrowheadStyle: 'fill: #F59E0B;',
                    curve: d3.curveBasis
                });
                hasEdge[depNodeId] = true;
            });
        });

        // Service nodes
        services.forEach(function(svc) {
            var svcNodeId = 'svc_' + svc;
            if (!addedNodes[svcNodeId]) {
                g.setNode(svcNodeId, {
                    label: svc,
                    style: 'fill: #DBEAFE; stroke: #3B82F6; stroke-width: 2.5px;',
                    labelStyle: 'fill: #1e40af; font-weight: bold; font-size: 12px;',
                    shape: 'rect', rx: 8, ry: 8,
                    width: Math.max(160, svc.length * 8), height: 38
                });
                addedNodes[svcNodeId] = true;
            }
        });

        // Connect changed files to services (heuristic: match path segments)
        files.forEach(function(f) {
            var fname = (typeof f === 'string') ? f : (f.filename || f);
            services.forEach(function(svc) {
                var svcLower = svc.toLowerCase().replace(/[-_]/g, '');
                if (fname.toLowerCase().replace(/[-_]/g, '').indexOf(svcLower) >= 0) {
                    g.setEdge('file_' + fname, 'svc_' + svc, {
                        style: 'stroke: #3B82F6; stroke-width: 1.5px; stroke-dasharray: 5,3;',
                        arrowheadStyle: 'fill: #3B82F6;',
                        curve: d3.curveBasis
                    });
                    hasEdge['svc_' + svc] = true;
                }
            });
        });

        // Endpoint nodes
        endpoints.forEach(function(ep) {
            var epNodeId = 'ep_' + ep;
            g.setNode(epNodeId, {
                label: ep,
                style: 'fill: #CFFAFE; stroke: #06B6D4; stroke-width: 2px;',
                labelStyle: 'fill: #155e75; font-size: 11px;',
                shape: 'diamond',
                width: Math.max(160, ep.length * 7), height: 36
            });
            addedNodes[epNodeId] = true;
        });

        // Connect services to endpoints
        endpoints.forEach(function(ep) {
            services.forEach(function(svc) {
                g.setEdge('svc_' + svc, 'ep_' + ep, {
                    style: 'stroke: #06B6D4; stroke-width: 1px; stroke-dasharray: 3,3;',
                    arrowheadStyle: 'fill: #06B6D4;',
                    curve: d3.curveBasis
                });
                hasEdge['ep_' + ep] = true;
            });
        });

        // Fix orphan nodes: attach any node without edges to PR origin
        Object.keys(addedNodes).forEach(function(nodeId) {
            if (nodeId !== 'pr_origin' && !hasEdge[nodeId]) {
                g.setEdge('pr_origin', nodeId, {
                    style: 'stroke: #94a3b8; stroke-width: 1px; stroke-dasharray: 4,4;',
                    arrowheadStyle: 'fill: #94a3b8;',
                    curve: d3.curveBasis
                });
            }
        });

        // Render with dagre-d3
        var svg = d3.select('#dagTreeSvg');
        svg.selectAll('*').remove();
        var inner = svg.append('g');

        var render = new dagreD3.render();
        render(inner, g);

        // Fit to container
        var graphWidth = g.graph().width + 60;
        var graphHeight = g.graph().height + 60;
        var container = document.getElementById('dagTreeContainer');
        var containerWidth = container.offsetWidth;
        var containerHeight = container.offsetHeight || 580;
        var scale = Math.min(containerWidth / graphWidth, containerHeight / graphHeight, 1);
        var xOffset = (containerWidth - graphWidth * scale) / 2;

        svg.attr('height', Math.max(containerHeight, graphHeight * scale + 40));
        svg.attr('width', Math.max(containerWidth, graphWidth * scale));
        inner.attr('transform', 'translate(' + (xOffset + 20) + ',20) scale(' + scale + ')');

        // Add zoom behavior
        var zoom = d3.zoom().scaleExtent([0.3, 3]).on('zoom', function(event) {
            inner.attr('transform', event.transform);
        });
        svg.call(zoom);
        svg.call(zoom.transform, d3.zoomIdentity.translate(xOffset + 20, 20).scale(scale));

        // Store graph reference for search highlighting
        window._dagGraph = g;
        window._dagInner = inner;
        window._dagSvg = svg;
        window._dagAddedNodes = addedNodes;

        // Node click — open side panel with file details
        inner.selectAll('g.node').on('click', function(event, nodeId) {
            var nodeData = g.node(nodeId);
            if (!nodeData) return;
            event.stopPropagation();
            openSidePanel(nodeId, g);
        });

        // Click on SVG background closes the side panel
        svg.on('click', function(event) {
            if (event.target === svg.node() || event.target.tagName === 'svg') {
                closeSidePanel();
                clearTreeSearch();
            }
        });

        // Edge hover tooltip showing relationship
        var edgeTooltip = document.getElementById('dagEdgeTooltip');
        inner.selectAll('g.edgePath').on('mouseenter', function(event) {
            var edge = d3.select(this);
            var edgeData = edge.datum();
            if (!edgeTooltip || !edgeData) return;

            var srcId = edgeData.v;
            var tgtId = edgeData.w;
            var srcName = (g.node(srcId)?.label || '').split('\n')[0];
            var tgtName = (g.node(tgtId)?.label || '').split('\n')[0];
            var srcRealId = srcId.replace(/^(file_|dep_|svc_|ep_)/, '');
            var tgtRealId = tgtId.replace(/^(file_|dep_|svc_|ep_)/, '');

            var explanation = '';
            if (srcId === 'pr_origin') {
                explanation = 'This PR directly changes <strong>' + tgtName + '</strong>';
            } else if (srcId.startsWith('file_') && tgtId.startsWith('dep_')) {
                var srcDesc = fileDescMap[srcRealId];
                var tgtDesc = fileDescMap[tgtRealId];
                explanation = '<strong>' + tgtName + '</strong> imports from <strong>' + srcName + '</strong>';
                if (srcDesc && srcDesc.affects && !srcDesc.affects.includes('No known')) {
                    explanation += '<br><span style="color:#F59E0B;">Impact:</span> ' + srcDesc.affects;
                } else {
                    explanation += '<br><span style="color:#F59E0B;">Impact:</span> Breaking changes in ' + srcName + ' will cascade here';
                }
            } else if (srcId.startsWith('file_') && tgtId.startsWith('svc_')) {
                explanation = '<strong>' + srcName + '</strong> belongs to the <strong>' + tgtName + '</strong> service';
            } else if (srcId.startsWith('svc_') && tgtId.startsWith('ep_')) {
                explanation = 'Endpoint <strong>' + tgtName + '</strong> is served by <strong>' + srcName + '</strong>';
            } else {
                explanation = '<strong>' + srcName + '</strong> connects to <strong>' + tgtName + '</strong>';
            }

            edgeTooltip.innerHTML = explanation;
            edgeTooltip.style.display = 'block';
            edgeTooltip.style.left = (event.pageX + 12) + 'px';
            edgeTooltip.style.top = (event.pageY - 10) + 'px';
        }).on('mousemove', function(event) {
            if (edgeTooltip) {
                edgeTooltip.style.left = (event.pageX + 12) + 'px';
                edgeTooltip.style.top = (event.pageY - 10) + 'px';
            }
        }).on('mouseleave', function() {
            if (edgeTooltip) edgeTooltip.style.display = 'none';
        });

        // Make edge paths wider for easier hovering
        inner.selectAll('g.edgePath path').style('stroke-width', function() {
            return Math.max(parseFloat(d3.select(this).style('stroke-width')) || 2, 3) + 'px';
        });

    };

    // Auto-init tree on page load (it's the default tab)
    setTimeout(function() {
        if (window._initDagTree) {
            window._initDagTree();
            var treeEl = document.getElementById('blastDependencyTree');
            if (treeEl) treeEl.dataset.rendered = 'true';
            initTreeSummary();
            initTreeSearch();
        }
    }, 500);

    // === Chat Panel Engine ===
    var _chatSearchIndex = [];

    function buildChatSearchIndex() {
        _chatSearchIndex = [];
        var allNodes = window._dagAddedNodes || {};
        var g = window._dagGraph;
        if (!g) return;
        Object.keys(allNodes).forEach(function(nodeId) {
            var nd = g.node(nodeId);
            if (!nd) return;
            var label = (nd.label || '').split('\n')[0];
            var realId = nodeId.replace(/^(file_|dep_|svc_|ep_)/, '');
            var ci = changeClassMap[realId] || null;
            var desc = fileDescMap[realId] || null;
            var type = 'unknown';
            if (nodeId === 'pr_origin') type = 'pr';
            else if (nodeId.startsWith('file_')) type = 'file';
            else if (nodeId.startsWith('dep_')) type = 'dependency';
            else if (nodeId.startsWith('svc_')) type = 'service';
            else if (nodeId.startsWith('ep_')) type = 'endpoint';
            _chatSearchIndex.push({
                nodeId: nodeId, label: label, fullPath: realId, type: type,
                score: ci ? (ci.risk_score || 0) : 0,
                changeType: ci ? (ci.change_type || '') : '',
                reasoning: ci ? (ci.reasoning || '') : '',
                summary: desc ? (desc.summary || '') : '',
                affects: desc ? (desc.affects || '') : '',
                linesChanged: ci ? (ci.lines_changed || 0) : 0,
                fullFileRead: ci ? (ci.full_file_read || false) : false,
                searchText: (label + ' ' + realId + ' ' + type + ' ' + (ci ? ci.change_type || '' : '') + ' ' + (ci ? ci.reasoning || '' : '') + ' ' + (desc ? desc.summary || '' : '')).toLowerCase()
            });
        });
    }

    function openSidePanel(nodeId, g) {
        var panel = document.getElementById('treeSidePanel');
        if (!panel.classList.contains('open')) {
            panel.classList.add('open');
        }
        // Add a file info message to chat
        var nd = g.node(nodeId);
        if (!nd) return;
        addNodeInfoToChat(nodeId);
        highlightNodePath(nodeId);
    }

    // Track last clicked file for contextual queries
    var _lastClickedFile = null;
    var _lastClickedNodeId = null;

    // Add a file info card to chat when a node is clicked
    function addNodeInfoToChat(nodeId) {
        var g = window._dagGraph;
        var nd = g.node(nodeId);
        if (!nd) return;
        var realId = nodeId.replace(/^(file_|dep_|svc_|ep_)/, '');
        var shortName = nd.label.split('\n')[0];
        var desc = fileDescMap[realId] || null;
        var ci = changeClassMap[realId] || null;

        // Track context
        _lastClickedFile = realId;
        _lastClickedNodeId = nodeId;

        var html = '<div class="chat-file-card" data-node-id="' + nodeId + '">';
        html += '<div class="d-flex align-items-center gap-2 mb-1">';
        html += '<span class="material-symbols-outlined" style="font-size:16px;color:#605DFF;">';
        if (nodeId.startsWith('svc_')) html += 'dns';
        else if (nodeId.startsWith('ep_')) html += 'api';
        else if (nodeId.startsWith('dep_')) html += 'call_split';
        else html += 'description';
        html += '</span>';
        html += '<span class="fw-bold fs-12">' + shortName + '</span>';
        if (ci && ci.risk_score) {
            var c = ci.risk_score >= 25 ? '#EF4444' : ci.risk_score >= 15 ? '#F97316' : ci.risk_score >= 5 ? '#F59E0B' : '#10B981';
            html += '<span class="badge rounded-pill fs-10 ms-auto" style="background:' + c + '20;color:' + c + ';">' + ci.risk_score + ' pts</span>';
        }
        html += '</div>';
        html += '<div class="fs-11 text-secondary text-truncate mb-1">' + realId + '</div>';

        // Summary
        var summary = '';
        if (desc && desc.summary) summary = desc.summary;
        else if (ci && ci.reasoning) summary = ci.reasoning;
        else if (nodeId.startsWith('svc_')) summary = 'Service in the blast radius.';
        else if (nodeId.startsWith('ep_')) summary = 'API endpoint exposed through affected service.';
        if (summary) html += '<div class="fs-11" style="line-height:1.5;">' + summary + '</div>';

        // Risk bar
        if (ci && ci.risk_score) {
            var rc = ci.risk_score >= 25 ? '#EF4444' : ci.risk_score >= 15 ? '#F97316' : '#F59E0B';
            html += '<div class="chat-risk-bar"><div class="chat-risk-fill" style="width:' + Math.min(ci.risk_score * 2.5, 100) + '%;background:' + rc + ';"></div></div>';
        }

        // Deps count
        if (depGraph[realId] && Array.isArray(depGraph[realId]) && depGraph[realId].length > 0) {
            html += '<div class="fs-11 text-secondary mt-1"><span class="material-symbols-outlined align-middle" style="font-size:12px;">subdirectory_arrow_right</span> ' + depGraph[realId].length + ' downstream dependent(s)</div>';
        }

        // Change type
        if (ci && ci.change_type) {
            html += '<div class="fs-10 mt-1"><code class="px-1 bg-primary bg-opacity-10 rounded">' + ci.change_type.replace(/_/g, ' ') + '</code>';
            if (ci.full_file_read) html += ' <span class="text-success fs-10">full code read</span>';
            html += '</div>';
        }

        // Quick action buttons — contextual to the clicked file
        html += '<div class="chat-quick-actions">';
        var safeFile = realId.replace(/"/g, '&quot;');
        html += '<button class="chat-quick-btn" data-query="Explain what ' + safeFile + ' does and why it matters"><span class="material-symbols-outlined" style="font-size:11px;">help</span> Explain this file</button>';
        html += '<button class="chat-quick-btn" data-query="What depends on ' + safeFile + '? Show the dependency chain"><span class="material-symbols-outlined" style="font-size:11px;">account_tree</span> Dependencies</button>';
        if (ci && ci.risk_score >= 10) {
            html += '<button class="chat-quick-btn" data-query="Why is ' + safeFile + ' risky? Break down the risk factors"><span class="material-symbols-outlined" style="font-size:11px;">warning</span> Why risky?</button>';
        }
        html += '<button class="chat-quick-btn" data-query="What services and endpoints are affected by changes to ' + safeFile + '?"><span class="material-symbols-outlined" style="font-size:11px;">hub</span> Impact</button>';
        html += '<button class="chat-quick-btn" data-file-preview="' + safeFile + '" style="border-color:#605DFF;"><span class="material-symbols-outlined" style="font-size:11px;">code</span> View code</button>';
        html += '</div>';

        html += '</div>';

        addBotMessage(html, true);
    }

    // Chat message helpers
    function addBotMessage(content, isHtml) {
        var el = document.createElement('div');
        el.className = 'chat-msg chat-bot';
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        if (isHtml) bubble.innerHTML = content;
        else bubble.textContent = content;
        el.appendChild(bubble);
        var container = document.getElementById('chatMessages');
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    function addUserMessage(text) {
        var el = document.createElement('div');
        el.className = 'chat-msg chat-user';
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = text;
        el.appendChild(bubble);
        var container = document.getElementById('chatMessages');
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    // Add AI source badge to bot messages
    function addConfidenceBadge(source) {
        var colors = { agent: '#10B981', openai: '#3B82F6', local: '#F59E0B' };
        var labels = { agent: 'Navigator Agent', openai: 'Azure OpenAI', local: 'Local Analysis' };
        var c = colors[source] || colors.local;
        var l = labels[source] || labels.local;
        return '<div class="chat-confidence"><span class="chat-confidence-dot" style="background:' + c + ';"></span> ' + l + '</div>';
    }

    // Export the chat conversation as markdown
    function exportChatConversation() {
        var msgs = document.querySelectorAll('#chatMessages .chat-msg');
        var md = '# DriftWatch Impact Chat — PR #' + @json($pullRequest->pr_number) + '\n';
        md += '_Exported: ' + new Date().toLocaleString() + '_\n\n---\n\n';
        msgs.forEach(function(msg) {
            var isUser = msg.classList.contains('chat-user');
            var bubble = msg.querySelector('.chat-bubble');
            if (!bubble) return;
            var text = bubble.innerText || bubble.textContent || '';
            if (text.trim()) {
                md += (isUser ? '**You**: ' : '**Navigator**: ') + text.trim() + '\n\n';
            }
        });

        var blob = new Blob([md], { type: 'text/markdown' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'driftwatch-chat-pr-' + @json($pullRequest->pr_number) + '.md';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    // === Inline Code Viewer ===
    function fetchFilePreview(filePath) {
        addUserMessage('Show me the code for ' + filePath.split('/').pop());

        var loadId = 'code-load-' + Date.now();
        addBotMessage('<div id="' + loadId + '" class="d-flex align-items-center gap-2"><div class="chat-typing-dots"><span></span><span></span><span></span></div><span class="fs-11 text-secondary ms-1">Fetching source code...</span></div>', true);

        fetch('/api/file-preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ pr_id: @json($pullRequest->id), file_path: filePath })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var loader = document.getElementById(loadId);
            if (loader) loader.closest('.chat-msg').remove();

            if (data.content) {
                renderCodeViewer(data);
            } else if (data.message) {
                addBotMessage('<div class="fs-11"><span class="material-symbols-outlined align-middle text-warning" style="font-size:12px;">info</span> ' + data.message + '</div>', true);
            } else {
                addBotMessage('<div class="fs-11 text-secondary">No source code available for this file. Set <code>GITHUB_TOKEN</code> in your environment to enable live code inspection.</div>', true);
            }
        })
        .catch(function(err) {
            var loader = document.getElementById(loadId);
            if (loader) loader.closest('.chat-msg').remove();
            addBotMessage('<div class="fs-11 text-warning">Failed to fetch code preview. Check your GitHub token configuration.</div>', true);
        });
    }

    function renderCodeViewer(data) {
        var shortName = data.file_path.split('/').pop();
        var hasDiff = data.diff && data.diff.length > 0;
        var ext = data.language || '';

        var html = '<div class="chat-code-viewer">';

        // Header with file name and tabs
        html += '<div class="chat-code-header">';
        html += '<span class="file-name" title="' + data.file_path.replace(/"/g, '&quot;') + '"><span class="material-symbols-outlined" style="font-size:12px;vertical-align:-2px;">code</span> ' + shortName + '</span>';
        html += '<div class="d-flex gap-1 align-items-center">';
        if (hasDiff) {
            html += '<button class="code-tab active" data-tab="diff">Diff</button>';
            html += '<button class="code-tab" data-tab="source">Source</button>';
        }
        html += '<button class="code-tab" data-expand-code="1" title="Full screen preview" style="margin-left:4px;"><span class="material-symbols-outlined" style="font-size:13px;vertical-align:-2px;">fullscreen</span></button>';
        html += '</div>';
        html += '</div>';

        // Code body — show diff by default if available, otherwise source
        if (hasDiff) {
            html += '<div class="chat-code-body" data-view="diff">' + formatDiff(data.diff) + '</div>';
            html += '<div class="chat-code-body" data-view="source" style="display:none;">' + formatSourceCode(data.content, ext) + '</div>';
        } else {
            html += '<div class="chat-code-body">' + formatSourceCode(data.content, ext) + '</div>';
        }

        // Stats bar
        html += '<div class="chat-code-stats">';
        html += '<span>' + ext.toUpperCase() + '</span>';
        if (data.size) html += '<span>' + formatFileSize(data.size) + '</span>';
        html += '<span>' + (data.content.split('\n').length) + ' lines</span>';
        if (data.source === 'github') html += '<span style="color:#10B981;">Live from GitHub</span>';
        else if (data.source === 'cached') html += '<span style="color:#F59E0B;">Cached from pipeline</span>';
        html += '</div>';

        html += '</div>';

        // Add ask-about-code suggestion
        html += '<div class="chat-quick-actions mt-1">';
        var sf = data.file_path.replace(/"/g, '&quot;');
        html += '<button class="chat-quick-btn" data-query="Analyze the code in ' + sf + ' — what are the key changes and potential issues?"><span class="material-symbols-outlined" style="font-size:11px;">analytics</span> Analyze code</button>';
        html += '<button class="chat-quick-btn" data-query="Are there any security concerns in the changes to ' + sf + '?"><span class="material-symbols-outlined" style="font-size:11px;">shield</span> Security check</button>';
        html += '</div>';

        addBotMessage(html, true);

        // Bind tab switching + expand button
        setTimeout(function() {
            var viewers = document.querySelectorAll('.chat-code-viewer');
            var lastViewer = viewers[viewers.length - 1];
            if (lastViewer) {
                lastViewer._codeData = data;
                lastViewer.querySelectorAll('.code-tab').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        if (tab.dataset.expandCode) {
                            openCodePreviewModal(lastViewer._codeData);
                            return;
                        }
                        lastViewer.querySelectorAll('.code-tab').forEach(function(t) { if (!t.dataset.expandCode) t.classList.remove('active'); });
                        tab.classList.add('active');
                        var view = tab.dataset.tab;
                        lastViewer.querySelectorAll('.chat-code-body').forEach(function(body) {
                            body.style.display = body.dataset.view === view ? '' : 'none';
                        });
                    });
                });
            }
        }, 100);
    }

    function formatDiff(diffText) {
        var lines = diffText.split('\n');
        var html = '';
        lines.forEach(function(line) {
            var escaped = escapeHtml(line);
            if (line.startsWith('@@')) {
                html += '<span class="diff-hdr">' + escaped + '</span>\n';
            } else if (line.startsWith('+') && !line.startsWith('+++')) {
                html += '<span class="diff-add">' + escaped + '</span>';
            } else if (line.startsWith('-') && !line.startsWith('---')) {
                html += '<span class="diff-del">' + escaped + '</span>';
            } else if (line.startsWith('diff --git')) {
                html += '<span class="diff-hdr">' + escaped + '</span>\n';
            } else {
                html += escaped + '\n';
            }
        });
        return html;
    }

    function formatSourceCode(content, ext) {
        var lines = content.split('\n');
        var html = '';
        var maxLines = Math.min(lines.length, 500); // Cap at 500 lines
        for (var i = 0; i < maxLines; i++) {
            html += '<span class="line-num">' + (i + 1) + '</span>' + escapeHtml(lines[i]) + '\n';
        }
        if (lines.length > 500) {
            html += '\n<span style="color:#f38ba8;">// ... ' + (lines.length - 500) + ' more lines truncated ...</span>\n';
        }
        return html;
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Process chat queries — calls the Navigator AI agent API, falls back to local matching
    var _prId = @json($pullRequest->id);

    function processChatQuery(query) {
        // Inject last-clicked file context for vague queries
        var q = query.toLowerCase().trim();
        if (_lastClickedFile && (q.match(/^(what does it|explain this|what is this|tell me about this|why is it|what does this|this file|explain it|what is it)/))) {
            query = query + ' (referring to: ' + _lastClickedFile + ')';
        }

        // Show typing indicator with animated dots
        var typingId = 'typing-' + Date.now();
        addBotMessage('<div id="' + typingId + '" class="d-flex align-items-center gap-2"><div class="chat-typing-dots"><span></span><span></span><span></span></div><span class="fs-11 text-secondary ms-1">Navigator is analyzing...</span></div>', true);

        // Disable send button while processing
        var sendBtn = document.getElementById('chatSendBtn');
        if (sendBtn) sendBtn.disabled = true;

        fetch('/api/impact-chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ pr_id: _prId, query: query })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            // Remove typing indicator
            var typing = document.getElementById(typingId);
            if (typing) typing.closest('.chat-msg').remove();

            // Render the AI response (supports markdown-ish formatting)
            var responseHtml = '<div class="fs-12">' + formatChatResponse(data.response || 'No response.') + '</div>';

            // Highlight nodes if the agent returned any
            var nodesToHighlight = data.highlight_nodes || [];
            if (nodesToHighlight.length > 0) {
                // Map file paths to dag node IDs (prefix with file_)
                var dagNodeIds = nodesToHighlight.map(function(f) { return 'file_' + f; }).filter(function(nid) {
                    return window._dagAddedNodes && window._dagAddedNodes[nid];
                });
                // Also try without prefix
                nodesToHighlight.forEach(function(f) {
                    if (window._dagAddedNodes && window._dagAddedNodes[f]) dagNodeIds.push(f);
                });
                if (dagNodeIds.length > 0) {
                    highlightMultipleNodes(dagNodeIds);
                    responseHtml += '<div class="fs-10 text-secondary mt-1"><span class="material-symbols-outlined align-middle" style="font-size:11px;">visibility</span> ' + dagNodeIds.length + ' node(s) highlighted in the tree</div>';
                }
            }

            // Add source confidence badge
            responseHtml += addConfidenceBadge(data.source || 'local');

            addBotMessage(responseHtml, true);

            // Render suggested follow-ups (use data-query + event delegation)
            var followups = data.suggested_followups || [];
            if (followups.length > 0) {
                var sugHtml = '<div class="chat-suggestions mt-1">';
                followups.forEach(function(f) {
                    sugHtml += '<button class="chat-suggest-btn" data-query="' + f.replace(/"/g, '&quot;') + '">' + f + '</button>';
                });
                sugHtml += '</div>';
                addBotMessage(sugHtml, true);
            }
        })
        .catch(function(err) {
            // Remove typing indicator
            var typing = document.getElementById(typingId);
            if (typing) typing.closest('.chat-msg').remove();

            // Fallback to local matching
            processLocalChatQuery(query);
        })
        .finally(function() {
            if (sendBtn) sendBtn.disabled = false;
        });
    }

    // Format AI response — basic markdown to HTML
    function formatChatResponse(text) {
        return text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n\n/g, '<br><br>')
            .replace(/\n/g, '<br>');
    }

    // Local fallback — keyword matching (used when API is unavailable)
    function processLocalChatQuery(query) {
        var q = query.toLowerCase().trim();
        var matches = [];
        var responseHtml = '';

        if (q.match(/highest risk|most dangerous|riskiest|critical|top risk/)) {
            var sorted = _chatSearchIndex.slice().sort(function(a, b) { return b.score - a.score; });
            matches = sorted.filter(function(n) { return n.score > 0; }).slice(0, 5);
            if (matches.length > 0) {
                responseHtml = '<div class="fw-medium fs-12 mb-2">Top ' + matches.length + ' highest risk items:</div>';
                matches.forEach(function(m) { responseHtml += buildChatFileCard(m); });
                highlightMultipleNodes(matches.map(function(m) { return m.nodeId; }));
            } else { responseHtml = 'No risk scores found in the current analysis.'; }
        }
        else if (q.match(/service|affected service|what service/)) {
            matches = _chatSearchIndex.filter(function(n) { return n.type === 'service'; });
            if (matches.length > 0) {
                responseHtml = '<div class="fw-medium fs-12 mb-2">' + matches.length + ' affected service(s):</div>';
                matches.forEach(function(m) { responseHtml += buildChatFileCard(m); });
                highlightMultipleNodes(matches.map(function(m) { return m.nodeId; }));
            } else { responseHtml = 'No services identified.'; }
        }
        else if (q.match(/depend|downstream|chain|import/)) {
            var withDeps = _chatSearchIndex.filter(function(n) {
                return depGraph[n.fullPath] && Array.isArray(depGraph[n.fullPath]) && depGraph[n.fullPath].length > 0;
            }).sort(function(a, b) { return (depGraph[b.fullPath] || []).length - (depGraph[a.fullPath] || []).length; });
            if (withDeps.length > 0) {
                responseHtml = '<div class="fw-medium fs-12 mb-2">Files with downstream dependencies:</div>';
                withDeps.slice(0, 5).forEach(function(m) {
                    var deps = depGraph[m.fullPath] || [];
                    responseHtml += buildChatFileCard(m, deps.length + ' dep(s): ' + deps.map(function(d) { return d.split('/').pop(); }).join(', '));
                });
                highlightMultipleNodes(withDeps.slice(0, 5).map(function(m) { return m.nodeId; }));
            } else { responseHtml = 'No dependency chains found.'; }
        }
        else if (q.match(/summary|overview|what changed|tell me|explain/)) {
            responseHtml = '<div class="fw-medium fs-12 mb-1">Impact Overview</div>';
            responseHtml += '<div class="fs-12">' + files.length + ' files changed across ' + services.length + ' service(s).</div>';
            if (blastSummary) responseHtml += '<div class="fs-12 mt-1">' + blastSummary + '</div>';
        }
        else {
            var words = q.split(/\s+/);
            matches = _chatSearchIndex.filter(function(n) {
                return words.some(function(w) { return w.length >= 2 && n.searchText.indexOf(w) >= 0; });
            }).slice(0, 8);
            if (matches.length > 0) {
                responseHtml = '<div class="fw-medium fs-12 mb-2">Found ' + matches.length + ' match(es):</div>';
                matches.forEach(function(m) { responseHtml += buildChatFileCard(m); });
                highlightMultipleNodes(matches.map(function(m) { return m.nodeId; }));
            } else {
                responseHtml = 'No matches found. Try asking about <strong>risk</strong>, <strong>services</strong>, <strong>dependencies</strong>, or search for a file name.';
            }
        }

        addBotMessage(responseHtml, true);
    }

    function buildChatFileCard(item, extraInfo) {
        var html = '<div class="chat-file-card" data-node-id="' + (item.nodeId || '').replace(/"/g, '&quot;') + '">';
        html += '<div class="d-flex align-items-center gap-2">';
        var iconMap = { file: 'description', dependency: 'call_split', service: 'dns', endpoint: 'api', pr: 'account_tree' };
        html += '<span class="material-symbols-outlined" style="font-size:14px;color:#605DFF;">' + (iconMap[item.type] || 'description') + '</span>';
        html += '<span class="fw-medium fs-11 text-truncate flex-grow-1">' + item.label + '</span>';
        if (item.score > 0) {
            var c = item.score >= 25 ? '#EF4444' : item.score >= 15 ? '#F97316' : '#F59E0B';
            html += '<span class="fs-10 fw-bold" style="color:' + c + ';">' + item.score + '</span>';
        }
        html += '</div>';
        if (item.reasoning) html += '<div class="fs-10 text-secondary mt-1 text-truncate">' + item.reasoning + '</div>';
        if (extraInfo) html += '<div class="fs-10 text-secondary mt-1">' + extraInfo + '</div>';
        if (item.score > 0) {
            var rc = item.score >= 25 ? '#EF4444' : item.score >= 15 ? '#F97316' : '#F59E0B';
            html += '<div class="chat-risk-bar mt-1"><div class="chat-risk-fill" style="width:' + Math.min(item.score * 2.5, 100) + '%;background:' + rc + ';"></div></div>';
        }
        html += '</div>';
        return html;
    }

    function sendChatFromInput() {
        var input = document.getElementById('chatInput');
        var text = input.value.trim();
        if (!text) return;
        input.value = '';
        addUserMessage(text);
        setTimeout(function() { processChatQuery(text); }, 150);
    }

    function sendChatQuery(text) {
        addUserMessage(text);
        setTimeout(function() { processChatQuery(text); }, 150);
    }

    function closeSidePanel() {
        document.getElementById('treeSidePanel').classList.remove('open');
        clearChatHighlights();
    }

    function clearChatHighlights() {
        var inner = window._dagInner;
        if (inner) {
            inner.selectAll('g.node').classed('node-dimmed', false).classed('node-highlighted', false);
            inner.selectAll('g.edgePath').classed('edge-dimmed', false).classed('edge-highlighted', false);
        }
    }

    // Highlight full path from PR origin to a specific node
    function highlightNodePath(targetNodeId) {
        var g = window._dagGraph;
        var inner = window._dagInner;
        if (!g || !inner) return;

        var ancestors = {};
        var queue = [targetNodeId];
        ancestors[targetNodeId] = true;
        while (queue.length > 0) {
            var current = queue.shift();
            (g.predecessors(current) || []).forEach(function(pred) {
                if (!ancestors[pred]) { ancestors[pred] = true; queue.push(pred); }
            });
        }
        var descendants = {};
        queue = [targetNodeId];
        descendants[targetNodeId] = true;
        while (queue.length > 0) {
            var cur = queue.shift();
            (g.successors(cur) || []).forEach(function(succ) {
                if (!descendants[succ]) { descendants[succ] = true; queue.push(succ); }
            });
        }
        var allRelevant = Object.assign({}, ancestors, descendants);

        inner.selectAll('g.node').classed('node-dimmed', true).classed('node-highlighted', false);
        inner.selectAll('g.edgePath').classed('edge-dimmed', true).classed('edge-highlighted', false);
        inner.selectAll('g.node').each(function(d) {
            if (allRelevant[d]) d3.select(this).classed('node-dimmed', false).classed('node-highlighted', true);
        });
        inner.selectAll('g.edgePath').each(function() {
            var ed = d3.select(this).datum();
            if (ed && allRelevant[ed.v] && allRelevant[ed.w]) d3.select(this).classed('edge-dimmed', false).classed('edge-highlighted', true);
        });
    }

    function highlightMultipleNodes(nodeIds) {
        var inner = window._dagInner;
        if (!inner) return;
        var matchSet = {};
        nodeIds.forEach(function(nid) { matchSet[nid] = true; });

        inner.selectAll('g.node').classed('node-dimmed', true).classed('node-highlighted', false);
        inner.selectAll('g.edgePath').classed('edge-dimmed', true).classed('edge-highlighted', false);
        inner.selectAll('g.node').each(function(d) {
            if (matchSet[d]) d3.select(this).classed('node-dimmed', false).classed('node-highlighted', true);
        });
        inner.selectAll('g.edgePath').each(function() {
            var ed = d3.select(this).datum();
            if (ed && (matchSet[ed.v] || matchSet[ed.w])) d3.select(this).classed('edge-dimmed', false).classed('edge-highlighted', true);
        });
    }

    // === Collapsible Impact Summary ===
    function toggleTreeSummary() {
        var body = document.getElementById('treeSummaryBody');
        var chevron = document.getElementById('treeSummaryChevron');
        body.classList.toggle('open');
        chevron.style.transform = body.classList.contains('open') ? 'rotate(180deg)' : '';
    }

    function initTreeSummary() {
        var totalDeps = 0;
        Object.keys(depGraph).forEach(function(k) { if (Array.isArray(depGraph[k])) totalDeps += depGraph[k].length; });
        document.getElementById('tsSummaryFiles').textContent = files.length;
        document.getElementById('tsSummaryServices').textContent = services.length;
        document.getElementById('tsSummaryDeps').textContent = totalDeps;
        document.getElementById('tsSummaryEndpoints').textContent = endpoints.length;
        document.getElementById('treeSummaryBadge').textContent = files.length + ' files, ' + services.length + ' services';

        var text = blastSummary || ('This PR modifies ' + files.length + ' file(s) affecting ' + services.length + ' service(s).' + (totalDeps > 0 ? ' ' + totalDeps + ' downstream dependencies.' : '') + (endpoints.length > 0 ? ' ' + endpoints.length + ' endpoint(s) exposed.' : ''));
        document.getElementById('tsSummaryText').textContent = text;

        var sorted = changeClassifications.slice().sort(function(a, b) { return (b.risk_score || 0) - (a.risk_score || 0); });
        var highRisk = sorted.filter(function(c) { return (c.risk_score || 0) >= 10; }).slice(0, 5);
        if (highRisk.length > 0) {
            var hrHtml = '';
            highRisk.forEach(function(c) {
                var score = c.risk_score || 0;
                var color = score >= 25 ? '#EF4444' : score >= 15 ? '#F97316' : '#F59E0B';
                hrHtml += '<div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-2" style="background:' + color + '10;border:1px solid ' + color + '30;cursor:pointer;" onclick="highlightNodePath(\'file_' + c.file + '\')">'
                    + '<span class="fw-bold fs-12" style="color:' + color + ';min-width:28px;">' + score + '</span>'
                    + '<div class="flex-grow-1 min-w-0"><div class="fw-medium fs-12 text-truncate">' + (c.file || '').split('/').pop() + '</div><div class="fs-11 text-secondary">' + (c.change_type || '').replace(/_/g, ' ') + '</div></div>'
                    + '<span class="material-symbols-outlined text-secondary" style="font-size:14px;">chevron_right</span></div>';
            });
            document.getElementById('tsSummaryHighRiskList').innerHTML = hrHtml;
            document.getElementById('tsSummaryHighRisk').style.display = 'block';
        }
    }

    function initTreeSearch() {
        buildChatSearchIndex();
        // Chat input — Enter to send
        var chatInput = document.getElementById('chatInput');
        if (chatInput) {
            chatInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); sendChatFromInput(); }
            });
        }
        // Send button — click to send
        var sendBtn = document.getElementById('chatSendBtn');
        if (sendBtn) {
            sendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sendChatFromInput();
            });
        }
        // Event delegation for all dynamic buttons in chat
        var chatContainer = document.getElementById('chatMessages');
        if (chatContainer) {
            chatContainer.addEventListener('click', function(e) {
                // Suggestion buttons (follow-ups)
                var sugBtn = e.target.closest('.chat-suggest-btn');
                if (sugBtn && sugBtn.dataset.query) {
                    e.preventDefault();
                    sendChatQuery(sugBtn.dataset.query);
                    return;
                }
                // Quick action buttons (on file cards)
                var quickBtn = e.target.closest('.chat-quick-btn');
                if (quickBtn) {
                    e.preventDefault();
                    // Check if it's a code preview button
                    if (quickBtn.dataset.filePreview) {
                        fetchFilePreview(quickBtn.dataset.filePreview);
                        return;
                    }
                    if (quickBtn.dataset.query) {
                        sendChatQuery(quickBtn.dataset.query);
                        return;
                    }
                }
                // File card clicks for highlighting
                var card = e.target.closest('.chat-file-card');
                if (card && card.dataset.nodeId) {
                    highlightNodePath(card.dataset.nodeId);
                }
            });
        }
        // Export conversation button
        var exportBtn = document.getElementById('chatExportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', exportChatConversation);
        }
        // Initialize speech-to-text if supported
        initSpeechToText();
    }

    // === Speech-to-Text (Web Speech API) ===
    var _speechRecognition = null;
    var _isRecording = false;

    function initSpeechToText() {
        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) return; // Browser doesn't support it

        var micBtn = document.getElementById('chatMicBtn');
        if (!micBtn) return;
        micBtn.style.display = ''; // Show the mic button

        _speechRecognition = new SpeechRecognition();
        _speechRecognition.continuous = false;
        _speechRecognition.interimResults = true;
        _speechRecognition.lang = 'en-US';

        _speechRecognition.onstart = function() {
            _isRecording = true;
            micBtn.classList.add('recording');
            micBtn.title = 'Listening... (click to stop)';
            document.getElementById('chatInput').placeholder = 'Listening...';
        };

        _speechRecognition.onresult = function(event) {
            var transcript = '';
            for (var i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            document.getElementById('chatInput').value = transcript;

            // If this is a final result, send it
            if (event.results[event.results.length - 1].isFinal) {
                stopSpeechRecognition();
                if (transcript.trim()) {
                    setTimeout(function() { sendChatFromInput(); }, 200);
                }
            }
        };

        _speechRecognition.onerror = function(event) {
            stopSpeechRecognition();
            if (event.error !== 'aborted' && event.error !== 'no-speech') {
                addBotMessage('<span class="fs-11 text-warning"><span class="material-symbols-outlined align-middle" style="font-size:12px;">warning</span> Voice input error: ' + event.error + '. Try typing instead.</span>', true);
            }
        };

        _speechRecognition.onend = function() {
            stopSpeechRecognition();
        };

        micBtn.addEventListener('click', function() {
            if (_isRecording) {
                _speechRecognition.stop();
            } else {
                try { _speechRecognition.start(); }
                catch(e) { /* already started */ }
            }
        });
    }

    function stopSpeechRecognition() {
        _isRecording = false;
        var micBtn = document.getElementById('chatMicBtn');
        if (micBtn) {
            micBtn.classList.remove('recording');
            micBtn.title = 'Voice input';
        }
        var input = document.getElementById('chatInput');
        if (input) input.placeholder = 'Ask about files, risk, dependencies...';
    }

    // === Panel Resize Handle ===
    (function() {
        var handle = document.getElementById('panelResizeHandle');
        var panel = document.getElementById('treeSidePanel');
        if (!handle || !panel) return;
        var startX, startW;
        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            startX = e.clientX;
            startW = panel.offsetWidth;
            handle.classList.add('dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', onUp);
        });
        function onDrag(e) {
            var diff = startX - e.clientX;
            var newW = Math.min(Math.max(startW + diff, 320), 800);
            panel.style.width = newW + 'px';
            panel.style.transition = 'none';
        }
        function onUp() {
            handle.classList.remove('dragging');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            panel.style.transition = '';
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', onUp);
        }
    })();

    // === Full-Screen Code Preview Modal ===
    var _cpmData = null;
    function openCodePreviewModal(data) {
        _cpmData = data;
        var overlay = document.getElementById('codePreviewOverlay');
        var shortName = data.file_path.split('/').pop();
        document.getElementById('cpmFileName').textContent = shortName;
        document.getElementById('cpmFilePath').textContent = data.file_path;

        var hasDiff = data.diff && data.diff.length > 0;
        var diffTab = document.getElementById('cpmDiffTab');
        var srcTab = document.getElementById('cpmSourceTab');
        diffTab.style.display = hasDiff ? '' : 'none';
        if (!hasDiff) {
            srcTab.classList.add('active');
            diffTab.classList.remove('active');
        } else {
            diffTab.classList.add('active');
            srcTab.classList.remove('active');
        }

        // Stats
        var statsHtml = '';
        if (data.language) statsHtml += '<span class="stat-badge">' + data.language.toUpperCase() + '</span>';
        if (data.size) statsHtml += '<span class="stat-badge">' + formatFileSize(data.size) + '</span>';
        var lines = data.content ? data.content.split('\n').length : 0;
        statsHtml += '<span class="stat-badge">' + lines + ' lines</span>';
        if (hasDiff) {
            var adds = (data.diff.match(/^\+[^+]/gm) || []).length;
            var dels = (data.diff.match(/^-[^-]/gm) || []).length;
            statsHtml += '<span class="stat-badge stat-add">+' + adds + '</span>';
            statsHtml += '<span class="stat-badge stat-del">-' + dels + '</span>';
        }
        if (data.source === 'github') statsHtml += '<span class="stat-badge" style="color:#a6e3a1;">Live from GitHub</span>';
        document.getElementById('cpmStats').innerHTML = statsHtml;

        // GitHub link
        var ghLink = document.getElementById('cpmGithubLink');
        @if($pullRequest->repo_full_name)
        ghLink.style.display = '';
        ghLink.onclick = function() { window.open('https://github.com/{{ $pullRequest->repo_full_name }}/blob/{{ $pullRequest->head_branch ?? "main" }}/' + data.file_path, '_blank'); };
        @endif

        // Render body
        renderCpmBody(hasDiff ? 'diff' : 'source');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function renderCpmBody(view) {
        var body = document.getElementById('cpmBody');
        if (!_cpmData) return;
        if (view === 'diff' && _cpmData.diff) {
            body.innerHTML = formatDiff(_cpmData.diff);
        } else {
            body.innerHTML = formatSourceCodeExpanded(_cpmData.content, _cpmData.language || '');
        }
    }

    function formatSourceCodeExpanded(content, lang) {
        if (!content) return '<span style="color:#6c7086;">No source code available</span>';
        var lines = content.split('\n');
        var html = '';
        for (var i = 0; i < lines.length; i++) {
            html += '<span class="line-num">' + (i + 1) + '</span>' + escapeHtml(lines[i]) + '\n';
        }
        return html;
    }

    function closeCodePreviewModal() {
        document.getElementById('codePreviewOverlay').classList.remove('show');
        document.body.style.overflow = '';
        _cpmData = null;
    }

    // Modal event bindings
    (function() {
        var overlay = document.getElementById('codePreviewOverlay');
        if (!overlay) return;

        document.getElementById('cpmCloseBtn').addEventListener('click', closeCodePreviewModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeCodePreviewModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('show')) closeCodePreviewModal();
        });

        document.getElementById('cpmDiffTab').addEventListener('click', function() {
            document.getElementById('cpmSourceTab').classList.remove('active');
            this.classList.add('active');
            renderCpmBody('diff');
        });
        document.getElementById('cpmSourceTab').addEventListener('click', function() {
            document.getElementById('cpmDiffTab').classList.remove('active');
            this.classList.add('active');
            renderCpmBody('source');
        });

        document.getElementById('cpmCopyBtn').addEventListener('click', function() {
            if (_cpmData && _cpmData.content) {
                navigator.clipboard.writeText(_cpmData.content).then(function() {
                    var btn = document.getElementById('cpmCopyBtn');
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">check</span> Copied!';
                    setTimeout(function() { btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">content_copy</span> Copy'; }, 2000);
                });
            }
        });
    })();

    // === Scroll Navigation Arrows (section-based) ===
    (function() {
        var nav = document.getElementById('scrollNav');
        var topBtn = document.getElementById('scrollToTopBtn');
        var bottomBtn = document.getElementById('scrollToBottomBtn');
        if (!nav) return;

        // Gather all major section cards on the page
        function getSections() {
            return Array.from(document.querySelectorAll('.dw-card, .dw-banner, .card.bg-white'));
        }

        var hideTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(hideTimeout);
            var scrollY = window.scrollY || window.pageYOffset;
            if (scrollY > 200) {
                nav.classList.add('visible');
            } else {
                nav.classList.remove('visible');
            }
            hideTimeout = setTimeout(function() {
                if ((window.scrollY || window.pageYOffset) < 200) nav.classList.remove('visible');
            }, 3000);
        });

        // Up arrow: scroll to previous section
        topBtn.addEventListener('click', function() {
            var sections = getSections();
            var scrollY = window.scrollY || window.pageYOffset;
            for (var i = sections.length - 1; i >= 0; i--) {
                var top = sections[i].getBoundingClientRect().top + scrollY - 80;
                if (top < scrollY - 10) {
                    window.scrollTo({ top: top, behavior: 'smooth' });
                    return;
                }
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Down arrow: scroll to next section
        bottomBtn.addEventListener('click', function() {
            var sections = getSections();
            var scrollY = window.scrollY || window.pageYOffset;
            for (var i = 0; i < sections.length; i++) {
                var top = sections[i].getBoundingClientRect().top + scrollY - 80;
                if (top > scrollY + 10) {
                    window.scrollTo({ top: top, behavior: 'smooth' });
                    return;
                }
            }
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        });
    })();

    // === Review Section Collapse Icon ===
    (function() {
        var body = document.getElementById('reviewCollapseBody');
        var icon = document.getElementById('reviewCollapseIcon');
        if (!body || !icon) return;
        body.addEventListener('hide.bs.collapse', function() { icon.style.transform = 'rotate(180deg)'; });
        body.addEventListener('show.bs.collapse', function() { icon.style.transform = 'rotate(0)'; });
    })();

    // === Full-screen Modal: Code Selection + Send to Chat ===
    var _cpmSelectedText = '';
    var _cpmOpenFiles = [];
    var _cpmActiveFileIndex = 0;

    (function() {
        var body = document.getElementById('cpmBody');
        var sendBtn = document.getElementById('cpmSendToChat');
        if (!body || !sendBtn) return;

        body.addEventListener('mouseup', function() {
            var sel = window.getSelection();
            var text = sel ? sel.toString().trim() : '';
            if (text.length > 5) {
                _cpmSelectedText = text;
                sendBtn.style.display = '';
            } else {
                _cpmSelectedText = '';
                sendBtn.style.display = 'none';
            }
        });

        sendBtn.addEventListener('click', function() {
            if (!_cpmSelectedText || !_cpmData) return;
            var fileName = _cpmData.file_path.split('/').pop();
            var attachHtml = '<div class="chat-code-attachment">';
            attachHtml += '<div class="att-label"><span class="material-symbols-outlined" style="font-size:12px;">attach_file</span> Code from ' + escapeHtml(fileName) + '</div>';
            attachHtml += '<pre>' + escapeHtml(_cpmSelectedText) + '</pre>';
            attachHtml += '</div>';
            addBotMessage(attachHtml, true);

            // Auto-query the AI about this code
            var query = 'Analyze this code snippet from ' + fileName + ':\n```\n' + _cpmSelectedText.substring(0, 500) + '\n```\nWhat are the potential issues or risks?';
            sendChatQuery(query);

            _cpmSelectedText = '';
            sendBtn.style.display = 'none';
            closeCodePreviewModal();
        });

        // Add File button
        var addFileBtn = document.getElementById('cpmAddFileBtn');
        var filePanel = document.getElementById('cpmFilePanel');
        var filePanelClose = document.getElementById('cpmFilePanelClose');
        var fileList = document.getElementById('cpmFileList');

        if (addFileBtn && filePanel) {
            addFileBtn.addEventListener('click', function() {
                filePanel.classList.toggle('open');
                if (filePanel.classList.contains('open')) renderFilePanel();
            });
            filePanelClose.addEventListener('click', function() { filePanel.classList.remove('open'); });
        }

        function renderFilePanel() {
            // Show all PR files from blast radius as options
            var files = @json($pullRequest->blastRadius?->affected_files ?? []);
            var html = '';

            // Show currently open files first
            _cpmOpenFiles.forEach(function(f, idx) {
                var name = (f.file_path || '').split('/').pop();
                html += '<div class="cpm-file-item ' + (idx === _cpmActiveFileIndex ? 'active' : '') + '" data-cpm-open-idx="' + idx + '">';
                html += '<span class="material-symbols-outlined" style="font-size:13px;">description</span>';
                html += '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(name) + '</span>';
                html += '</div>';
            });

            if (files.length > 0) {
                html += '<div style="padding:6px 14px 4px; font-size:10px; font-weight:600; text-transform:uppercase; color:#45475a; margin-top:6px;">PR Files</div>';
                files.forEach(function(file) {
                    var fp = file.file_path || file.path || file;
                    var name = fp.split('/').pop();
                    var alreadyOpen = _cpmOpenFiles.some(function(o) { return o.file_path === fp; });
                    if (alreadyOpen) return;
                    html += '<div class="cpm-file-item" data-cpm-load-file="' + escapeHtml(fp) + '">';
                    html += '<span class="material-symbols-outlined" style="font-size:13px;">add_circle_outline</span>';
                    html += '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(name) + '</span>';
                    html += '</div>';
                });
            }
            fileList.innerHTML = html;

            // Bind clicks
            fileList.querySelectorAll('[data-cpm-open-idx]').forEach(function(el) {
                el.addEventListener('click', function() {
                    var idx = parseInt(this.dataset.cpmOpenIdx);
                    switchCpmFile(idx);
                });
            });
            fileList.querySelectorAll('[data-cpm-load-file]').forEach(function(el) {
                el.addEventListener('click', function() {
                    var fp = this.dataset.cpmLoadFile;
                    loadFileIntoCpm(fp);
                });
            });
        }

        window.renderFilePanel = renderFilePanel;
    })();

    function switchCpmFile(idx) {
        if (idx >= 0 && idx < _cpmOpenFiles.length) {
            _cpmActiveFileIndex = idx;
            _cpmData = _cpmOpenFiles[idx];
            document.getElementById('cpmFileName').textContent = _cpmData.file_path.split('/').pop();
            document.getElementById('cpmFilePath').textContent = _cpmData.file_path;
            var hasDiff = _cpmData.diff && _cpmData.diff.length > 0;
            document.getElementById('cpmDiffTab').style.display = hasDiff ? '' : 'none';
            renderCpmBody(hasDiff ? 'diff' : 'source');
            if (typeof renderFilePanel === 'function') renderFilePanel();
        }
    }

    function loadFileIntoCpm(filePath) {
        fetch('/api/file-preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ pr_id: @json($pullRequest->id), file_path: filePath })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.content) {
                _cpmOpenFiles.push(data);
                switchCpmFile(_cpmOpenFiles.length - 1);
            }
        });
    }

    // Track open files when opening modal
    var _origOpenCodePreviewModal = openCodePreviewModal;
    openCodePreviewModal = function(data) {
        if (!_cpmOpenFiles.some(function(f) { return f.file_path === data.file_path; })) {
            _cpmOpenFiles.push(data);
        }
        _cpmActiveFileIndex = _cpmOpenFiles.findIndex(function(f) { return f.file_path === data.file_path; });
        _origOpenCodePreviewModal(data);
    };

    var _origCloseCodePreviewModal = closeCodePreviewModal;
    closeCodePreviewModal = function() {
        _cpmOpenFiles = [];
        _cpmActiveFileIndex = 0;
        document.getElementById('cpmFilePanel').classList.remove('open');
        _origCloseCodePreviewModal();
    };

    // === Text-to-Speech (Azure Speech) ===
    var _ttsAudio = null;
    var _ttsActiveBtn = null;

    // Extract only meaningful text from a card — skip icons, badges, buttons, metadata
    function extractImportantText(el) {
        var clone = el.cloneNode(true);
        // Remove elements that aren't useful to read aloud
        clone.querySelectorAll('button, .btn, .badge, .material-symbols-outlined, code, .form-control, .form-check, input, select, .progress, svg, canvas, .chat-quick-actions, .dw-tts-btn, .chat-tts-btn, .fs-10, style, script').forEach(function(e) { e.remove(); });
        var text = clone.innerText || clone.textContent || '';
        // Clean up: remove icon text residue, excessive whitespace
        text = text.replace(/volume_up|volume_off|expand_more|expand_less|content_copy/g, '')
                   .replace(/\s{3,}/g, '. ')
                   .replace(/\n{2,}/g, '. ')
                   .trim()
                   .substring(0, 3000);
        return text;
    }

    function initTtsButtons() {
        document.querySelectorAll('.dw-section-title').forEach(function(title) {
            if (title.querySelector('.dw-tts-btn')) return;
            var btn = document.createElement('button');
            btn.className = 'dw-tts-btn';
            btn.type = 'button';
            btn.title = 'Listen to this section';
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">volume_up</span>';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var card = title.closest('.card-body') || title.closest('.card');
                if (!card) return;
                var text = extractImportantText(card);
                speakText(text, btn);
            });
            title.appendChild(btn);
        });

        // Also add TTS to chat messages
        addTtsToChatMessages();
    }

    // Add TTS speaker icons to chat bot messages
    function addTtsToChatMessages() {
        document.querySelectorAll('.chat-msg.chat-bot .chat-bubble').forEach(function(bubble) {
            if (bubble.querySelector('.chat-tts-btn')) return;
            var text = (bubble.innerText || '').trim();
            if (text.length < 20) return;
            var btn = document.createElement('button');
            btn.className = 'chat-tts-btn';
            btn.type = 'button';
            btn.title = 'Listen';
            btn.style.cssText = 'position:absolute;top:4px;right:4px;background:none;border:none;cursor:pointer;opacity:0.4;transition:opacity 0.2s;padding:2px;line-height:1;';
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;color:#605DFF;">volume_up</span>';
            btn.addEventListener('mouseenter', function() { btn.style.opacity = '1'; });
            btn.addEventListener('mouseleave', function() { if (!btn.classList.contains('playing')) btn.style.opacity = '0.4'; });
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                speakText(extractImportantText(bubble), btn);
            });
            bubble.style.position = 'relative';
            bubble.appendChild(btn);
        });
    }

    // Watch for new chat messages and add TTS buttons
    var _chatMsgEl = document.getElementById('chatMessages');
    if (_chatMsgEl) {
        new MutationObserver(function() { setTimeout(addTtsToChatMessages, 100); }).observe(_chatMsgEl, { childList: true, subtree: true });
    }

    function speakText(text, btn) {
        // If already playing, stop
        if (_ttsAudio && _ttsActiveBtn === btn) {
            _ttsAudio.pause();
            _ttsAudio = null;
            btn.classList.remove('playing');
            btn.querySelector('.material-symbols-outlined').textContent = 'volume_up';
            _ttsActiveBtn = null;
            return;
        }
        // Stop any other playing audio
        if (_ttsAudio) {
            _ttsAudio.pause();
            if (_ttsActiveBtn) {
                _ttsActiveBtn.classList.remove('playing');
                _ttsActiveBtn.querySelector('.material-symbols-outlined').textContent = 'volume_up';
            }
        }

        btn.classList.add('playing');
        btn.querySelector('.material-symbols-outlined').textContent = 'volume_off';
        _ttsActiveBtn = btn;

        fetch('/api/tts', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'audio/mpeg', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ text: text })
        })
        .then(function(res) {
            if (!res.ok) throw new Error('TTS failed');
            return res.blob();
        })
        .then(function(blob) {
            var url = URL.createObjectURL(blob);
            _ttsAudio = new Audio(url);
            _ttsAudio.play();
            _ttsAudio.onended = function() {
                btn.classList.remove('playing');
                btn.querySelector('.material-symbols-outlined').textContent = 'volume_up';
                _ttsAudio = null;
                _ttsActiveBtn = null;
                URL.revokeObjectURL(url);
            };
        })
        .catch(function(err) {
            btn.classList.remove('playing');
            btn.querySelector('.material-symbols-outlined').textContent = 'volume_up';
            _ttsAudio = null;
            _ttsActiveBtn = null;
            console.warn('TTS unavailable:', err);
        });
    }

    // Initialize TTS buttons after page load
    setTimeout(initTtsButtons, 500);

    // === Full-screen Modal: Minimizable/Dockable Mode ===
    var _cpmDocked = false;
    (function() {
        var overlay = document.getElementById('codePreviewOverlay');
        var modal = overlay ? overlay.querySelector('.code-preview-modal') : null;
        if (!overlay || !modal) return;

        // Add minimize button to header action bar
        var actionBar = modal.querySelector('.cpm-action-bar');
        if (actionBar) {
            var minBtn = document.createElement('button');
            minBtn.className = 'cpm-action';
            minBtn.id = 'cpmMinimizeBtn';
            minBtn.title = 'Dock to side — keep chatting';
            minBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">picture_in_picture_alt</span> Dock';
            actionBar.insertBefore(minBtn, actionBar.querySelector('#cpmCopyBtn'));

            minBtn.addEventListener('click', function() {
                if (_cpmDocked) {
                    // Undock — go full screen again
                    overlay.style.cssText = '';
                    modal.style.cssText = 'display:flex;flex-direction:row;';
                    overlay.classList.add('show');
                    document.body.style.overflow = 'hidden';
                    minBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">picture_in_picture_alt</span> Dock';
                    _cpmDocked = false;
                } else {
                    // Dock to LEFT side (keeps chat visible on right)
                    overlay.style.cssText = 'display:flex !important; position:fixed; top:0; left:0; bottom:0; right:auto; width:50vw; max-width:700px; background:none; z-index:999; pointer-events:auto;';
                    modal.style.cssText = 'display:flex; flex-direction:row; width:100%; height:100%; border-radius:0; box-shadow:4px 0 24px rgba(0,0,0,0.3);';
                    document.body.style.overflow = '';
                    minBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">fullscreen</span> Full';
                    _cpmDocked = true;
                }
            });
        }

        // === Split View Button ===
        if (actionBar) {
            var splitBtn = document.createElement('button');
            splitBtn.className = 'cpm-action';
            splitBtn.id = 'cpmSplitBtn';
            splitBtn.title = 'Split view — compare files side by side';
            splitBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">vertical_split</span> Split';
            actionBar.insertBefore(splitBtn, actionBar.querySelector('#cpmCopyBtn'));

            var _splitMode = false;
            splitBtn.addEventListener('click', function() {
                var codeBody = modal.querySelector('#cpmCodeBody');
                if (!codeBody) return;

                if (_splitMode) {
                    // Exit split mode
                    var rightPane = document.getElementById('cpmSplitRight');
                    if (rightPane) rightPane.remove();
                    codeBody.style.width = '';
                    codeBody.style.flex = '';
                    splitBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">vertical_split</span> Split';
                    _splitMode = false;
                } else {
                    // Enter split mode — add second pane
                    codeBody.style.flex = '1';
                    codeBody.style.width = '50%';
                    codeBody.style.minWidth = '0';

                    var rightPane = document.createElement('div');
                    rightPane.id = 'cpmSplitRight';
                    rightPane.style.cssText = 'flex:1; width:50%; min-width:0; border-left:2px solid #313244; display:flex; flex-direction:column; background:#1e1e2e;';

                    // Header with file selector
                    var rpHeader = document.createElement('div');
                    rpHeader.style.cssText = 'padding:8px 12px; background:#181825; border-bottom:1px solid #313244; display:flex; align-items:center; gap:8px; flex-shrink:0;';
                    rpHeader.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;color:#cdd6f4;">description</span>'
                        + '<select id="cpmSplitFileSelect" style="flex:1;background:#313244;color:#cdd6f4;border:1px solid #45475a;border-radius:6px;padding:4px 8px;font-size:12px;">'
                        + '<option value="">Select a file to compare...</option>'
                        + '</select>'
                        + '<button id="cpmSplitToggleDiff" class="cpm-action" style="font-size:11px;" title="Toggle diff/source">Diff</button>';
                    rightPane.appendChild(rpHeader);

                    var rpBody = document.createElement('div');
                    rpBody.id = 'cpmSplitBody';
                    rpBody.style.cssText = 'flex:1; overflow:auto; padding:0; font-family:monospace; font-size:13px;';
                    rpBody.innerHTML = '<div style="padding:40px;text-align:center;color:#6c7086;"><span class="material-symbols-outlined" style="font-size:32px;">compare_arrows</span><p class="mt-2">Select a file from the dropdown to compare side by side</p></div>';
                    rightPane.appendChild(rpBody);

                    // Insert after codeBody
                    codeBody.parentNode.insertBefore(rightPane, codeBody.nextSibling);

                    // Populate file select with open files + all blast radius files
                    var sel = document.getElementById('cpmSplitFileSelect');
                    if (_cpmOpenFiles && _cpmOpenFiles.length > 0) {
                        var grp1 = document.createElement('optgroup');
                        grp1.label = 'Open Files';
                        _cpmOpenFiles.forEach(function(f, idx) {
                            var opt = document.createElement('option');
                            opt.value = 'open:' + idx;
                            opt.textContent = f.file_path.split('/').pop();
                            grp1.appendChild(opt);
                        });
                        sel.appendChild(grp1);
                    }
                    // Add blast radius files
                    if (typeof files !== 'undefined' && files.length > 0) {
                        var grp2 = document.createElement('optgroup');
                        grp2.label = 'PR Files';
                        files.forEach(function(f) {
                            var opt = document.createElement('option');
                            opt.value = 'fetch:' + f;
                            opt.textContent = f.split('/').pop();
                            grp2.appendChild(opt);
                        });
                        sel.appendChild(grp2);
                    }

                    // File select handler
                    sel.addEventListener('change', function() {
                        var val = sel.value;
                        if (!val) return;
                        var body = document.getElementById('cpmSplitBody');
                        if (val.startsWith('open:')) {
                            var idx = parseInt(val.split(':')[1]);
                            var fData = _cpmOpenFiles[idx];
                            if (fData) renderSplitPane(body, fData);
                        } else if (val.startsWith('fetch:')) {
                            var fPath = val.substring(6);
                            body.innerHTML = '<div style="padding:20px;text-align:center;color:#6c7086;"><div class="chat-typing-dots"><span></span><span></span><span></span></div> Loading...</div>';
                            fetch('/api/file-preview', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                                body: JSON.stringify({ pr_id: @json($pullRequest->id), file_path: fPath })
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(d) {
                                if (d.content) {
                                    renderSplitPane(body, d);
                                } else {
                                    body.innerHTML = '<div style="padding:20px;text-align:center;color:#f38ba8;">File content not available.</div>';
                                }
                            })
                            .catch(function() {
                                body.innerHTML = '<div style="padding:20px;text-align:center;color:#f38ba8;">Failed to fetch file.</div>';
                            });
                        }
                    });

                    // Diff/Source toggle for split pane
                    var diffToggle = document.getElementById('cpmSplitToggleDiff');
                    var _splitShowDiff = true;
                    if (diffToggle) {
                        diffToggle.addEventListener('click', function() {
                            _splitShowDiff = !_splitShowDiff;
                            diffToggle.textContent = _splitShowDiff ? 'Diff' : 'Source';
                            var selVal = sel.value;
                            if (selVal) sel.dispatchEvent(new Event('change'));
                        });
                    }

                    splitBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">close</span> Close Split';
                    _splitMode = true;
                }
            });

            function renderSplitPane(container, data) {
                var hasDiff = data.diff && data.diff.length > 0;
                var showDiff = document.getElementById('cpmSplitToggleDiff')?.textContent === 'Diff' && hasDiff;
                var content = showDiff ? data.diff : (data.content || '');
                var lines = content.split('\n');
                var html = '<table style="width:100%;border-collapse:collapse;font-size:12px;line-height:1.6;">';
                lines.forEach(function(line, i) {
                    var bg = 'transparent';
                    var color = '#cdd6f4';
                    if (showDiff) {
                        if (line.startsWith('+') && !line.startsWith('+++')) { bg = 'rgba(166,227,161,0.1)'; color = '#a6e3a1'; }
                        else if (line.startsWith('-') && !line.startsWith('---')) { bg = 'rgba(243,139,168,0.1)'; color = '#f38ba8'; }
                        else if (line.startsWith('@@')) { bg = 'rgba(137,180,250,0.08)'; color = '#89b4fa'; }
                    }
                    html += '<tr style="background:' + bg + ';"><td style="padding:0 8px;color:#585b70;text-align:right;user-select:none;width:40px;font-size:11px;">' + (i + 1) + '</td>';
                    html += '<td style="padding:0 8px;color:' + color + ';white-space:pre-wrap;word-break:break-all;">' + escapeHtml(line) + '</td></tr>';
                });
                html += '</table>';
                container.innerHTML = html;
                container.scrollTop = 0;
            }

            function escapeHtml(s) {
                return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
        }
    })();

    // Override close to also handle docked state
    var _origClose2 = closeCodePreviewModal;
    closeCodePreviewModal = function() {
        _cpmDocked = false;
        var overlay = document.getElementById('codePreviewOverlay');
        var modal = overlay ? overlay.querySelector('.code-preview-modal') : null;
        if (overlay) overlay.style.cssText = '';
        if (modal) modal.style.cssText = '';
        _origClose2();
    };

    // === Full-screen Modal: Edit Mode ===
    var _cpmEditMode = false;
    (function() {
        var actionBar = document.querySelector('.code-preview-modal .cpm-action-bar');
        if (!actionBar) return;

        var editBtn = document.createElement('button');
        editBtn.className = 'cpm-action';
        editBtn.id = 'cpmEditBtn';
        editBtn.title = 'Toggle edit mode';
        editBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">edit</span> Edit';
        editBtn.style.display = 'none'; // Hidden by default, shown if user is author
        actionBar.insertBefore(editBtn, actionBar.querySelector('#cpmCopyBtn'));

        // Show edit button if current user is the PR author
        var prAuthor = @json($pullRequest->pr_author ?? '');
        if (prAuthor) {
            editBtn.style.display = '';
        }

        editBtn.addEventListener('click', function() {
            if (!_cpmEditMode) {
                if (!confirm('Enable edit mode? Changes will be staged for review before submitting to GitHub.')) return;
                _cpmEditMode = true;
                var body = document.getElementById('cpmBody');
                body.contentEditable = 'true';
                body.style.outline = '2px solid #F97316';
                body.style.outlineOffset = '-2px';
                editBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;color:#F97316;">edit_off</span> Exit Edit';
                // Add save button
                if (!document.getElementById('cpmSaveBtn')) {
                    var saveBtn = document.createElement('button');
                    saveBtn.className = 'cpm-action';
                    saveBtn.id = 'cpmSaveBtn';
                    saveBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;color:#10B981;">save</span> Save Draft';
                    saveBtn.addEventListener('click', function() {
                        alert('Draft saved locally. Submit via GitHub to apply changes to the PR.');
                        _cpmEditMode = false;
                        body.contentEditable = 'false';
                        body.style.outline = '';
                        body.style.outlineOffset = '';
                        editBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">edit</span> Edit';
                        saveBtn.remove();
                    });
                    editBtn.parentNode.insertBefore(saveBtn, editBtn.nextSibling);
                }
            } else {
                _cpmEditMode = false;
                var body = document.getElementById('cpmBody');
                body.contentEditable = 'false';
                body.style.outline = '';
                body.style.outlineOffset = '';
                editBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">edit</span> Edit';
                var saveBtn = document.getElementById('cpmSaveBtn');
                if (saveBtn) saveBtn.remove();
            }
        });
    })();

    // === Blast Map — animated concentric radius visualization (lazy loaded) ===
    window._graphNodeMeta = {};

    window._initVisGraph = function() {
        var svg = document.getElementById('blastRadiusSvg');
        var container = document.getElementById('blastRadiusDynamic');
        if (!svg || !container) return;

        var W = container.clientWidth || 900;
        var H = container.clientHeight || 650;
        var cx = W / 2, cy = H / 2;
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);

        var meta = {};
        var nodeElements = [];

        // File type badge helper
        function fileTypeBadge(filePath) {
            var name = filePath.split('/').pop().toLowerCase();
            if (name.includes('test') || name.includes('spec')) return '<span class="badge bg-info bg-opacity-10 text-info fs-11">Test</span>';
            if (name.includes('controller')) return '<span class="badge bg-danger bg-opacity-10 text-danger fs-11">Controller</span>';
            if (name.includes('model') || name.includes('schema')) return '<span class="badge bg-warning bg-opacity-10 text-warning fs-11">Model</span>';
            if (name.includes('route') || name.includes('router')) return '<span class="badge bg-primary bg-opacity-10 text-primary fs-11">Routes</span>';
            if (name.includes('middleware')) return '<span class="badge bg-secondary bg-opacity-10 text-secondary fs-11">Middleware</span>';
            if (name.includes('migration')) return '<span class="badge bg-danger bg-opacity-10 text-danger fs-11">Migration</span>';
            if (name.includes('service') || name.includes('provider')) return '<span class="badge bg-success bg-opacity-10 text-success fs-11">Service</span>';
            return '<span class="badge bg-secondary bg-opacity-10 text-secondary fs-11">File</span>';
        }

        // Radius rings
        var rings = [
            { r: 90, label: 'CHANGED', color: '#EF4444', opacity: 0.3 },
            { r: 170, label: 'AFFECTED', color: '#F59E0B', opacity: 0.2 },
            { r: 250, label: 'SERVICES', color: '#3B82F6', opacity: 0.15 },
            { r: 310, label: 'ENDPOINTS', color: '#06B6D4', opacity: 0.1 }
        ];

        // Draw static concentric rings
        rings.forEach(function(ring, i) {
            // Filled glow ring
            var glow = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            glow.setAttribute('cx', cx); glow.setAttribute('cy', cy); glow.setAttribute('r', ring.r);
            glow.setAttribute('fill', 'url(#pulseGrad' + (i + 1) + ')');
            glow.style.animation = 'blastPulse ' + (3 + i * 0.5) + 's ease-in-out infinite';
            glow.style.animationDelay = (i * 0.3) + 's';
            svg.appendChild(glow);

            // Dashed ring
            var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', cx); circle.setAttribute('cy', cy); circle.setAttribute('r', ring.r);
            circle.setAttribute('class', 'blast-ring-static');
            circle.setAttribute('stroke', ring.color); circle.setAttribute('stroke-opacity', Math.min(ring.opacity * 2.5, 0.7));
            circle.setAttribute('stroke-width', '1.5');
            svg.appendChild(circle);

            // Ring label (positioned at top of ring)
            var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', cx); text.setAttribute('y', cy - ring.r - 6);
            text.setAttribute('class', 'blast-ring-label');
            text.setAttribute('fill', ring.color); text.setAttribute('fill-opacity', '0.8');
            text.textContent = ring.label;
            svg.appendChild(text);
        });

        // Animated expanding pulse rings (repeating)
        for (var p = 0; p < 3; p++) {
            var pulse = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            pulse.setAttribute('cx', cx); pulse.setAttribute('cy', cy); pulse.setAttribute('r', '0');
            pulse.setAttribute('fill', 'none'); pulse.setAttribute('stroke', '#605DFF'); pulse.setAttribute('stroke-opacity', '0.4');
            pulse.style.animation = 'blastRingExpand 4s ease-out infinite';
            pulse.style.animationDelay = (p * 1.33) + 's';
            svg.appendChild(pulse);
        }

        // Create SVG group for connections (drawn below nodes)
        var connGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        svg.appendChild(connGroup);

        // Create SVG group for nodes (drawn above connections)
        var nodeGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        svg.appendChild(nodeGroup);

        var nodeIdx = 0;

        // Helper: place a node as SVG circle + label
        function addNode(x, y, radius, color, label, id, metaObj, delay) {
            // Connection line from center
            var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', cx); line.setAttribute('y1', cy);
            line.setAttribute('x2', x); line.setAttribute('y2', y);
            line.setAttribute('stroke', color); line.setAttribute('stroke-opacity', '0.15');
            line.setAttribute('stroke-width', '1'); line.setAttribute('class', 'blast-connection');
            line.setAttribute('stroke-dasharray', '3 5');
            connGroup.appendChild(line);

            var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.setAttribute('class', 'blast-node');
            g.setAttribute('data-id', id);
            g.style.animation = 'blastNodeAppear 0.5s ease-out forwards';
            g.style.animationDelay = delay + 's';
            g.style.opacity = '0';

            var c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', x); c.setAttribute('cy', y); c.setAttribute('r', radius);
            c.setAttribute('fill', color); c.setAttribute('filter', 'url(#glow)');
            c.setAttribute('stroke', 'rgba(255,255,255,0.2)'); c.setAttribute('stroke-width', '1');
            g.appendChild(c);

            // Outer glow ring
            var glowRing = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            glowRing.setAttribute('cx', x); glowRing.setAttribute('cy', y); glowRing.setAttribute('r', radius + 4);
            glowRing.setAttribute('fill', 'none'); glowRing.setAttribute('stroke', color); glowRing.setAttribute('stroke-opacity', '0.3');
            glowRing.setAttribute('stroke-width', '1');
            glowRing.style.animation = 'blastPulse ' + (2 + Math.random()) + 's ease-in-out infinite';
            g.appendChild(glowRing);

            var t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            t.setAttribute('x', x); t.setAttribute('y', y + radius + 14);
            t.setAttribute('class', 'blast-node-label');
            t.textContent = label.length > 18 ? label.substring(0, 15) + '...' : label;
            g.appendChild(t);

            nodeGroup.appendChild(g);
            meta[id] = metaObj;
            nodeElements.push({ el: g, id: id, x: x, y: y, line: line });

            // Hover + click
            g.addEventListener('mouseenter', function(e) { showBlastHover(id, e); });
            g.addEventListener('mouseleave', function() { hideBlastHover(); });
            g.addEventListener('click', function() { showBlastDetail(id); });
        }

        // Distribute items evenly around a ring
        function ringPositions(count, ringR, offsetAngle) {
            var positions = [];
            var startAngle = offsetAngle || -Math.PI / 2;
            for (var i = 0; i < count; i++) {
                var angle = startAngle + (2 * Math.PI / count) * i;
                positions.push({ x: cx + ringR * Math.cos(angle), y: cy + ringR * Math.sin(angle) });
            }
            return positions;
        }

        // === CENTER — PR Node ===
        var prG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        prG.setAttribute('class', 'blast-node');
        prG.style.animation = 'blastNodeAppear 0.6s ease-out forwards';

        var prCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        prCircle.setAttribute('cx', cx); prCircle.setAttribute('cy', cy); prCircle.setAttribute('r', 32);
        prCircle.setAttribute('fill', '#605DFF'); prCircle.setAttribute('filter', 'url(#glowStrong)');
        prCircle.setAttribute('stroke', 'rgba(255,255,255,0.3)'); prCircle.setAttribute('stroke-width', '2');
        prG.appendChild(prCircle);

        var prGlow = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        prGlow.setAttribute('cx', cx); prGlow.setAttribute('cy', cy); prGlow.setAttribute('r', 40);
        prGlow.setAttribute('fill', 'none'); prGlow.setAttribute('stroke', '#605DFF'); prGlow.setAttribute('stroke-opacity', '0.4');
        prGlow.setAttribute('stroke-width', '2');
        prGlow.style.animation = 'blastPulse 2s ease-in-out infinite';
        prG.appendChild(prGlow);

        var prLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        prLabel.setAttribute('x', cx); prLabel.setAttribute('y', cy);
        prLabel.setAttribute('text-anchor', 'middle'); prLabel.setAttribute('dominant-baseline', 'central');
        prLabel.setAttribute('fill', '#fff'); prLabel.setAttribute('font-size', '13'); prLabel.setAttribute('font-weight', 'bold');
        prLabel.textContent = 'PR #{{ $pullRequest->pr_number }}';
        prG.appendChild(prLabel);
        nodeGroup.appendChild(prG);

        meta['pr'] = { category: 'pr', type: 'Pull Request', name: 'PR #{{ $pullRequest->pr_number }}',
            html: '<p class="mb-1">' + (blastSummary || 'Impact analysis for this pull request') + '</p>'
                + '<div class="d-flex gap-2 flex-wrap"><span class="badge" style="background:rgba(239,68,68,0.15);color:#EF4444;">' + depKeys.length + ' changed</span>'
                + '<span class="badge" style="background:rgba(245,158,11,0.15);color:#F59E0B;">' + files.length + ' total files</span>'
                + '<span class="badge" style="background:rgba(59,130,246,0.15);color:#3B82F6;">' + services.length + ' services</span></div>' };
        prG.addEventListener('mouseenter', function(e) { showBlastHover('pr', e); });
        prG.addEventListener('mouseleave', function() { hideBlastHover(); });

        // === RING 1 — Changed Files (red, r=90) ===
        var changedPos = ringPositions(depKeys.length, rings[0].r, -Math.PI / 2);
        depKeys.forEach(function(f, i) {
            var depCount = (depGraph[f] && Array.isArray(depGraph[f])) ? depGraph[f].length : 0;
            var size = Math.max(8, Math.min(18, 8 + depCount * 3));
            var pos = changedPos[i] || { x: cx, y: cy - rings[0].r };
            addNode(pos.x, pos.y, size, '#EF4444', f.split('/').pop(), 'changed_' + i, {
                category: 'changed', type: 'Changed File', name: f.split('/').pop(),
                html: '<div style="color:#94a3b8;" class="fs-12 mb-2">' + f + '</div>' + fileTypeBadge(f)
                    + ' Directly changed in this PR.'
                    + (depCount > 0 ? ' <strong>' + depCount + '</strong> downstream dependencies.' : ''),
                deps: depCount > 0 ? depGraph[f].map(function(d) { return d.split('/').pop(); }) : []
            }, 0.2 + i * 0.08);
        });

        // === RING 2 — Affected Files (amber, r=170) ===
        var affectedFiles = files.filter(function(f) { return !sourceFiles[f]; });
        var affectedPos = ringPositions(affectedFiles.length, rings[1].r, 0);
        affectedFiles.forEach(function(f, i) {
            var pos = affectedPos[i] || { x: cx + rings[1].r, y: cy };
            addNode(pos.x, pos.y, 7, '#F59E0B', f.split('/').pop(), 'affected_' + i, {
                category: 'affected', type: 'Affected File', name: f.split('/').pop(),
                html: '<div style="color:#94a3b8;" class="fs-12 mb-2">' + f + '</div>' + fileTypeBadge(f)
                    + ' In the blast radius — may be affected by upstream changes.'
            }, 0.5 + i * 0.06);
        });

        // === RING 3 — Services (blue, r=250) ===
        var svcPos = ringPositions(services.length, rings[2].r, -Math.PI / 4);
        services.forEach(function(s, i) {
            var pos = svcPos[i] || { x: cx + rings[2].r, y: cy };
            addNode(pos.x, pos.y, 12, '#3B82F6', s, 'svc_' + i, {
                category: 'service', type: 'Service', name: s,
                html: '<p class="mb-0">Service <strong style="color:#60a5fa;">' + s + '</strong> is in the blast radius. Monitor error rates and latency after deployment.</p>'
            }, 0.8 + i * 0.1);
        });

        // === RING 4 — Endpoints (cyan, r=310) ===
        var epPos = ringPositions(endpoints.length, rings[3].r, Math.PI / 6);
        endpoints.forEach(function(e, i) {
            var pos = epPos[i] || { x: cx + rings[3].r, y: cy };
            addNode(pos.x, pos.y, 6, '#06B6D4', e.length > 20 ? e.substring(0, 17) + '...' : e, 'ep_' + i, {
                category: 'endpoint', type: 'API Endpoint', name: e,
                html: '<p class="mb-0">Endpoint <code style="color:#22d3ee;">' + e + '</code> is exposed to changes. Verify backwards compatibility.</p>'
            }, 1.0 + i * 0.08);
        });

        // Draw dependency connections (changed → affected)
        depKeys.forEach(function(srcFile, srcIdx) {
            if (!Array.isArray(depGraph[srcFile])) return;
            depGraph[srcFile].forEach(function(dep) {
                var depIdx = affectedFiles.indexOf(dep);
                if (depIdx < 0) return;
                var from = changedPos[srcIdx], to = affectedPos[depIdx];
                if (!from || !to) return;
                var depLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                depLine.setAttribute('x1', from.x); depLine.setAttribute('y1', from.y);
                depLine.setAttribute('x2', to.x); depLine.setAttribute('y2', to.y);
                depLine.setAttribute('stroke', '#F59E0B'); depLine.setAttribute('stroke-opacity', '0.2');
                depLine.setAttribute('stroke-width', '1'); depLine.setAttribute('stroke-dasharray', '2 4');
                connGroup.appendChild(depLine);
            });
        });

        // === Update stats overlay ===
        var elChanged = document.getElementById('bmStatChanged');
        var elAffected = document.getElementById('bmStatAffected');
        var elServices = document.getElementById('bmStatServices');
        var elEndpoints = document.getElementById('bmStatEndpoints');
        var elRisk = document.getElementById('bmRiskScore');
        if (elChanged) elChanged.textContent = depKeys.length + ' changed';
        if (elAffected) elAffected.textContent = affectedFiles.length + ' affected';
        if (elServices) elServices.textContent = services.length + ' services';
        if (elEndpoints) elEndpoints.textContent = endpoints.length + ' endpoints';
        if (elRisk) {
            var score = {{ $pullRequest->blastRadiusResult->total_score ?? $pullRequest->blastRadiusResult->blast_radius_score ?? 0 }};
            elRisk.textContent = score + ' pts';
            if (score > 60) elRisk.style.color = '#EF4444';
            else if (score > 30) elRisk.style.color = '#F59E0B';
            else elRisk.style.color = '#10B981';
        }

        window._graphNodeMeta = meta;

        // === Hover card ===
        var hoverCard = document.getElementById('graphHoverCard');
        function showBlastHover(id, e) {
            var m = meta[id]; if (!m || !hoverCard) return;
            var colors = { pr: '#605DFF', changed: '#EF4444', affected: '#F59E0B', service: '#3B82F6', endpoint: '#06B6D4' };
            var c = colors[m.category] || '#605DFF';
            document.getElementById('hoverBadge').innerHTML = '<span class="badge px-2 py-1 fs-11" style="background:' + c + '30; color:' + c + ';">' + m.type + '</span>';
            document.getElementById('hoverTitle').textContent = m.name;
            document.getElementById('hoverPath').textContent = '';
            document.getElementById('hoverDescription').innerHTML = m.html || '';
            var hDeps = document.getElementById('hoverDeps');
            if (m.deps && m.deps.length > 0) {
                hDeps.innerHTML = '<div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:8px;margin-top:4px;"><span class="fs-11 fw-bold text-uppercase" style="color:#64748b;">Impacts:</span> '
                    + m.deps.slice(0, 6).map(function(d) { return '<code class="fs-11 px-1 rounded" style="background:rgba(245,158,11,0.15);color:#FBBF24;">' + d + '</code>'; }).join(' ')
                    + (m.deps.length > 6 ? ' <span class="fs-11" style="color:#64748b;">+' + (m.deps.length - 6) + ' more</span>' : '') + '</div>';
            } else { hDeps.innerHTML = ''; }
            var rect = container.getBoundingClientRect();
            var left = (e.clientX || e.pageX) - rect.left + 15;
            var top = (e.clientY || e.pageY) - rect.top - 20;
            if (left + 340 > rect.width) left = left - 370;
            if (left < 10) left = 10;
            if (top < 10) top = 10;
            hoverCard.style.left = left + 'px';
            hoverCard.style.top = top + 'px';
            hoverCard.style.display = 'block';

            // Dim other nodes
            nodeElements.forEach(function(n) {
                if (n.id !== id) { n.el.style.opacity = '0.2'; n.line.style.opacity = '0.05'; }
                else { n.el.style.opacity = '1'; n.line.setAttribute('stroke-opacity', '0.5'); }
            });
        }
        function hideBlastHover() {
            if (hoverCard) hoverCard.style.display = 'none';
            nodeElements.forEach(function(n) { n.el.style.opacity = '1'; n.line.setAttribute('stroke-opacity', '0.15'); });
        }

        // Click — show in detail panel below
        function showBlastDetail(id) {
            var m = meta[id]; if (!m) return;
            var panel = document.getElementById('blastInfoPanel');
            var header = document.getElementById('blastInfoHeader');
            var body = document.getElementById('blastInfoBody');
            if (!panel) return;
            panel.style.display = 'block';
            panel.style.background = '#0f172a'; panel.style.border = '1px solid rgba(96,93,255,0.3)';
            var colors = { pr: 'primary', changed: 'danger', affected: 'warning', service: 'primary', endpoint: 'info' };
            var bc = colors[m.category] || 'primary';
            header.style.background = '#1e293b';
            header.innerHTML = '<span class="badge bg-' + bc + ' bg-opacity-10 text-' + bc + '">' + m.type + '</span><span class="fw-bold flex-grow-1 text-white">' + m.name + '</span>';
            var bodyHtml = m.html || '';
            if (m.deps && m.deps.length > 0) {
                bodyHtml += '<div class="mt-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.1);"><span class="fw-bold fs-12 text-white">Downstream:</span><div class="d-flex flex-wrap gap-1 mt-1">'
                    + m.deps.map(function(d) { return '<code class="fs-11 px-1 rounded" style="background:rgba(245,158,11,0.15);color:#FBBF24;">' + d + '</code>'; }).join('')
                    + '</div></div>';
            }
            body.innerHTML = '<div class="fs-13" style="color:#cbd5e1;">' + bodyHtml + '</div>';
        }
    };

    // === Legend filter — toggle node categories (SVG-based) ===
    window.filterGraphType = function(btn, category) {
        btn.classList.toggle('dimmed');
        var isDimmed = btn.classList.contains('dimmed');
        var meta = window._graphNodeMeta;
        if (!meta) return;
        document.querySelectorAll('#blastRadiusSvg .blast-node').forEach(function(el) {
            var id = el.getAttribute('data-id');
            if (id && meta[id] && meta[id].category === category) {
                el.style.display = isDimmed ? 'none' : '';
            }
        });
    };

    // === DevOps Action Items ===
    (function() {
        var actionEl = document.getElementById('devopsActionItems');
        if (actionEl) {
            var items =[];
            var changedCount = depKeys.length;
            var downstreamTotal = 0;
            depKeys.forEach(function(k) { if (Array.isArray(depGraph[k])) downstreamTotal += depGraph[k].length });

            if (changedCount > 0) {
                var hasConfigFiles = depKeys.some(function(f) { return f.match(/\.(json|yaml|yml|env|toml|ini|xml)$/i) || f.includes('config'); });
                var hasMigrations = depKeys.some(function(f) { return f.includes('migration') || f.includes('schema') || f.includes('.sql'); });
                var hasApiFiles = depKeys.some(function(f) { return f.includes('controller') || f.includes('route') || f.includes('api') || f.includes('endpoint'); });

                items.push({
                    icon: 'code_blocks', color: 'danger', priority: 'Critical',
                    title: 'Review ' + changedCount + ' changed file' + (changedCount > 1 ? 's' : ''),
                    desc: 'Check for breaking API changes, removed exports, or changed function signatures.',
                    files: depKeys.map(function(f) { return f.split('/').pop(); })
                });

                if (hasConfigFiles) {
                    items.push({ icon: 'settings', color: 'danger', priority: 'Critical', title: 'Verify configuration changes', desc: 'Ensure environment variables are set in all deployment targets.' });
                }
                if (hasMigrations) {
                    items.push({ icon: 'storage', color: 'danger', priority: 'Critical', title: 'Test database migrations', desc: 'Run migrations on staging first. Ensure rollback exists.' });
                }
                if (hasApiFiles) {
                    items.push({ icon: 'api', color: 'warning', priority: 'High', title: 'API contract validation', desc: 'Verify request/response schemas are backwards compatible.' });
                }
            }

            if (downstreamTotal > 0) {
                items.push({
                    icon: 'bug_report', color: 'warning', priority: downstreamTotal > 2 ? 'High' : 'Medium',
                    title: 'Verify ' + downstreamTotal + ' downstream dep' + (downstreamTotal > 1 ? 's' : ''),
                    desc: 'These files import from changed code. Run integration tests covering these paths.'
                });
            }

            if (services.length > 0) {
                items.push({
                    icon: 'monitor_heart', color: 'primary', priority: 'Medium',
                    title: 'Monitor ' + services.join(', '),
                    desc: 'Watch error rates and latency after deploy. Have rollback ready.'
                });
            }

            if (files.length + downstreamTotal > 10) {
                items.push({
                    icon: 'rocket_launch', color: 'warning', priority: 'Recommended',
                    title: 'Use canary deployment',
                    desc: 'Wide blast radius (' + (files.length + downstreamTotal) + ' files). Deploy to 5% first, then gradually increase.'
                });
            }

            var html = '<div class="row g-3">';
            items.forEach(function(item) {
                html += '<div class="col-md-6"><div class="d-flex gap-3 p-3 border rounded-3 h-100">'
                    + '<div class="flex-shrink-0"><div class="wh-36 bg-' + item.color + ' bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"><span class="material-symbols-outlined text-' + item.color + '" style="font-size:18px;">' + item.icon + '</span></div></div>'
                    + '<div><div class="d-flex align-items-center gap-2 mb-1"><span class="fw-bold fs-13">' + item.title + '</span><span class="badge bg-' + item.color + ' bg-opacity-10 text-' + item.color + ' fs-11">' + item.priority + '</span></div>'
                    + '<p class="text-secondary fs-12 mb-0">' + item.desc + '</p>';
                if (item.files) {
                    html += '<div class="mt-1 d-flex flex-wrap gap-1">';
                    item.files.slice(0, 6).forEach(function(f) { html += '<code class="fs-11 px-1 bg-light rounded">' + f + '</code>'; });
                    if (item.files.length > 6) html += '<span class="fs-11 text-secondary">+' + (item.files.length - 6) + ' more</span>';
                    html += '</div>';
                }
                html += '</div></div></div>';
            });
            html += '</div>';
            actionEl.innerHTML = html;
        }
    })();

    // === What to Review — AI-powered file-level checklist ===
    (function() {
        var el = document.getElementById('reviewChecklist');
        if (!el) return;

        // Build review items sorted by risk score (highest first)
        var reviewItems =[];
        var prDiffUrl = prUrl ? prUrl + '/files' : '';

        // Changed files — primary review targets
        files.forEach(function(f) {
            var fname = (typeof f === 'string') ? f : (f.filename || f);
            var desc = fileDescMap[fname] || null;
            var classInfo = changeClassMap[fname] || null;
            var score = classInfo ? classInfo.risk_score : 5;
            var deps = (depGraph[fname] && Array.isArray(depGraph[fname])) ? depGraph[fname] :[];

            reviewItems.push({
                file: fname,
                shortName: fname.split('/').pop(),
                score: score,
                isChanged: true,
                summary: desc ? desc.summary : null,
                role: desc ? desc.role : getFileType(fname),
                risk: desc ? desc.risk : (classInfo ? classInfo.reasoning : null),
                affects: desc ? desc.affects : null,
                changeType: classInfo ? (classInfo.change_type || '').replace(/_/g, ' ') : null,
                deps: deps,
                icon: getFileIcon(fname)
            });
        });

        // Also include dep graph source files not in files array
        depKeys.forEach(function(f) {
            if (!reviewItems.some(function(r) { return r.file === f; })) {
                var desc = fileDescMap[f] || null;
                var classInfo = changeClassMap[f] || null;
                var score = classInfo ? classInfo.risk_score : 5;
                var deps = (depGraph[f] && Array.isArray(depGraph[f])) ? depGraph[f] :[];

                reviewItems.push({
                    file: f,
                    shortName: f.split('/').pop(),
                    score: score,
                    isChanged: true,
                    summary: desc ? desc.summary : null,
                    role: desc ? desc.role : getFileType(f),
                    risk: desc ? desc.risk : (classInfo ? classInfo.reasoning : null),
                    affects: desc ? desc.affects : null,
                    changeType: classInfo ? (classInfo.change_type || '').replace(/_/g, ' ') : null,
                    deps: deps,
                    icon: getFileIcon(f)
                });
            }
        });

        // Sort by score descending
        reviewItems.sort(function(a, b) { return b.score - a.score; });

        if (reviewItems.length === 0) {
            el.innerHTML = '<p class="text-secondary fs-13">No files to review.</p>';
            return;
        }

        var html = '';

        // Priority legend
        html += '<div class="d-flex gap-3 mb-3 flex-wrap">';
        html += '<span class="d-flex align-items-center gap-1 fs-12"><span style="width:10px;height:10px;border-radius:50%;background:#EF4444;display:inline-block;"></span> Critical (20+ pts)</span>';
        html += '<span class="d-flex align-items-center gap-1 fs-12"><span style="width:10px;height:10px;border-radius:50%;background:#F97316;display:inline-block;"></span> High (15-19 pts)</span>';
        html += '<span class="d-flex align-items-center gap-1 fs-12"><span style="width:10px;height:10px;border-radius:50%;background:#F59E0B;display:inline-block;"></span> Medium (5-14 pts)</span>';
        html += '<span class="d-flex align-items-center gap-1 fs-12"><span style="width:10px;height:10px;border-radius:50%;background:#10B981;display:inline-block;"></span> Low (&lt;5 pts)</span>';
        html += '</div>';

        reviewItems.forEach(function(item, idx) {
            var priorityColor = item.score >= 20 ? '#EF4444' : item.score >= 15 ? '#F97316' : item.score >= 5 ? '#F59E0B' : '#10B981';
            var priorityLabel = item.score >= 20 ? 'Critical' : item.score >= 15 ? 'High' : item.score >= 5 ? 'Medium' : 'Low';
            var filterKey = item.score >= 20 ? 'critical' : item.score >= 15 ? 'high' : item.score >= 5 ? 'medium' : 'low';
            var diffLink = prDiffUrl ? prDiffUrl + '#diff-' + btoa(item.file).replace(/=/g, '') : '';
            var sourceLink = repoFullName ? 'https://github.com/' + repoFullName + '/blob/HEAD/' + item.file : '';

            html += '<div class="review-item d-flex gap-3 p-3 mb-2 border rounded-3" data-priority="' + filterKey + '" data-file="' + item.file.toLowerCase() + '" data-name="' + item.shortName.toLowerCase() + '">';

            // Priority indicator
            html += '<div class="flex-shrink-0 d-flex flex-column align-items-center" style="min-width:44px;">';
            html += '<div style="width:36px;height:36px;border-radius:50%;background:' + priorityColor + '15;display:flex;align-items:center;justify-content:center;">';
            html += '<span class="material-symbols-outlined" style="font-size:18px;color:' + priorityColor + ';">' + item.icon + '</span>';
            html += '</div>';
            html += '<span class="fs-11 fw-bold mt-1" style="color:' + priorityColor + ';">' + item.score + 'pts</span>';
            html += '</div>';

            // Content
            html += '<div class="flex-grow-1 min-w-0">';

            // File name + badge row
            html += '<div class="d-flex align-items-center gap-2 mb-1 flex-wrap">';
            html += '<span class="fw-bold fs-13">' + item.shortName + '</span>';
            html += '<span class="badge fs-11" style="background:' + priorityColor + '20;color:' + priorityColor + ';">' + priorityLabel + '</span>';
            if (item.changeType) {
                html += '<code class="fs-11 px-1 bg-primary bg-opacity-10 rounded">' + item.changeType + '</code>';
            }
            if (item.role) {
                html += '<span class="text-secondary fs-11">' + item.role + '</span>';
            }
            html += '</div>';

            // File path
            html += '<div class="text-secondary fs-11 mb-1 text-truncate" style="font-family:monospace;">' + item.file + '</div>';

            // Downstream deps compact
            if (item.deps.length > 0) {
                html += '<div class="d-flex flex-wrap gap-1 mt-1">';
                html += '<span class="fs-11 text-secondary fw-medium">Downstream:</span> ';
                item.deps.slice(0, 4).forEach(function(d) {
                    var dShort = d.split('/').pop();
                    html += '<span class="fs-11 px-1 bg-warning bg-opacity-10 rounded">' + dShort + '</span>';
                });
                if (item.deps.length > 4) html += '<span class="fs-11 text-secondary">+' + (item.deps.length - 4) + ' more</span>';
                html += '</div>';
            }

            // Links row
            html += '<div class="d-flex gap-3 mt-2">';
            if (diffLink) {
                html += '<a href="' + diffLink + '" target="_blank" class="review-file-link d-flex align-items-center gap-1 fs-12">'
                    + '<span class="material-symbols-outlined" style="font-size:14px;">difference</span> View diff</a>';
            }
            if (sourceLink) {
                html += '<a href="' + sourceLink + '" target="_blank" class="review-file-link d-flex align-items-center gap-1 fs-12">'
                    + '<span class="material-symbols-outlined" style="font-size:14px;">code</span> View source</a>';
            }
            html += '</div>';

            html += '</div>'; // flex-grow-1

            // Hover tooltip — "Why review this?"
            html += '<div class="review-tooltip">';
            html += '<div class="fw-bold fs-12 mb-2 d-flex align-items-center gap-1"><span class="material-symbols-outlined" style="font-size:14px;color:' + priorityColor + ';">info</span> Why review this?</div>';
            // Risk bar
            html += '<div class="d-flex align-items-center gap-2 mb-2">';
            html += '<span class="fs-11 text-secondary" style="min-width:60px;">Risk Score</span>';
            html += '<div style="flex:1;height:6px;border-radius:3px;background:#f1f5f9;overflow:hidden;"><div style="height:100%;width:' + Math.min(item.score * 2.5, 100) + '%;background:' + priorityColor + ';border-radius:3px;"></div></div>';
            html += '<span class="fs-11 fw-bold" style="color:' + priorityColor + ';">' + item.score + '</span>';
            html += '</div>';
            if (item.risk) {
                html += '<div class="fs-11 mb-2"><span class="material-symbols-outlined align-middle" style="font-size:12px;color:' + priorityColor + ';">warning</span> ' + item.risk + '</div>';
            }
            if (item.summary) {
                html += '<div class="fs-11 text-secondary mb-2">' + item.summary + '</div>';
            }
            if (item.affects && !item.affects.includes('No known downstream')) {
                html += '<div class="fs-11"><span class="material-symbols-outlined align-middle text-info" style="font-size:12px;">call_split</span> ' + item.affects + '</div>';
            }
            if (item.deps.length > 0) {
                html += '<div class="fs-11 mt-1 text-secondary">' + item.deps.length + ' downstream dependenc' + (item.deps.length === 1 ? 'y' : 'ies') + '</div>';
            }
            html += '</div>';

            html += '</div>'; // review-item
        });

        el.innerHTML = html;

        // === Filter & search logic ===
        var filterBtns = document.querySelectorAll('#reviewFilterBtns button');
        var searchInput = document.getElementById('reviewFilterSearch');
        var emptyMsg = document.getElementById('reviewEmpty');
        var currentFilter = 'all';

        function applyReviewFilter() {
            var searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();
            var items = el.querySelectorAll('.review-item');
            var visible = 0;
            items.forEach(function(item) {
                var matchPriority = currentFilter === 'all' || item.dataset.priority === currentFilter;
                var matchSearch = !searchTerm || item.dataset.file.includes(searchTerm) || item.dataset.name.includes(searchTerm);
                if (matchPriority && matchSearch) {
                    item.style.display = '';
                    visible++;
                } else {
                    item.style.display = 'none';
                }
            });
            if (emptyMsg) emptyMsg.style.display = visible === 0 ? '' : 'none';
        }

        filterBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                filterBtns.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                currentFilter = btn.dataset.filter;
                applyReviewFilter();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', applyReviewFilter);
        }
    })();

    // === Mermaid Structural Flow — grouped by directory ===
    (function() {
        var m = 'flowchart TD\n';
        var safe = function(s) { return s.replace(/[^a-zA-Z0-9_]/g, '_'); };

        // PR origin
        m += '    PR["<strong>PR #{{ $pullRequest->pr_number }}</strong><br/><small>{{ $pullRequest->files_changed }} files changed</small>"]\n';
        m += '    style PR fill:#605DFF,color:#fff,stroke:#4b49cc,stroke-width:3px,rx:8\n';

        // Services
        if (services.length > 0) {
            m += '    subgraph SVC["Affected Services"]\n';
            m += '        direction TB\n';
            services.forEach(function(s, i) {
                m += '        S' + i + '["' + s + '"]\n';
                m += '        style S' + i + ' fill:#3B82F6,color:#fff,stroke:#2563EB,rx:6\n';
            });
            m += '    end\n';
            m += '    style SVC fill:#EFF6FF,stroke:#3B82F6,stroke-width:2px,rx:10\n';
            services.forEach(function(s, i) { m += '    PR ==>|impacts| S' + i + '\n'; });
        }

        // Group changed files by directory
        var changedByDir = {};
        var changedFileIdx = {};
        depKeys.forEach(function(f, globalIdx) {
            var parts = f.split('/');
            var dir = parts.length > 1 ? parts.slice(0, -1).join('/') : 'root';
            if (!changedByDir[dir]) changedByDir[dir] =[];
            changedByDir[dir].push(f);
            changedFileIdx[f] = 'CF' + globalIdx;
        });

        var dirIdx = 0;
        Object.keys(changedByDir).sort().forEach(function(dir) {
            var dirFiles = changedByDir[dir];
            var subId = 'CDIR' + dirIdx++;
            var dirLabel = dir.split('/').pop() || dir;
            m += '    subgraph ' + subId + '["' + dirLabel + '/ (' + dirFiles.length + ' changed)"]\n';
            m += '        direction TB\n';
            dirFiles.forEach(function(f) {
                var nodeId = changedFileIdx[f];
                var name = f.split('/').pop();
                var depCount = (depGraph[f] && Array.isArray(depGraph[f])) ? depGraph[f].length : 0;
                var label = depCount > 0 ? name + '<br/><small>' + depCount + ' deps</small>' : name;
                m += '        ' + nodeId + '["' + label + '"]\n';
                m += '        style ' + nodeId + ' fill:#EF4444,color:#fff,stroke:#DC2626,rx:6\n';
            });
            m += '    end\n';
            m += '    style ' + subId + ' fill:#FEF2F2,stroke:#EF4444,stroke-width:2px,rx:10\n';
            dirFiles.forEach(function(f) { m += '    PR -->|changes| ' + changedFileIdx[f] + '\n'; });
        });

        // Group affected files by directory
        var affectedByDir = {};
        var affectedIdx = 0;
        var affectedFileIdx = {};
        files.forEach(function(f) {
            if (sourceFiles[f]) return;
            var parts = f.split('/');
            var dir = parts.length > 1 ? parts.slice(0, -1).join('/') : 'root';
            if (!affectedByDir[dir]) affectedByDir[dir] =[];
            affectedByDir[dir].push(f);
            affectedFileIdx[f] = 'AF' + affectedIdx++;
        });

        if (Object.keys(affectedByDir).length > 0) {
            var aDirIdx = 0;
            Object.keys(affectedByDir).sort().forEach(function(dir) {
                var dirFiles = affectedByDir[dir];
                // If too many files in one dir, summarize
                if (dirFiles.length > 6) {
                    var summaryId = 'ASUMM' + aDirIdx;
                    m += '    ' + summaryId + '["' + dir.split('/').pop() + '/<br/><small>' + dirFiles.length + ' affected files</small>"]\n';
                    m += '    style ' + summaryId + ' fill:#F59E0B,color:#fff,stroke:#D97706,rx:6\n';
                    m += '    PR -.->|affects| ' + summaryId + '\n';
                } else {
                    var subId = 'ADIR' + aDirIdx;
                    m += '    subgraph ' + subId + '["' + dir.split('/').pop() + '/ (' + dirFiles.length + ' affected)"]\n';
                    m += '        direction TB\n';
                    dirFiles.forEach(function(f) {
                        var nodeId = affectedFileIdx[f];
                        m += '        ' + nodeId + '["' + f.split('/').pop() + '"]\n';
                        m += '        style ' + nodeId + ' fill:#F59E0B,color:#fff,stroke:#D97706,rx:6\n';
                    });
                    m += '    end\n';
                    m += '    style ' + subId + ' fill:#FFFBEB,stroke:#F59E0B,stroke-width:2px,rx:10\n';
                    // Connect to source files
                    dirFiles.forEach(function(f) {
                        var connected = false;
                        depKeys.forEach(function(src) {
                            if (!connected && Array.isArray(depGraph[src]) && depGraph[src].indexOf(f) >= 0) {
                                m += '    ' + changedFileIdx[src] + ' -.->|affects| ' + affectedFileIdx[f] + '\n';
                                connected = true;
                            }
                        });
                        if (!connected) m += '    PR -.->|affects| ' + affectedFileIdx[f] + '\n';
                    });
                }
                aDirIdx++;
            });
        }

        // Downstream deps — grouped, summarized for readability
        var downstreamByDir = {};
        var dIdx = 0;
        depKeys.forEach(function(srcFile) {
            if (!Array.isArray(depGraph[srcFile])) return;
            depGraph[srcFile].forEach(function(dep) {
                if (files.indexOf(dep) >= 0) return;
                var parts = dep.split('/');
                var dir = parts.length > 1 ? parts.slice(0, -1).join('/') : 'root';
                if (!downstreamByDir[dir]) downstreamByDir[dir] = { files:[], sources: {} };
                if (downstreamByDir[dir].files.indexOf(dep) < 0) downstreamByDir[dir].files.push(dep);
                downstreamByDir[dir].sources[srcFile] = true;
            });
        });

        if (Object.keys(downstreamByDir).length > 0) {
            var dsIdx = 0;
            Object.keys(downstreamByDir).sort().forEach(function(dir) {
                var group = downstreamByDir[dir];
                var nodeId = 'DS' + dsIdx++;
                var dirLabel = dir.split('/').pop() || dir;
                if (group.files.length > 3) {
                    m += '    ' + nodeId + '(["' + dirLabel + '/<br/><small>' + group.files.length + ' downstream</small>"])\n';
                } else {
                    m += '    ' + nodeId + '(["' + group.files.map(function(f) { return f.split('/').pop(); }).join(', ') + '"])\n';
                }
                m += '    style ' + nodeId + ' fill:#FBBF24,color:#1e293b,stroke:#F59E0B,rx:20\n';
                Object.keys(group.sources).forEach(function(src) {
                    if (changedFileIdx[src]) m += '    ' + changedFileIdx[src] + ' -.->|"may break"| ' + nodeId + '\n';
                });
            });
        }

        // Endpoints
        if (endpoints.length > 0) {
            m += '    subgraph EP["Exposed Endpoints"]\n';
            m += '        direction TB\n';
            endpoints.forEach(function(e, i) {
                var label = e.length > 30 ? e.substring(0, 28) + '..' : e;
                m += '        E' + i + '["' + label + '"]\n';
                m += '        style E' + i + ' fill:#06B6D4,color:#fff,stroke:#0891B2,rx:6\n';
            });
            m += '    end\n';
            m += '    style EP fill:#ECFEFF,stroke:#06B6D4,stroke-width:2px,rx:10\n';
            endpoints.forEach(function(e, i) { m += '    PR -.->|exposes| E' + i + '\n'; });
        }

        var mermaidEl = document.getElementById('blastMermaidDiagram');
        if (mermaidEl) mermaidEl.textContent = m;
    })();
    @endif

    // === Impact Treemap ===
    (function() {
        var treemapEl = document.getElementById('impactTreemap');
        if (!treemapEl) return;

        var treemapData =[];
        // Changed files — base weight + downstream deps
        depKeys.forEach(function(f) {
            var depCount = (depGraph[f] && Array.isArray(depGraph[f])) ? depGraph[f].length : 0;
            var weight = 3 + depCount * 2;
            treemapData.push({ x: f.split('/').pop(), y: weight, fillColor: '#EF4444',
                meta: { path: f, type: 'Changed', deps: depCount, desc: describeFile(f, true) } });
        });
        // Affected files (not source)
        files.forEach(function(f) {
            if (sourceFiles[f]) return;
            treemapData.push({ x: f.split('/').pop(), y: 2, fillColor: '#F59E0B',
                meta: { path: f, type: 'Affected', deps: 0, desc: describeFile(f, false) } });
        });
        // Downstream deps not in files array
        depKeys.forEach(function(srcFile) {
            if (!Array.isArray(depGraph[srcFile])) return;
            depGraph[srcFile].forEach(function(dep) {
                if (files.indexOf(dep) >= 0) return;
                treemapData.push({ x: dep.split('/').pop(), y: 1, fillColor: '#FBBF24',
                    meta: { path: dep, type: 'Downstream', deps: 0, desc: 'Depends on ' + srcFile.split('/').pop() } });
            });
        });
        // Services
        services.forEach(function(s) {
            treemapData.push({ x: s, y: 4, fillColor: '#3B82F6',
                meta: { path: s, type: 'Service', deps: 0, desc: 'Affected service' } });
        });
        // Endpoints
        endpoints.forEach(function(e) {
            treemapData.push({ x: e, y: 2, fillColor: '#06B6D4',
                meta: { path: e, type: 'Endpoint', deps: 0, desc: 'Exposed API endpoint' } });
        });

        if (treemapData.length === 0) return;

        var treemap = new ApexCharts(treemapEl, {
            series: [{ data: treemapData }],
            chart: { type: 'treemap', height: 320, fontFamily: 'inherit', toolbar: { show: false },
                animations: { enabled: true, speed: 800 } },
            plotOptions: { treemap: { distributed: true, enableShades: false,
                colorScale: { ranges:[
                    { from: 0, to: 2, color: '#FBBF24', name: 'Low Impact' },
                    { from: 3, to: 5, color: '#F59E0B', name: 'Medium Impact' },
                    { from: 6, to: 100, color: '#EF4444', name: 'High Impact' }
                ] }
            } },
            tooltip: { custom: function(opts) {
                var d = opts.w.config.series[0].data[opts.dataPointIndex];
                var m = d.meta || {};
                return '<div style="padding:10px 14px;font-size:12px;max-width:280px;line-height:1.5;">'
                    + '<div style="font-weight:700;margin-bottom:4px;">' + d.x + '</div>'
                    + '<div style="color:#64748b;margin-bottom:2px;">' + (m.path || '') + '</div>'
                    + '<div><span style="color:' + d.fillColor + ';font-weight:600;">' + (m.type || '') + '</span>'
                    + (m.deps > 0 ? ' — ' + m.deps + ' downstream deps' : '') + '</div>'
                    + '<div style="color:#94a3b8;margin-top:4px;">' + (m.desc || '') + '</div>'
                    + '</div>';
            } },
            dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600 },
                formatter: function(text, op) { return text.length > 20 ? text.substring(0, 18) + '..' : text; } },
            legend: { show: false }
        });
        treemap.render();
    })();

    // === Re-analyze / Resume loading animation ===
    function showAgentLoadingOverlay(startFromStage) {
        var overlay = document.getElementById('agentLoadingOverlay');
        if (!overlay) return;
        overlay.style.display = 'block';
        overlay.style.opacity = '0';
        requestAnimationFrame(function() {
            overlay.style.transition = 'opacity 0.4s ease';
            overlay.style.opacity = '1';
        });

        var startTime = Date.now();
        var elapsedEl = document.getElementById('loadingElapsed');
        var elapsedTimer = setInterval(function() {
            var secs = Math.floor((Date.now() - startTime) / 1000);
            if (elapsedEl) elapsedEl.textContent = secs + 's elapsed';
        }, 1000);

        var allStages = [
            { key: 'archaeologist', title: 'Mapping Blast Radius', subtitle: 'Archaeologist scanning dependencies...', icon: 'explore' },
            { key: 'historian', title: 'Calculating Risk Score', subtitle: 'Historian correlating with incidents...', icon: 'history' },
            { key: 'negotiator', title: 'Making Deploy Decision', subtitle: 'Negotiator weighing risk vs velocity...', icon: 'gavel' },
            { key: 'chronicler', title: 'Recording Feedback Loop', subtitle: 'Chronicler capturing analysis...', icon: 'auto_stories' }
        ];

        var startIdx = 0;
        if (startFromStage) {
            for (var si = 0; si < allStages.length; si++) {
                if (allStages[si].key === startFromStage) { startIdx = si; break; }
            }
        }

        // Mark completed stages immediately
        for (var ci = 0; ci < startIdx; ci++) {
            var sk = allStages[ci].key;
            var cimg = document.getElementById('img-' + sk);
            var clbl = document.getElementById('label-' + sk);
            var cbar = document.getElementById('bar-' + sk);
            var cst = document.getElementById('status-' + sk);
            if (cimg) { cimg.style.opacity = '1'; cimg.style.boxShadow = '0 0 12px rgba(16,185,129,0.3)'; }
            if (clbl) clbl.style.opacity = '1';
            if (cbar) cbar.style.width = '100%';
            if (cst) { cst.textContent = 'Complete'; cst.style.color = '#10B981'; }
        }

        // Animate remaining stages
        var remaining = allStages.slice(startIdx);
        var progressBar = document.getElementById('agentProgressBar');
        var titleEl = document.getElementById('loadingTitle');
        var subtitleEl = document.getElementById('loadingSubtitle');
        var mainIcon = document.getElementById('loadingMainIcon');

        // Set initial progress
        if (progressBar) progressBar.style.width = Math.round((startIdx / allStages.length) * 100) + '%';

        var baseDelay = 300;
        var stageDuration = 2200;

        remaining.forEach(function(stage, idx) {
            var globalIdx = startIdx + idx;
            var stageDelay = baseDelay + (idx * (stageDuration + 300));

            setTimeout(function() {
                var img = document.getElementById('img-' + stage.key);
                var label = document.getElementById('label-' + stage.key);
                var bar = document.getElementById('bar-' + stage.key);
                var status = document.getElementById('status-' + stage.key);
                if (img) { img.style.opacity = '1'; img.style.boxShadow = '0 0 16px rgba(96,93,255,0.4)'; }
                if (label) label.style.opacity = '1';
                if (status) { status.textContent = 'Processing...'; status.style.color = '#60a5fa'; }
                requestAnimationFrame(function() { if (bar) bar.style.width = '100%'; });
                if (titleEl) titleEl.textContent = stage.title;
                if (subtitleEl) subtitleEl.textContent = stage.subtitle;
                if (mainIcon) mainIcon.textContent = stage.icon;
                if (progressBar) progressBar.style.width = Math.round(((globalIdx + 0.5) / allStages.length) * 100) + '%';
            }, stageDelay);

            setTimeout(function() {
                var status = document.getElementById('status-' + stage.key);
                var img = document.getElementById('img-' + stage.key);
                if (status) { status.textContent = 'Complete'; status.style.color = '#10B981'; }
                if (img) img.style.boxShadow = '0 0 12px rgba(16,185,129,0.3)';
                if (progressBar) progressBar.style.width = Math.round(((globalIdx + 1) / allStages.length) * 100) + '%';
                if (globalIdx === allStages.length - 1) {
                    setTimeout(function() {
                        if (titleEl) titleEl.textContent = 'Analysis Complete';
                        if (subtitleEl) subtitleEl.textContent = 'Redirecting to results...';
                        if (mainIcon) { mainIcon.textContent = 'check_circle'; mainIcon.style.color = '#10B981'; }
                    }, 400);
                }
            }, stageDelay + stageDuration);
        });
    }

    // Hook to re-analyze form
    var reanalyzeForm = document.querySelector('form[action*="reanalyze"]');
    if (reanalyzeForm) {
        reanalyzeForm.addEventListener('submit', function() { showAgentLoadingOverlay(null); });
    }

    // Hook to resume pipeline form
    var resumeForm = document.querySelector('form[action*="resume-pipeline"]');
    if (resumeForm) {
        resumeForm.addEventListener('submit', function() {
            var pausedStage = @json($pullRequest->paused_at_stage ?? 'negotiator');
            showAgentLoadingOverlay(pausedStage);
        });
    }
});
</script>
@endpush