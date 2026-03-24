/**
 * Product page functionality
 * Handles variant selection, add-to-cart, image gallery, and image modal
 */
import { showToast } from '../core/toast.js';
import { post, parseJson } from '../core/api.js';
import { updateCartBadge } from '../components/cart-badge.js';
import { getByDataJs } from '../core/utils.js';

function getMessage(key, fallback) {
    return document.body?.dataset?.[key] || fallback;
}

// Get routes from data attributes
function getCartAddUrl() {
    return document.body.dataset.cartAddUrl || '/cart/add';
}

export function init() {
    // Variant selection
    const variantSelect = document.getElementById('variant-select');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const priceDisplay = document.getElementById('price-display');
    const skuDisplay = document.getElementById('sku-display');
    
    if (variantSelect) {
        variantSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const price = (parseInt(option.dataset.price) / 100).toFixed(2);
            const currency = option.dataset.currency;
            const sku = option.dataset.sku;
            const variantId = parseInt(option.value);
            
            // Update price display
            if (priceDisplay) {
                priceDisplay.textContent = price + ' ' + currency;
            }
            
            // Update SKU
            if (skuDisplay) {
                skuDisplay.textContent = 'SKU: ' + sku;
            }
            
            // Update add to cart button
            if (addToCartBtn) {
                addToCartBtn.dataset.variantId = variantId;
            }
        });
    }
    
    // Add to cart (if not using global handler)
    if (addToCartBtn && !addToCartBtn.dataset.js) {
        addToCartBtn.addEventListener('click', function() {
            const variantId = parseInt(this.dataset.variantId);
            const quantity = 1;
            const button = this;
            const originalHTML = button.innerHTML;
            
            if (!variantId || variantId <= 0) {
                showToast('error', getMessage('msgError', 'Please select a variant'));
                return;
            }
            
            button.disabled = true;
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i><span>${getMessage('msgLoading', 'Loading...')}</span>`;
            
            post(getCartAddUrl(), {
                variantId: variantId,
                quantity: quantity
            })
            .then(parseJson)
            .then(data => {
                if (data.success) {
                    showToast('success', data.message || getMessage('msgCartAdded', 'Item added to cart'));
                    if (data.totals?.totalQuantity !== undefined) {
                        updateCartBadge(data.totals.totalQuantity);
                    }
                    setTimeout(() => {
                        button.disabled = false;
                        button.innerHTML = originalHTML;
                    }, 1500);
                } else {
                    showToast('error', data.message || getMessage('msgError', 'An error occurred'));
                    button.disabled = false;
                    button.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', getMessage('msgCartAddError', 'An error occurred while adding to cart'));
                button.disabled = false;
                button.innerHTML = originalHTML;
            });
        });
    }
    
    // Image gallery - thumbnail click
    document.querySelectorAll('[data-js="product-thumbnail"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const imageSrc = this.dataset.imageSrc || this.querySelector('img')?.src;
            if (imageSrc) {
                changeMainImage(imageSrc, this);
            }
        });
    });
    
    // Image modal - main image click
    const mainImage = document.getElementById('main-product-image');
    if (mainImage) {
        mainImage.addEventListener('click', function() {
            openImageModal(this.src);
        });
    }
}

// Image gallery function
function changeMainImage(src, button) {
    const mainImg = document.getElementById('main-product-image');
    if (mainImg) {
        mainImg.src = src;
    }
    
    // Update active thumbnail
    document.querySelectorAll('[data-js="product-thumbnail"]').forEach(btn => {
        btn.classList.remove('border-primary-600', 'ring-2', 'ring-primary-200');
        btn.classList.add('border-gray-200');
    });
    if (button) {
        button.classList.add('border-primary-600', 'ring-2', 'ring-primary-200');
        button.classList.remove('border-gray-200');
    }
}

// Image modal function
function openImageModal(src) {
    const modal = document.getElementById('image-modal');
    if (!modal) {
        // Create modal if it doesn't exist
        const modalHTML = `
            <div id="image-modal" class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center hidden" onclick="closeImageModal()">
                <div class="max-w-4xl max-h-full p-4">
                    <img src="${src}" alt="Product image" class="max-w-full max-h-[90vh] object-contain">
                </div>
                <button type="button" class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300" onclick="closeImageModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const newModal = document.getElementById('image-modal');
        if (newModal) {
            newModal.classList.remove('hidden');
        }
    } else {
        const modalImg = modal.querySelector('img');
        if (modalImg) {
            modalImg.src = src;
        }
        modal.classList.remove('hidden');
    }
}

function closeImageModal() {
    const modal = document.getElementById('image-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Expose globally for backward compatibility
window.changeMainImage = changeMainImage;
window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;

