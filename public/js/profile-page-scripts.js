document.addEventListener('DOMContentLoaded', () => {
    const startEvaluationBtn = document.getElementById('start-evaluation-btn');

    // Check if the button exists on the page
    if (!startEvaluationBtn) {
        return;
    }

    startEvaluationBtn.addEventListener('click', function () {
        // Provide immediate feedback to the user
        this.textContent = 'Checking...';
        this.disabled = true;

        // Get the URL from the data attribute on the button
        const checkUrl = this.dataset.checkUrl;

        fetch(checkUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // --- SCENARIO C: All checks passed ---
                    showConfirmationModal({
                        title: 'Confirm Submission',
                        body: '<p>Are you sure you want to finalize your CCE submissions for evaluation? This action cannot be undone and your submissions will be locked.</p>',
                        confirmText: 'Confirm & Submit',
                        onConfirm: () => {
                            document.getElementById('submit-evaluation-form').submit();
                        }
                    });

                } else {
                    // --- SCENARIOS where submission cannot proceed ---

                    // UPDATED LOGIC: Check for the specific error type first.
                    if (data.error_type === 'rank_missing') {
                        // --- SCENARIO A: Rank is MISSING ---
                        showConfirmationModal({
                            title: 'Rank Not Set',
                            body: `<p>${data.message}</p>`,
                            confirmText: 'Acknowledge',
                            onConfirm: () => {
                                hideConfirmationModal();
                            }
                        });
                        const cancelBtn = document.getElementById('cancelConfirmationBtn');
                        if (cancelBtn) cancelBtn.style.display = 'none';

                    } else if (data.missing && Array.isArray(data.missing)) {
                        // --- SCENARIO B: Submissions are INCOMPLETE ---
                        let errorHtml = '<p>You cannot proceed. Please upload at least one document for the following Key Result Areas:</p><ul class="missing-kra-list">';
                        data.missing.forEach(function(item) {
                            errorHtml += `<li><a href="${item.route}">${item.name}</a></li>`;
                        });
                        errorHtml += '</ul>';

                        showConfirmationModal({
                            title: 'Missing Submissions',
                            body: errorHtml,
                            confirmText: 'Acknowledge',
                            onConfirm: () => {
                                hideConfirmationModal();
                            }
                        });

                        const cancelBtn = document.getElementById('cancelConfirmationBtn');
                        if (cancelBtn) cancelBtn.style.display = 'none';
                    } else {
                        throw new Error('An unknown error occurred during pre-check.');
                    }
                }
            })
            .catch(error => {
                console.error('Error checking submissions:', error);
                showConfirmationModal({
                    title: 'Error',
                    body: '<p>An unexpected error occurred while checking your submissions. Please try again later.</p>',
                    confirmText: 'Close',
                    onConfirm: () => {
                        hideConfirmationModal();
                    }
                });
                const cancelBtn = document.getElementById('cancelConfirmationBtn');
                if (cancelBtn) cancelBtn.style.display = 'none';
            })
            .finally(() => {
                this.textContent = 'Start CCE Evaluation Process';
                this.disabled = false;
            });
    });
});
