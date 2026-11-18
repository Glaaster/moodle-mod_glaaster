<?php
// Test page for Glaaster generic resource link detection.
//
// Reproduces the "page parcours" markup of the FFF PortailFormation theme (RemUI customised):
// document links without modtype_resource / f/* icon, one icon-only link + one title link per block.
//
// DEV ONLY - do not ship. Copy to the Moodle root (next to config.php), then open:
//   https://<moodle>/glaaster_test_formation.php?ids=12,34,56
// where ids are course module ids of mod_resource activities (the "id" in /mod/resource/view.php?id=).
// Delete the file once testing is done.

require(__DIR__ . '/config.php');

require_login();

$ids = optional_param('ids', '', PARAM_SEQUENCE);
$cmids = array_filter(array_map('intval', explode(',', $ids)));

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/glaaster_test_formation.php', ['ids' => $ids]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Glaaster - test page parcours');
$PAGE->set_heading('Glaaster - test page parcours');

echo $OUTPUT->header();

if (empty($cmids)) {
    echo $OUTPUT->notification(
        'Add ?ids=<cmid>,<cmid> to the URL (course module ids of file resources: PDF, DOCX, XLSX...).',
        'info'
    );
}

$svg = '<svg style="padding:5px;" class="smartchactivityicon mr-4" width="18" height="21" '
    . 'xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white">'
    . '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
    . '<path stroke-linecap="round" fill="white" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 '
    . '.656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" /></svg>';

foreach ($cmids as $i => $cmid) {
    $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
    $name = $cm ? format_string($cm->name) : "Unknown cmid {$cmid}";
    $type = $cm ? $cm->modname : '?';
    $url = (new moodle_url('/mod/resource/view.php', ['id' => $cmid]))->out(false);

    // Same structure as the FFF block (theme/remui/views/formation.php).
    echo '<div class="module-20 activity-element" id="activity-20-' . $i . '" style="display: block;">';
    echo '<div style="display: flex;">';
    echo '<a href="' . s($url) . '"><div class="course_activity_icon" '
        . 'style="background:#1d3c78;border-radius:50%;">' . $svg . '</div></a>';
    echo '<div>';
    echo '<a href="' . s($url) . '"></a>';
    echo '<a href="' . s($url) . '"><div class="FFF-Equipe-Bold fff-name-activity">' . $name . '</div></a>';
    echo '<div class="smartchmoduletype" style="font-size: 0.8rem;">fichier (' . s($type) . ')</div>';
    echo '<div></div>';
    echo '</div>';
    echo '</div>';
    echo '<hr/>';
    echo '</div>';
}

// Late block: checks the MutationObserver picks up content added after page load.
if (!empty($cmids)) {
    $late = (new moodle_url('/mod/resource/view.php', ['id' => reset($cmids)]))->out(false);
    $html = json_encode('<div class="activity-element"><div style="display:flex;"><div>'
        . '<a href="' . s($late) . '"><div class="fff-name-activity">Bloc ajouté après 2 s</div></a>'
        . '</div></div><hr/></div>');
    echo "<div id=\"late-container\"></div><script>setTimeout(function() {"
        . "document.getElementById('late-container').insertAdjacentHTML('beforeend', {$html});"
        . "}, 2000);</script>";
}

echo $OUTPUT->footer();
