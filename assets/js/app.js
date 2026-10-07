/**
 * SquirrelMail Single-Page Application (SPA) Controller & Client-Side Router
 * 
 * Features:
 * - Dynamic asynchronous workspace routing via Fetch & History pushState
 * - Native browser back/forward (popstate) support
 * - Async form interception preserving CSRF tokens
 * - Shadow DOM sandboxing for untrusted HTML emails (zero iframes)
 * - Remote tracking images privacy shield & unblock toggle
 * - Dynamic folder tree unread count updates
 * - Responsive off-canvas sidebar drawer controller
 * - Full Dark Mode toggle & theme engine integration
 */

(function (window, document) {
    'use strict';

    // -------------------------------------------------------------------------
    // SquirrelMail Legacy UI Compatibility & Event Handlers
    // -------------------------------------------------------------------------
    window.marked_row = window.marked_row || [];
    window.orig_row_colors = window.orig_row_colors || [];

    window.rowOver = function (chkboxName) {
        var chkbox = document.getElementById(chkboxName);
        if (!chkbox) return;
        var tr = chkbox.closest ? chkbox.closest('tr') : (chkbox.parentNode ? chkbox.parentNode.parentNode : null);
        if (tr) {
            tr.classList.add('mouse_over');
        }
    };

    window.setPointer = function (theRow, theRowNum, theAction, defaultClass, mouseoverClass, clickedClass, optEvent) {
        if (!theRow) return;
        var e = optEvent || window.event;
        mouseoverClass = mouseoverClass || 'mouse_over';
        clickedClass = clickedClass || 'clicked';

        // Prevent flickering when moving between cells or children within the same row
        if (theAction === 'out' && e && e.relatedTarget && theRow.contains(e.relatedTarget)) {
            return false;
        }

        if (theAction === 'over') {
            theRow.classList.add(mouseoverClass);
        } else if (theAction === 'out') {
            theRow.classList.remove(mouseoverClass);
        } else if (theAction === 'click') {
            theRow.classList.toggle(clickedClass);
            theRow.classList.toggle('selected', theRow.classList.contains(clickedClass));
            if (typeof theRowNum !== 'undefined') {
                window.marked_row[theRowNum] = theRow.classList.contains(clickedClass);
            }
        }
        return true;
    };

    window.row_click = function (chkboxName, event, formName, checkboxRealName, extra) {
        var chkbox = document.getElementById(chkboxName);
        if (chkbox) {
            chkbox.checked = !chkbox.checked;
            var tr = chkbox.closest ? chkbox.closest('tr') : (chkbox.parentNode ? chkbox.parentNode.parentNode : null);
            if (tr) {
                tr.classList.toggle('selected', chkbox.checked);
                tr.classList.toggle('clicked', chkbox.checked);
            }
            if (extra) {
                try { (0, eval)(extra); } catch (e) {}
            }
        }
    };

    window.toggle_all = function (formname, name_prefix, fancy) {
        var targetForm = document.getElementById(formname);
        if (!targetForm) return;
        var master = targetForm.querySelector ? targetForm.querySelector('#toggleAll, #checkall') : null;
        var isChecked = master ? master.checked : null;
        var checkboxes = targetForm.querySelectorAll ? targetForm.querySelectorAll('input[type="checkbox"]') : targetForm.elements;

        for (var i = 0; i < checkboxes.length; i++) {
            var cb = checkboxes[i];
            if (cb.type === 'checkbox' && cb !== master && (!name_prefix || (cb.name && cb.name.substring(0, 3) === name_prefix))) {
                cb.checked = (isChecked !== null) ? isChecked : !cb.checked;
                var tr = cb.closest ? cb.closest('tr') : (cb.parentNode ? cb.parentNode.parentNode : null);
                if (tr) {
                    tr.classList.toggle('selected', cb.checked);
                    tr.classList.toggle('clicked', cb.checked);
                }
            }
        }
    };

    if (typeof window.checkForm !== 'function') {
        window.checkForm = function () {};
    }



    const App = {
        config: {
            workspaceId: 'sm-workspace-content',
            sidebarId: 'sm-sidebar',
            backdropId: 'sm-sidebar-backdrop',
            loadingBarId: 'sm-loading-bar',
            themeToggleId: 'sm-theme-toggle',
            menuToggleId: 'sm-menu-toggle'
        },

        state: {
            currentUrl: window.location.href,
            isLoading: false,
            theme: localStorage.getItem('sm_theme') || 
                   (document.cookie.match(/(?:^|;\s*)sm_theme=([^;]*)/) ? decodeURIComponent(document.cookie.match(/(?:^|;\s*)sm_theme=([^;]*)/)[1]) : null) || 
                   (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        },

        getBaseUri() {
            if (window.sqmBaseUri) return window.sqmBaseUri;
            const script = document.querySelector('script[src*="assets/js/app.js"]');
            if (script) {
                const src = script.getAttribute('src');
                const idx = src.indexOf('assets/js/app.js');
                if (idx !== -1) {
                    return src.substring(0, idx);
                }
            }
            const path = window.location.pathname;
            const srcIdx = path.indexOf('/src/');
            if (srcIdx !== -1) return path.substring(0, srcIdx + 1);
            const pluginsIdx = path.indexOf('/plugins/');
            if (pluginsIdx !== -1) return path.substring(0, pluginsIdx + 1);
            return '/';
        },

        init() {
            this.applyTheme(this.state.theme);
            this.setupNavigation();
            this.setupFormInterception();
            this.setupMobileDrawer();
            this.setupThemeToggle();
            this.enhanceWorkspace(document.getElementById(this.config.workspaceId) || document.body);

            // Expose globally for inline scripts or plugins
            window.sqmApp = this;
        },

        // -------------------------------------------------------------------------
        // Loading Progress Bar
        // -------------------------------------------------------------------------
        showLoading() {
            this.state.isLoading = true;
            const bar = document.getElementById(this.config.loadingBarId);
            if (bar) {
                bar.classList.remove('done');
                bar.classList.add('active');
            }
        },

        hideLoading() {
            this.state.isLoading = false;
            const bar = document.getElementById(this.config.loadingBarId);
            if (bar) {
                bar.classList.add('done');
                setTimeout(() => {
                    bar.classList.remove('active', 'done');
                }, 300);
            }
        },

        // -------------------------------------------------------------------------
        // Theme Engine
        // -------------------------------------------------------------------------
        applyTheme(theme) {
            this.state.theme = theme;
            document.documentElement.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('sm_theme', theme);
                document.cookie = 'sm_theme=' + encodeURIComponent(theme) + '; path=/; max-age=31536000; SameSite=Lax';
            } catch(e) {}

            // Synchronize custom theme stylesheet link if present
            const customTheme = document.getElementById('sm-custom-theme-css');
            if (customTheme) {
                const href = (customTheme.getAttribute('href') || '').toLowerCase();
                if (theme === 'light') {
                    if (href.includes('dark') || href.includes('night') || href.includes('ocean')) {
                        customTheme.disabled = true;
                    }
                } else {
                    customTheme.disabled = false;
                }
            }

            const toggleBtn = document.getElementById(this.config.themeToggleId);
            if (toggleBtn) {
                const titleText = theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
                toggleBtn.setAttribute('title', titleText);
                toggleBtn.setAttribute('aria-label', titleText);
                toggleBtn.innerHTML = theme === 'dark'
                    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>'
                    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
            }
        },

        setupThemeToggle() {
            const toggleBtn = document.getElementById(this.config.themeToggleId);
            if (toggleBtn) {
                toggleBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const newTheme = this.state.theme === 'dark' ? 'light' : 'dark';
                    this.applyTheme(newTheme);
                };
            }
        },

        // -------------------------------------------------------------------------
        // Mobile Drawer Controller
        // -------------------------------------------------------------------------
        setupMobileDrawer() {
            const menuBtn = document.getElementById(this.config.menuToggleId);
            const sidebar = document.getElementById(this.config.sidebarId);
            const backdrop = document.getElementById(this.config.backdropId);

            const toggle = () => {
                const isOpen = sidebar.classList.toggle('sm-sidebar-open');
                if (backdrop) backdrop.classList.toggle('active', isOpen);
            };

            const close = () => {
                if (sidebar) sidebar.classList.remove('sm-sidebar-open');
                if (backdrop) backdrop.classList.remove('active');
            };

            if (menuBtn) {
                menuBtn.onclick = (e) => {
                    e.preventDefault();
                    toggle();
                };
            }

            if (backdrop) {
                backdrop.onclick = close;
            }

            // Close on link click on mobile (< 768px)
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 768 && e.target.closest('#sm-sidebar a')) {
                    close();
                }
            });
        },

        // -------------------------------------------------------------------------
        // Client-Side Routing & Navigation
        // -------------------------------------------------------------------------
        setupNavigation() {
            // Intercept standard link clicks
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (!link) return;

                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
                    return;
                }

                // External links or explicit target="_blank" / download
                if (link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('rel') === 'external') {
                    return;
                }

                // Resolve relative URLs correctly even when current page is in plugins/ or deep subpath
                let resolvedHref = href;
                const base = this.getBaseUri();
                const currentContextUrl = this.state.currentUrl || window.location.href;
                if (!/^https?:\/\/|^\/\//i.test(href)) {
                    if (href.startsWith('/')) {
                        resolvedHref = window.location.origin + href;
                    } else if (href.startsWith('../')) {
                        resolvedHref = window.location.origin + base + href.replace(/^\.\.\//, '');
                    } else if (href.startsWith('src/') || href.startsWith('plugins/') || href.startsWith('templates/')) {
                        resolvedHref = window.location.origin + base + href;
                    } else if (link.closest('#sm-workspace') && currentContextUrl.includes('/plugins/')) {
                        // Current workspace view is inside a plugin page; resolve relative to that plugin URL
                        resolvedHref = new URL(href, currentContextUrl).href;
                    } else {
                        // Standard SquirrelMail core script (e.g. right_main.php, webmail.php, compose.php, options.php)
                        resolvedHref = window.location.origin + base + 'src/' + href;
                    }
                }
                if (resolvedHref) resolvedHref = resolvedHref.replace(/&amp;/g, '&');

                const url = new URL(resolvedHref, window.location.origin);
                // Check if external domain
                if (url.origin !== window.location.origin) {
                    return;
                }

                // Check for signout or download
                if (url.pathname.includes('signout.php') || url.pathname.includes('download.php')) {
                    return;
                }

                // Normalize right_main.php to webmail.php in SPA router
                if (url.pathname.endsWith('/right_main.php')) {
                    url.pathname = url.pathname.replace(/\/right_main\.php$/, '/webmail.php');
                }

                // Clean out archaic/broken PG_SHOWALL parameter
                if (url.searchParams.has('PG_SHOWALL')) {
                    url.searchParams.delete('PG_SHOWALL');
                }

                // Handle left_main.php actions: "Check Mail", folder collapse/unfold
                if (url.pathname.endsWith('/left_main.php')) {
                    e.preventDefault();
                    this.refreshFolders(url.toString());
                    // If Check Mail was pressed (no fold/unfold query params), also refresh workspace if viewing a mailbox
                    if (!url.searchParams.has('fold') && !url.searchParams.has('unfold')) {
                        const current = new URL(this.state.currentUrl, window.location.origin);
                        if (current.pathname.includes('webmail.php') || current.pathname.includes('right_main.php')) {
                            this.navigate(this.state.currentUrl, false);
                        }
                    }
                    return;
                }

                e.preventDefault();
                this.navigate(url.toString(), true);
            });

            // Popstate: handle browser back / forward
            window.addEventListener('popstate', (e) => {
                const targetUrl = (e.state && e.state.url) ? e.state.url : window.location.href;
                this.navigate(targetUrl, false);
            });
        },

        async navigate(url, pushState = true) {
            this.showLoading();

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html, application/xhtml+xml, application/json'
                    }
                });

                // Check for server redirection header
                const redirectHeader = response.headers.get('X-Redirect-Location') || 
                                       response.headers.get('HX-Redirect') || 
                                       response.headers.get('HX-Location');
                if (redirectHeader) {
                    return this.navigate(redirectHeader, true);
                }

                // Check for JSON redirect
                const contentType = response.headers.get('Content-Type') || '';
                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data.redirect) {
                        return this.navigate(data.redirect, true);
                    }
                }

                const html = await response.text();
                this.renderWorkspace(html, response.url || url);

                if (pushState) {
                    window.history.pushState({ url: url }, '', url);
                }
                this.state.currentUrl = url;

                // Refresh unread counters in sidebar if viewing message or folder
                this.updateSidebarActiveFolder(url);

            } catch (err) {
                console.error('[SquirrelMail Router] Navigation error:', err);
                const container = document.getElementById(this.config.workspaceId);
                if (container) {
                    container.innerHTML = `<div class="sm-alert sm-alert-danger" style="margin:20px; padding:16px; border-radius:8px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;">
                        <strong>Network Error:</strong> Failed to load requested page. Please check your connection.
                    </div>`;
                }
            } finally {
                this.hideLoading();
            }
        },

        renderWorkspace(html, finalUrl) {
            const container = document.getElementById(this.config.workspaceId);
            if (!container) return;

            // Parse response HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Extract title if present
            const titleElem = doc.querySelector('title');
            if (titleElem && titleElem.textContent) {
                document.title = titleElem.textContent;
            }

            // Extract fragment: if the response contains #sm-workspace-content or #sm-workspace, extract only inner content
            let contentFragment = '';
            const incomingWorkspace = doc.getElementById('sm-workspace-content') || doc.getElementById('sm-workspace');

            if (incomingWorkspace) {
                contentFragment = incomingWorkspace.innerHTML;
            } else {
                // If it's a full document without wrapper or partial fragment
                const bodyElem = doc.querySelector('body');
                contentFragment = bodyElem ? bodyElem.innerHTML : html;
            }

            container.innerHTML = contentFragment;

            // Execute any scripts in the fragment safely
            container.querySelectorAll('script').forEach(script => {
                try {
                    if (script.src) {
                        const newScript = document.createElement('script');
                        newScript.src = script.src;
                        document.body.appendChild(newScript);
                        newScript.remove();
                    } else {
                        const code = script.textContent;
                        if (code && code.trim()) {
                            // Indirect eval executes in global scope without binding let/const to permanent global declarative record
                            (0, eval)(code);
                        }
                    }
                } catch (scriptErr) {
                    console.warn('[SquirrelMail Router] Fragment script execution warning:', scriptErr);
                }
            });

            // Enhance newly mounted workspace content
            this.enhanceWorkspace(container);

            // Scroll workspace to anchor or top
            const scrollContainer = document.getElementById('sm-workspace');
            try {
                const parsedFinal = new URL(finalUrl, window.location.origin);
                if (parsedFinal.hash) {
                    const targetEl = container.querySelector(parsedFinal.hash);
                    if (targetEl) {
                        targetEl.scrollIntoView({ behavior: 'smooth' });
                    } else if (scrollContainer) {
                        scrollContainer.scrollTop = 0;
                    }
                } else if (scrollContainer) {
                    scrollContainer.scrollTop = 0;
                }
            } catch (e) {
                if (scrollContainer) scrollContainer.scrollTop = 0;
            }
        },

        // -------------------------------------------------------------------------
        // Asynchronous Form Submissions (Preserving CSRF Tokens)
        // -------------------------------------------------------------------------
        setupFormInterception() {
            let lastClickedSubmitter = null;
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('button[type="submit"], input[type="submit"], button:not([type])');
                if (btn) {
                    lastClickedSubmitter = btn;
                }
            }, true);

            document.addEventListener('submit', async (e) => {
                const form = e.target;
                if (!form || form.getAttribute('target') === '_blank') return;

                const submitter = e.submitter || lastClickedSubmitter;
                // Important: Do NOT use submitter.formAction because HTML standard returns document.baseURI
                // when the formaction content attribute is not present. Use getAttribute('formaction')!
                let action = (submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || form.action || window.location.href;
                if (action) action = action.replace(/&amp;/g, '&');
                // If relative action, resolve against base + 'src/'
                const currentContextUrl = this.state.currentUrl || window.location.href;
                if (action && !/^https?:\/\/|^\/\//i.test(action)) {
                    if (action.startsWith('/')) {
                        action = window.location.origin + action;
                    } else if (action.startsWith('../')) {
                        action = window.location.origin + this.getBaseUri() + action.replace(/^\.\.\//, '');
                    } else if (action.startsWith('src/') || action.startsWith('plugins/')) {
                        action = window.location.origin + this.getBaseUri() + action;
                    } else if (form.closest('#sm-workspace') && currentContextUrl.includes('/plugins/')) {
                        action = new URL(action, currentContextUrl).href;
                    } else {
                        action = window.location.origin + this.getBaseUri() + 'src/' + action;
                    }
                }
                if (action) action = action.replace(/&amp;/g, '&');

                // Do not intercept auth, redirect, signout, install, or forms outside the SPA shell
                if (form.id === 'login_form' || 
                    form.name === 'login_form' || 
                    action.includes('redirect.php') || 
                    action.includes('signout.php') || 
                    action.includes('install.php') ||
                    !document.getElementById(this.config.workspaceId)) {
                    return;
                }

                e.preventDefault();
                this.showLoading();

                const method = (form.method || 'POST').toUpperCase();
                let formData;
                try {
                    formData = submitter ? new FormData(form, submitter) : new FormData(form);
                } catch (err) {
                    formData = new FormData(form);
                }
                if (submitter && submitter.name && !formData.has(submitter.name)) {
                    formData.append(submitter.name, submitter.value || '');
                }

                try {
                    let response;
                    if (method === 'GET') {
                        const urlObj = new URL(action, window.location.origin);
                        const formParams = new URLSearchParams(formData);
                        for (const [key, val] of formParams.entries()) {
                            urlObj.searchParams.set(key, val);
                        }
                        return this.navigate(urlObj.href, true);
                    } else {
                        response = await fetch(action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html, application/xhtml+xml, application/json'
                            }
                        });
                    }

                    // Check for redirect header
                    const redirectHeader = response.headers.get('X-Redirect-Location') || 
                                           response.headers.get('HX-Redirect') || 
                                           response.headers.get('HX-Location');
                    if (redirectHeader) {
                        return this.navigate(redirectHeader, true);
                    }

                    // Check for JSON redirect or response
                    const contentType = response.headers.get('Content-Type') || '';
                    if (contentType.includes('application/json')) {
                        const data = await response.json();
                        if (data.message && typeof this.showToast === 'function') {
                            this.showToast(data.message, data.success ? 'success' : 'error');
                        }
                        this.refreshFolders();
                        if (data.redirect) {
                            return this.navigate(data.redirect, true);
                        }
                        return;
                    }

                    const html = await response.text();
                    this.renderWorkspace(html, response.url || action);

                    // Refresh folders unread badges (e.g. after moving/deleting/sending)
                    this.refreshFolders();

                } catch (err) {
                    console.error('[SquirrelMail Form] Submission failed:', err);
                } finally {
                    this.hideLoading();
                }
            });
        },

        // -------------------------------------------------------------------------
        // Untrusted HTML Email Sandboxing via Shadow DOM
        // -------------------------------------------------------------------------
        enhanceWorkspace(container) {
            if (!container) return;

            // 1. Mount Sandboxed Shadow DOM for email bodies
            this.mountShadowDomEmails(container);

            // 2. Setup Drag-and-drop attachments for compose forms
            this.setupDragAndDropAttachments(container);

            // 3. Setup message list selection helpers
            this.setupMessageListHelpers(container);
        },

        mountShadowDomEmails(container) {
            const shadowHosts = container.querySelectorAll('.sm-email-shadow-container, #sm-email-shadow-host');
            shadowHosts.forEach(host => {
                if (host.shadowRoot) return; // Already attached

                const rawTemplate = host.querySelector('.sm-email-raw-template');
                if (!rawTemplate) return;

                const rawHtml = rawTemplate.innerHTML;
                const shadow = host.attachShadow({ mode: 'open' });

                // Scoped reset stylesheet inside shadow root
                const style = document.createElement('style');
                style.textContent = `
                    :host {
                        display: block;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        font-size: 14.5px;
                        line-height: 1.6;
                        color: #1e293b;
                        background-color: #ffffff;
                        padding: 18px 20px;
                        border-radius: 8px;
                        overflow-x: auto;
                        word-break: break-word;
                    }
                    img {
                        max-width: 100%;
                        height: auto;
                        vertical-align: middle;
                    }
                    a {
                        color: #2563eb;
                        text-decoration: underline;
                    }
                    blockquote {
                        margin: 1em 0;
                        padding-left: 14px;
                        border-left: 3px solid #cbd5e1;
                        color: #64748b;
                    }
                    pre, code {
                        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                        font-size: 13px;
                        background-color: #f1f5f9;
                        border-radius: 4px;
                        padding: 2px 4px;
                    }
                    pre {
                        padding: 12px;
                        white-space: pre-wrap;
                        word-break: break-word;
                        overflow-x: auto;
                    }
                    table {
                        max-width: 100%;
                        border-collapse: collapse;
                    }
                `;
                shadow.appendChild(style);

                // Sanitize HTML with DOMPurify
                let sanitizedHtml = rawHtml;
                let hasRemoteImages = false;

                if (window.DOMPurify) {
                    DOMPurify.addHook('afterSanitizeAttributes', function (node) {
                        // Open all links safely in a new tab
                        if (node.tagName === 'A' && node.hasAttribute('href')) {
                            node.setAttribute('target', '_blank');
                            node.setAttribute('rel', 'noopener noreferrer');
                        }
                        // Detect and shield remote tracking images
                        if (node.tagName === 'IMG' && node.hasAttribute('src')) {
                            const src = node.getAttribute('src');
                            if (/^https?:\/\//i.test(src)) {
                                hasRemoteImages = true;
                                node.setAttribute('data-blocked-src', src);
                                // Set transparent 1x1 pixel placeholder
                                node.setAttribute('src', 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%23cbd5e1"><rect width="16" height="16" rx="2"/></svg>');
                                node.style.border = '1px dashed #cbd5e1';
                                node.style.borderRadius = '4px';
                            }
                        }
                    });

                    sanitizedHtml = DOMPurify.sanitize(rawHtml, {
                        FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea'],
                        FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur'],
                        ALLOW_DATA_ATTR: true
                    });

                    DOMPurify.removeHook('afterSanitizeAttributes');
                }

                const bodyWrap = document.createElement('div');
                bodyWrap.className = 'sm-shadow-body-content';
                bodyWrap.innerHTML = sanitizedHtml;
                shadow.appendChild(bodyWrap);

                // Remote image warning banner
                if (hasRemoteImages) {
                    const banner = document.createElement('div');
                    banner.className = 'sm-remote-images-banner';
                    banner.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 16px;">🛡️</span>
                            <span>Remote images were blocked to protect your privacy.</span>
                        </div>
                        <button type="button" class="sm-btn sm-btn-sm sm-btn-primary" style="font-size: 12px; padding: 4px 10px;">
                            Load Remote Images
                        </button>
                    `;
                    host.parentNode.insertBefore(banner, host);

                    banner.querySelector('button').addEventListener('click', () => {
                        shadow.querySelectorAll('img[data-blocked-src]').forEach(img => {
                            img.src = img.getAttribute('data-blocked-src');
                            img.style.border = '';
                        });
                        banner.remove();
                    });
                }
            });
        },

        // -------------------------------------------------------------------------
        // Drag and Drop File Attachments
        // -------------------------------------------------------------------------
        setupDragAndDropAttachments(container) {
            const dropzone = container.querySelector('.sm-dropzone');
            const fileInput = container.querySelector('input[type="file"]');
            if (!dropzone || !fileInput) return;

            ['dragenter', 'dragover'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    fileInput.files = files;
                    const event = new Event('change', { bubbles: true });
                    fileInput.dispatchEvent(event);
                }
            });

            dropzone.addEventListener('click', () => {
                fileInput.click();
            });
        },

        // -------------------------------------------------------------------------
        // Message List Check-All & Row Highlighting
        // -------------------------------------------------------------------------
        setupMessageListHelpers(container) {
            const checkAll = container.querySelector('#checkall, input[name="checkall"], #toggleAll');
            if (checkAll) {
                checkAll.addEventListener('change', () => {
                    container.querySelectorAll('input[type="checkbox"][name^="msg["]').forEach(cb => {
                        cb.checked = checkAll.checked;
                        const row = cb.closest('tr');
                        if (row) {
                            row.classList.toggle('selected', cb.checked);
                            row.classList.toggle('clicked', cb.checked);
                        }
                    });
                });
            }

            container.querySelectorAll('input[type="checkbox"][name^="msg["]').forEach(cb => {
                cb.addEventListener('change', () => {
                    const row = cb.closest('tr');
                    if (row) {
                        row.classList.toggle('selected', cb.checked);
                        row.classList.toggle('clicked', cb.checked);
                    }
                });
            });

            // Native mouseenter / mouseleave eliminates any browser mouseout boundary glitches
            container.querySelectorAll('.table_messageList tr.sm-message-row, .table_messageList tr.even, .table_messageList tr.odd, tr.sm-message-row').forEach(row => {
                row.addEventListener('mouseenter', () => row.classList.add('mouse_over'));
                row.addEventListener('mouseleave', () => row.classList.remove('mouse_over'));
            });
        },

        // -------------------------------------------------------------------------
        // Folder Tree & Badge Auto-Updating
        // -------------------------------------------------------------------------
        async refreshFolders(targetUrl) {
            const refreshIcon = document.querySelector('.sm-folder-refresh-btn svg');
            try {
                this.showLoading();
                if (refreshIcon) refreshIcon.classList.add('sm-spin');

                const base = this.getBaseUri();
                const fetchUrl = targetUrl || (base + 'src/left_main.php?ajax=1');
                const response = await fetch(fetchUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newTree = doc.querySelector('.sqm_leftMain') || doc.querySelector('.sm-sidebar-folders-wrapper') || doc.querySelector('.sm-sidebar-tree-container');
                const curTree = document.querySelector('.sqm_leftMain') || document.querySelector('.sm-sidebar-folders-wrapper') || document.querySelector('.sm-sidebar-tree-container');

                if (newTree && curTree) {
                    curTree.innerHTML = newTree.innerHTML;
                }

                if (typeof window.sqmRefreshMultiCounts === 'function') {
                    window.sqmRefreshMultiCounts();
                }
            } catch (err) {
                console.error('[SquirrelMail] Failed to refresh folders:', err);
            } finally {
                if (refreshIcon) refreshIcon.classList.remove('sm-spin');
                this.hideLoading();
            }
        },

        updateSidebarActiveFolder(url) {
            try {
                const parsed = new URL(url, window.location.origin);
                let mailbox = parsed.searchParams.get('mailbox');
                if (!mailbox) {
                    const rf = parsed.searchParams.get('right_frame');
                    if (rf && rf.includes('mailbox=')) {
                        const rfParams = new URLSearchParams(rf.includes('?') ? rf.split('?')[1] : rf);
                        mailbox = rfParams.get('mailbox');
                    }
                }
                if (!mailbox) return;

                const targetMb = decodeURIComponent(mailbox).trim().toLowerCase();

                document.querySelectorAll('#sm-sidebar a').forEach(link => {
                    const linkHref = link.getAttribute('href') || '';
                    if (!linkHref.includes('mailbox=')) return;
                    try {
                        const linkUrl = new URL(link.href, window.location.origin);
                        const linkBox = linkUrl.searchParams.get('mailbox');
                        if (linkBox && decodeURIComponent(linkBox).trim().toLowerCase() === targetMb) {
                            link.classList.add('active');
                        } else {
                            link.classList.remove('active');
                        }
                    } catch(e) {}
                });
            } catch(err) {}
        }
    };

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => App.init());
    } else {
        App.init();
    }

})(window, document);
