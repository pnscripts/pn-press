{{--
    Keyboard support for menus rendered by press.partials.nav-item inside a [data-press-nav] element.
    Enter/Space open and close a dropdown (native <details>). Added here:
    Escape closes the innermost open menu and returns focus to its button; ArrowDown/ArrowUp/Home/End move
    through the open menu; ArrowRight opens a sub-menu, ArrowLeft closes it; opening one menu closes its
    siblings; clicking or tabbing outside closes everything.
--}}
<script>
(() => {
    const navs = document.querySelectorAll('[data-press-nav]');
    if (!navs.length) return;

    const closeWithin = (root, except) => root.querySelectorAll('details[data-press-menu][open]').forEach((d) => {
        if (d !== except && !d.contains(except)) d.open = false;
    });
    const summaryOf = (details) => details.querySelector(':scope > summary');
    const listOf = (details) => details.querySelector(':scope > ul');
    // Focusable entries of one open menu level: its links and the buttons of its sub-menus.
    const entriesOf = (details) => [...listOf(details).querySelectorAll(':scope > li > a, :scope > li > details > summary')];
    const openAndFocusFirst = (details) => { details.open = true; entriesOf(details)[0]?.focus(); };

    navs.forEach((nav) => {
        nav.addEventListener('toggle', (event) => {
            const details = event.target;
            if (!details.matches?.('details[data-press-menu]') || !details.open) return;
            const parentList = details.parentElement.closest('ul, [data-press-nav]');
            parentList.querySelectorAll(':scope > li > details[open], :scope > details[open]').forEach((d) => { if (d !== details) d.open = false; });
            summaryOf(details).setAttribute('aria-expanded', 'true');
        }, true);
        nav.addEventListener('toggle', (event) => {
            if (event.target.matches?.('details[data-press-menu]') && !event.target.open) summaryOf(event.target).setAttribute('aria-expanded', 'false');
        }, true);
        nav.querySelectorAll('details[data-press-menu] > summary').forEach((s) => s.setAttribute('aria-expanded', s.parentElement.open ? 'true' : 'false'));

        nav.addEventListener('keydown', (event) => {
            const target = event.target;
            const inMenu = target.closest('details[data-press-menu]');
            const ownDetails = target.matches('summary') ? target.parentElement : null;
            // The menu whose list holds the focused entry (null on the menu bar).
            const container = (target.matches('summary') ? ownDetails.parentElement : target).closest('ul')?.closest('details[data-press-menu]') ?? null;

            switch (event.key) {
                case 'Escape': {
                    const open = ownDetails?.open ? ownDetails : container ?? inMenu;
                    if (!open) return;
                    open.open = false;
                    summaryOf(open).focus();
                    event.preventDefault();
                    break;
                }
                case 'ArrowDown':
                case 'ArrowUp': {
                    if (!container) {
                        if (ownDetails && event.key === 'ArrowDown') { openAndFocusFirst(ownDetails); event.preventDefault(); }
                        return;
                    }
                    const entries = entriesOf(container);
                    const index = entries.indexOf(target);
                    const next = event.key === 'ArrowDown' ? (index + 1) % entries.length : (index - 1 + entries.length) % entries.length;
                    entries[next]?.focus();
                    event.preventDefault();
                    break;
                }
                case 'Home':
                case 'End': {
                    if (!container) return;
                    const entries = entriesOf(container);
                    (event.key === 'Home' ? entries[0] : entries[entries.length - 1])?.focus();
                    event.preventDefault();
                    break;
                }
                case 'ArrowRight': {
                    if (ownDetails && container) { openAndFocusFirst(ownDetails); event.preventDefault(); }
                    break;
                }
                case 'ArrowLeft': {
                    if (container && container.dataset.level !== '0') {
                        container.open = false;
                        summaryOf(container).focus();
                        event.preventDefault();
                    }
                    break;
                }
            }
        });

        nav.addEventListener('focusout', (event) => {
            if (event.relatedTarget && !nav.contains(event.relatedTarget)) closeWithin(nav, null);
        });
    });

    document.addEventListener('click', (event) => {
        navs.forEach((nav) => { if (!nav.contains(event.target)) closeWithin(nav, null); });
    });
})();
</script>
