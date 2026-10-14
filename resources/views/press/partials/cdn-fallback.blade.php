{{-- Used only when the Vite assets were never built (npm run build). --}}
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11"></script>
<style type="text/tailwindcss">
    @theme inline {
        --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
        --font-display: var(--press-font-display);
        --color-paper: var(--press-paper); --color-surface: var(--press-surface); --color-ink: var(--press-ink);
        --color-muted: var(--press-muted); --color-rule: var(--press-rule); --color-accent: var(--press-accent);
        --color-accent-ink: var(--press-accent-ink); --color-highlight: var(--press-highlight);
    }
    :root { --press-paper:#f6f3ec; --press-surface:#fffdf8; --press-ink:#1b1a17; --press-muted:#5f5b52; --press-rule:#e2dccf;
        --press-accent:#0f6e66; --press-accent-ink:#fff; --press-highlight:#f2c14e; --press-font-display:'Fraunces', Georgia, serif; }
    .press-container { margin-inline:auto; max-width:72rem; padding-inline:1.5rem; }
    .press-button { display:inline-flex; border-radius:999px; background:var(--press-accent); color:var(--press-accent-ink); padding:.7rem 1.35rem; font-weight:600; }
    .press-eyebrow { font-size:.75rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:var(--press-accent); }
    .press-prose > * + * { margin-top:1.25rem; } .press-prose a { color:var(--press-accent); text-decoration:underline; }
</style>
