/**
 * Checkout page functionality
 * Merges customer + address into one POST (two HTML forms, one request).
 */
export function init() {
    const placeOrderBtn = document.getElementById('place-order-btn');
    if (!placeOrderBtn) return;

    placeOrderBtn.addEventListener('click', function () {
        const customerForm = document.getElementById('customer-form');
        const addressForm = document.getElementById('address-form');
        const button = this;
        const textSpan = button.querySelector('.place-order-text');

        if (!customerForm || !addressForm) return;

        if (!customerForm.checkValidity()) {
            customerForm.reportValidity();
            return;
        }
        if (!addressForm.checkValidity()) {
            addressForm.reportValidity();
            return;
        }

        customerForm.querySelectorAll('.checkout-merge-field').forEach((el) => el.remove());

        const addressData = new FormData(addressForm);
        for (const [name, value] of addressData.entries()) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = typeof value === 'string' ? value : '';
            hidden.className = 'checkout-merge-field';
            customerForm.appendChild(hidden);
        }

        button.disabled = true;
        if (textSpan) {
            textSpan.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>…';
        }

        if (window.showLoading) {
            window.showLoading();
        }

        customerForm.submit();
    });
}
