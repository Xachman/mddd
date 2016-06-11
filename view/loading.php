
<?php if ($dm->getResponseMessage()) : ?>
    <div class="dm-response">
        <p><?= $dm->getResponseMessage(); ?></p>
    </div>
<?php endif; ?>
<img class="loading-img" src="/assets/img/gromit.gif" />