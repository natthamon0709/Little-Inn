<?php if(!empty($_GET['msg'])): ?>
<div class="bg-green-100 text-green-800 p-2 rounded mb-3"><?php echo h($_GET['msg']); ?></div>
<?php endif; ?>

<?php if(!empty($_GET['err'])): ?>
<div class="bg-red-100 text-red-800 p-2 rounded mb-3"><?php echo h($_GET['err']); ?></div>
<?php endif; ?>
