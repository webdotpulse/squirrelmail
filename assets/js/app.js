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
            theme: localStorage.getItem('sm_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
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
            localStorage.setItem('sm_theme', theme);

            const toggleBtn = document.getElementById(this.config.themeToggleId);
            if (toggleBtn) {
                toggleBtn.setAttribute('title', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
                toggleBtn.innerHTML = theme === 'dark'
                    ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>'
                    : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
            }
        },

        setupThemeToggle() {
            const toggleBtn = document.getElementById(this.config.themeToggleId);
            if (toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const newTheme = this.state.theme === 'dark' ? 'light' : 'dark';
                    this.applyTheme(newTheme);
                });
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
                menuBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    toggle();
                });
            }

            if (backdrop) {
                backdrop.addEventListener('click', close);
            }

            // Close on link click on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 1024 && e.target.closest('#sm-sidebar a')) {
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

                const url = new URL(link.href, window.location.origin);
                // Check if external domain
                if (url.origin !== window.location.origin) {
                    return;
                }

                // Check for signout or download
                if (url.pathname.includes('signout.php') || url.pathname.includes('download.php')) {
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
                const newScript = document.createElement('script');
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.body.appendChild(newScript);
                newScript.remove();
            });

            // Enhance newly mounted workspace content
            this.enhanceWorkspace(container);

            // Scroll workspace to top
            const scrollContainer = document.getElementById('sm-workspace');
            if (scrollContainer) scrollContainer.scrollTop = 0;
        },

        // -------------------------------------------------------------------------
        // Asynchronous Form Submissions (Preserving CSRF Tokens)
        // -------------------------------------------------------------------------
        setupFormInterception() {
            document.addEventListener('submit', async (e) => {
                const form = e.target;
                if (!form || form.getAttribute('target') === '_blank') return;

                const action = form.action || window.location.href;
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
                const formData = new FormData(form);

                try {
                    let response;
                    if (method === 'GET') {
                        const params = new URLSearchParams(formData).toString();
                        const targetUrl = action.split('?')[0] + (params ? '?' + params : '');
                        return this.navigate(targetUrl, true);
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

                    // Check for JSON redirect
                    const contentType = response.headers.get('Content-Type') || '';
                    if (contentType.includes('application/json')) {
                        const data = await response.json();
                        if (data.redirect) {
                            return this.navigate(data.redirect, true);
                        }
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
            const checkAll = container.querySelector('#checkall, input[name="checkall"]');
            if (checkAll) {
                checkAll.addEventListener('change', () => {
                    container.querySelectorAll('input[type="checkbox"][name^="msg["]').forEach(cb => {
                        cb.checked = checkAll.checked;
                        const row = cb.closest('tr');
                        if (row) row.classList.toggle('selected', cb.checked);
                    });
                });
            }

            container.querySelectorAll('input[type="checkbox"][name^="msg["]').forEach(cb => {
                cb.addEventListener('change', () => {
                    const row = cb.closest('tr');
                    if (row) row.classList.toggle('selected', cb.checked);
                });
            });
        },

        // -------------------------------------------------------------------------
        // Folder Tree & Badge Auto-Updating
        // -------------------------------------------------------------------------
        async refreshFolders() {
            try {
                const response = await fetch('left_main.php?ajax=1', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newTree = doc.querySelector('.sm-sidebar-folders-wrapper') || doc.querySelector('.sm-sidebar-tree-container');
                const curTree = document.querySelector('.sm-sidebar-folders-wrapper') || document.querySelector('.sm-sidebar-tree-container');

                if (newTree && curTree) {
                    curTree.innerHTML = newTree.innerHTML;
                }
            } catch (err) {
                console.error('[SquirrelMail] Failed to refresh folders:', err);
            }
        },

        updateSidebarActiveFolder(url) {
            const parsed = new URL(url, window.location.origin);
            const mailbox = parsed.searchParams.get('mailbox');
            if (!mailbox) return;

            document.querySelectorAll('#sm-sidebar .sm-folder-link').forEach(link => {
                const linkHref = link.getAttribute('href') || '';
                const linkUrl = new URL(link.href, window.location.origin);
                const linkBox = linkUrl.searchParams.get('mailbox');

                if (linkBox && linkBox.toLowerCase() === mailbox.toLowerCase()) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }
    };

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => App.init());
    } else {
        App.init();
    }

})(window, document);
