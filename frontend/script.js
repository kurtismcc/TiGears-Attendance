// Global variables
let selectedStudentId = null;
let selectedStudentButton = null;
let selectedStudentStatus = null;
let enteredStudentId = '';

// Get DOM elements
const studentButtons = document.querySelectorAll('.student-item');
const keypadContainer = document.getElementById('keypadContainer');
const confirmBtn = document.getElementById('confirmBtn');
const cancelBtn = document.getElementById('cancelBtn');
const messageDiv = document.getElementById('message');
const studentIdInput = document.getElementById('studentIdInput');
const clearBtn = document.getElementById('clearBtn');
const keypadButtons = document.querySelectorAll('.keypad-btn');
const studentLists = document.querySelector('.student-lists');

// Add click event to all student buttons
studentButtons.forEach(button => {
    button.addEventListener('click', function() {
        // Remove previous selection
        if (selectedStudentButton) {
            selectedStudentButton.classList.remove('selected');
        }

        // Select current student
        selectedStudentId = this.getAttribute('data-student-id');
        selectedStudentStatus = this.getAttribute('data-status');
        selectedStudentButton = this;
        this.classList.add('selected');

        // Clear any previous message
        hideMessage();

        // Show keypad and hide student lists
        enteredStudentId = '';
        studentIdInput.value = '';
        keypadContainer.style.display = 'block';
        studentLists.style.display = 'none';

        // Update confirm button text based on status
        if (selectedStudentStatus === 'in') {
            confirmBtn.textContent = 'Confirm Sign Out';
            confirmBtn.className = 'action-button confirm sign-out';
        } else if (selectedStudentStatus === 'out') {
            confirmBtn.textContent = 'Confirm Sign In';
            confirmBtn.className = 'action-button confirm sign-in';
        } else {
            confirmBtn.textContent = 'Confirm First Sign In';
            confirmBtn.className = 'action-button confirm sign-in';
        }

        // Fetch and display student standings
        fetchStudentStandings(selectedStudentId);

        // Scroll keypad into view
        keypadContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
});

// Keypad button clicks
keypadButtons.forEach(button => {
    button.addEventListener('click', function() {
        const value = this.getAttribute('data-value');

        if (value === 'backspace') {
            enteredStudentId = enteredStudentId.slice(0, -1);
        } else if (value && value !== 'backspace') {
            enteredStudentId += value;
        }

        studentIdInput.value = enteredStudentId;
    });
});

// Clear button
clearBtn.addEventListener('click', function() {
    enteredStudentId = '';
    studentIdInput.value = '';
});

// Confirm button
confirmBtn.addEventListener('click', function() {
    if (!selectedStudentId || !selectedStudentStatus) return;

    // Validate student ID
    if (enteredStudentId !== selectedStudentId) {
        showMessage('Incorrect ID', 'error');
        setTimeout(() => {
            clearSelection();
        }, 3000);
        return;
    }

    // Determine action based on current status
    let action = (selectedStudentStatus === 'in') ? 'out' : 'in';
    recordAttendance(selectedStudentId, action);
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
    selectedStudentStatus = null;
    enteredStudentId = '';
    studentIdInput.value = '';
    keypadContainer.style.display = 'none';
    studentLists.style.display = 'grid';
    hideMessage();
}

// Prevent double-tap zoom on buttons
document.querySelectorAll('button').forEach(button => {
    button.addEventListener('touchend', function(e) {
        e.preventDefault();
        this.click();
    }, { passive: false });
});

// Function to fetch and display student standings
function fetchStudentStandings(studentId) {
    // Reset standings display
    document.getElementById('standingConsecutiveRank').textContent = '-';
    document.getElementById('standingConsecutiveValue').textContent = '-';
    document.getElementById('standingScoreRank').textContent = '-';
    document.getElementById('standingScoreValue').textContent = '-';
    document.getElementById('standingTimeRank').textContent = '-';
    document.getElementById('standingTimeValue').textContent = '-';

    fetch(`../backend/student_standings.php?student_id=${encodeURIComponent(studentId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const s = data.standings;
                document.getElementById('standingConsecutiveRank').textContent = s.consecutive.rank;
                document.getElementById('standingConsecutiveValue').textContent = s.consecutive.value;
                document.getElementById('standingScoreRank').textContent = s.score.rank;
                document.getElementById('standingScoreValue').textContent = s.score.value;
                document.getElementById('standingTimeRank').textContent = s.time.rank;
                document.getElementById('standingTimeValue').textContent = s.time.value;
            }
        })
        .catch(error => {
            console.error('Error fetching standings:', error);
        });
}
