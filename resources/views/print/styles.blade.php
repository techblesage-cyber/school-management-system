    <style>
        :root { --ink: #111827; --muted: #6b7280; --line: #d1d5db; --accent: #1d4ed8; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: var(--ink); font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .page { max-width: 760px; margin: 24px auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .toolbar { max-width: 760px; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; padding: 0 16px; }
        .toolbar button { background: var(--accent); color: #fff; border: 0; border-radius: 6px; padding: 10px 18px; font-size: 15px; cursor: pointer; }
        header.school { display: flex; gap: 16px; align-items: center; border-bottom: 2px solid var(--ink); padding-bottom: 14px; margin-bottom: 18px; }
        header.school img { width: 72px; height: 72px; object-fit: contain; }
        header.school h1 { margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: .02em; }
        header.school p { margin: 2px 0 0; color: var(--muted); font-size: 13px; }
        h2.doc-title { text-align: center; font-size: 17px; letter-spacing: .12em; margin: 0 0 18px; }
        dl.details { display: grid; grid-template-columns: max-content 1fr max-content 1fr; gap: 6px 14px; margin: 0 0 18px; }
        dl.details dt { color: var(--muted); }
        dl.details dd { margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: 700; border-top: 2px solid var(--ink); border-bottom: 0; }
        .amount-box { border: 2px solid var(--ink); border-radius: 6px; padding: 12px 16px; margin: 0 0 16px; }
        .amount-box .big { font-size: 22px; font-weight: 700; }
        .muted { color: var(--muted); }
        .void-stamp { border: 3px solid #b91c1c; color: #b91c1c; font-weight: 800; font-size: 22px; text-align: center; padding: 8px; margin-bottom: 16px; letter-spacing: .2em; }
        .signatures { display: flex; justify-content: space-between; gap: 32px; margin-top: 40px; }
        .signatures div { flex: 1; border-top: 1px solid var(--ink); padding-top: 6px; color: var(--muted); font-size: 12px; }
        footer.printed { margin-top: 24px; color: var(--muted); font-size: 11px; text-align: center; }
        @media (max-width: 600px) {
            .page { margin: 8px; padding: 18px; }
            dl.details { grid-template-columns: max-content 1fr; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
        }
    </style>
