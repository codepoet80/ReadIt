<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/fetch.php';
require_once __DIR__ . '/includes/render.php';

$pdo = ri_get_db();
ri_refresh_if_stale($pdo);

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Ask for one extra row so we know whether a "next" page exists
// without a second COUNT(*) query.
$stmt = $pdo->prepare('SELECT * FROM items ORDER BY pub_date DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', ITEMS_PER_PAGE + 1, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasNext = count($rows) > ITEMS_PER_PAGE;
if ($hasNext) {
    array_pop($rows);
}

$pageTitle = SITE_NAME;
require __DIR__ . '/includes/header.php';
?>

<?php if (empty($rows)): ?>
  <p class="empty">
    <?php echo $page > 1 ? 'Nothing here.' : 'No articles yet &mdash; check back in a few minutes.'; ?>
  </p>
<?php else: ?>
  <div id="postList">
    <?php $rank = $offset + 1; ?>
    <?php foreach ($rows as $row): ?>
      <div class="post">
        <span class="rank"><?php echo (int) $rank; ?>.</span>
        <a class="thumb" href="<?php echo ri_h($row['link']); ?>"><?php if ($row['thumbnail']): ?><img src="<?php echo ri_h($row['thumbnail']); ?>" alt="" width="70" height="70"><?php endif; ?></a>
        <div class="postBody">
          <div class="title">
            <a class="postLink" href="<?php echo ri_h($row['link']); ?>"><?php echo ri_h($row['title']); ?></a>
            <span class="domain">(<?php echo ri_h(ri_domain($row['link'])); ?>)</span>
          </div>
          <div class="postMeta">
            <?php echo ri_h(ri_time_ago($row['pub_date'])); ?>
            &mdash; <span class="source"><?php echo ri_h($GLOBALS['FEEDS'][$row['source']]['label']); ?></span>
            &mdash; <a href="<?php echo ri_h(ri_url('/post.php?id=' . (int) $row['id'])); ?>">details</a>
          </div>
          <?php $teaser = ri_teaser($row['description']); ?>
          <?php if ($teaser !== ''): ?>
            <p class="teaser"><?php echo ri_h($teaser); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php $rank++; ?>
    <?php endforeach; ?>
  </div>

  <div id="nav">
    <?php if ($page > 1): ?>
      <a href="<?php echo ri_h(ri_url('/?page=' . ($page - 1))); ?>">&lsaquo; prev</a>
    <?php endif; ?>
    <?php if ($page > 1 && $hasNext): ?> | <?php endif; ?>
    <?php if ($hasNext): ?>
      <a href="<?php echo ri_h(ri_url('/?page=' . ($page + 1))); ?>">next &rsaquo;</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
