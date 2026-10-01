const LINK_CLASS = 'wako-admin-toolbar-logo-link';

export function updateLogoLink(menu, url, label) {
    const logo = menu?.querySelector?.('.sw-admin-menu__header-logo');
    if (!logo) return;

    let link = logo.parentElement;
    const isToolbarLink = link?.classList.contains(LINK_CLASS);

    if (!url) {
        if (isToolbarLink) {
            link.replaceWith(logo);
        }
        return;
    }

    if (!isToolbarLink) {
        // Keep the version-specific Core logo and its separate sidebar controls intact.
        link = logo.ownerDocument.createElement('a');
        // Opt out of Shopware's automatic external-link decoration and sizing.
        link.classList.add(LINK_CLASS, 'sw-external-link');
        logo.replaceWith(link);
        link.appendChild(logo);
    }

    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.setAttribute('aria-label', label);
    link.title = label;
}
