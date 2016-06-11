        </main>
        <?php if ($dm->getRedirect()) : ?>
            <script>window.location.href = '<?= $dm->getRedirect(); ?>';</script>
        <?php endif; ?>
    </body>
</html>
