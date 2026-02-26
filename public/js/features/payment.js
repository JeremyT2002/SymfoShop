/**
 * Payment page functionality
 * Handles Stripe payment integration
 */

export function init() {
    // Check if Stripe is loaded
    if (typeof Stripe === 'undefined') {
        console.error('Stripe.js is not loaded');
        return;
    }
    
    // Get Stripe key from data attribute
    const stripeKeyElement = document.querySelector('[data-stripe-key]');
    if (!stripeKeyElement) {
        console.error('Stripe key not found');
        return;
    }
    
    const stripePublishableKey = stripeKeyElement.dataset.stripeKey;
    const clientSecret = stripeKeyElement.dataset.clientSecret;
    const returnUrl = stripeKeyElement.dataset.returnUrl;
    
    if (!stripePublishableKey || !clientSecret || !returnUrl) {
        console.error('Missing Stripe configuration');
        return;
    }
    
    const stripe = Stripe(stripePublishableKey);
    const elements = stripe.elements();
    
    const paymentElement = elements.create('payment');
    const paymentElementContainer = document.getElementById('payment-element');
    
    if (!paymentElementContainer) {
        console.error('Payment element container not found');
        return;
    }
    
    paymentElement.mount('#payment-element');
    
    const submitButton = document.getElementById('submit-payment');
    const paymentMessage = document.getElementById('payment-message');
    
    if (!submitButton) {
        console.error('Submit button not found');
        return;
    }
    
    submitButton.addEventListener('click', async function() {
        submitButton.disabled = true;
        submitButton.textContent = 'Processing...';
        
        if (paymentMessage) {
            paymentMessage.style.display = 'none';
        }
        
        try {
            const {error} = await stripe.confirmPayment({
                elements,
                clientSecret: clientSecret,
                confirmParams: {
                    return_url: returnUrl,
                },
            });
            
            if (error) {
                if (paymentMessage) {
                    paymentMessage.style.display = 'block';
                    paymentMessage.textContent = error.message;
                    paymentMessage.style.color = '#dc2626';
                }
                submitButton.disabled = false;
                submitButton.textContent = 'Pay Now';
            }
        } catch (error) {
            console.error('Payment error:', error);
            if (paymentMessage) {
                paymentMessage.style.display = 'block';
                paymentMessage.textContent = 'An error occurred. Please try again.';
                paymentMessage.style.color = '#dc2626';
            }
            submitButton.disabled = false;
            submitButton.textContent = 'Pay Now';
        }
    });
}

