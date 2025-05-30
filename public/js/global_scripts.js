document.addEventListener('DOMContentLoaded', function() {
    // Live Clock Functionality
    const clockElement = document.getElementById('liveClock');
    function updateClock() {
        if (clockElement) {
            const now = new Date();
            const options = {
                timeZone: 'Asia/Manila',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            clockElement.textContent = now.toLocaleTimeString('en-US', options) + ' PST';
        }
    }
    if (clockElement) {
        updateClock();
        setInterval(updateClock, 1000);
    }

    // Attendance Button Functionality
    const timeInButton = document.getElementById('timeInButton');
    const timeOutButton = document.getElementById('timeOutButton');

    if (timeInButton) {
        timeInButton.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            const form = this.closest('form');
            if (form) {
                form.querySelector('input[name="action"]').value = 'time_in';
                // The form will submit as usual
            }
        });
    }

    if (timeOutButton) {
        timeOutButton.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            const form = this.closest('form');
            if (form) {
                form.querySelector('input[name="action"]').value = 'time_out';
                // The form will submit as usual
            }
        });
    }
});