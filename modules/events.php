<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('events');

$user = current_user();
$canManage = role_slug() !== 'resident';
$eventStatuses = ['Scheduled', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    if (!$canManage) {
        http_response_code(403);
        exit('Only barangay officials can manage events.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $date = (string) ($_POST['event_date'] ?? '');
        $time = (string) ($_POST['event_time'] ?? '');
        $location = trim((string) ($_POST['location'] ?? ''));
        $status = in_array($_POST['status'] ?? '', $eventStatuses, true) ? (string) $_POST['status'] : 'Scheduled';

        if ($title === '' || $date === '' || $location === '' || !strtotime($date)) {
            flash('danger', 'Title, a valid date and the venue are required.');
            redirect('modules/events.php' . ($id ? '?edit=' . $id : ''));
        }

        $params = [$title, trim((string) ($_POST['description'] ?? '')), $date, $time !== '' ? $time : null, $location, trim((string) ($_POST['organizer'] ?? '')) ?: $user['full_name'], $status];

        if ($id) {
            run_query('UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, organizer = ?, status = ?, updated_at = NOW() WHERE id = ?', [...$params, $id]);
            audit_log('Updated event', 'Events', $id, $title);
            flash('success', 'Event updated.');
        } else {
            run_query('INSERT INTO events (title, description, event_date, event_time, location, organizer, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [...$params, (int) $user['id']]);
            audit_log('Created event', 'Events', (int) db()->lastInsertId(), $title);
            flash('success', 'Community event posted.');
        }
        redirect('modules/events.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        run_query('DELETE FROM events WHERE id = ?', [$id]);
        audit_log('Deleted event', 'Events', $id, '');
        flash('success', 'Event deleted.');
        redirect('modules/events.php');
    }
}

$editing = $canManage && isset($_GET['edit']) ? fetch_one('SELECT * FROM events WHERE id = ?', [(int) $_GET['edit']]) : null;
$upcoming = fetch_all("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC");
$past = fetch_all("SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC LIMIT 20");

render_header('Events & Activities');
page_header('Events & Activities', 'Upcoming community events, activities and meetings of the barangay.', export_buttons('events'));

function render_event_rows(array $events, bool $canManage): void
{
    foreach ($events as $event): ?>
        <tr>
            <td><strong><?= e($event['title']) ?></strong><small class="muted"><br><?= e($event['description'] ?: '') ?></small></td>
            <td><?= e(date('M j, Y', strtotime($event['event_date']))) ?><?= $event['event_time'] ? '<br><small>' . e(date('g:i A', strtotime($event['event_time']))) . '</small>' : '' ?></td>
            <td><?= e($event['location']) ?></td>
            <td><?= e($event['organizer']) ?></td>
            <td><span class="<?= e(status_class($event['status'])) ?>"><?= e($event['status']) ?></span></td>
            <?php if ($canManage): ?>
            <td>
                <a class="btn btn-ghost" href="<?= e(app_url('modules/events.php?edit=' . (int) $event['id'])) ?>">Edit</a>
                <form method="post" style="display:inline" data-confirm="Delete this event?">
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                    <button class="btn btn-ghost" type="submit">Delete</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach;
}
?>

<?php if ($canManage): ?>
<section class="panel">
    <div class="panel-header"><h2><?= $editing ? 'Edit event' : 'Post a community event' ?></h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div class="form-field"><label>Title *</label><input class="form-control" name="title" value="<?= e($editing['title'] ?? '') ?>" required></div>
            <div class="form-field"><label>Venue *</label><input class="form-control" name="location" value="<?= e($editing['location'] ?? '') ?>" required></div>
            <div class="form-field"><label>Date *</label><input class="form-control" type="date" name="event_date" value="<?= e($editing['event_date'] ?? '') ?>" required></div>
            <div class="form-field"><label>Time</label><input class="form-control" type="time" name="event_time" value="<?= e(isset($editing['event_time']) ? substr((string) $editing['event_time'], 0, 5) : '') ?>"></div>
            <div class="form-field"><label>Organizer</label><input class="form-control" name="organizer" value="<?= e($editing['organizer'] ?? '') ?>" placeholder="Defaults to your name"></div>
            <div class="form-field"><label>Status</label>
                <select class="form-control" name="status"><?php foreach ($eventStatuses as $statusName): ?><option <?= selected($editing['status'] ?? 'Scheduled', $statusName) ?>><?= e($statusName) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-field full"><label>Description</label><textarea class="form-control" name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea></div>
            <div class="form-actions full">
                <button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Post event' ?></button>
                <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(app_url('modules/events.php')) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Upcoming events</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Event</th><th>When</th><th>Venue</th><th>Organizer</th><th>Status</th><?= $canManage ? '<th></th>' : '' ?></tr></thead>
            <tbody>
                <?php render_event_rows($upcoming, $canManage); ?>
                <?php if (!$upcoming): ?><tr><td colspan="6">No upcoming events.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($past): ?>
<section class="panel">
    <div class="panel-header"><h2>Past events</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Event</th><th>When</th><th>Venue</th><th>Organizer</th><th>Status</th><?= $canManage ? '<th></th>' : '' ?></tr></thead>
            <tbody><?php render_event_rows($past, $canManage); ?></tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php render_footer(); ?>
