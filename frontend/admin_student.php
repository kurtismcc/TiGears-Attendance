<?php
require_once '../backend/db.php';

// Get all students for dropdown
$result = $conn->query("SELECT student_id, name FROM students ORDER BY name ASC");
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Edit Student Attendance</title>
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .form-group select {
            padding: 12px 15px;
            font-size: 1em;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            width: 100%;
            max-width: 400px;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
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

        /* Table styles */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .attendance-table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }

        .attendance-table tr:hover {
            background: #f0f0f0;
        }

        .attendance-table tr.editing {
            background: #fff3cd;
        }

        .attendance-table input,
        .attendance-table select {
            padding: 8px;
            border: 2px solid #667eea;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .attendance-table input[type="datetime-local"] {
            width: 200px;
        }

        /* Icon buttons */
        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 8px;
            font-size: 1.2em;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .icon-btn:hover {
            background: #e0e0e0;
        }

        .icon-btn.edit {
            color: #667eea;
        }

        .icon-btn.delete {
            color: #f44336;
        }

        .icon-btn.save {
            color: #4CAF50;
        }

        .icon-btn.cancel {
            color: #9E9E9E;
        }

        .actions-cell {
            white-space: nowrap;
        }

        .empty-message {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 30px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-on_time {
            background: #d4edda;
            color: #155724;
        }

        .status-late {
            background: #f8d7da;
            color: #721c24;
        }

        .status-outside_window {
            background: #fff3cd;
            color: #856404;
        }

        .action-in {
            color: #4CAF50;
            font-weight: 600;
        }

        .action-out {
            color: #f44336;
            font-weight: 600;
        }

        .nav-links {
            margin-bottom: 20px;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-right: 20px;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="index.php">&larr; Back to Attendance</a>
            <a href="admin.php">Manage Windows</a>
        </div>

        <div class="header">
            <h1>Edit Student Attendance</h1>
        </div>

        <div id="message" class="message" style="display: none;"></div>

        <div class="admin-section">
            <h2 class="section-title">Select Student</h2>
            <div class="section-content">
                <div class="form-group">
                    <label for="studentSelect">Student</label>
                    <select id="studentSelect">
                        <option value="">-- Select a student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo htmlspecialchars($student['student_id']); ?>">
                                <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['student_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="admin-section" id="recordsSection" style="display: none;">
            <h2 class="section-title">Attendance Records</h2>
            <div class="section-content">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recordsBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
            msg.style.display = 'block';
            setTimeout(() => { msg.style.display = 'none'; }, 5000);
        }

        function formatDateTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function toLocalDateTimeValue(timestamp) {
            const date = new Date(timestamp);
            // Adjust for timezone offset
            const offset = date.getTimezoneOffset();
            const localDate = new Date(date.getTime() - offset * 60000);
            return localDate.toISOString().slice(0, 16);
        }

        function getStatusBadge(status) {
            if (!status) return '<span class="status-badge">-</span>';
            const labels = {
                'on_time': 'On Time',
                'late': 'Late',
                'outside_window': 'Outside Window'
            };
            return `<span class="status-badge status-${status}">${labels[status] || status}</span>`;
        }

        function renderRow(record, editing = false) {
            const tr = document.createElement('tr');
            tr.dataset.id = record.id;
            if (editing) tr.classList.add('editing');

            if (editing) {
                tr.innerHTML = `
                    <td>
                        <input type="datetime-local" class="edit-timestamp" value="${toLocalDateTimeValue(record.timestamp)}">
                    </td>
                    <td>
                        <select class="edit-action">
                            <option value="in" ${record.action === 'in' ? 'selected' : ''}>Sign In</option>
                            <option value="out" ${record.action === 'out' ? 'selected' : ''}>Sign Out</option>
                        </select>
                    </td>
                    <td>
                        <select class="edit-status">
                            <option value="" ${!record.status ? 'selected' : ''}>-</option>
                            <option value="on_time" ${record.status === 'on_time' ? 'selected' : ''}>On Time</option>
                            <option value="late" ${record.status === 'late' ? 'selected' : ''}>Late</option>
                            <option value="outside_window" ${record.status === 'outside_window' ? 'selected' : ''}>Outside Window</option>
                        </select>
                    </td>
                    <td class="actions-cell">
                        <button class="icon-btn save" title="Save" data-id="${record.id}">&#128190;</button>
                        <button class="icon-btn cancel" title="Cancel" data-id="${record.id}">&#10006;</button>
                    </td>
                `;
            } else {
                tr.innerHTML = `
                    <td>${formatDateTime(record.timestamp)}</td>
                    <td><span class="action-${record.action}">${record.action === 'in' ? 'Sign In' : 'Sign Out'}</span></td>
                    <td>${getStatusBadge(record.status)}</td>
                    <td class="actions-cell">
                        <button class="icon-btn edit" title="Edit" data-id="${record.id}">&#9998;</button>
                        <button class="icon-btn delete" title="Delete" data-id="${record.id}">&#10006;</button>
                    </td>
                `;
            }

            return tr;
        }

        let currentRecords = [];

        async function loadRecords(studentId) {
            const section = document.getElementById('recordsSection');
            const tbody = document.getElementById('recordsBody');

            if (!studentId) {
                section.style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`../backend/attendance_admin.php?student_id=${encodeURIComponent(studentId)}`);
                const data = await response.json();

                if (data.success) {
                    currentRecords = data.records;
                    tbody.innerHTML = '';

                    if (data.records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="empty-message">No attendance records found for this student.</td></tr>';
                    } else {
                        data.records.forEach(record => {
                            tbody.appendChild(renderRow(record));
                        });
                    }

                    section.style.display = 'block';
                } else {
                    showMessage(data.message || 'Failed to load records', 'error');
                }
            } catch (err) {
                showMessage('Network error: ' + err.message, 'error');
            }
        }

        // Student dropdown change
        document.getElementById('studentSelect').addEventListener('change', function() {
            loadRecords(this.value);
        });

        // Table click handlers
        document.getElementById('recordsBody').addEventListener('click', async function(e) {
            const btn = e.target.closest('.icon-btn');
            if (!btn) return;

            const id = parseInt(btn.dataset.id);
            const tr = btn.closest('tr');
            const record = currentRecords.find(r => r.id == id);

            if (btn.classList.contains('edit')) {
                // Enter edit mode
                const newTr = renderRow(record, true);
                tr.replaceWith(newTr);
            } else if (btn.classList.contains('cancel')) {
                // Cancel edit mode
                const newTr = renderRow(record, false);
                tr.replaceWith(newTr);
            } else if (btn.classList.contains('save')) {
                // Save changes
                const timestampInput = tr.querySelector('.edit-timestamp');
                const actionSelect = tr.querySelector('.edit-action');
                const statusSelect = tr.querySelector('.edit-status');

                const timestamp = new Date(timestampInput.value).toISOString().slice(0, 19).replace('T', ' ');
                const action = actionSelect.value;
                const status = statusSelect.value;

                try {
                    const response = await fetch('../backend/attendance_admin.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id, timestamp, action, status })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update local record
                        record.timestamp = timestamp;
                        record.action = action;
                        record.status = status || null;

                        const newTr = renderRow(record, false);
                        tr.replaceWith(newTr);
                        showMessage('Record updated successfully!', 'success');
                    } else {
                        showMessage(data.message || 'Failed to update record', 'error');
                    }
                } catch (err) {
                    showMessage('Network error: ' + err.message, 'error');
                }
            } else if (btn.classList.contains('delete')) {
                if (!confirm('Are you sure you want to delete this attendance record?')) return;

                try {
                    const response = await fetch(`../backend/attendance_admin.php?id=${id}`, {
                        method: 'DELETE'
                    });

                    const data = await response.json();

                    if (data.success) {
                        tr.remove();
                        currentRecords = currentRecords.filter(r => r.id != id);

                        if (currentRecords.length === 0) {
                            document.getElementById('recordsBody').innerHTML =
                                '<tr><td colspan="4" class="empty-message">No attendance records found for this student.</td></tr>';
                        }

                        showMessage('Record deleted successfully!', 'success');
                    } else {
                        showMessage(data.message || 'Failed to delete record', 'error');
                    }
                } catch (err) {
                    showMessage('Network error: ' + err.message, 'error');
                }
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
