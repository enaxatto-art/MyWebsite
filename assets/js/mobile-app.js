// Mobile App-Like Experience JavaScript

// Mobile Navigation
document.addEventListener('DOMContentLoaded', function() {
    initMobileNavigation();
    initBottomNavigation();
    initMobileTables();
    initSwipeGestures();
    initPullToRefresh();
    initPWA();
});

// Mobile Navigation Drawer
function initMobileNavigation() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const drawer = document.querySelector('.mobile-nav-drawer');
    const overlay = document.querySelector('.mobile-nav-overlay');
    const closeBtn = document.querySelector('.mobile-nav-header .close-btn');
    
    if (!menuBtn || !drawer) return;
    
    function openNav() {
        drawer.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeNav() {
        drawer.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    menuBtn.addEventListener('click', openNav);
    if (closeBtn) closeBtn.addEventListener('click', closeNav);
    if (overlay) overlay.addEventListener('click', closeNav);
    
    // Close on menu item click
    const menuItems = drawer.querySelectorAll('.mobile-nav-menu a');
    menuItems.forEach(item => {
        item.addEventListener('click', closeNav);
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer.classList.contains('active')) {
            closeNav();
        }
    });
}

// Bottom Navigation
function initBottomNavigation() {
    const bottomNavItems = document.querySelectorAll('.mobile-bottom-nav-item');
    
    bottomNavItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Add active state
            bottomNavItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

// Convert Tables to Mobile Cards
function initMobileTables() {
    if (window.innerWidth > 768) {
        // Show desktop tables, hide mobile cards
        document.querySelectorAll('.table.desktop-only').forEach(t => t.style.display = '');
        document.querySelectorAll('.mobile-card-table').forEach(c => c.style.display = 'none');
        return;
    }
    
    const tables = document.querySelectorAll('.table');
    
    tables.forEach(table => {
        const tableWrapper = table.closest('.mobile-table-wrapper') || table.parentElement;
        const cardContainer = document.createElement('div');
        cardContainer.className = 'mobile-card-table';
        
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        const rows = tbody ? tbody.querySelectorAll('tr') : [];
        
        if (!thead || !tbody) return;
        
        const headers = Array.from(thead.querySelectorAll('th')).map(th => th.textContent.trim());
        
        rows.forEach(row => {
            const card = document.createElement('div');
            card.className = 'mobile-table-card';
            
            const cells = Array.from(row.querySelectorAll('td'));
            
            cells.forEach((cell, index) => {
                if (index < headers.length) {
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'mobile-table-card-row';
                    
                    const label = document.createElement('div');
                    label.className = 'mobile-table-card-label';
                    label.textContent = headers[index];
                    
                    const value = document.createElement('div');
                    value.className = 'mobile-table-card-value';
                    
                    // Copy cell content (including HTML like buttons)
                    if (cell.querySelector('button, a')) {
                        value.innerHTML = cell.innerHTML;
                    } else {
                        value.textContent = cell.textContent.trim();
                    }
                    
                    rowDiv.appendChild(label);
                    rowDiv.appendChild(value);
                    card.appendChild(rowDiv);
                }
            });
            
            cardContainer.appendChild(card);
        });
        
        tableWrapper.appendChild(cardContainer);
    });
}

// Swipe Gestures
function initSwipeGestures() {
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    
    const swipeableElements = document.querySelectorAll('.swipeable, .mobile-nav-drawer, .modal.mobile-modal .modal-content');
    
    swipeableElements.forEach(element => {
        element.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });
        
        element.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe(element, touchStartX, touchStartY, touchEndX, touchEndY);
        }, { passive: true });
    });
    
    function handleSwipe(element, startX, startY, endX, endY) {
        const deltaX = endX - startX;
        const deltaY = endY - startY;
        const minSwipeDistance = 50;
        
        // Horizontal swipe
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance) {
            // Swipe left to close drawer
            if (deltaX < 0 && element.classList.contains('mobile-nav-drawer')) {
                const closeBtn = document.querySelector('.mobile-nav-header .close-btn');
                if (closeBtn) closeBtn.click();
            }
            
            // Swipe left to close modal
            if (deltaX < 0 && element.closest('.modal.mobile-modal')) {
                const closeBtn = element.querySelector('.close-btn');
                if (closeBtn) closeBtn.click();
            }
        }
    }
}

// Pull to Refresh
function initPullToRefresh() {
    const refreshContainers = document.querySelectorAll('.pull-to-refresh');
    let startY = 0;
    let isPulling = false;
    
    refreshContainers.forEach(container => {
        const indicator = container.querySelector('.pull-to-refresh-indicator');
        if (!indicator) return;
        
        container.addEventListener('touchstart', function(e) {
            if (window.scrollY === 0) {
                startY = e.touches[0].pageY;
                isPulling = false;
            }
        }, { passive: true });
        
        container.addEventListener('touchmove', function(e) {
            if (window.scrollY === 0 && startY > 0) {
                const currentY = e.touches[0].pageY;
                const deltaY = currentY - startY;
                
                if (deltaY > 0 && deltaY > 50) {
                    isPulling = true;
                    indicator.classList.add('active');
                    indicator.textContent = 'Pull to refresh...';
                }
            }
        }, { passive: true });
        
        container.addEventListener('touchend', function() {
            if (isPulling) {
                indicator.textContent = 'Refreshing...';
                // Reload page or fetch data
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                indicator.classList.remove('active');
            }
            startY = 0;
            isPulling = false;
        }, { passive: true });
    });
}

// PWA Features
function initPWA() {
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            const swPath = window.location.pathname.includes('/taangi/') 
                ? '/taangi/sw.js' 
                : '/sw.js';
            navigator.serviceWorker.register(swPath)
                .then(function(registration) {
                    console.log('ServiceWorker registration successful');
                })
                .catch(function(err) {
                    console.log('ServiceWorker registration failed', err);
                });
        });
    }
    
    // Add to Home Screen Prompt
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        showInstallButton();
    });
    
    function showInstallButton() {
        // Create install button if it doesn't exist
        let installBtn = document.getElementById('pwa-install-btn');
        if (!installBtn) {
            installBtn = document.createElement('button');
            installBtn.id = 'pwa-install-btn';
            installBtn.className = 'btn btn-primary btn-touch';
            installBtn.innerHTML = '<i class="fas fa-download"></i> Install App';
            installBtn.style.cssText = 'position: fixed; bottom: 100px; right: 1rem; z-index: 1000; border-radius: 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
            document.body.appendChild(installBtn);
            
            installBtn.addEventListener('click', function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function(choiceResult) {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                        }
                        deferredPrompt = null;
                        installBtn.remove();
                    });
                }
            });
        }
    }
}

// Mobile-optimized modals
function initMobileModals() {
    const modals = document.querySelectorAll('.modal');
    
    if (window.innerWidth <= 768) {
        modals.forEach(modal => {
            modal.classList.add('mobile-modal');
        });
    }
}

// Initialize on resize
window.addEventListener('resize', function() {
    initMobileModals();
    initMobileTables();
});

// Prevent zoom on double tap (iOS)
let lastTouchEnd = 0;
document.addEventListener('touchend', function(event) {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);

// Add haptic feedback (if available)
function hapticFeedback(type = 'light') {
    if ('vibrate' in navigator) {
        const patterns = {
            light: 10,
            medium: 20,
            heavy: 50
        };
        navigator.vibrate(patterns[type] || 10);
    }
}

// Add haptic feedback to buttons
document.addEventListener('click', function(e) {
    if (e.target.matches('button, .btn, a.btn')) {
        hapticFeedback('light');
    }
}, true);

// Back button handler (Android)
if (window.history && window.history.pushState) {
    window.addEventListener('popstate', function() {
        // Close modals/drawers on back button
        const drawer = document.querySelector('.mobile-nav-drawer');
        if (drawer && drawer.classList.contains('active')) {
            const closeBtn = document.querySelector('.mobile-nav-header .close-btn');
            if (closeBtn) closeBtn.click();
        }
    });
}

