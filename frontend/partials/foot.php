            </div>
        </div>
    </div>

    <!-- =========== Scripts =========  -->
    <script src="../js/main.js"></script>
    <?php if (!empty($pageScripts)) : foreach ($pageScripts as $script) : ?>
    <script src="<?php echo htmlspecialchars($script); ?>"></script>
    <?php endforeach; endif; ?>
    <!-- ====== ionicons ======= -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>

</html>
