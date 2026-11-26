</main>
<dialog class="access-dialog" id="accessibility-dialog" aria-labelledby="accessibility-title">
    <div class="access-dialog-head">
        <div>
            <p class="eyebrow" data-i18n="access.eyebrow">BARANGAY SAN ROQUE</p>
            <h2 id="accessibility-title" data-i18n="access.title">Help &amp; accessibility</h2>
        </div>
        <button type="button" class="dialog-close" id="close-accessibility" aria-label="Close help and accessibility settings">&times;</button>
    </div>

    <section class="access-section" aria-labelledby="settings-heading">
        <h3 id="settings-heading" data-i18n="access.settings">Display and voice settings</h3>
        <div class="access-setting">
            <label for="font-size" data-i18n="access.font_size">Text size</label>
            <select id="font-size">
                <option value="normal" data-i18n="access.font_normal">Normal</option>
                <option value="large" data-i18n="access.font_large">Large</option>
                <option value="larger" data-i18n="access.font_larger">Extra large</option>
            </select>
        </div>
        <label class="access-check"><input type="checkbox" id="high-contrast"><span data-i18n="access.high_contrast">High contrast colors</span></label>
        <label class="access-check"><input type="checkbox" id="dyslexia-font"><span data-i18n="access.readable_font">Use a simple, easy-to-read font</span></label>
        <label class="access-check"><input type="checkbox" id="reduce-motion"><span data-i18n="access.reduce_motion">Reduce animations</span></label>
        <div class="access-setting language-setting">
            <label for="site-language" data-i18n="access.language">Speech recognition language</label>
            <select id="site-language">
                <option value="en-PH">English (Philippines)</option>
                <option value="fil-PH">Filipino</option>
                <option value="ceb-PH">Cebuano</option>
                <option value="ilo-PH">Ilocano</option>
                <option value="hil-PH">Hiligaynon</option>
                <option value="war-PH">Waray</option>
                <option value="bik-PH">Bikol</option>
                <option value="pam-PH">Kapampangan</option>
                <option value="pag-PH">Pangasinan</option>
                <option value="cbk-PH">Chavacano</option>
                <option value="mrw-PH">Maranao</option>
                <option value="mdh-PH">Maguindanaon</option>
                <option value="tsg-PH">Tausug</option>
                <option value="akl-PH">Aklanon</option>
                <option value="krj-PH">Kinaray-a</option>
                <option value="sul-PH">Surigaonon</option>
                <option value="msb-PH">Masbateño</option>
                <option value="rol-PH">Romblomanon</option>
                <option value="ibg-PH">Ibanag</option>
                <option value="ivv-PH">Ivatan</option>
                <option value="kne-PH">Kankanaey</option>
                <option value="xsb-PH">Sambal</option>
                <option value="yka-PH">Yakan</option>
                <option value="itv-PH">Itawis</option>
                <option value="isd-PH">Isnag</option>
                <option value="bto-PH">Rinconada Bikol</option>
                <option value="blw-PH">Balangao</option>
                <option value="ifk-PH">Tuwali Ifugao</option>
                <option value="mbb-PH">Western Bukidnon Manobo</option>
                <option value="tbw-PH">Tagbanwa</option>
                <option value="bcl-PH">Central Bikol</option>
                <option value="sbl-PH">Botolan Sambal</option>
                <option value="und-PH">Other Philippine languages (English fallback)</option>
            </select>
            <p class="muted language-note" data-i18n="access.language_note">This preference selects the voice-recognition language, not translated page text. Interface text is in English; browser support varies by language.</p>
        </div>
    </section>

    <section class="access-section" aria-labelledby="howto-heading">
        <h3 id="howto-heading" data-i18n="access.howto">How to use this website</h3>
        <ol class="help-steps">
            <li><strong data-i18n="help.register_title">Create an account:</strong> <span data-i18n="help.register">Register with your name, email, contact number, and purok.</span></li>
            <li><strong data-i18n="help.file_title">File a concern:</strong> <span data-i18n="help.file">Choose a category, describe what happened and where, then submit. A photo is optional.</span></li>
            <li><strong data-i18n="help.track_title">Track progress:</strong> <span data-i18n="help.track">Open My Complaints to see your reference number, status, updates, and remarks.</span></li>
            <li><strong data-i18n="help.official_title">For barangay officials:</strong> <span data-i18n="help.official">Use Complaints to review reports and update their status; use Reports to filter and print summaries.</span></li>
        </ol>
        <p class="muted" id="page-help"></p>
    </section>

    <section class="access-section" aria-labelledby="voice-heading">
        <h3 id="voice-heading" data-i18n="access.voice">Voice assistance</h3>
        <p data-i18n="access.voice_intro">Voice commands need a supported browser and microphone permission. You can also have the current page read aloud.</p>
        <div class="actions voice-actions">
            <button type="button" class="btn" id="start-voice" data-i18n="access.start_voice">Start voice commands</button>
            <button type="button" class="btn btn-light" id="stop-voice" data-i18n="access.stop_voice">Stop voice commands</button>
            <button type="button" class="btn btn-outline" id="read-page" data-i18n="access.read_page">Read this page aloud</button>
            <button type="button" class="btn btn-light" id="stop-reading" data-i18n="access.stop_reading">Stop reading</button>
        </div>
        <p class="muted voice-hint" data-i18n="access.voice_hint">Voice commands are currently in English. Try: “open dashboard,” “file complaint,” “my complaints,” “help,” “increase text,” or “stop reading.”</p>
        <p class="access-status" id="access-status" role="status" aria-live="polite"></p>
    </section>
</dialog>
<footer class="footer">
    &copy; <?= date('Y') ?> <?= e(site_setting('barangay_name', BARANGAY_NAME)) ?> &middot; <?= e(APP_NAME) ?>
    &middot; <a href="<?= e(url('developer.php')) ?>">Web Developer</a>
    &middot; <a href="<?= e(url('privacy.php')) ?>">Privacy Notice</a>
</footer>
</body>
</html>
