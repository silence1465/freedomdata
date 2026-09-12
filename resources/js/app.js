document.addEventListener('DOMContentLoaded', () => {
    // Bundle selection — syncs into all payment forms + mobile auto-scroll
    const cards = document.querySelectorAll('.bundle-card');
    const infoBox = document.getElementById('selected-info');

    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            const id = card.dataset.id;
            // Sync product_id into all forms
            ['wallet_product_id', 'paystack_product_id', 'guest_product_id', 'manual_product_id', 'product_id'].forEach(elId => {
                const el = document.getElementById(elId);
                if (el) el.value = id;
            });

            if (infoBox) {
                infoBox.className = 'rounded-xl border-2 border-gold bg-gold-tint px-4 py-4 text-center';
                infoBox.innerHTML = '<div class="font-display font-bold text-lg text-ink">' + card.dataset.network + ' ' + card.dataset.gb + 'GB</div><div class="font-mono text-base font-semibold text-gold-dark">GHS ' + card.dataset.price + '</div>';
            }

            // Mobile: scroll to checkout panel
            if (window.innerWidth < 1024) {
                const panel = document.getElementById('checkout-panel');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    // Sync recipient into hidden form fields on every keystroke
    const recipientInput = document.getElementById('recipient');
    if (recipientInput) {
        recipientInput.addEventListener('input', () => {
            const val = recipientInput.value;
            ['wallet_recipient', 'paystack_recipient', 'guest_recipient', 'manual_recipient'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = val;
            });
        });
    }

    // Sync guest email into hidden field
    const guestEmail = document.getElementById('guest_email');
    if (guestEmail) {
        guestEmail.addEventListener('input', () => {
            const hidden = document.getElementById('guest_email_hidden');
            if (hidden) hidden.value = guestEmail.value;
        });
    }

    // Validate on form submit
    ['wallet-form', 'paystack-form', 'guest-form', 'manual-form'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', (e) => {
                // Sync recipient one last time
                const recipient = document.getElementById('recipient')?.value || '';
                const hiddenRecipient = document.getElementById(formId.replace('-form', '_recipient'));
                if (hiddenRecipient) hiddenRecipient.value = recipient;

                // Sync guest email one last time
                if (formId === 'guest-form') {
                    const email = document.getElementById('guest_email')?.value || '';
                    const hiddenEmail = document.getElementById('guest_email_hidden');
                    if (hiddenEmail) hiddenEmail.value = email;
                }

                // Check product selected
                const productInput = document.getElementById(formId.replace('-form', '_product_id'));
                if (productInput && !productInput.value) {
                    e.preventDefault();
                    alert('Please select a bundle first.');
                    return;
                }
                if (!recipient) {
                    e.preventDefault();
                    alert('Please enter a recipient phone number.');
                    return;
                }
                if (formId === 'guest-form' && !document.getElementById('guest_email')?.value) {
                    e.preventDefault();
                    alert('Please enter your email address.');
                    return;
                }

                // Validation passed — loading overlay will fire from global handler
            });
        }
    });

    // Order status polling
    const statusEl = document.getElementById('order-status');
    const orderId = statusEl?.dataset.orderId;
    if (orderId) {
        setInterval(async () => {
            try {
                const r = await fetch('/track/' + orderId + '/status');
                const data = await r.json();
                const badge = document.getElementById('status-badge');
                if (badge) badge.textContent = data.status;
                if (['DELIVERED', 'FAILED', 'REFUNDED'].includes(data.status)) {
                    location.reload();
                }
            } catch (e) {}
        }, 30000);
    }

    // USDT calculator
    const ghsInput = document.getElementById('ghs_amount');
    const usdtOutput = document.getElementById('usdt_equiv');
    const rateValue = document.getElementById('active_rate');
    if (ghsInput && usdtOutput && rateValue) {
        ghsInput.addEventListener('input', () => {
            const rate = parseFloat(rateValue.value) || 1;
            const ghs = parseFloat(ghsInput.value) || 0;
            usdtOutput.textContent = (ghs / rate).toFixed(4);
        });
    }

    // Loading overlay is handled globally in layouts/app.blade.php
    // Any forms with data-loading="false" will be skipped
});
