document.addEventListener('DOMContentLoaded', () => {
    // --- PART 1: DYNAMIC KRA PAGE LOGIC ---
    const isDynamicPage = !!document.getElementById('criterion-select');
    let activeCriterion = isDynamicPage ? document.getElementById('criterion-select').value : null;

    const getActiveElements = () => {
        const pageId = activeCriterion || document.body.dataset.pageId;
        if (!pageId) return {};
        const table = document.getElementById(`${pageId}-table`);
        return {
            table: table,
            tableBody: table ? table.querySelector('tbody') : document.getElementById('kra-table-body'),
            searchForm: table ? table.querySelector('form') : document.getElementById('kra-search-form'),
            loadMoreButton: document.getElementById('load-more-kra-btn'),
        };
    };

    if (isDynamicPage) {
        document.getElementById('criterion-select').addEventListener('change', (e) => {
            activeCriterion = e.target.value;
            document.querySelectorAll('.performance-metric-container').forEach(t => t.style.display = 'none');
            const activeElements = getActiveElements();
            if (activeElements.table) {
                activeElements.table.style.display = 'block';
                const hasMore = activeElements.tableBody.children.length >= 5;
                activeElements.loadMoreButton.style.display = hasMore ? 'inline-block' : 'none';
                window.loadData(true);
            }
        });
    }

    // --- PART 2: UPLOAD MODAL (TWO-STEP) ---
    document.querySelectorAll('.role-modal-container').forEach(initializeUploadModal);
    document.getElementById('upload-kra-button')?.addEventListener('click', () => {
        const modalId = activeCriterion ? `${activeCriterion}-modal` : 'kra-upload-modal';
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
    });

    // --- PART 3: LOAD MORE & SEARCH ---
    window.loadData = async (isSearch = false) => {
        const active = getActiveElements();
        if (!active.tableBody || !active.loadMoreButton || !active.searchForm) return;
        if (isSearch) {
            active.loadMoreButton.dataset.currentOffset = '0';
            const colspan = active.table.querySelectorAll('thead th').length;
            active.tableBody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center;">Loading...</td></tr>`;
        }
        const offset = parseInt(active.loadMoreButton.dataset.currentOffset, 10);
        const searchTerm = active.searchForm.querySelector('input[name="search"]').value;
        active.loadMoreButton.disabled = true;
        active.loadMoreButton.textContent = 'Loading...';
        try {
            let url = `${window.location.pathname}?ajax=true&offset=${offset}&search=${encodeURIComponent(searchTerm)}`;
            if(isDynamicPage) url += `&criterion=${activeCriterion}`;
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (isSearch) active.tableBody.innerHTML = '';
            if (data.html) active.tableBody.insertAdjacentHTML('beforeend', data.html);
            active.loadMoreButton.dataset.currentOffset = data.nextOffset;
            active.loadMoreButton.style.display = data.hasMore ? 'inline-block' : 'none';
            if (active.tableBody.children.length === 0) {
                 const colspan = active.table.querySelectorAll('thead th').length;
                 active.tableBody.innerHTML = `<tr id="no-results-row"><td colspan="${colspan}" style="text-align: center;">No items found.</td></tr>`;
                 active.loadMoreButton.style.display = 'none';
            } else {
                 active.tableBody.querySelector('#no-results-row')?.remove();
            }
        } catch (error) {
            console.error('Error loading data:', error);
        } finally {
            active.loadMoreButton.disabled = false;
            active.loadMoreButton.textContent = 'Load More +';
        }
    };

    document.querySelectorAll('.search-bar-container form').forEach(form => {
        const searchInput = form.querySelector('input[name="search"]');
        const searchBtnIcon = form.querySelector('i');
        const updateSearchIcon = () => {
            if (!searchBtnIcon) return;
            if (searchInput.value.trim() !== '') {
                searchBtnIcon.classList.remove('fa-magnifying-glass');
                searchBtnIcon.classList.add('fa-xmark');
            } else {
                searchBtnIcon.classList.remove('fa-xmark');
                searchBtnIcon.classList.add('fa-magnifying-glass');
            }
        };
        form.addEventListener('submit', (e) => { e.preventDefault(); window.loadData(true); });
        searchBtnIcon?.addEventListener('click', (e) => {
            if (searchBtnIcon.classList.contains('fa-xmark')) {
                e.preventDefault();
                searchInput.value = '';
                updateSearchIcon();
                window.loadData(true);
            }
        });
        searchInput.addEventListener('input', updateSearchIcon);
        updateSearchIcon();
    });
    
    document.getElementById('load-more-kra-btn')?.addEventListener('click', () => window.loadData(false));
    
    // --- INITIAL PAGE SETUP ---
    if (isDynamicPage) {
        document.querySelectorAll('.performance-metric-container').forEach(t => t.style.display = 'none');
        getActiveElements().table.style.display = 'block';
    }
});

function initializeUploadModal(modal) {
    const form = modal.querySelector('.kra-upload-form');
    if (!form) return;
    
    const numberInputs = form.querySelectorAll('input[type="number"][max]');
    numberInputs.forEach(input => {
        input.addEventListener('input', () => {
            const max = parseFloat(input.getAttribute('max'));
            if (parseFloat(input.value) > max) {
                input.value = max;
            }
        });
    });

    // ===================================================================
    // ================ Conditional Logic for KRA I Modals ===============
    // ===================================================================

    if (modal.id === 'instructional-materials-modal') {
        const categorySelect = form.querySelector('select[name="category"]');
        const typeSelect = form.querySelector('select[name="type"]');
        const typeGroup = typeSelect.closest('.form-group');

        const handleCategoryChange = () => {
            const selectedCategory = categorySelect.value;
            const types = window.instructionalMaterialOptions[selectedCategory] || [];

            typeSelect.innerHTML = '<option value="" disabled selected>Click here to select</option>';

            if (types.length > 0) {
                types.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    typeSelect.appendChild(option);
                });
                typeGroup.style.display = 'flex';
                typeSelect.required = true;
                typeSelect.disabled = false;
            } else {
                typeGroup.style.display = 'none';
                typeSelect.required = false;
                typeSelect.disabled = true;
                typeSelect.value = '';
            }
        };

        categorySelect.addEventListener('change', handleCategoryChange);
        handleCategoryChange();
    }

    if (modal.id === 'mentorship-services-modal') {
        const serviceTypeSelect = form.querySelector('select[name="service_type"]');
        const roleSelect = form.querySelector('select[name="role"]');
        const studentLabel = form.querySelector('#ms-student-label');
        const studentGroup = studentLabel ? studentLabel.closest('.form-group') : null;


        const handleServiceTypeChange = () => {
            const selectedServiceType = serviceTypeSelect.value;
            const roles = window.mentorshipRoleOptions[selectedServiceType] || [];

            if (studentGroup) {
                if (selectedServiceType) {
                    studentGroup.style.display = 'flex';
                    if (selectedServiceType === 'Competition Coaching') {
                        studentLabel.textContent = 'Competition Title *';
                        studentLabel.dataset.label = 'Competition Title';
                    } else {
                        studentLabel.textContent = 'Student Name(s) *';
                        studentLabel.dataset.label = 'Student Name(s)';
                    }
                } else {
                    studentGroup.style.display = 'none';
                }
            }
           
            roleSelect.innerHTML = '<option value="" disabled selected>Click here to select</option>';

            if (roles.length > 0) {
                roles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role;
                    option.textContent = role;
                    roleSelect.appendChild(option);
                });
            } else {
                roleSelect.innerHTML = '<option value="" disabled selected>No roles available</option>';
            }
        };

        serviceTypeSelect.addEventListener('change', handleServiceTypeChange);
        handleServiceTypeChange();
    }

    // ===================================================================
    // ================ Conditional Logic for KRA II Modals ==============
    // ===================================================================

    if (modal.id === 'research-outputs-modal') {
        const categorySelect = form.querySelector('select[name="category"]');
        const indexingGroup = form.querySelector('#ro-indexing-group');
        const doiGroup = form.querySelector('#ro-doi-group');

        const handleCategoryChange = () => {
            const selectedCategory = categorySelect.value;
            const isJournalOrConference = ['Journal Article', 'Conference Paper / Proceedings'].includes(selectedCategory);

            indexingGroup.style.display = isJournalOrConference ? 'flex' : 'none';
            indexingGroup.querySelector('select').disabled = !isJournalOrConference;
            indexingGroup.querySelector('select').required = isJournalOrConference;

            doiGroup.style.display = isJournalOrConference ? 'flex' : 'none';
            doiGroup.querySelector('input').disabled = !isJournalOrConference;
        };

        categorySelect.addEventListener('change', handleCategoryChange);
        handleCategoryChange();
    }

    if (modal.id === 'inventions-creative-works-modal') {
        const typeSelect = form.querySelector('select[name="type"]');
        const subtypeSelect = form.querySelector('select[name="sub_type"]');
        const statusLevelSelect = form.querySelector('select[name="status_level"]');

        const subtypeGroup = subtypeSelect.closest('.form-group');
        const statusLevelGroup = statusLevelSelect.closest('.form-group');

        const updateDropdown = (selectElement, optionsArray) => {
            selectElement.innerHTML = '<option value="" disabled selected>Click here to select</option>';
            if (optionsArray && optionsArray.length > 0) {
                optionsArray.forEach(optionText => {
                    const option = document.createElement('option');
                    option.value = optionText;
                    option.textContent = optionText;
                    selectElement.appendChild(option);
                });
                return true;
            }
            return false;
        };

        const handleTypeChange = () => {
            const selectedType = typeSelect.value;
            const subTypes = window.researchSubTypeOptions[selectedType] || [];
            const statusLevels = window.researchStatusLevelOptions[selectedType] || [];

            const hasSubTypes = updateDropdown(subtypeSelect, subTypes);
            subtypeGroup.style.display = hasSubTypes ? 'flex' : 'none';
            subtypeSelect.disabled = !hasSubTypes;
            subtypeSelect.required = hasSubTypes;

            const hasStatusLevels = updateDropdown(statusLevelSelect, statusLevels);
            statusLevelGroup.style.display = hasStatusLevels ? 'flex' : 'none';
            statusLevelSelect.disabled = !hasStatusLevels;
            statusLevelSelect.required = hasStatusLevels;
        };

        typeSelect.addEventListener('change', handleTypeChange);
        handleTypeChange();
    }
    
    // ===================================================================
    // ================ Conditional Logic for KRA III Modals =============
    // ===================================================================

    if (modal.id === 'service-community-modal') {
        const categorySelect = form.querySelector('select[name="category"]');
        const targetCommunityGroup = form.querySelector('#target-community-group');

        const handleCategoryChange = () => {
            const isCommunityService = categorySelect.value === 'Community Service / Outreach';
            targetCommunityGroup.style.display = isCommunityService ? 'flex' : 'none';
            targetCommunityGroup.querySelector('input').required = isCommunityService;
            targetCommunityGroup.querySelector('input').disabled = !isCommunityService;
             if (!isCommunityService) {
                targetCommunityGroup.querySelector('input').value = '';
            }
        };

        categorySelect.addEventListener('change', handleCategoryChange);
        handleCategoryChange();
    }
    
    if (modal.id === 'admin-designation-modal') {
        const ongoingCheckbox = form.querySelector('#ongoing-checkbox');
        const endDateInput = form.querySelector('input[name="end_date"]');

        const handleOngoingChange = () => {
            const isOngoing = ongoingCheckbox.checked;
            endDateInput.disabled = isOngoing;
            endDateInput.required = !isOngoing;
            if (isOngoing) {
                endDateInput.value = '';
            }
        };

        ongoingCheckbox.addEventListener('change', handleOngoingChange);
        handleOngoingChange();
    }

    // ===================================================================
    // ================ Conditional Logic for KRA IV Modals ==============
    // ===================================================================

    if (modal.id === 'prof-organizations-modal') {
        const isOfficerCheckbox = form.querySelector('#is-officer-checkbox');
        const officerRoleGroup = form.querySelector('#officer-role-group');

        const handleOfficerChange = () => {
            const isOfficer = isOfficerCheckbox.checked;
            officerRoleGroup.style.display = isOfficer ? 'flex' : 'none';
            officerRoleGroup.querySelector('input').required = isOfficer;
            officerRoleGroup.querySelector('input').disabled = !isOfficer;
             if (!isOfficer) {
                officerRoleGroup.querySelector('input').value = '';
            }
        };

        isOfficerCheckbox.addEventListener('change', handleOfficerChange);
        handleOfficerChange();
    }

    if (modal.id === 'prof-training-modal') {
        const typeSelect = form.querySelector('select[name="type"]');
        const hoursGroup = form.querySelector('#training-hours-group');
        const levelGroup = form.querySelector('#training-level-group');
        const nonDegreeTypes = ['Training / Seminar / Workshop', 'Conference / Forum / Symposium'];

        const handleTypeChange = () => {
            const selectedType = typeSelect.value;
            const isNonDegree = nonDegreeTypes.includes(selectedType);

            hoursGroup.style.display = isNonDegree ? 'flex' : 'none';
            hoursGroup.querySelector('input').required = isNonDegree;
            hoursGroup.querySelector('input').disabled = !isNonDegree;

            levelGroup.style.display = !isNonDegree && selectedType ? 'flex' : 'none';
            levelGroup.querySelector('input').required = !isNonDegree && selectedType;
            levelGroup.querySelector('input').disabled = isNonDegree || !selectedType;
            
            if (!isNonDegree) hoursGroup.querySelector('input').value = '';
            if (isNonDegree) levelGroup.querySelector('input').value = '';
        };

        typeSelect.addEventListener('change', handleTypeChange);
        handleTypeChange();
    }


    const closeBtn = modal.querySelector('.close-modal-btn');
    const initialStep = modal.querySelector('.initial-step');
    const confirmationStep = modal.querySelector('.confirmation-step');
    const proceedBtn = modal.querySelector('.proceed-btn');
    const backBtn = modal.querySelector('.back-btn');
    const confirmBtn = modal.querySelector('.confirm-btn');
    const messages = {
        initial: modal.querySelector('.modal-messages'),
        confirmation: modal.querySelector('.confirmation-message-area'),
        finalStatus: modal.querySelector('.final-status-message-area'),
    };
    const showStep = (step) => {
        initialStep.style.display = (step === 'initial') ? 'block' : 'none';
        confirmationStep.style.display = (step === 'confirmation') ? 'block' : 'none';
    };
    const hideModal = () => {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        Object.values(messages).forEach(el => el && (el.innerHTML = ''));
        form.reset();
        
        const conditionalTriggers = form.querySelectorAll(
            'select[name="category"], select[name="service_type"], select[name="type"], #ongoing-checkbox, #is-officer-checkbox'
        );
        conditionalTriggers.forEach(trigger => {
            if (trigger) {
                trigger.dispatchEvent(new Event('change'));
            }
        });

        [confirmBtn, backBtn, closeBtn].forEach(btn => btn && (btn.disabled = false));
        showStep('initial');
    };
    closeBtn.addEventListener('click', hideModal);
    modal.addEventListener('click', (e) => (e.target === modal) && hideModal());
    backBtn.addEventListener('click', () => { showStep('initial'); messages.finalStatus.innerHTML = ''; });
    proceedBtn.addEventListener('click', () => {
        if (messages.initial) messages.initial.innerHTML = '';
        if (!form.checkValidity()) {
            if (messages.initial) messages.initial.innerHTML = '<div class="alert-danger">Please fill out all required fields.</div>';
            return;
        }
        let confirmationHtml = 'Please confirm the following details:<br><br>';
        const formData = new FormData(form);
        formData.forEach((value, key) => {
            if (key.startsWith('_') || key === 'is_officer' || key === 'ongoing') return;
            const input = form.querySelector(`[name="${key}"]`);
            if (!input || input.type === 'hidden' || input.disabled) return;
            const label = input.closest('.form-group,.form-group-checkbox')?.querySelector('[data-label],label')?.dataset.label || key;
            let displayValue = (value instanceof File) ? value.name : value;
            if(input.tagName === 'SELECT' && input.options[input.selectedIndex]) {
                 displayValue = input.options[input.selectedIndex].text;
            }
            confirmationHtml += `<strong>${label}:</strong> ${displayValue}<br>`;
        });
        messages.confirmation.innerHTML = confirmationHtml;
        showStep('confirmation');
    });
    confirmBtn.addEventListener('click', async () => {
        const url = form.getAttribute('action');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const formData = new FormData(form);
        [confirmBtn, backBtn, closeBtn].forEach(btn => btn.disabled = true);
        messages.finalStatus.innerHTML = '<div class="alert-info">Uploading... Please wait.</div>';
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: formData,
            });
            const data = await response.json();
            if (!response.ok) {
                let errorMsg = data.message || 'Error.';
                if (response.status === 422 && data.errors) {
                    errorMsg = Object.values(data.errors).map(err => `<p>${err[0]}</p>`).join('');
                }
                throw new Error(errorMsg);
            }
            messages.finalStatus.innerHTML = `<div class="alert-success">${data.message}</div>`;
            setTimeout(() => { hideModal(); window.loadData(true); }, 1500);
        } catch (error) {
            messages.finalStatus.innerHTML = `<div class="alert-danger">${error.message}</div>`;
            [confirmBtn, backBtn, closeBtn].forEach(btn => btn.disabled = false);
        }
    });

document.addEventListener('DOMContentLoaded', function() {
    // Array of all file input IDs used across the modals
    const fileInputIds = [
        'org-proof-file-input',       // Criterion A: Organizations
        'training-proof-file-input',  // Criterion B: Training
        'awards-proof-file-input'     // Criterion C: Awards
    ];

    fileInputIds.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('change', function() {
                const criterion = this.closest('.role-modal-container').id.replace('prof-', '').replace('-modal', '');
                handleCertificateAutofill(this, criterion);
            });
        }
    });

    function handleCertificateAutofill(fileInput, criterion) {
        const file = fileInput.files[0];
        
        // Dynamically locate the status and message elements based on the criterion
        const statusDiv = document.getElementById('autofill-status-' + criterion);
        const messageSpan = document.getElementById('autofill-message-' + criterion);
        
        // Map the extraction fields to the specific IDs in the active modal
        let fields = {};
        
        if (criterion === 'training') {
            // Criterion B: Continuing Professional Education & Training
            fields = {
                issuer: document.getElementById('training-organizer-input'),
                date: document.getElementById('training-completion-date-input'),
                certHolderName: document.getElementById('training-cert-holder-name')
            };
        } else if (criterion === 'awards') {
            // Criterion C: Awards and Recognitions
            fields = {
                issuer: document.getElementById('awards-awarding-body-input'),
                date: document.getElementById('awards-end-date-input'),
                certHolderName: document.getElementById('awards-cert-holder-name')
            };
        } else if (criterion === 'organizations') {
            // Criterion A: Involvement in Professional Organizations
            fields = {
                issuer: document.getElementById('org-name-input'),
                date: document.getElementById('org-start-date-input'), // Assumes start date is extracted/used
                certHolderName: document.getElementById('org-cert-holder-name')
            };
        } else {
            return;
        }

        if (!file) return;

        // 1. Show loading state in the modal
        statusDiv.style.display = 'block';
        messageSpan.textContent = 'Extracting data... please wait.';
        messageSpan.style.color = '#fff'; 
        
        for (const key in fields) {
            if (fields[key]) fields[key].value = '';
        }

        const formData = new FormData();
        formData.append('proof_file', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content')); 

        fetch('/api/instructor/certificate/autofill', { 
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(error => { 
                    throw new Error(error.message || 'API endpoint error.'); 
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                
                // Populate the main fields
                fields.issuer.value = data.fields.issuer_name || '';
                fields.date.value = data.fields.completion_date || ''; // Populates the Date Completed/Awarded input
                
                // Populate the hidden certificate holder name for the validation check later
                if (fields.certHolderName) {
                    fields.certHolderName.value = data.fields.user_name_on_cert || '';
                }

                messageSpan.textContent = 'Autofill successful! Please review the extracted data.';
                messageSpan.style.color = '#38a169'; // Green
            } else {
                messageSpan.textContent = 'Autofill failed: ' + data.message + ' Enter details manually.';
                messageSpan.style.color = 'red';
            }
        })
        .catch(error => {
            messageSpan.textContent = 'A critical network error occurred. Please try again.';
            messageSpan.style.color = 'red';
            console.error('Extraction Error:', error);
        });
    }
});

}
