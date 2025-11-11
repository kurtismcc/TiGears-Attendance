// Global variables
let selectedStudentId = null;
let selectedStudentButton = null;

// Get DOM elements
const studentButtons = document.querySelectorAll('.student-item');
const actionButtons = document.getElementById('actionButtons');
const signInBtn = document.getElementById('signInBtn');
const signOutBtn = document.getElementById('signOutBtn');
const cancelBtn = document.getElementById('cancelBtn');
const messageDiv = document.getElementById('message');

// Add click event to all student buttons
studentButtons.forEach(button => {
    button.addEventListener('click', function() {
        // Remove previous selection
        if (selectedStudentButton) {
            selectedStudentButton.classList.remove('selected');
        }

        // Select current student
        selectedStudentId = this.getAttribute('data-student-id');
        selectedStudentButton = this;
        this.classList.add('selected');

        // Show action buttons
        actionButtons.style.display = 'flex';

        // Clear any previous message
        hideMessage();

        // Scroll action buttons into view
        actionButtons.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
});

// Sign In button
signInBtn.addEventListener('click', function() {
    if (selectedStudentId) {
        recordAttendance(selectedStudentId, 'in');
    }
});

// Sign Out button
signOutBtn.addEventListener('click', function() {
    if (selectedStudentId) {
        recordAttendance(selectedStudentId, 'out');
    }
});

// Cancel button
cancelBtn.addEventListener('click', function() {
    clearSelection();
});

// Function to record attendance
function recordAttendance(studentId, action) {
    fetch('../backend/attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            student_id: studentId,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            clearSelection();
            // Reload page after 2 seconds to update student lists
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showMessage('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('Network error. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Function to show message
function showMessage(message, type) {
    messageDiv.textContent = message;
    messageDiv.className = 'message show ' + type;

    // Scroll message into view
    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Don't auto-hide if page will reload
    if (type === 'error') {
        setTimeout(hideMessage, 3000);
    }
}

// Function to hide message
function hideMessage() {
    messageDiv.className = 'message';
}

// Function to clear selection
function clearSelection() {
    if (selectedStudentButton) {
        selectedStudentButton.classList.remove('selected');
    }
    selectedStudentId = null;
    selectedStudentButton = null;
    actionButtons.style.display = 'none';
}

// Prevent double-tap zoom on buttons
document.querySelectorAll('button').forEach(button => {
    button.addEventListener('touchend', function(e) {
        e.preventDefault();
        this.click();
    }, { passive: false });
});
