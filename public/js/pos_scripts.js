document.addEventListener('DOMContentLoaded', function () {
    const searchQueryInput = document.getElementById('posSearchQueryInput');
    const searchForm = document.getElementById('posSearchForm');
    let searchDebounceTimer;

    // Restore cursor position and focus
    if (searchQueryInput) {
        const cursorPos = sessionStorage.getItem('posSearchCursorPos');
        if (cursorPos !== null) {
            searchQueryInput.focus(); // Focus first
            searchQueryInput.setSelectionRange(parseInt(cursorPos), parseInt(cursorPos));
            sessionStorage.removeItem('posSearchCursorPos'); // Clean up
        } else {
            searchQueryInput.focus();
        }
    }

    // --- TOAST FUNCTIONALITY ---
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-notification alert-${type}`;

        const messageElement = document.createElement('div');
        messageElement.className = 'toast-message';
        messageElement.textContent = message;
        toast.appendChild(messageElement);

        const timerBar = document.createElement('div');
        timerBar.className = 'toast-timer-bar';
        toast.appendChild(timerBar);

        container.prepend(toast);

        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        const toastTimeout = 5000;
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => {
                if (toast.parentNode) {
                     toast.remove();
                }
            }, { once: true });
        }, toastTimeout);
    }

    // Check for PHP feedback (passed via window.posConfig) and display as toast
    if (window.posConfig && window.posConfig.feedback && window.posConfig.feedback.message) {
        showToast(window.posConfig.feedback.message, window.posConfig.feedback.type);
    }

    // JavaScript to show modals based on PHP config
    if (window.posConfig) {
        if (window.posConfig.showModal === 'quantity' && window.posConfig.isProductForModalAvailable) {
            var quantityModalEl = document.getElementById('quantityModal');
            if (quantityModalEl) {
                var quantityModal = new bootstrap.Modal(quantityModalEl);
                quantityModal.show();
            }
        } else if (window.posConfig.showModal === 'payment' && window.posConfig.isCartNotEmpty) {
             var paymentModalEl = document.getElementById('paymentModal');
             if (paymentModalEl) {
                var paymentModal = new bootstrap.Modal(paymentModalEl);
                paymentModal.show();
             }
        }
    }

    // Payment modal UI enhancements
    const paymentMethodSelect = document.getElementById('payment_method');
    const cashFieldsDiv = document.getElementById('cashFieldsContainer');
    const referenceFieldsDiv = document.getElementById('referenceFieldsContainer');
    const amountGivenInput = document.getElementById('amount_given');
    const changeDueDisplay = document.getElementById('change_due_display');
    const totalDue = (window.posConfig && typeof window.posConfig.currentCartTotal !== 'undefined') ? parseFloat(window.posConfig.currentCartTotal) : 0;


    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            const method = this.value;
            cashFieldsDiv.style.display = (method === 'Cash') ? 'block' : 'none';
            referenceFieldsDiv.style.display = (method === 'Card' || method === 'E-wallet') ? 'block' : 'none';
            if (amountGivenInput) amountGivenInput.required = (method === 'Cash');
            if (method !== 'Cash') {
                if (amountGivenInput) amountGivenInput.value = '';
                if (changeDueDisplay) changeDueDisplay.textContent = '0.00';
            }
        });
        if (paymentMethodSelect.value) { // Trigger on load if a value is pre-selected
            paymentMethodSelect.dispatchEvent(new Event('change'));
        }
    }

    if (amountGivenInput) {
        amountGivenInput.addEventListener('input', function() {
            if (paymentMethodSelect && paymentMethodSelect.value === 'Cash') {
                const given = parseFloat(this.value) || 0;
                const change = (given >= totalDue) ? (given - totalDue) : 0;
                if (changeDueDisplay) changeDueDisplay.textContent = change.toFixed(2);
            }
        });
    }

    // Auto-submit search form on input
    if (searchQueryInput && searchForm) {
        searchQueryInput.addEventListener('input', function() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                sessionStorage.setItem('posSearchCursorPos', searchQueryInput.selectionStart);
                searchForm.submit();
            }, 300);
        });
    }
});