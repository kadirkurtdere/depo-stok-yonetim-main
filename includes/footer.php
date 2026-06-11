
</main>
<footer class="app-footer">
  <span><?= h(APP_NAME) ?> &copy; <?= date('Y') ?></span>
</footer>

<!-- GLOBAL SCANNER MODAL -->
<div class="mgmt-modal-overlay" id="scanner-modal" onclick="if(event.target===this) closeScannerModal()">
  <div class="mgmt-modal" style="width: 100%; max-width: 400px; text-align:center;">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title">QR/Barkod Tarayıcı</div>
      <button class="mgmt-modal-close" onclick="closeScannerModal()">✕</button>
    </div>
    <div id="reader"></div>
    <div style="margin-top: 15px; font-size:13px; color:var(--muted);">Kamerayı raf veya ürün barkoduna yaklaştırın.</div>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
