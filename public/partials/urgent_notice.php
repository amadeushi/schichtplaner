<?php
/** @var array|null $user */
if (setting('urgent_notice_enabled', '0') === '1'):
    $urgentNoticeText = trim((string)setting('urgent_notice_text', ''));
    if ($urgentNoticeText !== ''):
?>
<div class="notice-board">
  <span class="badge urgent">Wichtig</span>
  <p class="notice-board-text"><?= e($urgentNoticeText) ?></p>
</div>
<?php
    endif;
endif;
