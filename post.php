<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/render.php';

$pdo = ri_get_db();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
$stmt->execute(array($id));
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header('HTTP/1.0 404 Not Found');
    $pageTitle = 'Not found - ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<p class="empty">That post doesn\'t exist (maybe it aged out of the cache).</p>';
    echo '<div id="nav"><a href="' . ri_h(ri_url('/')) . '">&lsaquo; back to ' . ri_h(SITE_NAME) . '</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $row['title'] . ' - ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div id="postList">
  <div class="post postSingle">
    <div class="postBody">
      <div class="title">
        <a class="postLink" href="<?php echo ri_h($row['link']); ?>"><?php echo ri_h($row['title']); ?></a>
        <span class="domain">(<?php echo ri_h(ri_domain($row['link'])); ?>)</span>
      </div>
      <div class="postMeta">
        <?php echo ri_h(ri_time_ago($row['pub_date'])); ?>
        &mdash; <span class="source"><?php echo ri_h($GLOBALS['FEEDS'][$row['source']]['label']); ?></span>
      </div>
      <?php if ($row['full_image']): ?>
        <p class="fullImageWrap"><img class="fullImage" src="<?php echo ri_h($row['full_image']); ?>" alt=""></p>
      <?php endif; ?>
      <div class="fullText">
        <?php echo ri_clean_description($row['description']); ?>
      </div>
      <p><a class="postLink" href="<?php echo ri_h($row['link']); ?>">Read the full article on <?php echo ri_h(ri_domain($row['link'])); ?> &rsaquo;</a></p>
    </div>
  </div>
</div>

<div id="nav">
  <a href="<?php echo ri_h(ri_url('/')); ?>">&lsaquo; back to <?php echo ri_h(SITE_NAME); ?></a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
