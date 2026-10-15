(() => {
    const messageDialog = document.getElementById('jass-message-dialog');
    if (messageDialog) {
        const title = document.getElementById('jass-message-title');
        const text = document.getElementById('jass-message-text');
        const cancel = document.getElementById('jass-message-cancel');
        const accept = document.getElementById('jass-message-accept');
        let pending = null;
        const approved = new WeakSet();
        const open = (message, confirmation = null) => {
            if (messageDialog.open) return;
            pending = confirmation;
            title.textContent = confirmation ? 'Confirmar operación' : 'Aviso';
            text.textContent = message;
            cancel.hidden = !confirmation;
            accept.textContent = confirmation ? 'Confirmar' : 'Entendido';
            messageDialog.showModal();
            (confirmation ? cancel : accept).focus();
        };
        window.jassAlert = message => open(message);
        document.addEventListener('submit', event => {
            const form = event.target;
            if (!form.matches('form[data-confirm]')) return;
            if (approved.delete(form)) return;
            event.preventDefault();
            open(form.dataset.confirm, { form, submitter: event.submitter });
        }, true);
        cancel.addEventListener('click', () => messageDialog.close());
        accept.addEventListener('click', () => {
            const operation = pending;
            messageDialog.close();
            if (!operation) return;
            approved.add(operation.form);
            try {
                operation.form.requestSubmit(operation.submitter ?? undefined);
            } finally {
                approved.delete(operation.form);
            }
        });
        messageDialog.addEventListener('close', () => { pending = null; });
    }

    // Only dirty operational forms prompt; report filters and logout never do.
    document.querySelectorAll('form[data-warn-unsaved]').forEach(form => {
        let dirty = false;
        form.addEventListener('input', () => dirty = true);
        form.addEventListener('change', () => dirty = true);
        form.addEventListener('submit', event => { queueMicrotask(() => {
    const messageDialog = document.getElementById('jass-message-dialog');
    if (messageDialog) {
        const title = document.getElementById('jass-message-title');
        const text = document.getElementById('jass-message-text');
        const cancel = document.getElementById('jass-message-cancel');
        const accept = document.getElementById('jass-message-accept');
        let pending = null;
        const approved = new WeakSet();
        const open = (message, confirmation = null) => {
            if (messageDialog.open) return;
            pending = confirmation;
            title.textContent = confirmation ? 'Confirmar operación' : 'Aviso';
            text.textContent = message;
            cancel.hidden = !confirmation;
            accept.textContent = confirmation ? 'Confirmar' : 'Entendido';
            messageDialog.showModal();
            (confirmation ? cancel : accept).focus();
        };
        window.jassAlert = message => open(message);
        document.addEventListener('submit', event => {
            const form = event.target;
            if (!form.matches('form[data-confirm]')) return;
            if (approved.delete(form)) return;
            event.preventDefault();
            open(form.dataset.confirm, { form, submitter: event.submitter });
        }, true);
        cancel.addEventListener('click', () => messageDialog.close());
        accept.addEventListener('click', () => {
            const operation = pending;
            messageDialog.close();
            if (!operation) return;
            approved.add(operation.form);
            try {
                operation.form.requestSubmit(operation.submitter ?? undefined);
            } finally {
                approved.delete(operation.form);
            }
        });
        messageDialog.addEventListener('close', () => { pending = null; });
    }
 if (!event.defaultPrevented) dirty = false; }); });
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
