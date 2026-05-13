<!-- ===================== FOOTER ===================== -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand-col">
        <img src="<?= epl_url('assets/img/logo-epl.png') ?>" alt="Elite Padel League" class="footer-logo">
        <p class="footer-desc">Mucho más que un torneo. Somos el circuito donde el deporte de alto nivel, la competencia justa y el tercer tiempo se encuentran.</p>
        <div class="footer-socials">
          <a href="#" class="social-link" title="Instagram">Ig</a>
          <a href="#" class="social-link" title="Facebook">Fb</a>
          <a href="#" class="social-link" title="WhatsApp">Wa</a>
        </div>
      </div>
      
      <div class="footer-links-col">
        <h4 class="footer-title">Enlaces Útiles</h4>
        <nav class="footer-nav">
          <a href="<?= epl_url() ?>">Inicio</a>
          <a href="<?= epl_url('torneos.php') ?>">Torneos</a>
          <a href="<?= epl_url('clasificacion.php') ?>">Clasificación</a>
          <a href="<?= epl_url('resultados.php') ?>">Resultados</a>
          <a href="<?= epl_url('jugadores.php') ?>">Jugadores</a>
        </nav>
      </div>
      
      <div class="footer-cta-col">
        <h4 class="footer-title">¿Listo para jugar?</h4>
        <p style="font-size: .85rem; color: rgba(255,255,255,.55); margin-bottom: 1rem;">Únete a la liga más competitiva de Chile y pon a prueba tu nivel.</p>
        <a href="<?= epl_url('registro.php') ?>" class="btn btn-primary">Inscribirme Ahora</a>
      </div>
    </div>
    
    <div class="footer-bottom">
      <p class="footer-copy">© <?= date('Y') ?> Elite Padel League. Todos los derechos reservados.</p>
    </div>
  </div>
</footer>
</body>
</html>
