<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('feed');

$user = current_user();
$canPost = role_slug() !== 'resident';
$canModerate = in_array(role_slug(), ['admin', 'punong_barangay', 'secretary'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' && $canPost) {
        $content = trim((string) ($_POST['content'] ?? ''));

        if ($content === '') {
            flash('danger', 'Write something before posting.');
        } else {
            try {
                $image = save_upload('feed_image', posts_dir(), true);
                run_query(
                    'INSERT INTO posts (user_id, author_name, author_role, content, image_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                    [(int) $user['id'], $user['full_name'], $user['position'] ?: $user['role_name'], $content, $image[0] ?? null]
                );
                audit_log('Posted to feed', 'Community Feed', (int) db()->lastInsertId(), mb_substr($content, 0, 80));
                flash('success', 'Posted to the community feed.');
            } catch (RuntimeException $error) {
                flash('danger', $error->getMessage());
            }
        }
        redirect('modules/feed.php');
    }

    if ($action === 'delete' && $canPost) {
        $post = fetch_one('SELECT * FROM posts WHERE id = ?', [(int) ($_POST['post_id'] ?? 0)]);

        if ($post && ($canModerate || (int) $post['user_id'] === (int) $user['id'])) {
            if (!empty($post['image_path'])) {
                $file = posts_dir() . '/' . basename((string) $post['image_path']);
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            run_query('DELETE FROM posts WHERE id = ?', [(int) $post['id']]);
            audit_log('Deleted feed post', 'Community Feed', (int) $post['id'], mb_substr((string) $post['content'], 0, 80));
            flash('success', 'Post deleted.');
        } else {
            flash('danger', 'You cannot delete that post.');
        }
        redirect('modules/feed.php');
    }
}

$posts = fetch_all('SELECT * FROM posts ORDER BY created_at DESC, id DESC LIMIT 50');

render_header('Community Feed');
page_header('Community Feed', 'News and updates from your barangay officials.');
?>

<?php if ($canPost): ?>
<section class="panel">
    <div class="panel-header"><h2>Share an update</h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="form-field full">
                <label>What is happening in the barangay?</label>
                <textarea class="form-control" name="content" rows="3" required></textarea>
            </div>
            <div class="form-field full">
                <label>Photo <small class="muted">(optional - JPG, PNG or WEBP up to 5 MB)</small></label>
                <input class="form-control" type="file" name="feed_image" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="form-actions full"><button class="btn" type="submit">Post</button></div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
<article class="panel">
    <div class="panel-header">
        <h2><?= e($post['author_name']) ?> <small class="muted">- <?= e($post['author_role']) ?></small></h2>
        <span class="muted"><?= e(date('M j, Y g:i A', strtotime($post['created_at']))) ?></span>
    </div>
    <div class="panel-body">
        <p><?= nl2br(e($post['content'])) ?></p>
        <?php if ($post['image_path']): ?>
            <img src="<?= e(app_url('uploads/posts/' . rawurlencode($post['image_path']))) ?>" alt="Post photo" style="max-width:100%;border-radius:12px;margin-top:12px">
        <?php endif; ?>
        <?php if ($canPost && ($canModerate || (int) $post['user_id'] === (int) $user['id'])): ?>
            <form method="post" data-confirm="Delete this post?" style="margin-top:12px">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <button class="btn btn-ghost" type="submit">Delete</button>
            </form>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
<?php if (!$posts): ?><section class="panel"><div class="panel-body"><p class="muted">No posts yet.</p></div></section><?php endif; ?>

<?php render_footer(); ?>
