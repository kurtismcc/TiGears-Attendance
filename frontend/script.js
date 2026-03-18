// Activate test competition mode if toggled (only on non-real competition days)
if (!COMPETITION_MODE && sessionStorage.getItem('competitionTestMode') === '1') {
    COMPETITION_MODE = true;
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.container').classList.add('competition-mode');
        const banner = document.getElementById('testCompBanner');
        if (banner) banner.style.display = '';
        const btn = document.getElementById('testCompModeBtn');
        if (btn) btn.classList.add('active');
        const rosterTitle = document.querySelector('.roster-title');
        if (rosterTitle) rosterTitle.textContent = 'Competition Check-Ins (Test)';
    });
}

// Global variables
let selectedStudentId = null;
let selectedStudentButton = null;
let selectedStudentStatus = null;
let enteredStudentId = '';

// Get DOM elements
const studentButtons = document.querySelectorAll('.roster-item');
const keypadContainer = document.getElementById('keypadContainer');
const confirmBtn = document.getElementById('confirmBtn');
const cancelBtn = document.getElementById('cancelBtn');
const messageDiv = document.getElementById('message');
const studentIdInput = document.getElementById('studentIdInput');
const clearBtn = document.getElementById('clearBtn');
const keypadButtons = document.querySelectorAll('.keypad-btn');
const studentRoster = document.querySelector('.student-roster');
const writeTagBtn = document.getElementById('writeTagBtn');
const nfcWaiting = document.getElementById('nfcWaiting');
const nfcStatus = document.getElementById('nfcStatus');

// NFC WebSocket state
let nfcSocket = null;
let nfcConnected = false;
let isWriteMode = false;

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

        // Show keypad and hide student roster
        enteredStudentId = '';
        studentIdInput.value = '';
        keypadContainer.style.display = 'block';
        studentRoster.style.display = 'none';
        writeTagBtn.style.display = 'none';

        // Update confirm button text based on status and mode
        if (COMPETITION_MODE) {
            confirmBtn.textContent = 'Check In';
            confirmBtn.className = 'action-button confirm sign-in';
        } else if (selectedStudentStatus === 'in') {
            confirmBtn.textContent = 'Confirm Sign Out';
            confirmBtn.className = 'action-button confirm sign-out';
        } else {
            confirmBtn.textContent = 'Confirm Sign In';
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
        updateWriteTagButton();
    });
});

// Clear button
clearBtn.addEventListener('click', function() {
    enteredStudentId = '';
    studentIdInput.value = '';
    writeTagBtn.style.display = 'none';
    cancelWriteMode();
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

    // Determine action based on current status (competition days are always 'in')
    let action = (COMPETITION_MODE || selectedStudentStatus !== 'in') ? 'in' : 'out';
    recordAttendance(selectedStudentId, action);
});

// Cancel button
cancelBtn.addEventListener('click', function() {
    cancelWriteMode();
    clearSelection();
});

// Write to Tag button
writeTagBtn.addEventListener('click', function() {
    if (!nfcConnected || !selectedStudentId) return;
    if (enteredStudentId !== selectedStudentId) {
        showMessage('Enter correct ID first', 'error');
        return;
    }

    isWriteMode = true;
    nfcWaiting.style.display = 'block';
    writeTagBtn.style.display = 'none';
    nfcSocket.send(JSON.stringify({
        type: 'write_tag',
        student_id: selectedStudentId
    }));
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
    studentRoster.style.display = 'block';
    writeTagBtn.style.display = 'none';
    nfcWaiting.style.display = 'none';
    hideMessage();
}

// Show "Write to Tag" button when entered ID matches selected student and NFC is connected
function updateWriteTagButton() {
    if (nfcConnected && selectedStudentId && enteredStudentId === selectedStudentId && !isWriteMode) {
        writeTagBtn.style.display = '';
    } else {
        writeTagBtn.style.display = 'none';
    }
}

// Cancel any pending write mode
function cancelWriteMode() {
    if (isWriteMode) {
        isWriteMode = false;
        nfcWaiting.style.display = 'none';
        if (nfcSocket && nfcSocket.readyState === WebSocket.OPEN) {
            nfcSocket.send(JSON.stringify({ type: 'cancel_write' }));
        }
    }
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

// ---------------------------------------------------------------------------
// NFC WebSocket Client
// ---------------------------------------------------------------------------

function connectNfc() {
    nfcSocket = new WebSocket('ws://localhost:8765');

    nfcSocket.onopen = function() {
        console.log('NFC bridge connected');
    };

    nfcSocket.onclose = function() {
        nfcConnected = false;
        updateNfcIndicator(false);
        writeTagBtn.style.display = 'none';
        // Auto-reconnect after 3 seconds
        setTimeout(connectNfc, 3000);
    };

    nfcSocket.onerror = function() {
        // onclose will fire after this, which handles reconnect
    };

    nfcSocket.onmessage = function(event) {
        let msg;
        try {
            msg = JSON.parse(event.data);
        } catch (e) {
            return;
        }

        switch (msg.type) {
            case 'reader_status':
                nfcConnected = msg.connected;
                updateNfcIndicator(msg.connected);
                break;

            case 'tag_scan':
                handleTagScan(msg.student_id);
                break;

            case 'write_complete':
                handleWriteComplete(msg.success, msg.student_id);
                break;

            case 'error':
                showMessage(msg.message, 'error');
                break;
        }
    };
}

function updateNfcIndicator(connected) {
    if (connected) {
        nfcStatus.className = 'nfc-status connected';
        nfcStatus.title = 'NFC Reader Connected';
    } else {
        nfcStatus.className = 'nfc-status disconnected';
        nfcStatus.title = 'NFC Reader Disconnected';
    }
}

function handleTagScan(studentId) {
    // Find the roster button for this student
    const button = document.querySelector(`.roster-item[data-student-id="${studentId}"]`);
    if (!button) {
        showMessage('Unknown tag', 'error');
        return;
    }

    // Determine current status and toggle (competition days always check in)
    const currentStatus = button.getAttribute('data-status');
    const action = (COMPETITION_MODE || currentStatus !== 'in') ? 'in' : 'out';

    // Briefly highlight the student in the roster
    button.classList.add('selected');
    setTimeout(() => button.classList.remove('selected'), 2000);

    // Auto sign-in/out
    recordAttendance(studentId, action);
}

function handleWriteComplete(success, studentId) {
    isWriteMode = false;
    nfcWaiting.style.display = 'none';

    if (success) {
        showMessage('Tag written successfully!', 'success');
        setTimeout(() => {
            clearSelection();
        }, 2000);
    } else {
        showMessage('Failed to write tag. Try again.', 'error');
        updateWriteTagButton();
    }
}

// Start NFC connection on page load
connectNfc();

// ---------------------------------------------------------------------------
// Test Competition Mode toggle
// ---------------------------------------------------------------------------

const testCompModeBtn = document.getElementById('testCompModeBtn');
if (testCompModeBtn) {
    testCompModeBtn.addEventListener('click', function() {
        if (sessionStorage.getItem('competitionTestMode') === '1') {
            sessionStorage.removeItem('competitionTestMode');
        } else {
            sessionStorage.setItem('competitionTestMode', '1');
        }
        window.location.reload();
    });
}

// ---------------------------------------------------------------------------
// Competition Mode: Countdown Timers
// ---------------------------------------------------------------------------

if (COMPETITION_MODE) {
    function updateCompetitionTimers() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.roster-item').forEach(button => {
            const timerEl = button.querySelector('.roster-timer');
            if (!timerEl) return;

            const lastCheckin = parseInt(button.dataset.lastCheckin) || 0;

            if (!lastCheckin) {
                timerEl.textContent = 'Not checked in';
                timerEl.className = 'roster-timer timer-none';
                return;
            }

            const elapsed = now - lastCheckin;
            const remaining = 3600 - elapsed;

            if (remaining > 0) {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                timerEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
                // Color based on urgency
                if (remaining > 900) {
                    timerEl.className = 'roster-timer timer-ok';
                } else if (remaining > 300) {
                    timerEl.className = 'roster-timer timer-warn';
                } else {
                    timerEl.className = 'roster-timer timer-urgent';
                }
            } else {
                timerEl.textContent = 'OVERDUE';
                timerEl.className = 'roster-timer timer-overdue';
            }
        });
    }

    updateCompetitionTimers();
    setInterval(updateCompetitionTimers, 1000);
}
