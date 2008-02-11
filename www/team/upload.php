<?php
/**
 * Handle web submissions
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/* for easy parsing of the status by the commandline websubmit client */
define('NONINTERACTIVE', isset($_REQUEST['noninteractive']));

require('init.php');

if ( ! ENABLE_WEBSUBMIT_SERVER ) {
	error("Websubmit disabled.");
}

if ( !isset($_POST['submit']) ) {
	if (NONINTERACTIVE) error("No 'submit' done.");
	header('Location: websubmit.php');
	return;
}
if ( is_null($cid) ) {
	echo "<p><em>No active contest</em></p>\n";
	require('../footer.php');
	exit;
}
$now = now();
if ( difftime($cdata['starttime'], $now) > 0 ) {
	echo "<p><em>Contest has not yet started.</em></p>\n";
	require('../footer.php');
	exit;
}


/** helper to output an error message. */
function err($string) {
	if (NONINTERACTIVE) error($string);

	echo '<div id="uploadstatus">';
	logmsg(LOG_WARNING, $string);
	echo '</div>';
	
	require('../footer.php');
	exit;
}

ini_set("upload_max_filesize", SOURCESIZE * 1024);

$title = 'Submit';
require('../header.php');

echo "<h2>Submit - upload status</h2>\n\n";

ob_implicit_flush();

switch ( $_FILES['code']['error'] ) {
	case UPLOAD_ERR_OK: // everything ok!
		break;
	case UPLOAD_ERR_INI_SIZE:
		error('The uploaded file is too large (exceeds the upload_max_filesize directive).');
	case UPLOAD_ERR_FORM_SIZE:
		error('The uploaded file is too large (exceeds the MAX_FILE_SIZE directive).');
	case UPLOAD_ERR_PARTIAL:
		error('The uploaded file was only partially uploaded.');
	case UPLOAD_ERR_NO_FILE:
		error('No file was uploaded.');
	case 6:	// UPLOAD_ERR_NO_TMP_DIR, constant doesn't exist in our minimal PHP version
		error('Missing a temporary folder. Contact staff.');
	case 7: // UPLOAD_ERR_CANT_WRITE
		error('Failed to write file to disk. Contact staff.');
	case 8: // UPLOAD_ERR_EXTENSION
		error('File upload stopped by extension. Contact staff.');
	default:
		error('Unknown error while uploading: '. $_FILES['code']['error'] .
			'. Contact staff.');
}

$filename = $_FILES['code']['name'];

/* Determine the problem */
$probid = @$_POST['probid'];

if ( empty($probid) ) {
	if ( strpos($filename, '.') === false ) {
		err('Unable to autodetect the problem from the uploaded filename');
	}
	$probid = strtolower(substr($filename, 0, strpos($filename, '.')));
}

$prob = $DB->q('MAYBETUPLE SELECT probid, name FROM problem
                WHERE allow_submit = 1 AND probid = %s AND cid = %i',
               $probid, $cid);

if ( ! isset($prob) ) err("Unable to find problem '$probid'");

/* Determine the language */
$langext = @$_POST['langext'];

if ( empty($langext) ) {
	if ( strrpos($filename, '.') === false ) {
		err('Unable to autodetect the language from the uploaded filename');
	}
	$fileext = strtolower(substr($filename, strrpos($filename, '.')+1));

	$all_lang_exts = explode(" ", LANG_EXTS);
	
	foreach ($all_lang_exts as $langextlist) {
		$langexts = explode(",", $langextlist);

		// Skip first element: that's the language name
		for ($i = 1; $i < count($langexts); $i++) {
			if ( $langexts[$i]==$fileext ) $langext = $langexts[1];
		}
	}
	
	if ( empty($langext) ) err("Unable to find language for extension '$fileext'");
}

$lang = $DB->q('MAYBETUPLE SELECT langid, name FROM language
                WHERE extension = %s AND allow_submit = 1', $langext);

if ( ! isset($lang) ) err("Unable to find language '$langext'");

echo "<table>\n" .
	"<tr><td>Problem: </td><td><i>".htmlspecialchars($prob['name'])."</i></td></tr>\n" .
	"<tr><td>Language:</td><td><i>".htmlspecialchars($lang['name'])."</i></td></tr>\n" .
	"</table>\n";

submit_solution($login, $ip, $probid, $langext, $_FILES['code']['tmp_name']); 

echo '<div id="uploadstatus">';
if (NONINTERACTIVE) echo '<!-- noninteractive-upload-successful -->';
echo "<p>Upload successful.</p>";
echo "</div>\n";

require('../footer.php');
