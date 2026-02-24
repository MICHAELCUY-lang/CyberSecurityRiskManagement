<?php
/**
 * Partial: header.php
 * Include at top of every page: include __DIR__ . '/partials/header.php';
 * Set $pageTitle before including.
 */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <style>
        /* ====================================================
           DESIGN SYSTEM - Black / White / Gray Only
           Charts: Blue (#2563eb) = low/compliant
                   Red  (#dc2626) = high/critical
        ==================================================== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0a0a0a;
            --bg-card:     #141414;
            --bg-elevated: #1e1e1e;
            --border:      #2a2a2a;
            --border-light:#383838;
            --text:        #f0f0f0;
            --text-muted:  #888;
            --text-dim:    #555;
            --sidebar-w:   240px;
            --header-h:    56px;
            --chart-blue:  #2563eb;
            --chart-red:   #dc2626;
            --risk-low:    #f0f0f0;
            --risk-med:    #d0d0d0;
            --risk-high:   #dc2626;
            --risk-crit:   #8b0000;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        /* ---- Layout ---- */
        .layout { display: flex; min-height: 100vh; }

        .main-content {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .page-header {
            height: var(--header-h);
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .page-header h1 {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .page-header .breadcrumb {
            font-size: 12px;
            color: var(--text-muted);
        }

        .content-area {
            padding: 28px;
            flex: 1;
        }

        /* ---- Cards ---- */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        /* ---- KPI Grid ---- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 20px;
        }

        .kpi-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -.02em;
            line-height: 1;
        }

        .kpi-value.danger { color: var(--chart-red); }
        .kpi-value.safe   { color: var(--chart-blue); }

        .kpi-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ---- Tables ---- */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-muted);
            background: var(--bg-elevated);
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-elevated); }

        /* ---- Risk badges ---- */
        .badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 2px;
        }

        .badge-low      { background: #1a1a1a; color: #aaa; border: 1px solid #333; }
        .badge-medium   { background: #1a1a2a; color: #6699ff; border: 1px solid #2a3a5a; }
        .badge-high     { background: #2a0000; color: #ff6b6b; border: 1px solid #5a1a1a; }
        .badge-critical { background: #1a0000; color: #ff2222; border: 1px solid #7a0000; }
        .badge-compliant    { background: #001a2a; color: #66aaff; border: 1px solid #1a3a5a; }
        .badge-partial      { background: #1a1a1a; color: #aaa; border: 1px solid #333; }
        .badge-non-compliant{ background: #2a0000; color: #ff6b6b; border: 1px solid #5a1a1a; }
        .badge-na           { background: #111; color: #555; border: 1px solid #222; }

        /* ---- Forms ---- */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }

        label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        input[type=text],
        input[type=number],
        input[type=file],
        select,
        textarea {
            background: var(--bg);
            border: 1px solid var(--border-light);
            border-radius: 3px;
            color: var(--text);
            padding: 8px 10px;
            font-size: 13px;
            font-family: inherit;
            width: 100%;
            outline: none;
            transition: border-color .15s;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #fff;
        }

        textarea { resize: vertical; min-height: 80px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 3px;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s, color .15s;
        }

        .btn:hover { background: #d0d0d0; }

        .btn-ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border-light);
        }

        .btn-ghost:hover { border-color: #fff; background: transparent; }

        .btn-danger {
            background: var(--chart-red);
            color: #fff;
        }

        .btn-danger:hover { background: #b91c1c; }

        /* ---- Alerts ---- */
        .alert {
            padding: 12px 16px;
            border-radius: 3px;
            margin-bottom: 16px;
            font-size: 13px;
            border-left: 3px solid;
        }

        .alert-success { border-color: #fff; background: #1a1a1a; color: #f0f0f0; }
        .alert-error   { border-color: var(--chart-red); background: #1a0000; color: #ff6b6b; }
        .alert-info    { border-color: var(--chart-blue); background: #00101a; color: #6699ff; }

        /* ---- Horizontal Severity Bar ---- */
        .sev-bar-wrap { display: flex; align-items: center; gap: 8px; }
        .sev-bar-track {
            flex: 1;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }
        .sev-bar-fill { height: 100%; border-radius: 3px; transition: width .4s ease; }
        .sev-bar-fill.blue { background: var(--chart-blue); }
        .sev-bar-fill.red  { background: var(--chart-red); }
        .sev-bar-label { font-size: 11px; color: var(--text-muted); min-width: 32px; text-align: right; }

        /* ---- Utility ---- */
        .text-muted  { color: var(--text-muted); }
        .text-danger { color: var(--chart-red); }
        .text-blue   { color: var(--chart-blue); }
        .mt-1 { margin-top: 8px; }
        .mt-2 { margin-top: 16px; }
        .mt-3 { margin-top: 24px; }
        .mb-1 { margin-bottom: 8px; }
        .mb-2 { margin-bottom: 16px; }
        .flex  { display: flex; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 16px; }
        .font-mono { font-family: 'Consolas', 'Courier New', monospace; }

        /* ---- Print ---- */
        @media print {
            .sidebar, .page-header { display: none; }
            .main-content { margin: 0; }
            body { background: #fff; color: #000; }
        }
    </style>
</head>
<body>
<div class="layout">
