/**
 * Cart page functionality
 * Handles quantity updates, item removal, coupon application, and cart clearing
 */
import { showToast } from '../core/toast.js';
import { post, parseJson } from '../core/api.js';
import { getAllByDataJs, getByDataJs } from '../core/utils.js';
import { formatPrice } from '../core/utils.js';

// Get routes from data attributes (check body or first container)
function getCartUpdateUrl() {
    const container = document.querySelector('[data-cart-update-url]');
    return container?.dataset.cartUpdateUrl || document.body.dataset.cartUpdateUrl || '/cart/update';
}

function getCartRemoveUrl() {
    const container = document.querySelector('[data-cart-remove-url]');
    return container?.dataset.cartRemoveUrl || document.body.dataset.cartRemoveUrl || '/cart/remove';
}

function getCartCouponApplyUrl() {
    const container = document.querySelector('[data-cart-coupon-apply-url]');
    return container?.dataset.cartCouponApplyUrl || document.body.dataset.cartCouponApplyUrl || '/cart/coupon/apply';
}

function getCartCouponRemoveUrl() {
    const container = document.querySelector('[data-cart-coupon-remove-url]');
    return container?.dataset.cartCouponRemoveUrl || document.body.dataset.cartCouponRemoveUrl || '/cart/coupon/remove';
}

// Get translation strings from data attributes
function getTranslation(key) {
    return document.body.dataset[key] || key;
}

export function init() {
    // Quantity input change detection and price update
    document.querySelectorAll('input[type="number"][data-variant-id]').forEach(input => {
        const variantId = parseInt(input.dataset.variantId);
        const originalValue = input.value;
        const updateBtn = input.closest('.flex')?.querySelector('[data-js="cart-update-quantity"]');
        const priceElement = document.getElementById('item-total-' + variantId);
        const unitPrice = priceElement ? parseInt(priceElement.dataset.unitPrice) : 0;
        const currency = priceElement ? priceElement.dataset.currency : 'EUR';
        
        // Function to update price display
        const updatePrice = () => {
            if (priceElement && unitPrice > 0) {
                const quantity = parseInt(input.value) || 1;
                const total = (unitPrice * quantity) / 100;
                priceElement.textContent = formatPrice(unitPrice * quantity, currency);
            }
        };
        
        input.addEventListener('input', function() {
            const quantity = parseInt(this.value) || 1;
            if (quantity >= 1) {
                updatePrice();
                if (this.value !== originalValue && updateBtn) {
                    updateBtn.classList.remove('hidden');
                } else if (updateBtn) {
                    updateBtn.classList.add('hidden');
                }
            }
        });
        
        input.addEventListener('change', function() {
            const quantity = parseInt(this.value) || 1;
            if (quantity < 1) {
                this.value = 1;
                updatePrice();
            }
            if (this.value !== originalValue && updateBtn) {
                updateBtn.classList.remove('hidden');
            } else if (updateBtn) {
                updateBtn.classList.add('hidden');
            }
        });
    });

    // Quantity decrease
    getAllByDataJs('cart-quantity-decrease').forEach(btn => {
        btn.addEventListener('click', function() {
            const variantId = parseInt(this.dataset.variantId);
            const input = document.getElementById('quantity-' + variantId);
            if (input) {
                const currentValue = parseInt(input.value);
                if (currentValue > 1) {
                    input.value = currentValue - 1;
                    input.dispatchEvent(new Event('input'));
                    input.dispatchEvent(new Event('change'));
                }
            }
        });
    });

    // Quantity increase
    getAllByDataJs('cart-quantity-increase').forEach(btn => {
        btn.addEventListener('click', function() {
            const variantId = parseInt(this.dataset.variantId);
            const input = document.getElementById('quantity-' + variantId);
            if (input) {
                input.value = parseInt(input.value) + 1;
                input.dispatchEvent(new Event('input'));
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    // Update quantity
    getAllByDataJs('cart-update-quantity').forEach(btn => {
        btn.addEventListener('click', function() {
            const variantId = parseInt(this.dataset.variantId);
            const quantityInput = document.getElementById('quantity-' + variantId);
            const quantity = parseInt(quantityInput?.value);
            
            if (!quantity || quantity < 1) {
                showToast('error', getTranslation('cartQuantityMinError') || 'Quantity must be at least 1');
                return;
            }

            updateCartItem(variantId, quantity, this);
        });
    });

    // Remove item
    getAllByDataJs('cart-remove-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const variantId = parseInt(this.dataset.variantId);
            const button = this;
            
            const confirmMessage = getTranslation('cartRemoveItemConfirm') || 'Remove this item from cart?';
            if (window.showConfirm) {
                window.showConfirm(
                    getTranslation('cartRemoveItem') || 'Remove Item',
                    confirmMessage,
                    () => removeCartItem(variantId, button)
                );
            } else {
                if (confirm(confirmMessage)) {
                    removeCartItem(variantId, button);
                }
            }
        });
    });

    // Coupon form submission
    const couponForm = getByDataJs('cart-coupon-form');
    if (couponForm) {
        couponForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const codeInput = document.getElementById('coupon-code');
            const applyBtn = document.getElementById('apply-coupon-btn');
            const errorDiv = document.getElementById('coupon-error');
            const code = codeInput?.value.trim().toUpperCase();
            
            if (!code) {
                if (errorDiv) {
                    errorDiv.textContent = getTranslation('couponCodeRequired') || 'Coupon code is required';
                    errorDiv.classList.remove('hidden');
                }
                return;
            }
            
            if (errorDiv) {
                errorDiv.classList.add('hidden');
            }
            
            if (applyBtn) {
                applyBtn.disabled = true;
                applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
            
            post(getCartCouponApplyUrl(), { code: code })
                .then(parseJson)
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message || getTranslation('couponAppliedSuccess') || 'Coupon applied successfully');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        if (errorDiv) {
                            errorDiv.textContent = data.message || getTranslation('couponError') || 'An error occurred';
                            errorDiv.classList.remove('hidden');
                        }
                        showToast('error', data.message || getTranslation('couponError') || 'An error occurred');
                        if (applyBtn) {
                            applyBtn.disabled = false;
                            applyBtn.innerHTML = getTranslation('couponApply') || 'Apply';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.textContent = getTranslation('couponError') || 'An error occurred';
                        errorDiv.classList.remove('hidden');
                    }
                    showToast('error', getTranslation('couponError') || 'An error occurred');
                    if (applyBtn) {
                        applyBtn.disabled = false;
                        applyBtn.innerHTML = getTranslation('couponApply') || 'Apply';
                    }
                });
        });
    }
    
    // Remove coupon button
    const removeCouponBtn = getByDataJs('cart-remove-coupon');
    if (removeCouponBtn) {
        removeCouponBtn.addEventListener('click', function() {
            post(getCartCouponRemoveUrl(), {})
                .then(parseJson)
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message || getTranslation('couponRemoved') || 'Coupon removed');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showToast('error', data.message || getTranslation('couponError') || 'An error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', getTranslation('couponError') || 'An error occurred');
                });
        });
    }

    // Checkout link handler
    const checkoutLink = document.getElementById('checkout-link');
    if (checkoutLink) {
        checkoutLink.addEventListener('click', function(e) {
            const icon = this.querySelector('.checkout-icon');
            const text = this.querySelector('.checkout-text');
            if (icon) icon.className = 'fas fa-spinner fa-spin mr-2';
            if (text) text.textContent = getTranslation('commonLoading') || 'Loading...';
            this.style.pointerEvents = 'none';
        });
    }

    // Clear cart handler
    const clearCartBtn = document.getElementById('clearCartBtn');
    const clearCartForm = document.getElementById('clearCartForm');
    if (clearCartBtn && clearCartForm) {
        clearCartBtn.addEventListener('click', function() {
            const button = this;
            const originalText = button.querySelector('.clear-cart-text')?.textContent;
            
            const confirmMessage = getTranslation('cartClearCartConfirm') || 'Are you sure you want to clear your cart?';
            if (window.showConfirm) {
                window.showConfirm(
                    getTranslation('cartClearCart') || 'Clear Cart',
                    confirmMessage,
                    () => {
                        button.disabled = true;
                        if (button.querySelector('.clear-cart-text')) {
                            button.querySelector('.clear-cart-text').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + (getTranslation('commonLoading') || 'Loading...');
                        }
                        clearCartForm.submit();
                    }
                );
            } else {
                if (confirm(confirmMessage)) {
                    button.disabled = true;
                    if (button.querySelector('.clear-cart-text')) {
                        button.querySelector('.clear-cart-text').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + (getTranslation('commonLoading') || 'Loading...');
                    }
                    clearCartForm.submit();
                }
            }
        });
    }
}

function updateCartItem(variantId, quantity, button) {
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + (getTranslation('cartUpdating') || 'Updating...');
    
    post(getCartUpdateUrl(), {
        variantId: variantId,
        quantity: quantity
    })
    .then(parseJson)
    .then(data => {
        if (data.success) {
            if (data.totals) {
                updateSummaryTotals(data.totals);
            }
            showToast('success', data.message || getTranslation('cartUpdatedSuccessfully') || 'Cart updated successfully');
            button.classList.add('hidden');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', data.message || 'An error occurred');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>' + (getTranslation('cartUpdate') || 'Update');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', getTranslation('cartErrorUpdating') || 'An error occurred while updating the cart');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>' + (getTranslation('cartUpdate') || 'Update');
    });
}

function removeCartItem(variantId, button) {
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + (getTranslation('cartRemoving') || 'Removing...');
    
    post(getCartRemoveUrl(), {
        variantId: variantId
    })
    .then(parseJson)
    .then(data => {
        if (data.success) {
            showToast('success', data.message || getTranslation('cartItemRemoved') || 'Item removed from cart');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('error', data.message || 'An error occurred');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-trash mr-1"></i>' + (getTranslation('cartRemove') || 'Remove');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', getTranslation('cartErrorRemoving') || 'An error occurred while removing the item');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-trash mr-1"></i>' + (getTranslation('cartRemove') || 'Remove');
    });
}

function updateSummaryTotals(totals) {
    const itemsLabel = document.getElementById('summary-items-label');
    const itemsTotal = document.getElementById('summary-items-total');
    const subtotal = document.getElementById('summary-subtotal');
    const discountLine = document.getElementById('discount-line');
    const discountAmount = document.getElementById('summary-discount');
    
    if (itemsLabel && totals.totalQuantity !== undefined) {
        itemsLabel.textContent = `${getTranslation('cartItems') || 'Items'} (${totals.totalQuantity}):`;
    }
    
    if (itemsTotal && totals.subtotal !== undefined && totals.currency) {
        itemsTotal.textContent = formatPrice(totals.subtotal, totals.currency);
    }
    
    // Update discount line
    if (totals.discount !== undefined && totals.discount > 0) {
        if (discountLine) {
            discountLine.classList.remove('hidden');
        }
        if (discountAmount && totals.currency) {
            discountAmount.textContent = '-' + formatPrice(totals.discount, totals.currency);
        }
    } else {
        if (discountLine) {
            discountLine.classList.add('hidden');
        }
    }
    
    if (subtotal && totals.subtotal !== undefined && totals.currency) {
        const finalSubtotal = totals.subtotal - (totals.discount || 0);
        subtotal.textContent = formatPrice(finalSubtotal, totals.currency);
    }
}

