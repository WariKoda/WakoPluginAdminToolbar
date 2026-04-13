import Plugin from 'src/plugin-system/plugin.class';

const STORAGE_KEY = 'wako.admin-toolbar.collapsed';

export default class AdminToolbarPlugin extends Plugin {

    init() {
        this._customerContextLoaded = false;
        this._customerContextLoading = false;
        this._customerContextUnavailable = false;

        this._checkAdminSession();
    }

    async _checkAdminSession() {
        let verified = false;

        try {
            // Single request replaces toolbar-session + _info/me + user/{id}
            const response = await fetch('/admin/toolbar-auth', {
                credentials: 'include',
            });

            if (response.status !== 200) return;

            const data = await response.json();
            if (!data?.enabled) return;

            verified = true;

            const collapsed = localStorage.getItem(STORAGE_KEY) === 'true';
            this._showToolbar(data, collapsed);
        } catch (_) {
            // Network error or non-admin context — keep toolbar hidden
        } finally {
            if (!verified) {
                this.el.classList.add('wako-admin-toolbar--hidden');
                document.body.style.paddingTop = '';

            }
        }
    }

    _showToolbar(session, collapsed) {
        this._permissions = session?.permissions ?? {};

        this.el.classList.remove('wako-admin-toolbar--hidden');
        this.el.classList.toggle('wako-admin-toolbar--collapsed', collapsed);

        this._applyPermissions();
        this._updateBodyPadding();
        this._populateUser(session);
        this._initCopyButtons();
        this._initClearCache();
        this._initToggle();
        this._initVariantsDropdown();
        this._initCustomerContext();
    }

    _applyPermissions() {
        const featurePermissions = {
            'clear-cache': 'canClearCache',
            'customer-context': 'canViewCustomerContext',
            'view-rules': 'canViewRules',
            'edit-product': 'canEditProduct',
            'edit-category': 'canEditCategory',
            'edit-cms-page': 'canEditCmsPage',
            'edit-landing-page': 'canEditLandingPage',
        };

        Object.entries(featurePermissions).forEach(([feature, permissionKey]) => {
            this.el.querySelectorAll(`[data-toolbar-feature="${feature}"]`).forEach((el) => {
                el.classList.toggle('wako-admin-toolbar__feature--hidden', !this._hasPermission(permissionKey));
            });
        });

        this._toggleContainerVisibility('.wako-admin-toolbar__center');
        this._updateSeparators();
    }

    _hasPermission(permissionKey) {
        return this._permissions?.[permissionKey] === true;
    }

    _toggleContainerVisibility(selector) {
        const container = this.el.querySelector(selector);
        if (!container) return;

        const hasVisibleChildren = Array.from(container.children)
            .some((child) => !child.classList.contains('wako-admin-toolbar__feature--hidden'));

        container.classList.toggle('wako-admin-toolbar__feature--hidden', !hasVisibleChildren);
    }

    _updateSeparators() {
        const container = this.el.querySelector('.wako-admin-toolbar__right');
        if (!container) return;

        const children = Array.from(container.children);

        children.forEach((child, index) => {
            if (!child.classList.contains('wako-admin-toolbar__sep')) {
                return;
            }

            const previous = this._findVisibleSibling(children, index, -1);
            const next = this._findVisibleSibling(children, index, 1);
            const shouldShow = !!previous
                && !!next
                && !previous.classList.contains('wako-admin-toolbar__sep')
                && !next.classList.contains('wako-admin-toolbar__sep');

            child.classList.toggle('wako-admin-toolbar__sep--hidden', !shouldShow);
        });
    }

    _findVisibleSibling(children, startIndex, direction) {
        for (let index = startIndex + direction; index >= 0 && index < children.length; index += direction) {
            const child = children[index];
            if (child.classList.contains('wako-admin-toolbar__feature--hidden')) {
                continue;
            }

            return child;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Copy actions
    // -------------------------------------------------------------------------

    _initCopyButtons() {
        const entityBtn = this.el.querySelector('[data-toolbar-copy-id]');
        if (entityBtn) {
            const entityId = entityBtn.dataset.entityId;
            this._bindCopyButton(entityBtn, () => entityId, {
                originalText: entityBtn.textContent.trim(),
            });
        }

        const customerNumberBtn = this.el.querySelector('[data-toolbar-copy-customer-number]');
        const customerNumberEl = this.el.querySelector('[data-toolbar-customer-number]');
        this._bindCopyButton(customerNumberBtn, () => customerNumberEl?.textContent ?? '');

        const emailBtn = this.el.querySelector('[data-toolbar-copy-customer-email]');
        const emailEl = this.el.querySelector('[data-toolbar-customer-email]');
        this._bindCopyButton(emailBtn, () => emailEl?.textContent ?? '');
    }

    _bindCopyButton(btn, getText, { originalIcon = 'copy', originalText = '' } = {}) {
        if (!btn || typeof getText !== 'function') return;

        btn.addEventListener('click', async () => {
            const text = `${getText() ?? ''}`.trim();
            if (!text || btn.disabled) return;

            try {
                await navigator.clipboard.writeText(text);
                this._setButtonContent(btn, 'check', originalText);
                setTimeout(() => { this._setButtonContent(btn, originalIcon, originalText); }, 1500);
            } catch (_) {
                // Clipboard API unavailable (non-HTTPS, etc.)
            }
        });
    }

    // -------------------------------------------------------------------------
    // Clear cache
    // -------------------------------------------------------------------------

    _initClearCache() {
        if (!this._hasPermission('canClearCache')) return;

        const btn = this.el.querySelector('[data-toolbar-clear-cache]');
        if (!btn) return;

        btn.addEventListener('click', () => this._clearCache(btn));
    }

    async _clearCache(btn) {
        if (btn.disabled) return;

        btn.disabled = true;
        this._setButtonContent(btn, null, '…');

        try {
            const response = await fetch('/admin/toolbar-clear-cache', {
                method: 'DELETE',
                credentials: 'include',
            });

            this._setButtonContent(btn, response.ok ? 'check' : 'x');
        } catch (_) {
            this._setButtonContent(btn, 'x');
        }

        setTimeout(() => {
            this._setButtonContent(btn, 'sync');
            btn.disabled = false;
        }, 2000);
    }

    // -------------------------------------------------------------------------
    // DOM helper — safe alternative to innerHTML for button content
    // -------------------------------------------------------------------------

    /**
     * Replaces an element's content with an optional inline SVG icon and text
     * using safe DOM APIs. Icons reference the <symbol> definitions rendered
     * in the Twig template via <use href="#wako-icon-{name}">.
     *
     * @param {HTMLElement} el      Target element to update
     * @param {string|null} icon    Icon name, e.g. "check", "refresh", "x"
     * @param {string}      [text]  Optional text label after the icon
     */
    _setButtonContent(el, icon, text = '') {
        el.textContent = '';

        if (icon) {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'wako-admin-toolbar__icon');
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('focusable', 'false');
            const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
            use.setAttribute('href', `#wako-icon-${icon}`);
            svg.appendChild(use);
            el.appendChild(svg);
        }

        if (text) {
            el.appendChild(document.createTextNode(` ${text}`));
        }
    }

    // -------------------------------------------------------------------------
    // Collapse toggle
    // -------------------------------------------------------------------------

    _initToggle() {
        const toggleBtn = this.el.querySelector('[data-toolbar-toggle]');
        const tab = this.el.querySelector('[data-toolbar-tab]');

        toggleBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            this._toggleCollapsed();
        });

        tab?.addEventListener('click', () => this._toggleCollapsed());

        tab?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this._toggleCollapsed();
            }
        });
    }

    _toggleCollapsed() {
        const collapsed = this.el.classList.toggle('wako-admin-toolbar--collapsed');
        this._updateBodyPadding();
        this._saveCollapsedState(collapsed);
    }

    _updateBodyPadding() {
        document.body.style.paddingTop = this.el.offsetHeight > 0
            ? `${this.el.offsetHeight}px`
            : '';
    }

    _saveCollapsedState(collapsed) {
        localStorage.setItem(STORAGE_KEY, collapsed);
    }

    // -------------------------------------------------------------------------
    // Variant product dropdown
    // -------------------------------------------------------------------------

    _initVariantsDropdown() {
        if (!this._hasPermission('canLoadVariants')) return;

        this.el.querySelectorAll('[data-toolbar-variants]').forEach(el => {
            el.addEventListener('mouseenter', () => this._loadVariants(el), { once: true });
        });
    }

    async _loadVariants(dropdown) {
        const parentId = dropdown.dataset.toolbarVariants;
        const adminBaseUrl = dropdown.dataset.adminBaseUrl;
        const menu = dropdown.querySelector('.wako-admin-toolbar__dropdown-menu');
        if (!menu || !parentId) return;

        try {
            const response = await fetch(`/admin/toolbar-variants/${encodeURIComponent(parentId)}`, {
                credentials: 'include',
            });

            if (!response.ok) return;

            const data = await response.json();
            const variants = data.variants ?? [];
            if (variants.length === 0) return;

            variants.forEach(variant => {
                const a = document.createElement('a');
                a.href = `${adminBaseUrl}#/sw/product/detail/${variant.id}`;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = 'wako-admin-toolbar__dropdown-item';
                a.textContent = variant.label ?? variant.id;
                menu.appendChild(a);
            });

            dropdown.classList.add('wako-admin-toolbar__dropdown--loaded');
        } catch (_) {
            // ignore — submenu just won't appear
        }
    }

    // -------------------------------------------------------------------------
    // Customer context
    // -------------------------------------------------------------------------

    _initCustomerContext() {
        if (!this._hasPermission('canViewCustomerContext')) return;

        const dropdown = this.el.querySelector('[data-toolbar-customer-dropdown]');
        if (!dropdown) return;

        const load = () => this._loadCustomerContext(dropdown);

        dropdown.addEventListener('mouseenter', load);
        dropdown.addEventListener('focusin', load);
        dropdown.addEventListener('click', load);
    }

    async _loadCustomerContext(dropdown) {
        if (!dropdown || this._customerContextLoaded || this._customerContextLoading || this._customerContextUnavailable) {
            return;
        }

        this._customerContextLoading = true;

        try {
            const response = await fetch('/admin/toolbar-customer-context', {
                credentials: 'include',
            });

            if (response.status !== 200) {
                this._customerContextUnavailable = true;
                return;
            }

            const data = await response.json();
            if (!data?.customer) {
                this._customerContextUnavailable = true;
                return;
            }

            this._populateCustomerContext(dropdown, data.customer, data.activeRules ?? []);
            this._customerContextLoaded = true;
        } catch (_) {
            // ignore — allow retry on next interaction
        } finally {
            this._customerContextLoading = false;
        }
    }

    _populateCustomerContext(dropdown, customer, activeRules) {
        if (!dropdown) return;

        const adminBaseUrl = this.el.dataset.adminBaseUrl ?? '';
        const name = dropdown.querySelector('[data-toolbar-customer-name]');
        const customerNumber = dropdown.querySelector('[data-toolbar-customer-number]');
        const email = dropdown.querySelector('[data-toolbar-customer-email]');
        const customerNumberCopy = dropdown.querySelector('[data-toolbar-copy-customer-number]');
        const emailCopy = dropdown.querySelector('[data-toolbar-copy-customer-email]');
        const rulesCount = dropdown.querySelector('[data-toolbar-customer-rules-count]');
        const rulesList = dropdown.querySelector('[data-toolbar-customer-rules]');
        const emptyState = dropdown.querySelector('[data-toolbar-customer-rules-empty]');

        const displayName = customer.displayName
            ?? [customer.firstName, customer.lastName].filter(Boolean).join(' ').trim();

        const customerNumberValue = `${customer.customerNumber ?? ''}`.trim();
        const emailValue = `${customer.email ?? ''}`.trim();

        if (name) name.textContent = displayName;
        if (customerNumber) customerNumber.textContent = customerNumberValue;
        if (email) email.textContent = emailValue;
        if (customerNumberCopy) customerNumberCopy.disabled = customerNumberValue === '';
        if (emailCopy) emailCopy.disabled = emailValue === '';
        if (rulesCount) rulesCount.textContent = `(${activeRules.length})`;

        if (rulesList) {
            rulesList.textContent = '';

            activeRules.forEach(rule => {
                const item = document.createElement('li');
                item.className = 'wako-admin-toolbar__rule-item';

                if (adminBaseUrl && rule.id) {
                    const link = document.createElement('a');
                    link.className = 'wako-admin-toolbar__rule-link';
                    link.href = `${adminBaseUrl}#/sw/settings/rule/detail/${rule.id}`;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = rule.name ?? rule.id;
                    link.title = rule.id;
                    item.appendChild(link);
                } else {
                    item.textContent = rule.name ?? rule.id;
                    if (rule.id) {
                        item.title = rule.id;
                    }
                }

                rulesList.appendChild(item);
            });
        }

        if (emptyState) {
            emptyState.style.display = activeRules.length > 0 ? 'none' : '';
        }

        dropdown.classList.add('wako-admin-toolbar__dropdown--loaded');
    }

    // -------------------------------------------------------------------------
    // User
    // -------------------------------------------------------------------------

    _populateUser() {
        // The user link is fully rendered by Twig — nothing to populate.
    }
}
