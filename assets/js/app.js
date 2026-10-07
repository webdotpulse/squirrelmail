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
                if (theme === 'light' && (href.includes('dark.css') || href.includes('night.css'))) {
                    customTheme.disabled = true;
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

                // Handle empty_trash.php action: Purge Trash
                if (url.pathname.endsWith('/empty_trash.php')) {
                    e.preventDefault();
                    this.showLoading();
                    fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        this.refreshFolders();
                        // If current workspace is viewing the Trash mailbox, reload it to reflect empty trash
                        const current = new URL(this.state.currentUrl, window.location.origin);
                        if (current.pathname.includes('webmail.php') || current.pathname.includes('right_main.php')) {
                            this.navigate(this.state.currentUrl, false);
                        }
                        this.showToast('Trash emptied successfully.');
                    })
                    .catch(err => {
                        console.error('Error emptying trash:', err);
                        this.showToast('Failed to empty trash.', 'error');
                    })
                    .finally(() => {
                        this.hideLoading();
                    });
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

                // Safety guard: left_main.php is the sidebar fragment and must NEVER be rendered inside #sm-workspace
                const effectiveUrl = response.url || url;
                if (effectiveUrl.includes('/left_main.php')) {
                    this.refreshFolders();
                    return;
                }

                const html = await response.text();
                this.renderWorkspace(html, effectiveUrl);

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
                    const type = (script.getAttribute('type') || '').toLowerCase().trim();
                    if (type && type !== 'text/javascript' && type !== 'application/javascript' && type !== 'module') {
                        return;
                    }
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

            // 4. Setup HTML Signature Formatting Toolbar & Visual WYSIWYG Editor
            this.setupHtmlSignatureEditors(container);
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
        // HTML Signature Dual-Mode WYSIWYG & Formatting Toolbar
        // -------------------------------------------------------------------------
        setupHtmlSignatureEditors(container) {
            if (!container) return;

            const textareas = container.querySelectorAll('textarea[name*="html_signature"], textarea[name*="html_sig"]');
            if (!textareas.length) return;

            const studioUrl = this.getBaseUri() + 'plugins/signature_creator/options.php';

            textareas.forEach(ta => {
                if (ta.dataset.smSigEditorInit === 'true') return;
                ta.dataset.smSigEditorInit = 'true';

                // Wrap textarea in rich editor component
                const wrapper = document.createElement('div');
                wrapper.className = 'sm-sig-editor-wrapper';

                // Modern formatting toolbar
                const toolbar = document.createElement('div');
                toolbar.className = 'sm-sig-toolbar';
                toolbar.innerHTML = `
                    <div class="sm-sig-toolbar-group">
                        <button type="button" class="sm-sig-btn" data-cmd="undo" title="Undo (Ctrl+Z)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn" data-cmd="redo" title="Redo (Ctrl+Y)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                        </button>
                    </div>
                    <div class="sm-sig-toolbar-sep"></div>
                    <div class="sm-sig-toolbar-group">
                        <select class="sm-sig-select sm-sig-font-family" title="Font Family">
                            <option value="">Font Family</option>
                            <option value="Arial, Helvetica, sans-serif">Arial</option>
                            <option value="'Segoe UI', Roboto, Helvetica, sans-serif">Segoe UI</option>
                            <option value="Georgia, serif">Georgia</option>
                            <option value="'Times New Roman', Times, serif">Times New Roman</option>
                            <option value="'Courier New', Courier, monospace">Courier New</option>
                            <option value="Verdana, Geneva, sans-serif">Verdana</option>
                            <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                        </select>
                        <select class="sm-sig-select sm-sig-font-size" title="Font Size">
                            <option value="">Size</option>
                            <option value="1">Small</option>
                            <option value="3">Normal</option>
                            <option value="5">Large</option>
                            <option value="6">Huge</option>
                        </select>
                    </div>
                    <div class="sm-sig-toolbar-sep"></div>
                    <div class="sm-sig-toolbar-group">
                        <button type="button" class="sm-sig-btn" data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>
                        <button type="button" class="sm-sig-btn" data-cmd="italic" title="Italic (Ctrl+I)"><i style="font-family:serif;">I</i></button>
                        <button type="button" class="sm-sig-btn" data-cmd="underline" title="Underline (Ctrl+U)"><u>U</u></button>
                        <button type="button" class="sm-sig-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                    </div>
                    <div class="sm-sig-toolbar-sep"></div>
                    <div class="sm-sig-toolbar-group">
                        <label class="sm-sig-btn sm-sig-color-btn" title="Text Color">
                            <span class="sm-sig-color-label">A</span>
                            <span class="sm-sig-color-bar sm-sig-text-color-bar"></span>
                            <input type="color" class="sm-sig-color-input sm-sig-text-color-picker" value="#1e293b">
                        </label>
                        <label class="sm-sig-btn sm-sig-color-btn" title="Highlight / Background Color">
                            <span class="sm-sig-color-label" style="font-size:11px;">🎨</span>
                            <span class="sm-sig-color-bar sm-sig-bg-color-bar" style="background-color:#ffff00;"></span>
                            <input type="color" class="sm-sig-color-input sm-sig-bg-color-picker" value="#ffff00">
                        </label>
                    </div>
                    <div class="sm-sig-toolbar-sep"></div>
                    <div class="sm-sig-toolbar-group">
                        <button type="button" class="sm-sig-btn" data-cmd="justifyLeft" title="Align Left">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn" data-cmd="justifyCenter" title="Align Center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn" data-cmd="justifyRight" title="Align Right">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="sm-sig-toolbar-sep"></div>
                    <div class="sm-sig-toolbar-group">
                        <button type="button" class="sm-sig-btn" data-cmd="insertUnorderedList" title="Bulleted List">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><line x1="4" y1="6" x2="4.01" y2="6"/><line x1="4" y1="12" x2="4.01" y2="12"/><line x1="4" y1="18" x2="4.01" y2="18"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn" data-cmd="insertOrderedList" title="Numbered List">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn sm-sig-btn-hr" title="Insert Divider Line (HR)">
                            <span style="font-weight:700; line-height:1;">—</span>
                        </button>
                    </div>
                    <div class="sm-sig-toolbar-sep"></div>
                    <div class="sm-sig-toolbar-group">
                        <button type="button" class="sm-sig-btn sm-sig-btn-link" title="Insert / Edit Link">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn sm-sig-btn-img" title="Insert Image / Logo URL">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="sm-sig-btn" data-cmd="removeFormat" title="Clear Formatting">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                        </button>
                    </div>
                    <div class="sm-sig-toolbar-spacer"></div>
                    <div class="sm-sig-tabs">
                        <button type="button" class="sm-sig-tab sm-sig-tab-visual active" title="Visual WYSIWYG Mode">👁️ Visual</button>
                        <button type="button" class="sm-sig-tab sm-sig-tab-source" title="Raw HTML Source Mode">&lt;/&gt; HTML Source</button>
                    </div>
                `;

                // Contenteditable Visual Surface
                const visualEditor = document.createElement('div');
                visualEditor.className = 'sm-sig-visual-editor';
                visualEditor.contentEditable = 'true';
                visualEditor.spellcheck = true;
                visualEditor.setAttribute('role', 'textbox');
                visualEditor.setAttribute('aria-multiline', 'true');
                visualEditor.setAttribute('placeholder', 'Enter or format your rich HTML signature here...');
                visualEditor.innerHTML = ta.value || '';

                // Live Email Preview Box
                const previewCard = document.createElement('div');
                previewCard.className = 'sm-sig-preview-card';
                previewCard.innerHTML = `
                    <div class="sm-sig-preview-header">
                        <div class="sm-sig-preview-title">
                            <span class="sm-sig-preview-dot"></span>
                            <span class="sm-sig-preview-label">LIVE SIGNATURE PREVIEW</span>
                        </div>
                        <a href="${studioUrl}" class="sm-sig-studio-link" title="Launch Advanced Signature & Template Studio">
                            ✒️ Signature Studio
                        </a>
                    </div>
                    <div class="sm-sig-preview-body"></div>
                `;

                const previewBody = previewCard.querySelector('.sm-sig-preview-body');

                // Re-parent elements inside wrapper
                ta.parentNode.insertBefore(wrapper, ta);
                wrapper.appendChild(toolbar);
                wrapper.appendChild(visualEditor);
                wrapper.appendChild(ta);
                wrapper.appendChild(previewCard);

                // Configure textarea as source editor
                ta.classList.add('sm-sig-source-editor');
                ta.style.display = 'none';

                // Helpers
                const updatePreview = () => {
                    const content = (visualEditor.style.display !== 'none' ? visualEditor.innerHTML : ta.value).trim();
                    if (content && content !== '<br>' && content !== '<p><br></p>') {
                        previewBody.innerHTML = content;
                    } else {
                        previewBody.innerHTML = '<span class="sm-sig-empty-placeholder">(Signature is currently empty)</span>';
                    }
                };

                const syncVisualToTextarea = () => {
                    ta.value = visualEditor.innerHTML;
                    updatePreview();
                };

                const syncTextareaToVisual = () => {
                    visualEditor.innerHTML = ta.value;
                    updatePreview();
                };

                // Prevent toolbar buttons from stealing focus from contenteditable
                toolbar.querySelectorAll('button, .sm-sig-btn').forEach(btn => {
                    btn.addEventListener('mousedown', (e) => {
                        if (!btn.classList.contains('sm-sig-tab')) {
                            e.preventDefault();
                        }
                    });
                });

                // Mode switcher tabs
                const tabVisual = toolbar.querySelector('.sm-sig-tab-visual');
                const tabSource = toolbar.querySelector('.sm-sig-tab-source');
                const visualOnlyControls = toolbar.querySelectorAll('[data-cmd], .sm-sig-select, .sm-sig-color-btn, .sm-sig-btn-hr, .sm-sig-btn-link, .sm-sig-btn-img');

                tabVisual.addEventListener('click', () => {
                    if (tabVisual.classList.contains('active')) return;
                    syncTextareaToVisual();
                    ta.style.display = 'none';
                    visualEditor.style.display = 'block';
                    tabVisual.classList.add('active');
                    tabSource.classList.remove('active');
                    visualOnlyControls.forEach(c => c.removeAttribute('disabled'));
                    visualEditor.focus();
                });

                tabSource.addEventListener('click', () => {
                    if (tabSource.classList.contains('active')) return;
                    syncVisualToTextarea();
                    visualEditor.style.display = 'none';
                    ta.style.display = 'block';
                    tabSource.classList.add('active');
                    tabVisual.classList.remove('active');
                    visualOnlyControls.forEach(c => c.setAttribute('disabled', 'disabled'));
                    ta.focus();
                });

                // Format commands
                toolbar.querySelectorAll('button[data-cmd]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const cmd = btn.getAttribute('data-cmd');
                        visualEditor.focus();
                        document.execCommand(cmd, false, null);
                        syncVisualToTextarea();
                    });
                });

                // Font Family
                const fontSelect = toolbar.querySelector('.sm-sig-font-family');
                if (fontSelect) {
                    fontSelect.addEventListener('change', () => {
                        if (fontSelect.value) {
                            visualEditor.focus();
                            document.execCommand('fontName', false, fontSelect.value);
                            syncVisualToTextarea();
                        }
                    });
                }

                // Font Size
                const sizeSelect = toolbar.querySelector('.sm-sig-font-size');
                if (sizeSelect) {
                    sizeSelect.addEventListener('change', () => {
                        if (sizeSelect.value) {
                            visualEditor.focus();
                            document.execCommand('fontSize', false, sizeSelect.value);
                            syncVisualToTextarea();
                        }
                    });
                }

                // Text Color Picker
                const textColorPicker = toolbar.querySelector('.sm-sig-text-color-picker');
                const textColorBar = toolbar.querySelector('.sm-sig-text-color-bar');
                if (textColorPicker) {
                    textColorPicker.addEventListener('input', () => {
                        textColorBar.style.backgroundColor = textColorPicker.value;
                        visualEditor.focus();
                        document.execCommand('foreColor', false, textColorPicker.value);
                        syncVisualToTextarea();
                    });
                }

                // Background Color Picker
                const bgColorPicker = toolbar.querySelector('.sm-sig-bg-color-picker');
                const bgColorBar = toolbar.querySelector('.sm-sig-bg-color-bar');
                if (bgColorPicker) {
                    bgColorPicker.addEventListener('input', () => {
                        bgColorBar.style.backgroundColor = bgColorPicker.value;
                        visualEditor.focus();
                        if (!document.execCommand('hiliteColor', false, bgColorPicker.value)) {
                            document.execCommand('backColor', false, bgColorPicker.value);
                        }
                        syncVisualToTextarea();
                    });
                }

                // Insert Link
                const btnLink = toolbar.querySelector('.sm-sig-btn-link');
                if (btnLink) {
                    btnLink.addEventListener('click', () => {
                        const sel = window.getSelection();
                        const selectedText = sel ? sel.toString().trim() : '';
                        const url = prompt('Enter Web Address / URL (e.g. https://example.com):', 'https://');
                        if (url && url !== 'https://') {
                            visualEditor.focus();
                            if (selectedText.length > 0) {
                                document.execCommand('createLink', false, url);
                            } else {
                                const text = prompt('Enter link display text:', url);
                                const linkHtml = `<a href="${url}" target="_blank" rel="noopener noreferrer" style="color:#2563eb; text-decoration:underline;">${text || url}</a>`;
                                document.execCommand('insertHTML', false, linkHtml);
                            }
                            visualEditor.querySelectorAll('a').forEach(a => {
                                a.setAttribute('target', '_blank');
                                a.setAttribute('rel', 'noopener noreferrer');
                            });
                            syncVisualToTextarea();
                        }
                    });
                }

                // Insert Image / Logo
                const btnImg = toolbar.querySelector('.sm-sig-btn-img');
                if (btnImg) {
                    btnImg.addEventListener('click', () => {
                        const imgUrl = prompt('Enter Image / Logo URL (e.g. https://example.com/logo.png):', 'https://');
                        if (imgUrl && imgUrl !== 'https://') {
                            visualEditor.focus();
                            const imgHtml = `<img src="${imgUrl}" alt="Signature Logo" style="max-height:48px; max-width:240px; vertical-align:middle; margin:4px 0;" /><br>`;
                            if (!document.execCommand('insertHTML', false, imgHtml)) {
                                visualEditor.innerHTML += imgHtml;
                            }
                            syncVisualToTextarea();
                        }
                    });
                }

                // Insert Divider Line
                const btnHr = toolbar.querySelector('.sm-sig-btn-hr');
                if (btnHr) {
                    btnHr.addEventListener('click', () => {
                        visualEditor.focus();
                        const hrHtml = '<hr style="border:none; border-top:1px solid #cbd5e1; margin:8px 0;" />';
                        if (!document.execCommand('insertHTML', false, hrHtml)) {
                            visualEditor.innerHTML += hrHtml;
                        }
                        syncVisualToTextarea();
                    });
                }

                // Two-way synchronization event handlers
                visualEditor.addEventListener('input', syncVisualToTextarea);
                visualEditor.addEventListener('keyup', syncVisualToTextarea);
                visualEditor.addEventListener('blur', syncVisualToTextarea);
                visualEditor.addEventListener('paste', () => setTimeout(syncVisualToTextarea, 20));

                ta.addEventListener('input', syncTextareaToVisual);
                ta.addEventListener('keyup', syncTextareaToVisual);
                ta.addEventListener('change', syncTextareaToVisual);

                // Form submission listener ensures textarea has current contents before serialization
                const form = ta.form || ta.closest('form');
                if (form) {
                    form.addEventListener('submit', () => {
                        if (visualEditor.style.display !== 'none') {
                            ta.value = visualEditor.innerHTML;
                        } else {
                            visualEditor.innerHTML = ta.value;
                        }
                    }, true);
                }

                // Initial preview render
                updatePreview();
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
