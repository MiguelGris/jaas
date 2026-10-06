(() => {
    // Only dirty operational forms prompt; report filters and logout never do.
    document.querySelectorAll('form[data-warn-unsaved]').forEach(form => {
        let dirty = false;
        form.addEventListener('input', () => dirty = true);
        form.addEventListener('change', () => dirty = true);
        form.addEventListener('submit', event => { queueMicrotask(() => { if (!event.defaultPrevented) dirty = false; }); });
        window.addEventListener('beforeunload', event => {
            if (!dirty) return;
            event.preventDefault(); event.returnValue = '';
        });
    });
    const navigation = document.getElementById('sidebar-navigation');
    if (!navigation) return;
    const key = `jass.favorites.${navigation.dataset.userId}`;
    let favorites = [];
    try { favorites = JSON.parse(localStorage.getItem(key) || '[]'); if (!Array.isArray(favorites)) favorites = []; } catch (_) {}
    const links = [...navigation.querySelectorAll('[data-favorite-link]')];
    const render = () => {
        const target = document.getElementById('favorite-links');
        target.replaceChildren();
        links.filter(link => favorites.includes(link.getAttribute('href'))).forEach(link => {
            const copy = link.cloneNode(true); copy.removeAttribute('data-favorite-link'); target.append(copy);
        });
        document.getElementById('favorite-navigation').hidden = !target.children.length;
    };
    links.forEach(link => {
        const wrapper = document.createElement('div'); wrapper.className = 'flex items-center';
        link.before(wrapper); wrapper.append(link); link.classList.add('flex-1', 'min-w-0');
        const button = document.createElement('button'); button.type = 'button'; button.className = 'rounded-lg px-3 py-2 text-amber-300';
        const update = () => {
            const selected = favorites.includes(link.getAttribute('href'));
            button.textContent = selected ? '★' : '☆'; button.setAttribute('aria-pressed', String(selected));
            button.setAttribute('aria-label', `${selected ? 'Quitar de' : 'Añadir a'} favoritos: ${link.textContent.trim()}`);
        };
        button.addEventListener('click', () => {
            const href = link.getAttribute('href'); favorites = favorites.includes(href) ? favorites.filter(value => value !== href) : [...favorites, href];
            try { localStorage.setItem(key, JSON.stringify(favorites)); } catch (_) {}
            update(); render();
        });
        wrapper.append(button); update();
    });
    render();
})();
