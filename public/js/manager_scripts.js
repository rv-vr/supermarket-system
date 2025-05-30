document.addEventListener('DOMContentLoaded', function () {
    // --- Manage Users Page Specific Scripts ---
    const roleSelect = document.getElementById('role');
    const vendorSection = document.getElementById('vendor_assignment_section');
    const vendorSelect = document.getElementById('associated_vendor_name');
    const userRoleVendorValue = 'Vendor'; // Value for Vendor role, ensure this matches UserRole::Vendor->value

    if (roleSelect && vendorSection && vendorSelect) {
        function toggleVendorAssignment() {
            if (roleSelect.value === userRoleVendorValue) {
                vendorSection.style.display = 'block';
                vendorSelect.required = true;
            } else {
                vendorSection.style.display = 'none';
                vendorSelect.required = false;
                vendorSelect.value = ''; // Clear selection
            }
        }

        roleSelect.addEventListener('change', toggleVendorAssignment);
        
        // Initial check on page load (especially for edit form)
        // The PHP script should set data attributes if needed for initial state,
        // or this can be simplified if the initial display is handled by PHP.
        // For now, this JS handles dynamic changes.
        // If editing a user, PHP would have pre-selected the role.
        // We can rely on PHP to set the initial display state of vendorSection
        // or pass the current role via a data attribute if needed for JS to hide/show initially.
        // The provided PHP already handles initial display for edit. This JS is for dynamic changes.
        toggleVendorAssignment(); // Call once to set initial state based on current selection
    }

    // Generic Modal Data Population (Example for Delete User Modal)
    const deleteUserModal = document.getElementById('deleteUserModal');
    if (deleteUserModal) {
        deleteUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const username = button.getAttribute('data-username-delete');
            const modalUsernameInput = deleteUserModal.querySelector('#modal_username_delete');
            const modalUsernameDisplay = deleteUserModal.querySelector('#modal_username_delete_display');
            if(modalUsernameInput) modalUsernameInput.value = username;
            if(modalUsernameDisplay) modalUsernameDisplay.textContent = username;
        });
    }

    // Generic Modal Data Population (Example for Delete Vendor Modal in manage_vendors.php)
    const deleteVendorModal = document.getElementById('deleteVendorModal');
    if (deleteVendorModal) {
        deleteVendorModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const vendorName = button.getAttribute('data-vendor-name-delete');
            const modalVendorNameInput = deleteVendorModal.querySelector('#modal_vendor_name_delete');
            const modalVendorNameDisplay = deleteVendorModal.querySelector('#modal_vendor_name_delete_display');
            if(modalVendorNameInput) modalVendorNameInput.value = vendorName;
            if(modalVendorNameDisplay) modalVendorNameDisplay.textContent = vendorName;
        });
    }


    // --- Manage Schedule Page Specific Scripts ---
    // Tab activation based on URL hash
    const hash = window.location.hash;
    if (hash) {
        let targetTab = hash;
        if (hash === '#time-off-requests') { // Specific case from manage_schedule.php
            targetTab = '#time-off-content';
        }
        const triggerEl = document.querySelector('.nav-tabs button[data-bs-target="' + targetTab + '"]');
        if (triggerEl) {
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
        } else {
             // Fallback for edit_user query param to activate the schedule edit tab
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_user')) {
                const scheduleEditTab = document.querySelector('.nav-tabs button[data-bs-target="#edit-schedule-content"]');
                if (scheduleEditTab) {
                    const tab = new bootstrap.Tab(scheduleEditTab);
                    tab.show();
                }
            }
        }
    } else {
        // Default to the first tab if no hash and not editing user
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('edit_user')) {
            const firstTab = document.querySelector('#scheduleTabs .nav-link');
             if (firstTab && document.getElementById('edit-schedule-content')) { // Ensure it's the schedule page
                const tab = new bootstrap.Tab(firstTab);
                tab.show();
            }
        } else if (urlParams.has('edit_user') && document.getElementById('edit-schedule-content')) {
            // If edit_user is present, ensure the schedule edit tab is active
            const scheduleEditTab = document.querySelector('.nav-tabs button[data-bs-target="#edit-schedule-content"]');
            if (scheduleEditTab) {
                const tab = new bootstrap.Tab(scheduleEditTab);
                tab.show();
            }
        }
    }
});