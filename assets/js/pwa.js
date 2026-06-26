/**
 * PWA Controller - Frest App
 * Manages service worker lifecycle, updates, connectivity notifications, and custom install prompts.
 */

(function () {
    // 1. Check if PWA is enabled via site configuration
    const config = window.FREST_CONFIG || { siteUrl: '.', pwaEnabled: true };
    if (!config.pwaEnabled) {
        // Auto-unregister service worker if disabled by system administrator
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(registrations => {
                for (let registration of registrations) {
                    registration.unregister().then(success => {
                        if (success) console.log('PWA: Service worker unregistered successfully.');
                    });
                }
            });
        }
        return;
    }

    let deferredPrompt = null;
    let refreshing = false;

    // Helper to get site URL
    const getSiteUrl = () => {
        return config.siteUrl.replace(/\/$/, '');
    };

    // 2. Service Worker Registration and Update Handling
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swUrl = `${getSiteUrl()}/sw.js`;
            
            // Bypass HTTP Cache for Service Worker to ensure instant detection
            navigator.serviceWorker.register(swUrl, { updateViaCache: 'none' })
                .then(reg => {
                    console.log('PWA: SW registered successfully with scope:', reg.scope);

                    // Check for updates periodically or on load
                    if (reg.waiting) {
                        showUpdateNotification(reg.waiting);
                    }

                    reg.addEventListener('updatefound', () => {
                        const newWorker = reg.installing;
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    showUpdateNotification(newWorker);
                                }
                            });
                        }
                    });

                    // Check for service worker updates periodically (every 5 minutes)
                    setInterval(() => {
                        if (navigator.onLine) {
                            reg.update().catch(err => console.log('PWA: Periodic update check failed:', err));
                        }
                    }, 5 * 60 * 1000);

                    // Check for updates when the user returns/focuses on the app (visibility change & window focus)
                    const checkUpdate = () => {
                        if (navigator.onLine) {
                            reg.update().catch(err => console.log('PWA: Manual update check failed:', err));
                        }
                    };
                    
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            checkUpdate();
                        }
                    });
                    
                    window.addEventListener('focus', checkUpdate);
                })
                .catch(err => {
                    console.error('PWA: SW registration failed:', err);
                });
        });

        // Reload the page when the service worker controller changes (skipWaiting completes)
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    }

    // 3. Show PWA Update Toast Notification
    function showUpdateNotification(worker) {
        // Ensure no duplicate update toasts
        const existingToast = document.getElementById('pwa-update-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const updateToast = document.createElement('div');
        updateToast.id = 'pwa-update-toast';
        updateToast.innerHTML = `
            <div class="pwa-update-toast-content">
                <i class="fa-solid fa-cloud-arrow-down pwa-update-toast-icon"></i>
                <div class="pwa-update-toast-info">
                    <div class="pwa-update-toast-title">Đã có phiên bản mới</div>
                    <div class="pwa-update-toast-desc">Cập nhật để trải nghiệm mượt mà nhất</div>
                </div>
                <button class="pwa-update-btn">Cập nhật</button>
            </div>
        `;

        document.body.appendChild(updateToast);

        // Action when clicking update
        const updateBtn = updateToast.querySelector('.pwa-update-btn');
        updateBtn.addEventListener('click', () => {
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang tải...';
            
            // Post message to skipWaiting
            worker.postMessage({ action: 'skipWaiting' });
            
            // Slide up and remove toast smoothly after trigger
            setTimeout(() => {
                updateToast.classList.add('pwa-update-toast-hide');
                setTimeout(() => updateToast.remove(), 300);
            }, 600);
        });
    }

    // 4. Online/Offline Status Notification
    window.addEventListener('online', () => {
        showToastHTML(`
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-wifi" style="color: var(--success); font-size: 14px; flex-shrink: 0;"></i>
                <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">Đã kết nối lại Internet! 🟢</span>
            </div>
        `, 4000);
    });

    window.addEventListener('offline', () => {
        showToastHTML(`
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-wifi-slash" style="color: #f59e0b; font-size: 14px; flex-shrink: 0;"></i>
                <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">Bạn đang ngoại tuyến. Một số tính năng có thể bị giới hạn. 🔌</span>
            </div>
        `, 6000);
    });

    // Helper to display generic toast with custom HTML
    function showToastHTML(htmlContent, duration = 3000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = htmlContent;
        
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px) scale(0.95)';
            toast.style.transition = 'all 0.22s cubic-bezier(0.16, 1, 0.3, 1)';
            setTimeout(() => toast.remove(), 230);
        }, duration);
    }

    // 5. In-App PWA Install Banner Logic
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 76 and older from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        // Show our custom install banner if it hasn't been dismissed
        if (!localStorage.getItem('pwa-prompt-dismissed')) {
            // Show almost immediately (300ms delay for smooth animation layout)
            setTimeout(showInstallBanner, 300);
        }
    });

    function showInstallBanner() {
        // Check if banner already exists
        if (document.getElementById('pwa-install-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner glassmorphism-card';
        
        banner.innerHTML = `
            <div class="pwa-install-content">
                <div class="pwa-install-logo-wrapper">
                    <img src="${config.pwaIcon || (getSiteUrl() + '/assets/images/icons/icon-192x192.png')}" alt="Frest Logo" class="pwa-install-logo">
                </div>
                <div class="pwa-install-text">
                    <h4>Cài đặt Frest App</h4>
                    <p>Trải nghiệm mượt mà, nhắn tin nhanh và hoạt động ngoại tuyến.</p>
                </div>
                <div class="pwa-install-actions">
                    <button id="pwa-install-btn" class="pwa-btn-primary">Cài đặt</button>
                    <button id="pwa-dismiss-btn" class="pwa-btn-secondary"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        `;

        document.body.appendChild(banner);

        // Add event listeners to the buttons
        const installBtn = document.getElementById('pwa-install-btn');
        const dismissBtn = document.getElementById('pwa-dismiss-btn');

        // Automatically hide the install banner after 3 seconds if there is no interaction
        let autoDismissTimer = setTimeout(() => {
            hideInstallBanner();
        }, 3000);

        installBtn.addEventListener('click', () => {
            clearTimeout(autoDismissTimer);
            if (!deferredPrompt) return;
            
            // Show the native browser prompt
            deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('PWA: User accepted the install prompt');
                } else {
                    console.log('PWA: User dismissed the install prompt');
                }
                deferredPrompt = null;
                hideInstallBanner();
            });
        });

        dismissBtn.addEventListener('click', () => {
            clearTimeout(autoDismissTimer);
            // Save dismissal to local storage so it doesn't show again
            localStorage.setItem('pwa-prompt-dismissed', 'true');
            hideInstallBanner();
        });
    }

    function hideInstallBanner() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.classList.add('pwa-banner-hide');
            setTimeout(() => banner.remove(), 400); // Wait for transition to complete
        }
    }

    // Listen to success installation
    window.addEventListener('appinstalled', (evt) => {
        console.log('PWA: App successfully installed to homescreen!');
        hideInstallBanner();
        showToastHTML(`
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check" style="color: var(--success); font-size: 14px; flex-shrink: 0;"></i>
                <span style="font-size: 13px; font-weight: 600; color: var(--text-primary);">Frest App đã được cài đặt thành công! 🎉</span>
            </div>
        `, 4000);
    });
})();
