// LernovaAI - Main JavaScript File

// Make sure functions are available globally immediately
(function() {
    'use strict';
    
    // Confirmation Modal System - Define early so it's available for inline handlers
    window.showConfirmModal = function(message, onConfirm, onCancel) {
        try {
            // Function to actually show the modal
            function showModal() {
                // Check if Bootstrap is available
                if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
                    // Fallback to browser confirm if Bootstrap not loaded
                    if (confirm(message)) {
                        if (onConfirm) onConfirm();
                    } else {
                        if (onCancel) onCancel();
                    }
                    return;
                }
                
                const modal = document.getElementById('confirmModal');
                const modalMessage = document.getElementById('confirmModalMessage');
                
                if (!modal || !modalMessage) {
                    // Fallback to browser confirm if modal not found
                    if (confirm(message)) {
                        if (onConfirm) onConfirm();
                    } else {
                        if (onCancel) onCancel();
                    }
                    return;
                }
                
                // Set the message
                modalMessage.textContent = message;
                
                // Dispose of any existing modal instance
                let bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.dispose();
                }
                
                // Create new modal instance
                bsModal = new bootstrap.Modal(modal, {
                    backdrop: 'static',
                    keyboard: false
                });
                
                // Get the confirm button (it should exist in the modal)
                const confirmBtn = modal.querySelector('#confirmModalConfirmBtn');
                
                // Remove all existing event listeners from confirm button by cloning
                if (confirmBtn) {
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    
                    // Add event listener to the new button
                    newConfirmBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        bsModal.hide();
                        if (onConfirm) {
                            setTimeout(function() {
                                onConfirm();
                            }, 150); // Small delay to allow modal to close
                        }
                    });
                }
                
                // Handle cancel (modal close) - use once option
                const handleHidden = function() {
                    if (onCancel) {
                        onCancel();
                    }
                };
                
                // Remove any existing listeners and add new one
                modal.removeEventListener('hidden.bs.modal', handleHidden);
                modal.addEventListener('hidden.bs.modal', handleHidden, { once: true });
                
                // Show modal
                bsModal.show();
            }
            
            // Execute immediately if DOM and Bootstrap are ready
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                    showModal();
                } else {
                    // Wait for Bootstrap to load
                    let attempts = 0;
                    const maxAttempts = 50;
                    const checkBootstrap = setInterval(function() {
                        attempts++;
                        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                            clearInterval(checkBootstrap);
                            showModal();
                        } else if (attempts >= maxAttempts) {
                            clearInterval(checkBootstrap);
                            showModal(); // Will use browser confirm fallback
                        }
                    }, 100);
                }
            } else {
                // Wait for DOM
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                            showModal();
                        } else {
                            let attempts = 0;
                            const maxAttempts = 50;
                            const checkBootstrap = setInterval(function() {
                                attempts++;
                                if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
                                    clearInterval(checkBootstrap);
                                    showModal();
                                } else if (attempts >= maxAttempts) {
                                    clearInterval(checkBootstrap);
                                    showModal();
                                }
                            }, 100);
                        }
                    }, 100);
                });
            }
        } catch (error) {
            console.error('Error showing confirmation modal:', error);
            // Fallback to browser confirm on error
            if (confirm(message)) {
                if (onConfirm) onConfirm();
            } else {
                if (onCancel) onCancel();
            }
        }
    };
    
    // Toast Notification System - Define early
    window.showToast = function(message, type = 'info', duration = 5000) {
        const toastContainer = document.querySelector('.toast-container');
        const toastElement = document.getElementById('toastNotification');
        const toastMessage = document.getElementById('toastMessage');
        
        if (!toastElement || !toastMessage) {
            // Fallback to alert if toast not found
            alert(message);
            return;
        }
        
        // Clone toast to create a new instance
        const newToast = toastElement.cloneNode(true);
        newToast.id = 'toastNotification_' + Date.now();
        const newToastMessage = newToast.querySelector('.toast-body');
        const newToastHeader = newToast.querySelector('.toast-header');
        const newToastIcon = newToastHeader.querySelector('i');
        
        // Set message
        newToastMessage.textContent = message;
        
        // Set type/color
        const typeClasses = {
            'success': { icon: 'bi-check-circle-fill', color: 'text-success' },
            'error': { icon: 'bi-exclamation-triangle-fill', color: 'text-danger' },
            'warning': { icon: 'bi-exclamation-circle-fill', color: 'text-warning' },
            'info': { icon: 'bi-info-circle-fill', color: 'text-primary' }
        };
        
        const typeConfig = typeClasses[type] || typeClasses['info'];
        newToastIcon.className = `bi ${typeConfig.icon} ${typeConfig.color} me-2`;
        
        // Add to container
        toastContainer.appendChild(newToast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(newToast, {
            autohide: true,
            delay: duration
        });
        bsToast.show();
        
        // Remove from DOM after hidden
        newToast.addEventListener('hidden.bs.toast', function() {
            newToast.remove();
        });
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    console.log('LernovaAI System Loaded');
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Add fade-in animation to elements
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach(function(element, index) {
        element.style.animationDelay = (index * 0.1) + 's';
    });
    
    // Handle data-confirm attributes with custom modal
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm');
            const href = this.getAttribute('href') || this.getAttribute('data-href');
            if (href && message) {
                showConfirmModal(message, function() {
                    window.location.href = href;
                });
            }
        });
    });
    
    // Handle confirmation buttons (delete, logout, etc.) with data-message attribute
    function setupConfirmationButtons() {
        // Single event delegation handler for all confirmation buttons
        document.addEventListener('click', function(e) {
            // Check if clicked element or parent has data-message attribute
            let target = e.target;
            while (target && target !== document) {
                // Check if it's a link or button with data-message
                if (target.hasAttribute && target.hasAttribute('data-message')) {
                    const message = target.getAttribute('data-message');
                    const href = target.getAttribute('href') || target.getAttribute('data-href');
                    
                    // Only proceed if we have both message and href
                    if (message && href) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        // Call confirmation modal
                        if (typeof window.showConfirmModal === 'function') {
                            window.showConfirmModal(message, function() {
                                window.location.href = href;
                            });
                        } else {
                            // Fallback to browser confirm
                            if (confirm(message)) {
                                window.location.href = href;
                            }
                        }
                        return false;
                    }
                    break;
                }
                target = target.parentElement;
            }
        }, true); // Use capture phase to catch early
        
        // Also attach directly as backup for immediate buttons
        const confirmButtons = document.querySelectorAll('[data-message]');
        confirmButtons.forEach(function(button) {
            const href = button.getAttribute('href') || button.getAttribute('data-href');
            if (href) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    const message = this.getAttribute('data-message');
                    const href = this.getAttribute('href') || this.getAttribute('data-href');
                    
                    if (message && href) {
                        if (typeof window.showConfirmModal === 'function') {
                            window.showConfirmModal(message, function() {
                                window.location.href = href;
                            });
                        } else {
                            // Fallback to browser confirm
                            if (confirm(message)) {
                                window.location.href = href;
                            }
                        }
                    }
                    return false;
                }, true); // Use capture phase
            }
        });
    }
    
    // Setup confirmation buttons when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupConfirmationButtons);
    } else {
        setupConfirmationButtons();
    }
    
    // Tooltip initialization (if using Bootstrap tooltips)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Animation for stats cards
    const statsCards = document.querySelectorAll('.stats-card');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
            }
        });
    }, observerOptions);
    
    statsCards.forEach(card => observer.observe(card));
    
    console.log('LernovaAI Initialization Complete');
});

// Add CSS animation for fadeInUp
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Functions are now defined at the top of the file in the IIFE

