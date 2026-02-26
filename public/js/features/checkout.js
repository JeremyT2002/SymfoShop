/**
 * Checkout page functionality
 * Handles checkout form submission
 */
import { getByDataJs } from '../core/utils.js';

export function init() {
    const placeOrderBtn = document.getElementById('place-order-btn');
    if (!placeOrderBtn) return;
    
    placeOrderBtn.addEventListener('click', function() {
        const customerForm = document.getElementById('customer-form');
        const addressForm = document.getElementById('address-form');
        const button = this;
        const textSpan = button.querySelector('.place-order-text');
        
        if (customerForm && addressForm) {
            // Show loading state
            button.disabled = true;
            if (textSpan) {
                textSpan.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            }
            
            // Show global loading overlay if available
            if (window.showLoading) {
                window.showLoading();
            }
            
            // Submit customer form which will include address form data via handleRequest
            customerForm.submit();
        }
    });
}

