<?php
require_once '../backend/db.php';

// Get all attendance windows
$result = $conn->query("SELECT id, day_of_week, start_time, end_time, valid_from, valid_to FROM attendance_windows ORDER BY valid_from, day_of_week, start_time");
$windows = [];
while ($row = $result->fetch_assoc()) {
    $windows[] = $row;
}

// Get all competition days
$compResult = $conn->query("SELECT id, date, label FROM competition_days ORDER BY date DESC");
$competitionDays = [];
while ($row = $compResult->fetch_assoc()) {
    $competitionDays[] = $row;
}

// Day names for display
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Attendance Windows</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2em;
            font-weight: bold;
            padding: 12px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0;
            margin: 0;
        }

        .section-content {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 20px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }

        .form-group select,
        .form-group input {
            padding: 10px 15px;
            font-size: 1em;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            touch-action: manipulation;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #da190b 100%);
            color: white;
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .window-list {
            margin-top: 20px;
        }

        .window-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
        }

        .window-item:last-child {
            margin-bottom: 0;
        }

        .window-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .window-day {
            font-weight: bold;
            color: #667eea;
            min-width: 100px;
        }

        .window-time {
            color: #333;
        }

        .empty-message {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
        }

        .nav-links {
            margin-bottom: 15px;
        }

        .nav-links a {
            display: inline-block;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-right: 20px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .admin-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .section-content {
            border: none;
            border-radius: 0;
        }

        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="index.php">&larr; Back to Attendance</a>
            <a href="admin_student.php">Edit Student Records</a>
        </div>

        <div class="header">
            <h1>Manage Attendance Windows</h1>
        </div>

        <div id="message" class="message" style="display: none;"></div>

        <div class="admin-section">
            <h2 class="section-title">Add New Window</h2>
            <div class="section-content">
                <form id="addWindowForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dayOfWeek">Day of Week</label>
                            <select id="dayOfWeek" name="day_of_week" required>
                                <option value="0">Sunday</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="startTime">Start Time</label>
                            <input type="time" id="startTime" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label for="endTime">End Time</label>
                            <input type="time" id="endTime" name="end_time" required>
                        </div>
                        <div class="form-group">
                            <label for="validFrom">Valid From</label>
                            <input type="date" id="validFrom" name="valid_from" value="2000-01-01" required>
                        </div>
                        <div class="form-group">
                            <label for="validTo">Valid To</label>
                            <input type="date" id="validTo" name="valid_to" value="2040-01-01" required>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Add Window</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="admin-section">
            <h2 class="section-title">Competition Days</h2>
            <div class="section-content">
                <p style="margin-bottom:15px; color:#555;">On competition days, students check in every hour. Normal attendance streaks ignore these days.</p>
                <form id="addCompDayForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="compDate">Date</label>
                            <input type="date" id="compDate" name="date" required>
                        </div>
                        <div class="form-group">
                            <label for="compLabel">Label (optional)</label>
                            <input type="text" id="compLabel" name="label" placeholder="e.g. Regional Championship" style="min-width:220px;">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Add Competition Day</button>
                        </div>
                    </div>
                </form>
                <div class="window-list" id="compDayList">
                    <?php if (empty($competitionDays)): ?>
                        <p class="empty-message">No competition days configured yet.</p>
                    <?php else: ?>
                        <?php foreach ($competitionDays as $day): ?>
                            <div class="window-item" data-id="<?php echo $day['id']; ?>">
                                <div class="window-info">
                                    <span class="window-day" style="color:#e67e22;"><?php echo htmlspecialchars($day['date']); ?></span>
                                    <span class="window-time"><?php echo htmlspecialchars($day['label'] ?: '(no label)'); ?></span>
                                </div>
                                <button class="btn btn-danger delete-comp-btn" data-id="<?php echo $day['id']; ?>">Delete</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-section">
            <h2 class="section-title">Current Windows</h2>
            <div class="section-content">
                <div class="window-list" id="windowList">
                    <?php if (empty($windows)): ?>
                        <p class="empty-message">No attendance windows configured yet.</p>
                    <?php else: ?>
                        <?php foreach ($windows as $window): ?>
                            <div class="window-item" data-id="<?php echo $window['id']; ?>">
                                <div class="window-info">
                                    <span class="window-day"><?php echo $dayNames[$window['day_of_week']]; ?></span>
                                    <span class="window-time">
                                        <?php
                                        echo date('g:i A', strtotime($window['start_time']));
                                        echo ' - ';
                                        echo date('g:i A', strtotime($window['end_time']));
                                        ?>
                                    </span>
                                    <span class="window-dates" style="color:#888; font-size:0.85em;">
                                        <?php echo $window['valid_from']; ?> &ndash; <?php echo $window['valid_to']; ?>
                                    </span>
                                </div>
                                <button class="btn btn-danger delete-btn" data-id="<?php echo $window['id']; ?>">Delete</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
            msg.style.display = 'block';
            setTimeout(() => { msg.style.display = 'none'; }, 5000);
        }

        function formatTime(timeStr) {
            const [hours, minutes] = timeStr.split(':');
            const h = parseInt(hours);
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = h % 12 || 12;
            return `${h12}:${minutes} ${ampm}`;
        }

        // Add window form
        document.getElementById('addWindowForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const dayOfWeek = parseInt(document.getElementById('dayOfWeek').value);
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            const validFrom = document.getElementById('validFrom').value;
            const validTo = document.getElementById('validTo').value;

            if (startTime >= endTime) {
                showMessage('Start time must be before end time', 'error');
                return;
            }

            if (validFrom > validTo) {
                showMessage('Valid from date must be before valid to date', 'error');
                return;
            }

            try {
                const response = await fetch('../backend/windows.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        day_of_week: dayOfWeek,
                        start_time: startTime,
                        end_time: endTime,
                        valid_from: validFrom,
                        valid_to: validTo
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Window added successfully!', 'success');

                    // Add to list
                    const list = document.getElementById('windowList');
                    const emptyMsg = list.querySelector('.empty-message');
                    if (emptyMsg) emptyMsg.remove();

                    const item = document.createElement('div');
                    item.className = 'window-item';
                    item.dataset.id = data.id;
                    item.innerHTML = `
                        <div class="window-info">
                            <span class="window-day">${dayNames[dayOfWeek]}</span>
                            <span class="window-time">${formatTime(startTime)} - ${formatTime(endTime)}</span>
                            <span class="window-dates" style="color:#888; font-size:0.85em;">${validFrom} &ndash; ${validTo}</span>
                        </div>
                        <button class="btn btn-danger delete-btn" data-id="${data.id}">Delete</button>
                    `;
                    list.appendChild(item);

                    // Reset form
                    this.reset();
                } else {
                    showMessage(data.message || 'Failed to add window', 'error');
                }
            } catch (err) {
                showMessage('Network error: ' + err.message, 'error');
            }
        });

        // Add competition day
        document.getElementById('addCompDayForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const date = document.getElementById('compDate').value;
            const label = document.getElementById('compLabel').value;

            try {
                const response = await fetch('../backend/competition_days.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date, label })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Competition day added!', 'success');

                    const list = document.getElementById('compDayList');
                    const emptyMsg = list.querySelector('.empty-message');
                    if (emptyMsg) emptyMsg.remove();

                    const item = document.createElement('div');
                    item.className = 'window-item';
                    item.dataset.id = data.id;
                    item.innerHTML = `
                        <div class="window-info">
                            <span class="window-day" style="color:#e67e22;">${date}</span>
                            <span class="window-time">${label || '(no label)'}</span>
                        </div>
                        <button class="btn btn-danger delete-comp-btn" data-id="${data.id}">Delete</button>
                    `;
                    list.prepend(item);
                    this.reset();
                } else {
                    showMessage(data.message || 'Failed to add competition day', 'error');
                }
            } catch (err) {
                showMessage('Network error: ' + err.message, 'error');
            }
        });

        // Delete competition day
        document.getElementById('compDayList').addEventListener('click', async function(e) {
            if (!e.target.classList.contains('delete-comp-btn')) return;

            const id = e.target.dataset.id;
            if (!confirm('Remove this competition day?')) return;

            try {
                const response = await fetch(`../backend/competition_days.php?id=${id}`, {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Competition day removed.', 'success');
                    const item = document.querySelector(`#compDayList .window-item[data-id="${id}"]`);
                    if (item) item.remove();

                    const list = document.getElementById('compDayList');
                    if (list.children.length === 0) {
                        list.innerHTML = '<p class="empty-message">No competition days configured yet.</p>';
                    }
                } else {
                    showMessage(data.message || 'Failed to remove', 'error');
                }
            } catch (err) {
                showMessage('Network error: ' + err.message, 'error');
            }
        });

        // Delete window
        document.getElementById('windowList').addEventListener('click', async function(e) {
            if (!e.target.classList.contains('delete-btn')) return;

            const id = e.target.dataset.id;
            if (!confirm('Are you sure you want to delete this window?')) return;

            try {
                const response = await fetch(`../backend/windows.php?id=${id}`, {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Window deleted successfully!', 'success');
                    const item = document.querySelector(`.window-item[data-id="${id}"]`);
                    if (item) item.remove();

                    // Check if list is empty
                    const list = document.getElementById('windowList');
                    if (list.children.length === 0) {
                        list.innerHTML = '<p class="empty-message">No attendance windows configured yet.</p>';
                    }
                } else {
                    showMessage(data.message || 'Failed to delete window', 'error');
                }
            } catch (err) {
                showMessage('Network error: ' + err.message, 'error');
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
