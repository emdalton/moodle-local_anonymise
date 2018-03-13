<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Code checker library code.
 *
 * @package    local_pseudonymise
 * @copyright  Gavin Henrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if (defined('CLI_SCRIPT') && CLI_SCRIPT == true) {
    define('BLOCK_CHAR', '.');
} else {
    define('BLOCK_CHAR', '&#9608;');
}

require_once($CFG->libdir . '/formslib.php');

// Files required by uninstallation processes.
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Action form for the Pseudonymise page.
 *
 * @copyright  Gavin Henrick
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_pseudonymise_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'action', true);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('action', PARAM_BOOL);

        $mform->addElement('checkbox', 'activities', get_string('activities', 'local_pseudonymise'));
        $mform->setType('activities', PARAM_BOOL);

        $mform->addElement('checkbox', 'categories', get_string('categories', 'local_pseudonymise'));
        $mform->setType('categories', PARAM_BOOL);

        $mform->addElement('checkbox', 'courses', get_string('courses', 'local_pseudonymise'));
        $mform->setType('courses', PARAM_BOOL);

        $mform->addElement('checkbox', 'site', get_string('includesite', 'local_pseudonymise'));
        $mform->setType('site', PARAM_BOOL);
        $mform->disabledIf('site', 'courses', 'notchecked');

        $mform->addElement('checkbox', 'files', get_string('files', 'local_pseudonymise'));
        $mform->setType('files', PARAM_BOOL);

        $mform->addElement('checkbox', 'users', get_string('users', 'local_pseudonymise'));
        $mform->setType('users', PARAM_BOOL);

        $mform->addElement('checkbox', 'password', get_string('resetpasswords', 'local_pseudonymise'));
        $mform->setType('password', PARAM_BOOL);
        $mform->setDefault('password', 'checked');
        $mform->disabledIf('password', 'users', 'notchecked');

        $mform->addElement('checkbox', 'admin', get_string('includeadmin', 'local_pseudonymise'));
        $mform->setType('users', PARAM_BOOL);
        $mform->disabledIf('admin', 'users', 'notchecked');

        $mform->addElement('checkbox', 'others', get_string('others', 'local_pseudonymise'));
        $mform->setType('others', PARAM_BOOL);

        $mform->addElement('submit', 'submitbutton', get_string('anonymise', 'local_pseudonymise'));
    }
}

function pseudonymise_activities() {

	global $DB;

	$modules = $DB->get_records('modules');
	$countmodules = $DB->count_records('modules');

	debugging('Pseudonymising activities', DEBUG_DEVELOPER);

	foreach ($modules as $module) {
		if (!debugging('', DEBUG_DEVELOPER)) {
			echo BLOCK_CHAR . ' ';
		}

		if (get_string_manager()->string_exists('pluginname', 'mod_' . $module->name)) {
			$modulename = get_string('pluginname', 'mod_' . $module->name);
		} else {
			$modulename = $module->name;
		}

		$moduleinstances = $DB->get_recordset($module->name);
		$countmodules = $DB->count_records($module->name);

		//debugging('there are ' . $countmodules . ' modules of type ' . $module->name . ' in the list', DEBUG_DEVELOPER);
		foreach ($moduleinstances as $moduleinstance) {
			/* $randomid = assign_random_id(); */
			//$pseudoid = assign_serial_pseudo_id($countmodules);
			$pseudoid = assign_serial_pseudo_id($countmodules*10);
			//debugging('changing activity ' . $moduleinstance->name . ' name to ' . $pseudoid, DEBUG_DEVELOPER);
			$moduleinstance->name = $modulename . ' ' . $pseudoid;
			try {
				$DB->update_record($module->name, $moduleinstance, true);
				debugging('changed activity ' . $module->name . ' name to ' . $moduleinstance->name, DEBUG_DEVELOPER);
			} catch (Exception $ex) {
				debugging('error attempting update_record ' . $ex, DEBUG_DEVELOPER);
				debugging('Skipped activity ' . $moduleinstance->name . ' update', DEBUG_DEVELOPER);
			} //try
		} //foreach ($moduleinstances
	} //foreach $modules

	$moduleinstances->close();
}

function pseudonymise_categories() {

    global $DB;

    $categoyprefix = get_string('category');
    $descriptionprefix = get_string('description');

    debugging('Pseudonymising categories', DEBUG_DEVELOPER);

    $allcategories = $DB->get_recordset('course_categories');
		$countcategories = $DB->count_records('course_categories');
debugging('there are ' . $countcategories . ' categories in the list', DEBUG_DEVELOPER);
    foreach ($allcategories as $category) {

    /* $randomid = assign_random_id(); */
    $pseudoid = assign_pseudo_id($countcategories);
        $category->name = $categoyprefix . ' ' . $pseudoid;
        assign_if_not_null($category, 'description', $descriptionprefix . $pseudoid);
        assign_if_not_null($category, 'idnumber', $pseudoid);
        $DB->update_record('course_categories', $category, true);
    }
    $allcategories->close();
}

function pseudonymise_courses($site = false) {

    global $DB;

    $courseprefix = get_string('course');
    $descriptionprefix = get_string('description');
    $sectionprefix = get_string('section');
    $sitecourse = 1;

    debugging('Pseudonymising courses');

    // Pseudonymise course data.
    $courses = $DB->get_recordset('course');
 		$countcourses = $DB->count_records('course');
debugging('there are ' . $countcourses . ' courses in the list', DEBUG_DEVELOPER);
	foreach ($courses as $course) {

        if (!$site && $course->format == 'site') {
            $sitecourse = $course->id;
            continue;
        }

    /* $randomid = assign_random_id(); */
     $pseudoid = assign_pseudo_id($countcourses);
        $course->fullname = $courseprefix . ' ' . $pseudoid;
        $course->shortname = $courseprefix . ' ' . $pseudoid;
        assign_if_not_null($course, 'idnumber', $pseudoid);
        assign_if_not_null($course, 'summary', $descriptionprefix . ' ' . $pseudoid);
        $DB->update_record('course', $course, true);
    }
    $courses->close();

    debugging('Pseudonymising sections');

    // Pseudonymise sections - replace with numbers
    $sections = $DB->get_recordset('course_sections');
 		$countsections = $DB->count_records('course_sections');
debugging('there are ' . $countsections . ' sections in the list', DEBUG_DEVELOPER);
    foreach ($sections as $section) {

        if (!$site && $section->course == $sitecourse) {
            continue;
        }
            $pseudoid = assign_serial_pseudo_id($countsections);

        assign_if_not_null($section, 'name', $sectionprefix . ' ' . $pseudoid);
        assign_if_not_null($section, 'summary', $descriptionprefix . ' ' . $pseudoid);

        $DB->update_record('course_sections', $section, true);
    }
    $sections->close();
}

function pseudonymise_files() {
    global $DB;

    debugging('Pseudonymising files');

    $files = $DB->get_recordset('files');
 		$countfiles = $DB->count_records('files');
    foreach ($files as $file) {

        assign_if_not_null($file, 'author', 'user ' . $file->userid);
        assign_if_not_null($file, 'source', '');
        if ($file->filename !== '.') {
            assign_if_not_null($file, 'filename', assign_serial_pseudo_id($countfiles));
        }
        if ($file->filepath !== '/') {
            assign_if_not_null($file, 'filepath', '/' . assign_serial_pseudo_id($countfiles) . '/');
        }
        $DB->update_record('files', $file);
    }
    $files->close();
}

function pseudonymise_users($password = false, $admin = false) {

    global $CFG, $DB;

    require_once($CFG->dirroot . '/user/lib.php');

    // Delete all deleted users.
    $DB->delete_records('user', array('deleted' => 1));

    $defaultcity = get_string('defaultusercity', 'local_pseudonymise');
    $defaultcountry = get_string('defaultusercountry', 'local_pseudonymise');
    $userstring = strtolower(get_string('user'));
    $domain = get_string('defaultdomain', 'local_pseudonymise');
    $fields = array(
        'firstname' => get_string('firstname'),
        'lastname' => get_string('lastname'),
        'password' => get_string('password'),
        'skype' => get_string('skypeid'),
        'yahoo' => get_string('yahooid'),
        'aim' => get_string('aimid'),
        'msn' => get_string('msnid'),
        'institution' => get_string('institution'),
        'department' => get_string('department'),
        'address' => get_string('address'),
        'description' => get_string('description'),
        'firstnamephonetic' => get_string('firstnamephonetic'),
        'lastnamephonetic' => get_string('lastnamephonetic'),
        'middlename' => get_string('middlename'),
        'alternatename' => get_string('alternatename'),
    );

    debugging('Pseudonymising users');

    // Clear fields in the user table.
    $allusers = $DB->get_recordset('user', array('deleted' => 0));

	//how many users did we get? BUG: this method may be clearing $allusers?
	$countusers = $DB->count_records('user', array('deleted' => 0));

	//debugging('there are ' . $countusers . ' users in the list', DEBUG_DEVELOPER);
    foreach ($allusers as $user) {

        if ($user->username == 'guest' || (!$admin && $user->username == 'admin')) {
            continue;
        }
    //debugging('current user ' . $user->id . ' username ' . $user->username, DEBUG_DEVELOPER);

         /* this function is specific to assigning a plausible given name */
       $pseudogname = assign_pseudo_gname();
        /* this function is specific to assigning a plausible surname */
        $pseudosname = assign_pseudo_sname($pseudogname);
        if ($user->username != 'admin') {
            $user->username = $val = iconv('UTF-8', 'ASCII//TRANSLIT',
                preg_replace('/\W/', '', strtolower($userstring . $pseudogname . $pseudosname)));
        }
    //debugging('new name '  . $pseudogname . ' ' . $pseudosname, DEBUG_DEVELOPER);
         $pseudoid = str_replace(" ","",strtolower(assign_serial_pseudo_id($countusers)));
    //debugging('new serialized string ' . $pseudoid, DEBUG_DEVELOPER);

	    /* assign_if_not_null($user, 'idnumber', $pseudoid); */
        assign_if_not_null($user, 'idnumber', $pseudogname . $pseudosname);
        foreach ($fields as $field => $translation) {
            assign_if_not_null($user, $field, $translation . ' ' . $pseudoid);
        }
        assign_if_not_null($user, 'firstname', $pseudogname);
        assign_if_not_null($user, 'lastname', $pseudosname);

        // Moving here fields specially small, we need to limit their size.
        assign_if_not_null($user, 'email', $pseudogname . $pseudosname . '@'. $domain);
        assign_if_not_null($user, 'icq', 'icq ' . substr($pseudoid, 0, 10));
        assign_if_not_null($user, 'phone1', 'phone1 ' . substr($pseudoid, 0, 12));
        assign_if_not_null($user, 'phone2', 'phone2 ' . substr($pseudoid, 0, 12));
        assign_if_not_null($user, 'url', 'http://' . $pseudoid . '.com');
        assign_if_not_null($user, 'lastip', 'lastip ' . substr($pseudoid, 0, 37));
        assign_if_not_null($user, 'secret', 'secret ' . substr($pseudoid, 0, 7));

        // Defaults.
        assign_if_not_null($user, 'city', $defaultcity);
        assign_if_not_null($user, 'country', $defaultcountry);
	    // remove next line!!!
        //assign_if_not_null($user, 'password', $pseudoid);
        $user->picture = 0;
        try {
    //debugging('updating user ' . $user->id . ' with username ' . $user->username . ' and password ' . $user->$password, DEBUG_DEVELOPER);
            user_update_user($user, $user->username == 'admin' ? false : $password, false);
    //debugging('updated user ' . $user->id . ' named ' . $pseudogname . ' ' . $pseudosname, DEBUG_DEVELOPER);
        } catch (Exception $ex) {
            // No problem if there is any inconsistency just skip it.
            debugging('error attempting user_update_user ' . $ex, DEBUG_DEVELOPER);
            debugging('Skipped user ' . $user->id . ' update', DEBUG_DEVELOPER);
        }
    }
    $allusers->close();

    // Clear custom profile fields.
    $customfields = $DB->get_recordset('user_info_data');
    foreach ($customfields as $field) {
        $field->data = '';
        $DB->update_record('user_info_data', $field, true);
    }
    $customfields->close();
}

/**
 * Here we:
 *
 * - Delete all non-core database tables
 * - Delete all non-core mdl_config_plugins entries
 * - Delete all core sensitive records from mdl_config_plugins and mdl_config
 * - Delete all user sessions stored data
 * - Update all ips to 0.0.0.0
 * - Delete core sensitive records that don't fall in any of the points above
 * - Anonymise database text and varchar fields (there is a list of excluded fields)
 *
 * @access public
 * @return void
 */
function pseudonymise_others($pseudonymiseactivities, $pseudonymisepassword) {
    global $DB;

    // List all non-standard plugins in the system.
    $noncoreplugins = array();
    foreach (\core_component::get_plugin_types() as $plugintype => $plugintypedir) {

        $allplugins = \core_component::get_plugin_list($plugintype);
        $standardplugins = core_plugin_manager::standard_plugins_list($plugintype);
        if (!is_array($standardplugins)) {
            $standardplugins = array();
        }
        $plugintypenoncore = array_diff(array_keys($allplugins), $standardplugins);

        foreach ($plugintypenoncore as $pluginname) {
            // We don't want to delete local pseudonymise.
            if ($plugintype !== 'local' && $pluginname !== 'pseudonymise') {
                $name = $plugintype . '_' . $pluginname;
                $noncoreplugins[$name] = $allplugins[$pluginname];
            }
        }
    }

    debugging('Uninstalling non-core stuff', DEBUG_DEVELOPER);

    // Delete all non-core mdl_config_plugins records and tables.
    if ($noncoreplugins) {

        $dbman = $DB->get_manager();

        foreach ($noncoreplugins as $pluginname => $path) {

            $shortname = substr($pluginname, strpos($pluginname, '_') + 1);
            $plugintype = substr($pluginname, 0, strpos($pluginname, '_'));
            $legacyname = $plugintype . '/' . $shortname;

            try {
                uninstall_plugin($plugintype, $shortname);
            } catch (moodle_exception $e) {
                // Catch any possible issue with 3rd party code. Notify it and provide a workaround.
                debugging('Not possible to complete ' . $pluginname . ' uninstall process, falling back to database tables ' .
                    'and config values removal');

                // Delete all plugin tables.
                $dbfile = $path . '/db/install.xml';
                if (file_exists($dbfile)) {
                    $dbman->delete_tables_from_xmldb_file($dbfile);
                }
            }

            // Cleanup from core tables regardless of successfull uninstall.
            $DB->delete_records('config_plugins', array('plugin' => $pluginname));

            // Also delete records stored without the plugintype part of the plugin name.
            $DB->delete_records('config_plugins', array('plugin' => $shortname));

            // And records using type/name syntax.
            $DB->delete_records('config_plugins', array('plugin' => $legacyname));
        }
    }

    debugging('Deleting config_plugins data', DEBUG_DEVELOPER);

    // Delete core plugins sensitive mdl_config_plugins records.
    $sensitiveplugins = array('auth_cas', 'auth_db', 'auth_fc', 'auth_imap', 'auth_ldap', 'auth_nntp', 'auth_pam', 'auth_pop3',
        'auth_shibboleth',
        'enrol_database', 'enrol_ldap', 'enrol_paypal',
        'logstore_database',
        'repository_youtube', 'repository_dropbox', 'repository_flickr_public', 'repository_boxnet', 'repository_flickr',
        'repository_googledocs', 'repository_merlot', 'repository_picasa', 'repository_s3', 'repository_skydrive',
        'search_solr'
    );
    foreach ($sensitiveplugins as $pluginname) {

        $shortname = substr($pluginname, strpos($pluginname, '_') + 1);
        // e.g. auth plugins before 3.3
        $oldname = str_replace('_', '/', $pluginname);

        $sql = "DELETE FROM {config_plugins} WHERE (plugin = :pluginname OR plugin = :shortname OR plugin = :oldname) AND name != 'version'";
        $DB->execute($sql, array('pluginname' => $pluginname, 'shortname' => $shortname, 'oldname' => $oldname));
    }

    // Also hub, which is not a plugin but its data is stored in config_plugins.
    $DB->delete_records('config_plugins', array('plugin' => 'hub'));
    $DB->delete_records('config_plugins', array('plugin' => 'mnet'));
    $DB->delete_records('config_plugins', array('plugin' => 'core_plugin'));

    debugging('Deleting config sensitive data', DEBUG_DEVELOPER);

    // Also delete core sensitive records in mdl_config.
    $sensitiveconfigvalues = array(
        'jabberhost', 'jabberserver', 'jabberusername', 'jabberpassword', 'jabberport',
        'airnotifierurl', 'airnotifierport', 'airnotifiermobileappname', 'airnotifierappname', 'airnotifieraccesskey',
        'BigBlueButtonBNSecuritySalt', 'bigbluebuttonbn_server_url', 'BigBlueButtonBNServerURL', 'bigbluebuttonbn_shared_secret',
        'chat_serverhost', 'chat_serverip', 'chat_serverport', 'curlsecurityallowedport', 'curlsecurityblockedhosts', 'geoip2file',
        'geoipfile', 'googlemapkey3', 'maintenance_message', 'messageinbound_domain', 'messageinbound_host',
        'messageinbound_hostpass', 'messageinbound_hostssl', 'messageinbound_hostuser', 'messageinbound_mailbox', 'noreplyaddress',
        'proxybypass', 'proxyhost', 'proxypassword', 'proxyport', 'proxytype', 'proxyuser', 'recaptchaprivatekey',
        'recaptchapublickey', 'smtphosts', 'smtppass', 'smtpsecure', 'smtpuser', 'supportemail', 'supportname', 'badges_badgesalt',
        'badges_defaultissuername', 'badges_defaultissuercontact', 'cronremotepassword', 'turnitin_account_id', 'turnitin_secret',
        'turnitin_proxyurl', 'turnitin_proxyport', 'turnitin_proxyuser', 'turnitin_proxypassword'
    );
    foreach ($sensitiveconfigvalues as $name) {
        // We update rather than delete because there is code that relies incorrectly on CFG vars being set.
        if ($record = $DB->get_record('config', array('name' => $name))) {
            $record->value = '';
            $DB->update_record('config', $record);
        }
    }

    debugging('Deleting other stuff', DEBUG_DEVELOPER);

    // Other records.
    $DB->delete_records('user_preferences', array('name' => 'login_lockout_secret'));
    $DB->delete_records('user_preferences', array('name' => 'flickr_'));
    $DB->delete_records('user_preferences', array('name' => 'flickr__nsid'));
    $DB->delete_records('user_preferences', array('name' => 'dropbox__request_secret'));

    try {
        $DB->delete_records('sessions');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('log');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('config_log');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('portfolio_log');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('mnet_log');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('upgrade_log');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('scorm_aicc_session');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('mnet_session');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('user_password_history');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('user_password_resets');
    } catch (dml_exception $e) {
        // Ignore.
    }
    try {
        $DB->delete_records('user_private_key');
    } catch (dml_exception $e) {
        // Ignore.
    }

    debugging('Getting rid of all ips', DEBUG_DEVELOPER);

    // Get rid of all ips.
    $params = array('ip' => '0.0.0.0');
    try {
        $updateips = "UPDATE {user_private_key} SET iprestriction = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {user} SET lastip = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {registry} SET ipaddress = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {register_downloads} SET ip = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {mnet_host} SET ip_address = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {external_tokens} SET iprestriction = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {external_services_users} SET iprestriction = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {chat_users} SET ip = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // Ignore.
    }
    try {
        $updateips = "UPDATE {logstore_standard_log} SET ip = :ip";
        $DB->execute($updateips, $params);
    } catch (dml_exception $ex) {
        // np, ignoring logstore_standard if not installed (although not worth the dataset if uninstalled....).
    }

    // We don't want to pseudonymise these database table columns because the system would not work as expected
    // without them or they contain numeric or they contain data that do not need to be pseudonymised.
    $excludedcolumns = get_excluded_text_columns();

    // List of varchar fields to pseudonymise, already excluded varchar fields that are required by the system
    // to work properly.
    $varchars = get_varchar_fields_to_update();

    // List of activities, we skip activity names pseudonymisation.
    $activitynamefields = get_activity_name_fields();

    // Iterate through all system tables and set random values to text and varchar fields.
    $tables = $DB->get_tables(false);
    foreach ($tables as $tablename) {

        if (!debugging('', DEBUG_DEVELOPER)) {
            echo BLOCK_CHAR . ' ';
        }

        $toupdate = array();
        $columns = $DB->get_columns($tablename, false);
        foreach ($columns as $columnname => $column) {

            // Some text fields can not be cleared or the site would not make sense.
            if (!empty($excludedcolumns[$tablename]) && in_array($columnname, $excludedcolumns[$tablename])) {
                continue;
            }

            // Text fields, all of them but the excluded ones.
            if (($DB->get_dbfamily() === 'postgres' && $column->type === 'text') ||
                ($DB->get_dbfamily() === 'mysql' && $column->type === 'longtext')) {
                $toupdate[$columnname] = $columnname;
            }

            // All listed varchars.
            if (!empty($varchars[$tablename]) && !empty($varchars[$tablename][$columnname])) {

                // Skip activity names and user password if required.
                if (($pseudonymiseactivities || empty($activitynamefields[$tablename]) || empty($activitynamefields[$tablename][$columnname])) &&
                        ($pseudonymisepassword || $tablename !== 'user' || $columnname !== 'password')) {
                    $toupdate[$columnname] = $columnname;
                }
            }
        }

        // Update all table records if there is any text column that should be cleaned.
        if (!empty($toupdate)) {
            debugging('Pseudonymising ' . $tablename . ' records', DEBUG_DEVELOPER);
            pseudonymise_table_records($tablename, $toupdate);
        }
    }

    purge_all_caches();
}

function pseudonymise_table_records($tablename, $columns) {
    global $DB;

    try {
        $records = $DB->get_recordset($tablename);
    } catch (dml_exception $ex) {
        mtrace('Skipping ' . $tablename . ' table pseudonymisation process as it does not exist in this Moodle site');
        return;
    }

    foreach ($records as $record) {
        $updaterecord = false;

        // Set each of the text columns value to a random string with the same length.
        foreach ($columns as $columnname) {

            // Skip unexisting columns.
            if (!isset($record->{$columnname})) {
                continue;
            }
            /* not changing this for pseudonymiser for now, but later may try to preserve some lexical markers */
            $len = \core_text::strlen($record->{$columnname});
            if ($len) {
                $updaterecord = true;
                $record->{$columnname} = assign_random_string($len);
            }
        }
        if ($updaterecord) {
            $DB->update_record($tablename, $record);
        }
    }
    $records->close();
}

function assign_if_not_null(&$object, $field, $newvalue) {
           // debugging('attempting to assign if not null ' . $field . ' new value ' . $newvalue, DEBUG_DEVELOPER);
    if (
        property_exists($object, $field) &&
        isset($object->$field) &&
        !empty($object->$field)
    ) {
        $object->$field = $newvalue;
    }
}

function assign_random_string($len) {
    $random = random_string($len);

    // Add some spacing so texts don't appear in 1 single line.
    $random = preg_replace("/\d/"," ", $random);

    return $random;
}

function assign_random_id() {

    // Keep track of used IDs during the running of the script.
    static $usedids = array();

    do {
        $id = rand(1, PHP_INT_MAX);
    } while (array_search($id, $usedids) !== false);

    $usedids[] = $id;

    return $id;
}

function assign_pseudo_id($len) {
    // rather than just assigning a random string of junk,
    // this algorithm assembles a phrase string consisting of randomized strings categorized by type
    // accept a length parameter to determine how long the phrase needs to be for uniqueness
    // Keep track of used IDs during the running of the script.
    static $usedpseudoids = array();
    $prefixlist = explode(",", "Introduction to,Introductory,Junior,Intermediate,Elementary,Applied,General,Freshman,Senior,Integrated,Special,Independent Study in,Thesis in,Advanced,Fundamentals of,Topics in,Internship in,Seminar in,Colloquium in,Exploring,Teaching,Foundations of,Investigating,Condensed,Beginnings of,Studies in,Peer-Led");
    $adjlist = explode(",","Abnormal,Abstract,Academic,Acquired,Active,Acute,Advanced,Aerial,Algebraic,Analytical,Ancient,Anthropological,Apocalyptic,Applied,Aquatic,Aqueous,Archaeological,Archaic,Architectural,Arctic,Artificial,Artist's,Artistic,Assisted,Assistive,Athenian,Athletic,Atlantic,Auditory,Aural,Bad,Baroque,Basic,Behavioral,Biochemical,Bioenvironmental,Biological,Biomechanical,Biomedical,Brave,Broken,Buried,Canine,Cardiac,Cardiopulmonary,Cellular,Changing,Chemical,Children's,Choral,Chronic,Civic,Civil,Classical,Client-Side,Clinical,Coastal,Cognitive,Collaborative,Collective,Collegiate,Commercial,Communicative,Community-Based,Commutative,Comparative,Complex,Composite,Computational,Concrete,Condensed,Contemporary,Context,Controlled,Controversial,Conversational,Coolest,Cooperative,Core,Corporate,Creative,Criminological,Critical,Crop,Cross-Cultural,Cruel,Culinary,Cultural,Current,Daily,Dairy,Delinquent,Derivative,Developmental,Dietetic,Differential,Digital,Disabled,Discrete,Disruptive,Distributed,Do-It-Yourself,Domestic,Dystopian,Earliest,Early,East,Eastern,Ecological,Economic,Ecosystem,Educational,Eighteenth-Century,Electric,Electrical,Electrochemical,Electromagnetic,Electromechanical,Electronic,Elementary,Embedded,Endangered,Entrepreneurial,Environmental,Epic,Equine,Ergogenic,Estuarine,Ethical,Ethnic,Ethnographic,Eukaryotic,European,Evolutionary,Exceptional,Experimental,Extreme,Famous,Federal,Financial,Finite,Floral,Floricultural,Foreign,Forensic,Formal,Functional,Fundamental,Fungal,Gender-Based,Gendered,General,Genetic,Geo-Environmental,Geographic,Geological,Geotechnical,Geriatric,Glacial,Global,Golden,Good,Gothic,Governmental,Graded,Grand,Graphic,Great,Green,Growing,Happy,Hazardous,Healthy,Hellenic,Hellenistic,Herbaceous,High,High-Performance,High-Tech,Hispanic,Historic,Historical,Horticultural,Igneous,Inclusive,Independent,Indigenous,Individual,Industrial,Infectious,Inorganic,Institutional,Instrumental,Integrated,Integrating,Intellectual,Interactive,Interdisciplinary,Internal,International,Interpersonal,Invasive,Invertebrate,Investigative,Juvenile,Large,Later,Legal,Linear,Linguistic,Literary,Local,Long,Lost,Macroeconomic,Major,Mammalian,Managerial,Marine,Mass,Material,Maternal,Mathematical,Mechanical,Medical,Medieval,Mediterranean,Mental,Mesoamerican,Metamorphic,Methodological,Microbial,Microeconomic,Microscopic,Mock,Model,Modern,Molecular,Monetary,Multicultural,Multidimensional,Multidisciplinary,Multilingual,Multinational,Musculoskeletal,Musical,Mystic,Napoleonic,National,Native,Natural,Naval,Near,Networked,New,Nineteenth-Century,Non-Profit,Non-Western,Nonlinear,Nonprofit,North,Northeast,Northern,Nuclear,Nucleic,Numerical,Nutritional,Object-Oriented,Occupational,Ocean,Old,Older,One-Dimensional,Open,Organic,Organizational,Oriented,Ornamental,Outdoor,Parallel,Park,Past,Pathogenic,Pathologic,Period,Periodic,Personal,Physical,Physiological,Piano,Poisonous,Political,Pond-Less,Pop,Popular,Positive,Postcolonial,Pre-Colonial,Pre-Modern,Pre-Practicum,Pre-Stressed,Preceptorial,Predicitve,Prehospital,Prenatal,Present,Pressurized,Primary,Principle,Private,Problem,Process,Professional,Programmable,Prokaryotic,Psychological,Psychosocial,Public,Quantitative,Quantum,Rain,Random,Real,Recreational,Regional,Reinforced,Related,Remote,Renewable,Residential,Restaurant,Rhetorical,Right,Roman,Romanesque,Romantic,Rural,Scenic,Scientific,Second,Secondary,Selected,Semantic,Sensory,Small,Smart,Social,Societal,Sociological,Solar,Solid,Special,Spectroscopic,Spring,Stagewise,State,Statistical,Strategic,Stressed,Structural,Sub-Saharan,Supervised,Supreme,Surgical,Sustainable,Symphonic,Technical,Technological,Technology-Related,Terrestrial,Theatrical,Therapeutic,Thermal,Third,Top,Torn,Tropical,Tudor,Twentieth,Twentieth-Century,Ubiquitous,Ugly,Uniform,United,Unusual,Urban,Vertebrate,Veterinary,Victorian,Virtual,Visual,Vocal,Water-Borne,Western,Winter,Woody,Workshop,World,Young");
    $subjectlist = explode(",","Analysis,Analytics,Anatomy,Anesthesia,Animals,Anthropology,Aquaculture,Arabic,Arboriculture,Archaeology,Architecture,Art,Aspects,Asphalt,Astronomy,Astrophysics,Athletics,Attention,Audiology,Autism,Avatars,Babies,Bacteriology,Baking,Ballet,Band,Banking,Barbarians,Bards,Basketball,Beaches,Behavior,Behaviorism,Being,Biochemistry,Biodiversity,Bioengineering,Biogeochemistry,Biogeography,Bioinformatics,Biology,Biomaterials,Biomechanics,Bionics,Biostatistics,Biotech,Biotechnology,Botany,Bureaucracy,Business,Calculus,Camelids,Canoeing,Capital,Capitalism,Careers,Catering,Centuries,Ceramics,Change,Channel,Chaos,Characters,Chaucer,Chekhov,Chemistry,Childhood,Children,Chinese,Choreography,Cinema,Circuits,Cities,Citizenship,Civilization,Climate,Climbing,Coasts,Cognition,Combinatorics,Combustion,Comedy,Communication,Communications,Communities,Community,Competency,Competition,Compiler,Compocinema,Composition,Computation,Computers,Computing,Concepts,Concert,Condition,Conditions,Conflicts,Conformation,Congress,Conquest,Consciousness,Conservation,Constitution,Construction,Consulting,Consumer/Buyer,Contemporaries,Contexts,Contracting,Control,Controversy,Convention,Conversation,Corrosion,Cosmology,Costume,Counseling,Counterpoint,Counterterrorism,Course,Courses,Courts,Creativity,Crime,Criminology,Criticism,Crusades,Cuisine,Culture,Cultures,Curriculum,Cyberculture,Cyborgs,Dali,Dancers,Darkroom,Data,Database,Deaf,Death,Decision,Decisions,Dehumanization,Deities,Delineation,Delinquency,Democracies,Democracy,Democrats,Demography,Dendrology,Descartes,Designs,Development,Devils,Diagnostics,Dialects,Dialogue,Dictators,Diction,Diet,Dietetics,Diffraction,Dining,Diplomacy,Dirt,Disabilities,Disability,Discoveries,Discovery,Disease,Diseases,Disorders,Display,Dissent,Diversity,Diving,DNA,Documentation,Dogs,Draft,Dragons,Drama,Dramatics,Drawing,Dreaming,Dreams,Dressage,Drug,Drugs,Drum,Dying,Dynamics,Dysfunction,E-Business,Ear,Earthquakes,Ecogastronomy,Ecohydrology,Ecology,Econometrics,Economics,Ecosystems,Ecotourism,Education,Educators,Electricity,Electrocardiography,Emergencies,Emigration,Empires,Employment,Endocrinology,Energy,Engineering,Engines,English,Enlightenment,Enrollment,Enterprises,Entomology,Entrepreneurship,Environment,Environments,Epidemics,Epidemiology,Epistemology,Equality,Equations,Equipment,Equity/Venture,Espionage,Estate,Ethics,Ethnicity,Euphonium,Europe,Evaluation,Event,Evolution,Exceptionalities,Exceptionality,Exercise,Existentialism,Experiment,Expository,Extinction,Eye,Eyes,Facilitation,Facilities,Facility,Families,Family,Farm,Fate,Fatigue,Faulting,Feature,Features,Feminism,Feminist,Feminists,Festival,Fiber,Fiction,Fields,Film,Finance,Fire,Fish,Fisheries,Fishes,Fishing,Fitness,Flow,Flower,Fluid,Fluids,Flute,Folklife,Folklore,Food,Football,Forage,Forecasting,Forensics,Forestland,Forestry,Fort,Fossil,Fossils,Foundation,Foundations,Fracture,Framing,France,Franchising,Francophone,Frankenstein,Freedom,French,Frog,Fruit,Fuels,Function,Functions,Fund,Fundraising,Game,Games,Garden,Gauguin,Gender,Genes,Genetics,Genius,Genomics,Genres,Geochemistry,Geodesy,Geodynamics,Geography,Geology,Geometry,Geophysics,Geotectonics,Germ,Germs,Globalization,Goggles,Government,Grammar,Grant,Grantsmanship,Graphics,Grassland,Greek,Greenhouse,Grounds,Groundwater,Guilt,Guitar,Gymnastics,Habitat,Habitats,Happiness,Harassment,Hardware,Hazards,Headlines,Healing,Health,Heat,Hematology,Herbs,Herd,Heretics,Hertiage,Histology,Historians,Histories,Historiography,History,Hittite,Hockey,Home,Homer,Homicide,Horsemanship,Horses,Hospitality,Houses,Humanities,Humanity,Hungarian,Hydrodynamics,Hydrology,Ichthyology,Ideas,Identification,Identities,Ideology,Immersion,Immigration,Immunohematology,Immunology,Inclusion,Income,Indian,Individuals,Industry,Inequality,Infancy,Inference,Information,Infrastructure,Injuries,Innovation,Insects,Institutions,Instruction,Instrumentation,Instruments,Intaglio,Intelligence,Interaction,Interactions,Interest,Interpretation,Intervention,Interventions,Inventory,Invertebrates,Investment,Investments,Irish,Irrigation,Isotope,Italian,Japanese,Jazz,Journal,Journalism,Judgment,Justice,Kant,Kayaking,Kinesiology,Kinetics,King,Kingdom,Labor,Landforms,Landscape,Language,Languages,Latin,Law,Leadership,Learners,Learning,Lectures,Leisure,Leonardo,Liberties,Liberty,Life,Lifespan,Lighting,Linearity,Linguistics,Literacy,Literature,Literatures,Lithography,Lives,Livestock,Lodging,Logging,Logic,Loss,Madness,Magnetism,Maintenance,Makeover,Makeup,Mammalogy,Management,Manufacturing,Marching,Marketing,Markets,Masonry,Masterpieces,Materials,Mathematics,Matter,Meaning,Measurement,Measurements,Mechanics,Mechanisms,Mechanization,Media,Mediation,Medicine,Mentoring,Metabolism,Metallurgy,Metaphysics,Microbes,Microbiology,Microfluidics,Micronutrients,Microprocessors,Microscopy,Mildews,Milton,Mind,Mine,Mineralogy,Mining,Modalities,Models,Modernism,Modernization,Molds,Molecules,Mona Lisa,Money,Mood,Morality,Morphology,Motherhood,Movement,Mozart,Museum,Mushrooms,Music,Mycology,Myth,Mythology,Nanoscience,Nature,Navigation,Neotropics,Networks,Neurobiology,Neurology,Neuroscience,News,Nonfiction,Norms,Novel,Number,Nursing,Nutrition,Obesity,Occupation,Occupations,Oceanography,Opera,Operation,Operations,Opinion,Opportunities,Opportunity,Optics,Orchestration,Order,Organization,Organizations,Origins,Ornithology,Oxen,Oxygen,Painting,Paleoclimatology,Paleontology,Paradise,Parasites,Parasitology,Parenting,Participation,Parties,Pastry,Pathologies,Pathology,Paths,Pathways,Pavement,Peace,Pedagogy,People,Peoples,Perception,Percussion,Performance,Periodicals,Periods,Persistence,Personality,Persons,Perspective,Perspectives,Persuasion,Persuasive,Pet,Petrology,Pharmacology,Philosophies,Philosophy,Phlebotomy,Phonetics,Phonology,Photography,Physics,Physiology,Pioneers,Pirates,Place,Plants,Playwriting,Poetry,Pointe,Policies,Policy,Politics,Pollution,Population,Populations,Portuguese,Post-Modernism,Pots,Poultry,Poverty,Power,Powers,Prevention,Pricing,Primates,Printmaking,Privilege,Probability,Problems,Processes,Production,Products,Professions,Profits,Programs,Projects,Promotion,Proof,Propaganda,Propagation,Properties,Prose,Protection,Proteomics,Protest,Psychobiology,Psychology,Puppetry,Pursuits,Reading,Reason,Reasoning,Rebellion,Recreation,Reform,Region,Regression,Regulation,Regulations,Rehabilitation,Relations,Relationships,Relativity,Republics,Rescue,Research,Researchers,Resiliency,Resources,Responses,Responsibility,Revenue,Revolution,Revolutions,Rhetorics,Riding,Rights,Risk,Risks,Ritual,Roads,Robots,Roles,Romance,Romanticism,Roots,Rounds,Ruminants,Russian,Safety,Sales,Sanitation,Sanskrit,Schools,Scientists,Seamanship,Securities,Sedimentology,Selection,Sensation,Sense,Sensibility,Separation,Serology,Services,Settings,Shakespeare,Signals,Silviculture,Singers,Skills,Societies,Society,Sociolinguistics,Sociology,Software,Soils,Solids,Solutions,Sources,Spanish,Species,Specifications,Spectacle,Speeches,Sports,Stagecraft,States,Statistics,Status,Stereotypes,Stories,Story,Storytelling,Strategy,Strength,Stress,Structure,Structures,Struggle,Students,Supplements,Support,Surveying,Survival,Sustainability,Symmetry,Syntax,Systems,Talk,Tasks,Taste,Taxes,Teachers,Teamwork,Technicians,Techniques,Technologies,Tests,Texts,Themes,Theories,Theory,Therapies,Therapy,Thermodynamics,Thinking,Thought,Tides,Time,Times,Tissue,Tools,Topics,Topology,Tourism,Toxicology,Traditions,Tragedy,Translation,Transmission,Transportation,Travel,Treatment,Trees,Trials,Tropics,Truths,Urbanization,Use,Uses,Utopia,Values,Verification,Vertebrates,Vibration,Violence,Virology,Visions,Visualization,Voices,Volcanology,Volunteers,Voters,Waves,Ways,Wealth,Weather,Weight,Welding,Welfare,Well-Being,Wellness,Wetlands,Wine,Furniture,Woodworking,Work,Workshops,Worlds,Writers,Youth,Zoology" );
    $modlist = explode(",", "Instrumental Methods of,Properties and Production of,Design of,Behavior of,Dynamics of,Ethics of,Theory of,Evaluation of,Interactive,History of,Philosophy of,Principles of,Applications of,Analysis of,Mechanics of,Persuasion in,Psychology of");
    $modnounlist = explode(",","Action,Adventure,Air,Angle,Animal,Asset,Backcountry,Bass,Bassoon,Beverage,Blood,Brain,Brass,Bridge,Camp,Cancer,Care,Career,Catastrophe,Cattle,Cell,Chamber,Character,Child,Choir,Clarinet,Club,Combat,Community,Computer,Conflict,Content,Cooking,Cost,Court,Courtroom,Criminal,Cycle,Dance,Earth,Earthquake,Economy,Electron,Element,Emergency,Empire,Ensemble,Forest,Freshman,Freshwater,Future,Horn,Horse,Host-Microbe,Human,Human-Computer,Human-Environment,Human/Animal,Ice,Idea,Identity,Illness,Image,Injury,Internet,Lake,Land,Lifetime,Lobster,Locavore,Machine,Mammal,Market,Matrix,Microbiome,Microcomputer,Military,Motor,Multimedia,Nation,Network,Newt,Oboe,Pest,Plague,Planet,Plant,Plant-Animal,Plant-Microbe,Plasma,Play,Police,Pollock,Prescription,Presidency,Product,Profession,Protein,Quality,Quantity,Renaissance,Republic,Resort,Resource,Responder,Resume,Reward,Rock,Room,Rope,Ruminant,Satellite,Saxophone,Scene,School,Scuba,Sculpture,Sea,Security,Seed,Self,Service,Shop,Sign,Signal,Silver,Soil,Song,Soprano,Space,Spectrum,Speech,Sport,Spreadsheet,Stage,Steel,Stormwater,Stream,String,Summer,Swine,Symphony,System,Table,Tax,Teacher,Team,Technician,Technology,Theatre,Timber,Toe,Tour,Track,Transport,Tree,Trial,Trombone,Trumpet,Tuba,Turf,Underwater,University,Value,Vegetable,Video,Viola,Violin,Violoncello,Voice,Waste,Water,Watershed,Web,Weekend,Wetland,Wilderness,Wildlife,Wind,Woodwind,Worker,Workplace");
    $postfixlist = explode (",","Abundance,Acquisition,Administration,Advocacy,Affairs,Analysis,Application,Applications,Approach,Approaches,Argumentation,Articulation,Arts,Assessment,Assurance,Audiences,Auditing,Authors,Awareness,Based,Basis,Baseline,Case,Century,Challenge,Classroom,Continuing,Country,Creation,Description,Design,Directed,Emergence,Enhancing,Evolution,Examination,Examining,Excursions,Execution,Expedition,Experience,Experiences,Experiments,Exploration,Explorations,Exploring,Factors,Field,Fieldwork,Formulating,Fundamentals,Impact,Improvement,Insights,Installation,Integration,Investigations,Involvement,Issues,Lab,Laboratory,Lecture,Method,Methodology,Methods,Misconceptions,Misuse,Mitigation,Mixtures,Moving,Myths,Needs,Observation,Paradigm,Paradigms,Peopling,Practical,Practice,Practices,Practicum,Preparation,Principles,Program,Pursuit,Readings,Restoration,Review,Science,Sciences,Simulation,Speakers,Statement,Views,Works");
    $levellist = explode(",","I,II,III,IV");
    $gerundlist = explode(",", "Abstracting,Accepting,Adopting,Analyzing,Applying,Approaching,Attending to,Attributing,Balancing,Becoming Aware of,Budgeting,Building,Calssifying,Carlifying,Carrying out,Categorizing,Characterizing,Checking,Coaching,Committing to,Comparing,Computing,Conceptualizing,Concluding,Conducting,Constructing,Constructing models,Contrasting,Coordinating,Creating,Critiquing,Deconstructing,Designing,Detecting,Developing,Differentiating,Directing,Discriminating,Distinguishing,Editing,Educating,Enabling,Evaluating,Executing,Exemplifying,Explaining,Extrapolating,Finding Coherence,Focusing,Generalizing,Generating,Handling,Harvesting,Hearing,Hypothesizing,Identifying,Illustrating,Imaging,Implementing,Inferring,Instantiating,Integrating,Interpolating,Interpreting,Investigating,Judging,Living,Making,Managing,Mapping,Marketing,Matching,Modeling,Monitoring,Opening,Operating,Organizing,Outlining,Paraphrasing,Parsing,Planning,Positioning,Predicting,Preferring,Processing,Producing,Programming,Pruning,Recalling,Recognizing,Reflecting,Remaking,Remembering,Rendering,Reporting,Representing,Resolving,Responding,Responding to,Retrieving,Sampling,Scripting,Selecting,Sensing,Structuring,Subsuming,Summarizing,Supervising,Supporting,Sustaining,Taking,Teaching,Testing,Thinking,Threading,Training,Transforming,Translating,Tutoring,Understanding,Using,Valuing,Winning,Writing");
    $majorlist = explode(",","Engineering and Physical Science,Bioengineering,Chemical Engineering,Chemistry,Civil Engineering,Computer Science,Earth Science,Electrical and Computer Engineering,Environmental Engineering,Information Technology,Integrated Applied Mathematics,Materials Science,Mathematics and Statistics,Mechanical Engineering,Ocean Engineering,Physics,Technology,Liberal Arts,Anthropology,Arabic,Art and Art History,Chinese,Classics,Communication,Education,English,French,Geography,German,Greek,History,Humanities,Italian Studies,Japanese,Justice Studies,Latin,Linguistics,Music,Music Education,Philosophy,Political Science,Portuguese,Psychology,Russian,Sociology,Spanish,Theatre and Dance,Life Sciences and Agriculture,Animal Science,Biochemistry,Molecular and Cellular Biology,Biology,Biomedical Science,Community and Environmental Planning,Environmental and Resource Economics,Environmental Conservation and Sustainability,Environmental Science,Equine Studies,Forestry,Genetics,Life Sciences and Agriculture,Marine Biology,Natural Resources,Neuroscience and Behavior,Nutrition,Sustainable Agriculture and Food Systems,Tourism,Wildlife and Conservation Biology,Zoology,Health and Human Services,Athletic Training,Communication Sciences and Disorders,Health Management and Policy,Human Development and Family Studies,Kinesiology,Nursing,Occupational Therapy,Recreation Management and Policy,Social Work,Applied Science,Agricultural Mechanization,Applied Animal Science,Applied Business Management,Civil Technology,Community Leadership,Culinary Arts and Nutrition,Forest Technology,Horticultural Technology,Integrated Agriculture Management,Veterinary Technology,Business and Economics,Accounting and Finance,Business Administration,Decision Science,Ecogastronomy,Economics,Hospitality Management,Management,Marketing");


    $maxcount = 1;
     do {

     // initial subject list, e.g. "Anatomy"
     $id = $subjectlist[rand(0,count($subjectlist)-1)];
     $maxcount = $maxcount * count($subjectlist);

     if (rand(0,1)<$len/$maxcount) {
     //insert modifying noun like "Adventure"
     $id = $modnounlist[rand(0,count($modnounlist)-1)] . " " . $id;
     		 $maxcount =  $maxcount * count($modnounlist);
     }

     if (rand(0,1)<$len/$maxcount) {
	     //insert adjective like "Abstract"
	     $id = $adjlist[rand(0,count($adjlist)-1)] . " " . $id;
	     $maxcount =  $maxcount * count($adjlist);

	     if (rand(0,1)<$len/$maxcount) {
		     // prepend modifier like "Instrumental methods of"
		     //$id = $modlist[rand(0,count($modlist))];
		     $id = $modlist[rand(0,count($modlist)-1)] . " " . $id;
		     $maxcount =  $maxcount * count($modlist);
	     }
     }

     if (rand(0,1)<$len/$maxcount) {
     // prepend level prefix like "Introduction to"
      $id = $prefixlist[rand(0,count($prefixlist)-1)] . " " . $id;
    		 $maxcount =  $maxcount * count($prefixlist);
     }


     if (rand(0,1)<($len/$maxcount)*.5) {
     		 // combo courses like "Anthropology and Aquaculture"
     		 $id = $id . " and " . $subjectlist[rand(0,count($subjectlist)-1)];
     		 $maxcount =  $maxcount * count($subjectlist);
     }

     if (rand(0,1)<$len/$maxcount) {
     //posftix like "Administration"
       $id = $id  . " " . $postfixlist[rand(0,count($postfixlist)-1)];
     		 $maxcount =  $maxcount * count($postfixlist);
     }

/*     if (rand(0,1)>(($len - $maxcount)/$len)*100000000000000000) {
     		 // for subject majors
     		 $id = $id  . " for " . $majorlist[rand(0,count($majorlist)-1)] . " Majors";
     		 $maxcount =  $maxcount * count($majorlist);
     }
 */
     if (rand(0,1)<($len/$maxcount)*.5) {
    //level I II III IV
       $id = $id  . " " . $levellist[rand(0,count($levellist)-1)];
     		 $maxcount =  $maxcount * count($levellist);
     }
     if (rand(0,1)<$len/$maxcount) {
     // prepend gerund like "Analyzing"
      $id = $gerundlist[rand(0,count($gerundlist)-1)] . " " . $id;
    		 $maxcount =  $maxcount * count($gerundlist);
     }



    // add modulus (as string) to end of string if there is anything left
  if ($len > $maxcount) {
      $id = $id . " " . substr(strval($len-$maxcount),-3);
  } // if
     } while (array_search($id, $usedpseudoids) !== false);
    $usedpseudoids[] = $id;
    return $id;
}

function assign_pseudo_gname() {
    // rather than just assigning a random string of junk,
    // this algorithm selects a name at random from a list of most common given names
    $givennamelist = explode(",","Mary,Patricia,Linda,Barbara,Elizabeth,Jennifer,Maria,Susan,Margaret,Dorothy,Lisa,Nancy,Karen,Betty,Helen,Sandra,Donna,Carol,Ruth,Sharon,Michelle,Laura,Sarah,Kimberly,Deborah,Jessica,Shirley,Cynthia,Angela,Melissa,Brenda,Amy,Anna,Rebecca,Virginia,Kathleen,Pamela,Martha,Debra,Amanda,Stephanie,Carolyn,Christine,Marie,Janet,Catherine,Frances,Ann,Joyce,Diane,Alice,Julie,Heather,Teresa,Doris,Gloria,Evelyn,Jean,Cheryl,Mildred,Katherine,Joan,Ashley,Judith,Rose,Janice,Kelly,Nicole,Judy,Christina,Kathy,Theresa,Beverly,Denise,Tammy,Irene,Jane,Lori,Rachel,Marilyn,Andrea,Kathryn,Louise,Sara,Anne,Jacqueline,Wanda,Bonnie,Julia,Ruby,Lois,Tina,Phyllis,Norma,Paula,Diana,Annie,Lillian,Emily,Robin,Peggy,Crystal,Gladys,Rita,Dawn,Connie,Florence,Tracy,Edna,Tiffany,Carmen,Rosa,Cindy,Grace,Wendy,Victoria,Edith,Kim,Sherry,Sylvia,Josephine,Thelma,Shannon,Sheila,Ethel,Ellen,Elaine,Marjorie,Carrie,Charlotte,Monica,Esther,Pauline,Emma,Juanita,Anita,Rhonda,Hazel,Amber,Eva,Debbie,April,Leslie,Clara,Lucille,Jamie,Joanne,Eleanor,Valerie,Danielle,Megan,Alicia,Suzanne,Michele,Gail,Bertha,Darlene,Veronica,Jill,Erin,Geraldine,Lauren,Cathy,Joann,Lorraine,Lynn,Sally,Regina,Erica,Beatrice,Dolores,Bernice,Audrey,Yvonne,Annette,June,Samantha,Marion,Dana,Stacy,Ana,Renee,Ida,Vivian,Roberta,Holly,Brittany,Melanie,Loretta,Yolanda,Jeanette,Laurie,Katie,Kristen,Vanessa,Alma,Sue,Elsie,Beth,Jeanne,Vicki,Carla,Tara,Rosemary,Eileen,Terri,Gertrude,Lucy,Tonya,Ella,Stacey,Wilma,Gina,Kristin,Jessie,Natalie,Agnes,Vera,Willie,Charlene,Bessie,Delores,Melinda,Pearl,Arlene,Maureen,Colleen,Allison,Tamara,Joy,Georgia,Constance,Lillie,Claudia,Jackie,Marcia,Tanya,Nellie,Minnie,Marlene,Heidi,Glenda,Lydia,Viola,Courtney,Marian,Stella,Caroline,Dora,Jo,Vickie,Mattie,Terry,Maxine,Irma,Mabel,Marsha,Myrtle,Lena,Christy,Deanna,Patsy,Hilda,Gwendolyn,Jennie,Nora,Margie,Nina,Cassandra,Leah,Penny,Kay,Priscilla,Naomi,Carole,Brandy,Olga,Billie,Dianne,Tracey,Leona,Jenny,Felicia,Sonia,Miriam,Velma,Becky,Bobbie,Violet,Kristina,Toni,Misty,Mae,Shelly,Daisy,Ramona,Sherri,Erika,Katrina,Claire,Lindsey,Lindsay,Geneva,Guadalupe,Belinda,Margarita,Sheryl,Cora,Faye,Ada,Natasha,Sabrina,Isabel,Marguerite,Hattie,Harriet,Molly,Cecilia,Kristi,Brandi,Blanche,Sandy,Rosie,Joanna,Iris,Eunice,Angie,Inez,Lynda,Madeline,Amelia,Alberta,Genevieve,Monique,Jodi,Janie,Maggie,Kayla,Sonya,Jan,Lee,Kristine,Candace,Fannie,Maryann,Opal,Alison,Yvette,Melody,Luz,Susie,Olivia,Flora,Shelley,Kristy,Mamie,Lula,Lola,Verna,Beulah,Antoinette,Candice,Juana,Jeannette,Pam,Kelli,Hannah,Whitney,Bridget,Karla,Celia,Latoya,Patty,Shelia,Gayle,Della,Vicky,Lynne,Sheri,Marianne,Kara,Jacquelyn,Erma,Blanca,Myra,Leticia,Pat,Krista,Roxanne,Angelica,Johnnie,Robyn,Francis,Adrienne,Rosalie,Alexandra,Brooke,Bethany,Sadie,Bernadette,Traci,Jody,Kendra,Jasmine,Nichole,Rachael,Chelsea,Mable,Ernestine,Muriel,Marcella,Elena,Krystal,Angelina,Nadine,Kari,Estelle,Dianna,Paulette,Lora,Mona,Doreen,Rosemarie,Angel,Desiree,Antonia,Hope,Ginger,Janis,Betsy,Christie,Freda,Mercedes,Meredith,Lynette,Teri,Cristina,Eula,Leigh,Meghan,Sophia,Eloise,Rochelle,Gretchen,Cecelia,Raquel,Henrietta,Alyssa,Jana,Kelley,Gwen,Kerry,Jenna,Tricia,Laverne,Olive,Alexis,Tasha,Silvia,Elvira,Casey,Delia,Sophie,Kate,Patti,Lorena,Kellie,Sonja,Lila,Lana,Darla,May,Mindy,Essie,Mandy,Lorene,Elsa,Josefina,Jeannie,Miranda,Dixie,Lucia,Marta,Faith,Lela,Johanna,Shari,Camille,Tami,Shawna,Elisa,Ebony,Melba,Ora,Nettie,Tabitha,Ollie,Jaime,Winifred,Kristie,Marina,Alisha,Aimee,Rena,Myrna,Marla,Tammie,Latasha,Bonita,Patrice,Ronda,Sherrie,Addie,Francine,Deloris,Stacie,Adriana,Cheri,Shelby,Abigail,Celeste,Jewel,Cara,Adele,Rebekah,Lucinda,Dorthy,Chris,Effie,Trina,Reba,Shawn,Sallie,Aurora,Lenora,Etta,Lottie,Kerri,Trisha,Nikki,Estella,Francisca,Josie,Tracie,Marissa,Karin,Brittney,Janelle,Lourdes,Laurel,Helene,Fern,Elva,Corinne,Kelsey,Ina,Bettie,Elisabeth,Aida,Caitlin,Ingrid,Iva,Eugenia,Christa,Goldie,Cassie,Maude,Jenifer,Therese,Frankie,Dena,Lorna,Janette,Latonya,Candy,Morgan,Consuelo,Tamika,Rosetta,Debora,Cherie,Polly,Dina,Jewell,Fay,Jillian,Dorothea,Nell,Trudy,Esperanza,Patrica,Kimberley,Shanna,Helena,Carolina,Cleo,Stefanie,Rosario,Ola,Janine,Mollie,Lupe,Alisa,Lou,Maribel,Susanne,Bette,Susana,Elise,Cecile,Isabelle,Lesley,Jocelyn,Paige,Joni,Rachelle,Leola,Daphne,Alta,Ester,Petra,Graciela,Imogene,Jolene,Keisha,Lacey,Glenna,Gabriela,Keri,Ursula,Lizzie,Kirsten,Shana,Adeline,Mayra,Jayne,Jaclyn,Gracie,Sondra,Carmela,Marisa,Rosalind,Charity,Tonia,Beatriz,Marisol,Clarice,Jeanine,Sheena,Angeline,Frieda,Lily,Robbie,Shauna,Millie,Claudette,Cathleen,Angelia,Gabrielle,Autumn,Katharine,Summer,Jodie,Staci,Lea,Christi,Jimmie,Justine,Elma,Luella,Margret,Dominique,Socorro,Rene,Martina,Margo,Mavis,Callie,Bobbi,Maritza,Lucile,Leanne,Jeannine,Deana,Aileen,Lorie,Ladonna,Willa,Manuela,Gale,Selma,Dolly,Sybil,Abby,Lara,Dale,Ivy,Dee,Winnie,Marcy,Luisa,Jeri,Magdalena,Ofelia,Meagan,Audra,Matilda,Leila,Cornelia,Bianca,Simone,Bettye,Randi,Virgie,Latisha,Barbra,Georgina,Eliza,Leann,Bridgette,Rhoda,Haley,Adela,Nola,Bernadine,Flossie,Ila,Greta,Ruthie,Nelda,Minerva,Lilly,Terrie,Letha,Hilary,Estela,Valarie,Brianna,Rosalyn,Earline,Catalina,Ava,Mia,Clarissa,Lidia,Corrine,Alexandria,Concepcion,Tia,Sharron,Rae,Dona,Ericka,Jami,Elnora,Chandra,Lenore,Neva,Marylou,Melisa,Tabatha,Serena,Avis,Allie,Sofia,Jeanie,Odessa,Nannie,Harriett,Loraine,Penelope,Milagros,Emilia,Benita,Allyson,Ashlee,Tania,Tommie,Esmeralda,Karina,Eve,Pearlie,Zelma,Malinda,Noreen,Tameka,Saundra,Hillary,Amie,Althea,Rosalinda,Jordan,Lilia,Alana,Gay,Clare,Alejandra,Elinor,Michael,Lorrie,Jerri,Darcy,Earnestine,Carmella,Taylor,Noemi,Marcie,Liza,Annabelle,Louisa,Earlene,Mallory,Carlene,Nita,Selena,Tanisha,Katy,Julianne,John,Lakisha,Edwina,Maricela,Margery,Kenya,Dollie,Roxie,Roslyn,Kathrine,Nanette,Charmaine,Lavonne,Ilene,Kris,Tammi,Suzette,Corine,Kaye,Jerry,Merle,Chrystal,Lina,Deanne,Lilian,Juliana,Aline,Luann,Kasey,Maryanne,Evangeline,Colette,Melva,Lawanda,Yesenia,Nadia,Madge,Kathie,Eddie,Ophelia,Valeria,Nona,Mitzi,Mari,Georgette,Claudine,Fran,Alissa,Roseann,Lakeisha,Susanna,Reva,Deidre,Chasity,Sheree,Carly,James,Elvia,Alyce,Deirdre,Gena,Briana,Araceli,Katelyn,Rosanne,Wendi,Tessa,Berta,Marva,Imelda,Marietta,Marci,Leonor,Arline,Sasha,Madelyn,Janna,Juliette,Deena,Aurelia,Josefa,Augusta,Liliana,Young,Christian,Lessie,Amalia,Savannah,Anastasia,Vilma,Natalia,Rosella,Lynnette,Corina,Alfreda,Leanna,Carey,Amparo,Coleen,Tamra,Aisha,Wilda,Karyn,Cherry,Queen,Maura,Mai,Evangelina,Rosanna,Hallie,Erna,Enid,Mariana,Lacy,Juliet,Jacklyn,Freida,Madeleine,Mara,Hester,Cathryn,Lelia,Casandra,Bridgett,Angelita,Jannie,Dionne,Annmarie,Katina,Beryl,Phoebe,Millicent,Katheryn,Diann,Carissa,Maryellen,Liz,Lauri,Helga,Gilda,Adrian,Rhea,Marquita,Hollie,Tisha,Tamera,Angelique,Francesca,Britney,Kaitlin,Lolita,Florine,Rowena,Reyna,Twila,Fanny,Janell,Ines,Concetta,Bertie,Alba,Brigitte,Alyson,Vonda,Pansy,Elba,Noelle,Letitia,Kitty,Deann,Brandie,Louella,Leta,Felecia,Sharlene,Lesa,Beverley,Robert,Isabella,Herminia,Terra,Celina,James,John,Robert,Michael,William,David,Richard,Charles,Joseph,Thomas,Christopher,Daniel,Paul,Mark,Donald,George,Kenneth,Steven,Edward,Brian,Ronald,Anthony,Kevin,Jason,Matthew,Gary,Timothy,Jose,Larry,Jeffrey,Frank,Scott,Eric,Stephen,Andrew,Raymond,Gregory,Joshua,Jerry,Dennis,Walter,Patrick,Peter,Harold,Douglas,Henry,Carl,Arthur,Ryan,Roger,Joe,Juan,Jack,Albert,Jonathan,Justin,Terry,Gerald,Keith,Samuel,Willie,Ralph,Lawrence,Nicholas,Roy,Benjamin,Bruce,Brandon,Adam,Harry,Fred,Wayne,Billy,Steve,Louis,Jeremy,Aaron,Randy,Howard,Eugene,Carlos,Russell,Bobby,Victor,Martin,Ernest,Phillip,Todd,Jesse,Craig,Alan,Shawn,Clarence,Sean,Philip,Chris,Johnny,Earl,Jimmy,Antonio,Danny,Bryan,Tony,Luis,Mike,Stanley,Leonard,Nathan,Dale,Manuel,Rodney,Curtis,Norman,Allen,Marvin,Vincent,Glenn,Jeffery,Travis,Jeff,Chad,Jacob,Lee,Melvin,Alfred,Kyle,Francis,Bradley,Jesus,Herbert,Frederick,Ray,Joel,Edwin,Don,Eddie,Ricky,Troy,Randall,Barry,Alexander,Bernard,Mario,Leroy,Francisco,Marcus,Micheal,Theodore,Clifford,Miguel,Oscar,Jay,Jim,Tom,Calvin,Alex,Jon,Ronnie,Bill,Lloyd,Tommy,Leon,Derek,Warren,Darrell,Jerome,Floyd,Leo,Alvin,Tim,Wesley,Gordon,Dean,Greg,Jorge,Dustin,Pedro,Derrick,Dan,Lewis,Zachary,Corey,Herman,Maurice,Vernon,Roberto,Clyde,Glen,Hector,Shane,Ricardo,Sam,Rick,Lester,Brent,Ramon,Charlie,Tyler,Gilbert,Gene,Marc,Reginald,Ruben,Brett,Angel,Nathaniel,Rafael,Leslie,Edgar,Milton,Raul,Ben,Chester,Cecil,Duane,Franklin,Andre,Elmer,Brad,Gabriel,Ron,Mitchell,Roland,Arnold,Harvey,Jared,Adrian,Karl,Cory,Claude,Erik,Darryl,Jamie,Neil,Jessie,Christian,Javier,Fernando,Clinton,Ted,Mathew,Tyrone,Darren,Lonnie,Lance,Cody,Julio,Kelly,Kurt,Allan,Nelson,Guy,Clayton,Hugh,Max,Dwayne,Dwight,Armando,Felix,Jimmie,Everett,Jordan,Ian,Wallace,Ken,Bob,Jaime,Casey,Alfredo,Alberto,Dave,Ivan,Johnnie,Sidney,Byron,Julian,Isaac,Morris,Clifton,Willard,Daryl,Ross,Virgil,Andy,Marshall,Salvador,Perry,Kirk,Sergio,Marion,Tracy,Seth,Kent,Terrance,Rene,Eduardo,Terrence,Enrique,Freddie,Wade,Austin,Stuart,Fredrick,Arturo,Alejandro,Jackie,Joey,Nick,Luther,Wendell,Jeremiah,Evan,Julius,Dana,Donnie,Otis,Shannon,Trevor,Oliver,Luke,Homer,Gerard,Doug,Kenny,Hubert,Angelo,Shaun,Lyle,Matt,Lynn,Alfonso,Orlando,Rex,Carlton,Ernesto,Cameron,Neal,Pablo,Lorenzo,Omar,Wilbur,Blake,Grant,Horace,Roderick,Kerry,Abraham,Willis,Rickey,Jean,Ira,Andres,Cesar,Johnathan,Malcolm,Rudolph,Damon,Kelvin,Rudy,Preston,Alton,Archie,Marco,Wm,Pete,Randolph,Garry,Geoffrey,Jonathon,Felipe,Bennie,Gerardo,Ed,Dominic,Robin,Loren,Delbert,Colin,Guillermo,Earnest,Lucas,Benny,Noel,Spencer,Rodolfo,Myron,Edmund,Garrett,Salvatore,Cedric,Lowell,Gregg,Sherman,Wilson,Devin,Sylvester,Kim,Roosevelt,Israel,Jermaine,Forrest,Wilbert,Leland,Simon,Guadalupe,Clark,Irving,Carroll,Bryant,Owen,Rufus,Woodrow,Sammy,Kristopher,Mack,Levi,Marcos,Gustavo,Jake,Lionel,Marty,Taylor,Ellis,Dallas,Gilberto,Clint,Nicolas,Laurence,Ismael,Orville,Drew,Jody,Ervin,Dewey,Al,Wilfred,Josh,Hugo,Ignacio,Caleb,Tomas,Sheldon,Erick,Frankie,Stewart,Doyle,Darrel,Rogelio,Terence,Santiago,Alonzo,Elias,Bert,Elbert,Ramiro,Conrad,Pat,Noah,Grady,Phil,Cornelius,Lamar,Rolando,Clay,Percy,Dexter,Bradford,Merle,Darin,Amos,Terrell,Moses,Irvin,Saul,Roman,Darnell,Randal,Tommie,Timmy,Darrin,Winston,Brendan,Toby,Van,Abel,Dominick,Boyd,Courtney,Jan,Emilio,Elijah,Cary,Domingo,Santos,Aubrey,Emmett,Marlon,Emanuel,Jerald,Edmond,Emil,Dewayne,Will,Otto,Teddy,Reynaldo,Bret,Morgan,Jess,Trent,Humberto,Emmanuel,Stephan,Louie,Vicente,Lamont,Stacy,Garland,Miles,Micah,Efrain,Billie,Logan,Heath,Rodger,Harley,Demetrius,Ethan,Eldon,Rocky,Pierre,Junior,Freddy,Eli,Bryce,Antoine,Robbie,Kendall,Royce,Sterling,Mickey,Chase,Grover,Elton,Cleveland,Dylan,Chuck,Damian,Reuben,Stan,August,Leonardo,Jasper,Russel,Erwin,Benito,Hans,Monte,Blaine,Ernie,Curt,Quentin,Agustin,Murray,Jamal,Devon,Adolfo,Harrison,Tyson,Burton,Brady,Elliott,Wilfredo,Bart,Jarrod,Vance,Denis,Damien,Joaquin,Harlan,Desmond,Elliot,Darwin,Ashley,Gregorio,Buddy,Xavier,Kermit,Roscoe,Esteban,Anton,Solomon,Scotty,Norbert,Elvin,Williams,Nolan,Carey,Rod,Quinton,Hal,Brain,Rob,Elwood,Kendrick,Darius,Moises,Son,Marlin,Fidel,Thaddeus,Cliff,Marcel,Ali,Jackson,Raphael,Bryon,Armand,Alvaro,Jeffry,Dane,Joesph,Thurman,Ned,Sammie,Rusty,Michel,Monty,Rory,Fabian,Reggie,Mason,Graham,Kris,Isaiah,Vaughn,Gus,Avery,Loyd,Diego,Alexis,Adolph,Norris,Millard,Rocco,Gonzalo,Derick,Rodrigo,Gerry,Stacey,Carmen,Wiley,Rigoberto,Alphonso,Ty,Shelby,Rickie,Noe,Vern,Bobbie,Reed,Jefferson,Elvis,Bernardo,Mauricio,Hiram,Donovan,Basil,Riley,Ollie,Nickolas,Maynard,Scot,Vince,Quincy,Eddy,Sebastian,Federico,Ulysses,Heriberto,Donnell,Cole,Denny,Davis,Gavin,Emery,Ward,Romeo,Jayson,Dion,Dante,Clement,Coy,Odell,Maxwell,Jarvis,Bruno,Issac,Mary,Dudley,Brock,Sanford,Colby,Carmelo,Barney,Nestor,Hollis,Stefan,Donny,Art,Linwood,Beau,Weldon,Galen,Isidro,Truman,Delmar,Johnathon,Silas,Frederic,Dick,Kirby,Irwin,Cruz,Merlin,Merrill,Charley,Marcelino,Lane,Harris,Cleo,Carlo,Trenton,Kurtis,Hunter,Aurelio,Winfred,Vito,Collin,Denver,Carter,Leonel,Emory,Pasquale,Mohammad,Mariano,Danial,Blair,Landon,Dirk,Branden,Adan,Numbers,Clair,Buford,German,Bernie,Wilmer,Joan,Emerson,Zachery,Fletcher,Jacques,Errol,Dalton,Monroe,Josue,Dominique,Edwardo,Booker,Wilford,Sonny,Shelton,Carson,Theron,Raymundo,Daren,Tristan,Houston,Robby,Lincoln,Jame,Genaro,Gale,Bennett,Octavio,Cornell,Laverne,Hung,Arron,Antony,Herschel,Alva,Giovanni,Garth,Cyrus,Cyril,Ronny,Stevie,Lon,Freeman,Erin,Duncan,Kennith,Carmine,Augustine,Young,Erich,Chadwick,Wilburn,Russ,Reid,Myles,Anderson,Morton,Jonas,Forest,Mitchel,Mervin,Zane,Rich,Jamel,Lazaro,Alphonse,Randell,Major,Johnie,Jarrett,Brooks,Ariel,Abdul,Dusty,Luciano,Lindsey,Tracey,Seymour,Scottie,Eugenio,Mohammed,Sandy,Valentin,Chance,Arnulfo,Lucien,Ferdinand,Thad,Ezra,Sydney,Aldo,Rubin,Royal,Mitch,Earle,Abe,Wyatt,Marquis,Lanny,Kareem,Jamar,Boris,Isiah,Emile,Elmo,Aron,Leopoldo,Everette,Josef,Gail,Eloy,Dorian,Rodrick,Reinaldo,Lucio,Jerrod,Weston,Hershel,Barton,Parker,Lemuel,Lavern,Burt,Jules,Gil,Eliseo,Ahmad,Nigel,Efren,Antwan,Alden,Margarito,Coleman,Refugio,Dino,Osvaldo,Les,Deandre,Normand,Kieth,Ivory,Andrea,Trey,Norberto,Napoleon,Jerold,Fritz,Rosendo,Milford,Sang,Deon,Christoper,Alfonzo,Lyman,Josiah,Brant,Wilton,Rico,Jamaal,Dewitt,Carol,Brenton,Yong,Olin,Foster,Faustino,Claudio,Judson,Gino,Edgardo,Berry,Alec,Tanner,Jarred,Donn,Trinidad,Tad,Shirley,Prince,Porfirio,Odis,Maria,Lenard,Chauncey,Chang,Tod,Mel,Marcelo,Kory,Augustus,Keven,Hilario,Bud,Sal,Rosario,Orval,Mauro,Dannie,Zachariah,Olen,Anibal,Milo,Jed,Frances,Thanh,Dillon,Amado,Newton,Connie,Lenny,Tory,Richie,Lupe,Horacio,Brice,Mohamed,Delmer,Dario,Reyes,Dee,Mac,Jonah,Jerrold,Robt,Hank,Sung,Rupert,Rolland,Kenton,Damion,Chi,Antone,Waldo,Fredric,Bradly,Quinn,Kip,Burl,Walker,Tyree,Jefferey,Ahmed");

     $id = $givennamelist[rand(0,count($givennamelist)-1)];

    return $id;
}

function assign_pseudo_sname($pseudogname) {
    // rather than just assigning a random string of junk,
    // this algorithm selects at random from a list of common surnames
    // Keep track of used name combinations during the running of the script.
    static $usednames = array();
    $familynamelist = explode(",","Smith,Johnson,Williams,Brown,Jones,Miller,Davis,Garcia,Rodriguez,Wilson,Martinez,Anderson,Taylor,Thomas,Hernandez,Moore,Martin,Jackson,Thompson,White,Lopez,Lee,Gonzalez,Harris,Clark,Lewis,Robinson,Walker,Perez,Hall,Young,Allen,Sanchez,Wright,King,Scott,Green,Baker,Adams,Nelson,Hill,Ramirez,Campbell,Mitchell,Roberts,Carter,Phillips,Evans,Turner,Torres,Parker,Collins,Edwards,Stewart,Flores,Morris,Nguyen,Murphy,Rivera,Cook,Rogers,Morgan,Peterson,Cooper,Reed,Bailey,Bell,Gomez,Kelly,Howard,Ward,Cox,Diaz,Richardson,Wood,Watson,Brooks,Bennett,Gray,James,Reyes,Cruz,Hughes,Price,Myers,Long,Foster,Sanders,Ross,Morales,Powell,Sullivan,Russell,Ortiz,Jenkins,Gutierrez,Perry,Butler,Barnes,Fisher,Henderson,Coleman,Simmons,Patterson,Jordan,Reynolds,Hamilton,Graham,Kim,Gonzales,Alexander,Ramos,Wallace,Griffin,West,Cole,Hayes,Chavez,Gibson,Bryant,Ellis,Stevens,Murray,Ford,Marshall,Owens,Mcdonald,Harrison,Ruiz,Kennedy,Wells,Alvarez,Woods,Mendoza,Castillo,Olson,Webb,Washington,Tucker,Freeman,Burns,Henry,Vasquez,Snyder,Simpson,Crawford,Jimenez,Porter,Mason,Shaw,Gordon,Wagner,Hunter,Romero,Hicks,Dixon,Hunt,Palmer,Robertson,Black,Holmes,Stone,Meyer,Boyd,Mills,Warren,Fox,Rose,Rice,Moreno,Schmidt,Patel,Ferguson,Nichols,Herrera,Medina,Ryan,Fernandez,Weaver,Daniels,Stephens,Gardner,Payne,Kelley,Dunn,Pierce,Arnold,Tran,Spencer,Peters,Hawkins,Grant,Hansen,Castro,Hoffman,Hart,Elliott,Cunningham,Knight,Bradley,Carroll,Hudson,Duncan,Armstrong,Berry,Andrews,Johnston,Ray,Lane,Riley,Carpenter,Perkins,Aguilar,Silva,Richards,Willis,Matthews,Chapman,Lawrence,Garza,Vargas,Watkins,Wheeler,Larson,Carlson,Harper,George,Greene,Burke,Guzman,Morrison,Munoz,Jacobs,Obrien,Lawson,Franklin,Lynch,Bishop,Carr,Salazar,Austin,Mendez,Gilbert,Jensen,Williamson,Montgomery,Harvey,Oliver,Howell,Dean,Hanson,Weber,Garrett,Sims,Burton,Fuller,Soto,Mccoy,Welch,Chen,Schultz,Walters,Reid,Fields,Walsh,Little,Fowler,Bowman,Davidson,May,Day,Schneider,Newman,Brewer,Lucas,Holland,Wong,Banks,Santos,Curtis,Pearson,Delgado,Valdez,Pena,Rios,Douglas,Sandoval,Barrett,Hopkins,Keller,Guerrero,Stanley,Bates,Alvarado,Beck,Ortega,Wade,Estrada,Contreras,Barnett,Caldwell,Santiago,Lambert,Powers,Chambers,Nunez,Craig,Leonard,Lowe,Rhodes,Byrd,Gregory,Shelton,Frazier,Becker,Maldonado,Fleming,Vega,Sutton,Cohen,Jennings,Parks,Mcdaniel,Watts,Barker,Norris,Vaughn,Vazquez,Holt,Schwartz,Steele,Benson,Neal,Dominguez,Horton,Terry,Wolfe,Hale,Lyons,Graves,Haynes,Miles,Park,Warner,Padilla,Bush,Thornton,Mccarthy,Mann,Zimmerman,Erickson,Fletcher,Mckinney,Page,Dawson,Joseph,Marquez,Reeves,Klein,Espinoza,Baldwin,Moran,Love,Robbins,Higgins,Ball,Cortez,Le,Griffith,Bowen,Sharp,Cummings,Ramsey,Hardy,Swanson,Barber,Acosta,Luna,Chandler,Daniel,Blair,Cross,Simon,Dennis,O'connor,Quinn,Gross,Navarro,Moss,Fitzgerald,Doyle,Mclaughlin,Rojas,Rodgers,Stevenson,Singh,Yang,Figueroa,Harmon,Newton,Paul,Manning,Garner,Mcgee,Reese,Francis,Burgess,Adkins,Goodman,Curry,Brady,Christensen,Potter,Walton,Goodwin,Mullins,Molina,Webster,Fischer,Campos,Avila,Sherman,Todd,Chang,Blake,Malone,Wolf,Hodges,Juarez,Gill,Farmer,Hines,Gallagher,Duran,Hubbard,Cannon,Miranda,Wang,Saunders,Tate,Mack,Hammond,Carrillo,Townsend,Wise,Ingram,Barton,Mejia,Ayala,Schroeder,Hampton,Rowe,Parsons,Frank,Waters,Strickland,Osborne,Maxwell,Chan,Deleon,Norman,Harrington,Casey,Patton,Logan,Bowers,Mueller,Glover,Floyd,Hartman,Buchanan,Cobb,French,Kramer,Mccormick,Clarke,Tyler,Gibbs,Moody,Conner,Sparks,Mcguire,Leon,Bauer,Norton,Pope,Flynn,Hogan,Robles,Salinas,Yates,Lindsey,Lloyd,Marsh,Mcbride,Owen,Solis,Pham,Lang,Pratt,Lara,Brock,Ballard,Trujillo,Shaffer,Drake,Roman,Aguirre,Morton,Stokes,Lamb,Pacheco,Patrick,Cochran,Shepherd,Cain,Burnett,Hess,Li,Cervantes,Olsen,Briggs,Ochoa,Cabrera,Velasquez,Montoya,Roth,Meyers,Cardenas,Fuentes,Weiss,Wilkins,Hoover,Nicholson,Underwood,Short,Carson,Morrow,Colon,Holloway,Summers,Bryan,Petersen,Mckenzie,Serrano,Wilcox,Carey,Clayton,Poole,Calderon,Gallegos,Greer,Rivas,Guerra,Decker,Collier,Wall,Whitaker,Bass,Flowers,Davenport,Conley,Houston,Huff,Copeland,Hood,Monroe,Massey,Roberson,Combs,Franco,Larsen,Pittman,Randall,Skinner,Wilkinson,Kirby,Cameron,Bridges,Anthony,Richard,Kirk,Bruce,Singleton,Mathis,Bradford,Boone,Abbott,Charles,Allison,Sweeney,Atkinson,Horn,Jefferson,Rosales,York,Christian,Phelps,Farrell,Castaneda,Nash,Dickerson,Bond,Wyatt,Foley,Chase,Gates,Vincent,Mathews,Hodge,Garrison,Trevino,Villarreal,Heath,Dalton,Valencia,Callahan,Hensley,Atkins,Huffman,Roy,Boyer,Shields,Lin,Hancock,Grimes,Glenn,Cline,Delacruz,Camacho,Dillon,Parrish,O'neill,Melton,Booth,Kane,Berg,Harrell,Pitts,Savage,Wiggins,Brennan,Salas,Marks,Russo,Sawyer,Baxter,Golden,Hutchinson,Liu,Walter,Mcdowell,Wiley,Rich,Humphrey,Johns,Koch,Suarez,Hobbs,Beard,Gilmore,Ibarra,Keith,Macias,Khan,Andrade,Ware,Stephenson,Henson,Wilkerson,Dyer,Mcclure,Blackwell,Mercado,Tanner,Eaton,Clay,Barron,Beasley,Oneal,Small,Preston,Wu,Zamora,Macdonald,Vance,Snow,Mcclain,Stafford,Orozco,Barry,English,Shannon,Kline,Jacobson,Woodard,Huang,Kemp,Mosley,Prince,Merritt,Hurst,Villanueva,Roach,Nolan,Lam,Yoder,Mccullough,Lester,Santana,Valenzuela,Winters,Barrera,Orr,Leach,Berger,Mckee,Strong,Conway,Stein,Whitehead,Bullock,Escobar,Knox,Meadows,Solomon,Velez,O'donnell,Kerr,Stout,Blankenship,Browning,Kent,Lozano,Bartlett,Pruitt,Buck,Barr,Gaines,Durham,Gentry,Mcintyre,Sloan,Rocha,Melendez,Herman,Sexton,Moon,Hendricks,Rangel,Stark,Lowery,Hardin,Hull,Sellers,Ellison,Calhoun,Gillespie,Mora,Knapp,Mccall,Morse,Dorsey,Weeks,Nielsen,Livingston,Leblanc,Mclean,Bradshaw,Glass,Middleton,Buckley,Schaefer,Frost,Howe,House,Mcintosh,Ho,Pennington,Reilly,Hebert,Mcfarland,Hickman,Noble,Spears,Conrad,Arias,Galvan,Velazquez,Huynh,Frederick,Randolph,Cantu,Fitzpatrick,Mahoney,Peck,Villa,Michael,Donovan,Mcconnell,Walls,Boyle,Mayer,Zuniga,Giles,Pineda,Pace,Hurley,Mays,Mcmillan,Crosby,Ayers,Case,Bentley,Shepard,Everett,Pugh,David,Mcmahon,Dunlap,Bender,Hahn,Harding,Acevedo,Raymond,Blackburn,Duffy,Landry,Dougherty,Bautista,Shah,Potts,Arroyo,Valentine,Meza,Gould,Vaughan,Fry,Rush,Avery,Herring,Dodson,Clements,Sampson,Tapia,Bean,Lynn,Crane,Farley,Cisneros,Benton,Ashley,Mckay,Finley,Best,Blevins,Friedman,Moses,Sosa,Blanchard,Huber,Frye,Krueger,Bernard,Rosario,Rubio,Mullen,Benjamin,Haley,Chung,Moyer,Choi,Horne,Yu,Woodward,Ali,Nixon,Hayden,Rivers,Estes,Mccarty,Richmond,Stuart,Maynard,Brandt,O'connell,Hanna,Sanford,Sheppard,Church,Burch,Levy,Rasmussen,Coffey,Ponce,Faulkner,Donaldson,Schmitt,Novak,Costa,Montes,Booker,Cordova,Waller,Arellano,Maddox,Mata,Bonilla,Stanton,Compton,Kaufman,Dudley,Mcpherson,Beltran,Dickson,Mccann,Villegas,Proctor,Hester,Cantrell,Daugherty,Cherry,Bray,Davila,Rowland,Madden,Levine,Spence,Good,Irwin,Werner,Krause,Petty,Whitney,Baird,Hooper,Pollard,Zavala,Jarvis,Holden,Hendrix,Haas,Mcgrath,Bird,Lucero,Terrell,Riggs,Joyce,Rollins,Mercer,Galloway,Duke,Odom,Andersen,Downs,Hatfield,Benitez,Archer,Huerta,Travis,Mcneil,Hinton,Zhang,Hays,Mayo,Fritz,Branch,Mooney,Ewing,Ritter,Esparza,Frey,Braun,Gay,Riddle,Haney,Kaiser,Holder,Chaney,Mcknight,Gamble,Vang,Cooley,Carney,Cowan,Forbes,Ferrell,Davies,Barajas,Shea,Osborn,Bright,Cuevas,Bolton,Murillo,Lutz,Duarte,Kidd,Key,Cooke,Nguyen,Lee,Kim,Patel,Tran,Chen,Wong,Le,Yang,Wang,Chang,Chan,Pham,Li,Park,Singh,Lin,Liu,Wu,Huang,Lam,Huynh,Ho,Choi,Yu,Shah,Chung,Khan,Zhang,Vang,Truong,Ng,Phan,Lim,Xiong,Vu,Cheng,Cho,Vo,Tang,Ngo,Chu,Lu,Kang,Ly,Hong,Dang,Hoang,Do,Chin,Tan,Lau,Bui,Kaur,Han,Ma,Duong,Leung,Yee,Song,Cheung,Ali,Shin,Ahmed,Yi,Thao,Lai,Hsu,Fong,Reyes,Sun,Chow,Young,Liang,Lo,Hwang,Santos,Cruz,Oh,Sharma,Chau,Garcia,Ha,Kumar,Xu,Desai,Thomas,Hu,Luu,Zhou,Dinh,Yoon,Trinh,Tam,Luong,Chong,Chiu,Zheng,Cao,Zhu,Woo,Zhao,Jung,Mai,Ko,Ramos,Chun,Her,Smith,Kong,Gupta,Yoo,Doan,Moua,Kwon,Pak,Delacruz,Tsai,Mehta,Mendoza,Tong,Jiang,Bautista,Shen,Vue,Su,Thai,Eng,Johnson,Dao,Chou,Hussain,Pan,Ahn,Chao,Kwan,Quach,Lor,Rahman,Fernandez,Cha,Yan,Vuong,Chiang,Leong,He,Flores,Fung,Dong,Nakamura,To,Choe,Ong,Tanaka,Louie,Yun,Lopez,Tu,Yamamoto,Mathew,Deguzman,Yuen,Moon,Fang,Yeung,Kuo,Rivera,Son,Rao,Gonzales,Ahmad,Moy,Guo,Pang,So,Yeh,Perez,Aquino,Gill,Reddy,Tsang,Williams,Brown,Wei,George,Sato,Villanueva,Feng,Ta,Saechao,Liao,Joseph,Malik,Lum,Deleon,Castillo,Tom,Jain,Lew,Jang,Hung,Fu,Watanabe,Fan,Au,Hsieh,Amin,Gao,Yip,Phung,Ching,Yuan,Sung,Tse,Suh,Lui,Castro,Peng,Chi,Gee,Yim,Jin,An,Hua,Kao,Abraham,Van,Hui,Joshi,Kwong,Luo,Prasad,Martin,Jones,Chowdhury,Domingo,Miller,Parikh,Xie,Shi,Rodriguez,Martinez,Torres,Diep,Ye,Hernandez,Min,Begum,Suzuki,Hang,Islam,Sanchez,Quan,La,Du,Kwok,Davis,Yao,Mei,Takahashi,Syed,Yen,Lei,Varghese,Tseng,Cai,Choy,Nam,Hom,Law,Siddiqui,David,Santiago,Wan,Ling,John,Long,Yin,Tolentino,Shih,Thach,Gong,Gandhi,King,Bae,Ito,Deng,Matsumoto,Ramirez,Guan,Soriano,Delrosario,Mao,Yamada,Dizon,Seo,Poon,Sandhu,Anderson,Mak,Lao,Wilson,Im,Zeng,Tung,Chon,Qureshi,Shim,Koo,Chew,Yoshida,Wen,Pascual,Jacob,Persaud,Ung,Valdez,Francisco,Ku,Saito,Bhatt,Low,Yung,Kobayashi,Siu,Tai,Bhakta,Corpuz,Won,Rhee,Sim,Alam,Das,Man,Kato,Mui,Ton,Kimura,Higa,Sok,Navarro,Chaudhry,Chien,Diaz,You,Mercado,Gutierrez,Kwak,Gu,Sam,Yap,Sin,Mohammed,Jun,Nair,Rana,Thompson,Taylor,Chea,Lieu,Roy,Vong,Yong,Miranda,Iqbal,Gomez,Hou,Oshiro,Xiao,Tao,Medina,Shaikh,Jeong,Morales,Ting,Go,Chua,Bhatia,Ignacio,Sasaki,Paul,Ocampo,Javier,Dhillon,Tsui,Moore,Hayashi,Zhong,Pascua,Verma,Koh,Manuel,Velasco,Yau,Mar,Fernando,White,Teng,Hossain,Sheth,Dejesus,Sheikh,Loo,Kam,Devera,Ding,Hasan,Trieu,Angeles,Dai,Dave,Yamaguchi,Jimenez,Kung,Ferrer,Antonio,Chuang,Jose,Bang,Grewal,Espiritu,Murakami,Sidhu,Salvador,Enriquez,Mahmood,Ou,Arora,Agarwal,Silva,Sakamoto,Lewis,Ni,Samuel,Szeto,Hall,Gonzalez,Lay,Uddin,Trivedi,Hashimoto,Shimizu,Clark,Uy,Fujimoto,Ham,Marquez,Hassan,Weng,Chai,Guerrero,Ikeda,Kan,Aguilar,Giang,Asuncion,Agustin,Srinivasan,Evangelista,Mariano,Sy,Mistry,Cortez,Inouye,Heng,Ancheta,Keo,Harris,Nishimura,Pineda,Tsao,Nakagawa,Lal,Hsiao,Delossantos,Paik,Delosreyes,Nelson,Saelee,Sison,Sinha,Sarmiento,Jackson,Romero,Mirza,Peralta,Yamashita,Kuang,Cabrera,Kaneshiro,Concepcion,Abe,Manalo,Mo,Ji,Jo,Seto,Pandya,Mori,Hahn,Ang,Chacko,Jeon,Mann,Fujii,Alvarez,Parekh,Chandra,Mok,Okamoto,Bhatti,Kapoor,Shaw,Kulkarni,Joo,Mah,Lou,Qiu,Allen,James,Chae,Krishnan,Vyas,Ren,Akhtar,Sakai,Saephan,Robinson,Baker,Adams,Shao,Banh,Pai,Lien,Ghosh,Ono,Abad,Joe,Xia,Miyamoto,Baek,Acosta,Salazar,Trang,Dam,Valencia,Iyer,Nakano,Choudhury,Saetern,Zou,Huh,Ruiz,Matsuda,Philip,Saini,Wright,Alexander,Walker,Trinidad,Sohn,Meng,Lan,Shu,Baig,Hill,Haque,Rashid,Tieu,Subramanian,Scott,Alcantara,Rai,Roberts,Legaspi,Phu,Guzman,Byun,Padilla,Malhotra,Tien,Anand,Bains,Karim,Zhen,Samson,Maeda,Ogawa,Seng,Hyun,Galang,Andres,Shum,Okada,Khang,Bernardo,Gabriel,Naik,Estrada,Molina,Shukla,Agrawal,Ansari,Butt,Modi,Thakkar,Ota,Chee,Khanna,Ray,Chand,Ro,Kapadia,Honda,Harada,Toy,Campbell,Zaman,Daniel,Qian,Loh,Dhaliwal,Luk,Phillips,Tamura,Taing,Ishii,Serrano,Tian,Ju,Morita,Chopra,Mong,Cui,Parmar,Rizvi,Yamasaki,Ip,Soni,Luna,Mohan,Chinn,Mark,Ying,Fajardo,Rosario,Yue,Ram,Merchant,Mohamed,Aoki,Fujita,Simon,Lowe,Vergara,Sum,Decastro,Aziz,Mun,Husain,Ortiz,Sen,Hasegawa,Ishikawa,Borja,Hur,Arakaki,Gan,Bai,Dsouza,Ruan,Mitchell,Viray,Peterson,Ventura,Miyashiro,Parker,Masuda,Menon,Herrera,Mathews,Doshi,Carter,Raza,Xue,Situ,Green,Wada,Lang,Mishra,Khuu,Garg,Srivastava,Sethi,Ryu,Narayan,Nakamoto,Evans,Delarosa,Co,Fukuda,Camacho,Mathur,Lucas,Nakashima,Luc,Mohammad,Gomes,San,Morris,Whang,Endo,Brar,Rehman,Fernandes,Ngai,Goto,Zaidi,Mallari,Nakayama,Natividad,Chauhan,Rosales,See,Cook,Lorenzo,Murphy,Yoshimura,Choo,Pangilinan,Nakata,Banerjee,Kelly,Pal,Than,Varughese,Edwards,Briones,Che,Carlos,Robles,Wood,Hirata,Mac,Hamada,Aggarwal,Som,Noh,Patil,Saeteurn,Sebastian,Nicolas,Thor,Cherian,Inoue,Rogers,Soohoo,Chawla,Lakhani,Chui,Dy,Carino,Austria,Qu,Oda,Momin,Beltran,Mach,Anwar,Kondo,Roque,Juan,Miguel,Tamayo,Nomura,Doi,Kawamoto,Takeuchi,Meas,Paek,Stewart,Perera,Rajan,Qin,Jan,Cheema,Prakash,Collins,No,Mateo,Gin,Youn,Tso,Nagata,Morgan,Sales,Yam,Cheong,Kurian,Dalal,Randhawa,Cordero,Goel,Mathai,Jew,Pathak,Hara,Watson,Sheng,Chaudhary,Hao,Um,Mukherjee,Vora,Kue,Kothari,Hsia,Auyeung,Ashraf,Raj,Raja,Cunanan,Tat,Villegas,Turner,Kawamura,Vasquez,Saxena,Sultana,Shimabukuro,Bhat,Kubota,Yokoyama,Bartolome,Chavez,Quon,Pillai,Khalid,Liou,Atienza,Esguerra,Shankar,Saeed,Zamora,Lok,Bell,Kinoshita,Crisostomo,Saha,Ishida,Gamboa,Guevarra,Cooper,Miura,Morimoto,Chia,Tuazon,Reed,Mendiola,Yoshioka,Ogata,Canlas,Ballesteros,Bailey,Nishimoto,Baltazar,Shrestha,Jen,Jue,Aslam,Yeo,Viloria,Raman,Bajwa,Campos,Chatterjee,Padua,Uyeda,Phuong,Peters,Qi,Espinosa,Saleem,Ramachandran,Gray,Vargas,Huey,Taniguchi,Desilva,Ouyang,Munoz,Haq,Pena,Valenzuela,Sood,Abbas,Sarkar,Ahuja,Thong,Okamura,Ortega,Murthy,Rodrigues,Khatri,Narayanan,Foster,Castaneda,Ward,Pablo,Manzano,Ross,Miao,Shieh,Shetty,Sze,Magno,Murata,Uchida,Raju,Shibata,Roxas,Suarez,Velasquez,Esteban,Sullivan,Cox,Salas,Higashi,Brooks,Blanco,Kawakami,Goyal,Varma,Kha,Alfonso,Kawasaki,Haider,Tomita,Suen,Bennett,Nhan,Deshpande,Goo,Perry,Puri,Lung,Arellano,Siddiqi,Tham,Datta,Howard,Yamauchi,Aguinaldo,Sevilla,Dominguez,Stevens,Price,Pon,In,Jia,Mejia,Custodio,May,Pereira,Bhagat,Mitra,Chanthavong,Pho,Takeda,Guillermo,Ing,Alonzo,Hirano,Solomon,Nguy,Pasion,Goh,Solis,Tram,Miah,Foo,Matsumura,Franco,Moreno,Sao,Voong,Francis,Lazaro,Seth,Nakasone,Nishida,Spencer,Galvez,Ouk,Russell,Shan,Nghiem,Cristobal,Henry,Kee,Guevara,Luke,Mian,Calderon,Bun,Natarajan,Christian,Jong,Ismail,Sing,Kuan,Sang,Bansal,Bach,Matsui,Quinto,Ke,Manansala,Richardson,Asato,Kubo,On,Fujiwara,Hughes,Matsuoka,Ryan,Baik,Iwamoto,Ros,Dean,Ullah,Dulay,Soo,Akhter,Maharaj,Fisher,Olson,Wing,Koshy,Basu,Pae,Panganiban,Danh,Dumlao,Okazaki,Fontanilla,Kaneko,Rim,Tanabe,Wai,Shiroma,Toledo,Yiu,Jordan,Hashmi,Loi,Dunn,Feliciano,Imai,Umali,Kojima,Murray,Kohli,Leng,Koga,Hamilton,Ge,Myers,Meyer,Cadiz,Kikuchi,Bhattacharya,Takemoto,Furukawa,Jacinto,Yasuda,Yamane,Chuong,Mata,Roh,Fukushima,Batra,Nayak,Barnes,Touch,Oyama,Hau,Sanjuan,Raval,Lucero,Albano,Shimada,Kono,Fuentes,Ra,Huie,Felix,Estrella,Tay,Kapur,Pradhan,Pen,Stone,Villa,Rong,Powell,Reynolds,Gulati,Wallace,Graham,Nath,Vaidya,Clemente,Delapena,Iwasaki,Palma,Prak,Jani,Misra,Fox,Lazo,Hamid,Jamal,Cole,Corpus,Geronimo,Din,Win,Dutta,Mody,Doe,Eugenio,Pao,Pandey,Bonifacio,Andrade,Carreon,Mau,Chiou,Ellis,Hansen,Leu,Sue,Abella,Sugimoto,Sunga,Henderson,Wee,Arakawa,Kamal,Paras,Abbasi,Chadha,Kennedy,Bueno,Kin,Roldan,Kahn,Escobar,Duenas,Encarnacion,Toyama,Bose,Mukai,Poblete,Uppal,Bao,Walia,Gregorio,Choudhry,Marcelo,Yamashiro,Mu,Quang,Solanki,Bhandari,Sahota,Mittal,Gallardo,Carpio,Rose,Andrews,Hsueh,Matias,Tamashiro,Mahajan,Naqvi,Vicente,Mcdonald,Akiyama,Matsuura,Biswas,Uehara,Fukumoto,Khong,Ky,Marasigan,Kieu,Habib,Shahid,Nishikawa,Awan,Duan,Babu,Vohra,Yoshikawa,Sugiyama,Coloma,Uyehara,Marshall,Tomas,Virani,Lacson,Quiambao,Panchal,Duran,Kay,Araki,Ahluwalia,Hon,Mehra,Butler,Sablan,Virk,Harrison,Ohara,Zafar,Te,Bhardwaj,Kawaguchi,Patterson,West,Yoshimoto,Inthavong,Abalos,Snyder,Pun,Kamath,Cortes,Ordonez,Farooq,Kodama,Burns,Hunt,Gonzaga,Sanders,Kane,Buenaventura,Bajaj,Kazi,Ishihara,Rahim,Hidalgo,Nunez,Raymundo,Kabir,Arai,Macaraeg,Bhargava,Lawrence,Akbar,Leon,Thi,Takata,Chhay,Krishnamurthy,Figueroa,Basa,Kho,Ronquillo,Miyake,Ming,Yano,Hayes,Day,Abdullah,Memon,Chum,Niu,Lozano,Nguyenthi,Simmons,Jenkins,Ramesh,Sumida,Delgado,Aguirre,Viernes,Adachi,Apostol,Viswanathan,Men,Ilagan,Gordon,Hague,Yo,Simpson,Azam,Salcedo,Mani,Hee,Hirai,Pong,Pacheco,Matsunaga,Soong,Acharya,Coleman,Hoque,Sakata,Atwal,Higuchi,Mizuno,Pimentel,Nitta,Siddique,Lian,Ando,Ponce,Agbayani,Jeng,Constantino,Suri,Javed,Krishna,Nakajima,Ngan,Thakur,Zhuang,Palmer,Chokshi,Rubio,Leo,Tariq,Pandit,Ibrahim,Bondoc,Ueda,Matsuo,Nakanishi,Pei,Dey,Mina,Kawahara,Schmidt,Johnston,Grover,Tiwari,Maruyama,Jee,Iwata,Sultan,Ok,Richards,Larson,Ibarra,Gibson,Wagner,Vann,Nagai,Benjamin,Herr,Say,Tin,Ford,Jafri,Porter,Bernabe,Arshad,Xing,Avila,Julian,Naidu,Shiu,Riaz,Hsiung,Lem,Mason,Aragon,Paulino,Fujioka,Jensen,Suk,Cayabyab,Ramakrishnan,Yabut,Carlson,Griffin,Devi,Kelley,Cabral,Salim,Sundaram,Lane,Bernal,Kaul,Bhavsar,Sharif,Khawaja,Paz,Nakahara,Oka,Lara,Basilio,Baccam,Yagi,Ahsan,Shang,Bhatnagar,Lobo,Sengupta,Tsou,Prabhu,Chakraborty,Warren,Otsuka,Koyama,Sabado,Kazmi,Crawford,Sar,Xiang,Wells,Black,Felipe,Tsuji,Ty,Sit,Otani,Tokunaga,Tsoi,Ferguson,Chim,Lalani,Suresh,Der,Bhalla,Pinto,Bibi,Johal,Tiu,Hanson,Latif,Bedi,Mayeda,Webb,Rojas,Yadao,Nazareno,Sehgal,Sou,Hunter,Caballero,Gopal,Bermudez,Yamanaka,Brahmbhatt,Robertson,Latu,Teruya,Benitez,Muhammad,Hoy,Jamil,Pu,Sekhon,Punzalan,Boyd,Alejandro,Matthews,Obrien,Bashir,Daniels,Armstrong,Dionisio,Vi,Choudhary,Sayavong,Gardner,Nasir,Hart,Fatima,Hori,Kalra,Bi,Dixit,Quijano,Tailor,Shenoy,Chiem,Dias,Muraoka,Arce,Noda,Kawai,Salinas,Hilario,Toor,Takagi,Chahal,Oliver,Tee,Nie,Nakama,Thaker,Yum,Freeman,Teo,Borromeo,Khurana,Afzal,Meneses,Okubo,Gorospe,Shroff,Liew,Zhan,Rani,Labrador,Bal,Nepomuceno,Imamura,Noguchi,Rho,Thang,Isaac,Dacanay,Oshima,Mills,Cuevas,Dasgupta,Saephanh,Woods,Paredes,Arif,Chaudhari,Morrison,Lama,Rosete,Tea,Nakao,Raghavan,Cardenas,Bala,Gaspar,Ping,Lall,Deocampo,Kawano,Peter,Om,Rice,Arnold,Barrera,Okumura,Medrano,Shon,Ozaki,Goswami,Hoffman,Tahir,Nong,Sangha,Nichols,Desouza,Dea,Andaya,Valera,Sannicolas,Ngu,Vea,Hem,Thammavongsa,Khatoon,Ramanathan,Bhasin,Balasubramanian,Dixon,Willis,Katayama,Myint,Taira,Miyazaki,Carroll,Mayo,Em,Bacani,Chhabra,Contreras,Anthony,Hy,Morikawa,Singhal,Tep,Widjaja,Kuruvilla,Po,Sawhney,Carpenter,Burke,Mustafa,Miyasato,Jhaveri,Ban,Ramaswamy,Vega,Ravi,Baluyot,Berry,Sakurai,Hicks,Chinen,Austin,Srey,Aung,Mang,Nishi,Yokota,Bustamante,Dass,Agcaoili,Kahlon,Tripathi,Alvarado,Mir,Kitagawa,Joy,Mahmud,Mojica,Villareal,Souza,Hum,Terada,Elliott,Arceo,Horiuchi,Weaver,Jacobs,Roman,Medeiros,Delapaz,Phang,Thammavong,Oum,Pulido,Moses,Ledesma,Takayama,Gatchalian,Bryant,Cam,Grant,Naito,Ning,Tak,Owens,Barrett,Tandon,Onishi,Okuda,Tsay,Pierce,Perkins,Lie,Kanda,Delmundo,Hattori,Bustos,Chock,Dan,Saiki,Yamazaki,Myung,Madrid,Baba,Cunningham,Ibanez,Halim,Samonte,Kou,Beck,Lea,Khoo,Aguila,Hing,Ige,Vinh,Tucker,Prado,Kealoha,Ganesh,Heu,Purohit,Vidal,Look,Minami,Nanda,Tsan,Toma,Florendo,Liwanag,Chohan,Zee,Handa,Kannan,Ichikawa,Cen,Fok,Bravo,Eusebio,Samra,Badua,Subramaniam,Mendez,Sandoval,Nawaz,Papa,Marcos,Blas,Mohiuddin,Jing,Alba,Alejo,Tani,Masood,Knight,Magat,Chhim,Almazan,Bradley,Bishop,Tejada,Deo,Qazi,Hayashida,Chapman,Burgos,Alberto,Cummings,Hameed,Vien,Sia,Takeshita,Yadav,Collado,Garrido,Dutt,Asif,Okimoto,Vitug,Correa,Arevalo,Panjwani,Louis,Montemayor,Siharath,Hipolito,Franklin,Zia,Zapanta,Kitamura,Benson,Bassi,Stephens,Soon,Heo,Char,Cong,Shek,Upadhyay,Hudson,Leano,Chhun,Duncan,Cariaga,Baksh,Estacio,Tecson,Fukunaga,Jha,Walsh,Dayrit,Iyengar,Gunawan,Montgomery,Manivong,Sridhar,Sha,Sui,Madan,Madamba,Ganesan,Espino,Espejo,Izumi,Phong,Jou,Soto,Kai,Foronda,Pagaduan,Riley,Wheeler,Duque,Pangan,Lovan,Matsushita,Hawkins,Weber,Omori,Balakrishnan,Nakai,Pangelinan,Si,Manalang,Deol,Sakaguchi,Jim,Bumanglag,Shishido,Miyata,Lynch,Newman,Bello,Sanjose,Takara,Rajagopalan,Fuller,Tayag,Chiao,Cervantes,Tun,Bhuiyan,Reid,Carrillo,Nathan,Marzan,Kurihara,Stanley,Nishioka,Khatun,Radhakrishnan,Alegre,Kil,Costa,Madriaga,Arcilla,Swaminathan,Hirose,Tenorio,Sarwar,Oza,Payne,Salonga,Morin,Joaquin,Kadakia,Huo,Prom,Lue,Walters,Yon,Lagman,Resurreccion,Kawashima,Troung,Andrada,Bakshi,Carandang,Dee,Guinto,Tsukamoto,Suon,Acoba,Sachdeva,Khim,Welch,Vincent,Ishibashi,Takaki,Dhar,Rastogi,Montoya,Victoria,Komatsu,Arriola,Quadri,Venkataraman,Gilbert,Khokhar,Ozawa,Pyon,Oliva,Akamine,Jennings,Davidson,Masih,Sakuma,Soh,Xi,Acuna,Valerio,Monzon,Fujikawa,Asano,Sheu,Dark,Nakatani,Tamanaha,Venkatesh,Deshmukh,Xin,Holmes,Greene,Centeno,Taketa,Oki,Mam,Cu,Narang,Pearson,Pung,Kham,Sem,Pi,Villar,Jahan,Ninh,Powers,Frank,Zabala,Ghani,Hanif,Konishi,Mochizuki,Harvey,Hay,Yoshino,Ohashi,Yambao,Parks,Bo,Aguon,Nishiyama,Kudo,Giron,Rasheed,Oo,Nijjar,Frias,Shinn,Bacchus,Ki,Un,Lindsey,Kiang,Farooqui,Cordova,Sagun,Akram,Finau,Torio,Naeem,Salgado,Hafeez,Seki,Oishi,Kuroda,Craig,Jay,Villamor,Hata,Lac,Cacho,Yousuf,Coronel,Narciso,Adriano,Kamdar,Vy,Michael,Imperial,Hoo,Bak,Belen,Montero,Sankar,Goya,Minhas,Advani,Choung,Gonsalves,Iida,Schultz,Kau,Maung,Sachdev,Holt,Mock,Sanpedro,Singson,Akter,Blevins,Hartley,Stover,Hurley,Toles,Burkes,Reyes,Guthrie,Ames,Coe,Scarborough,Carver,Westmoreland,Grissom,Cottrell,Wray,Pender,Flynn,Tipton,Ennis,Bouie,Dewitt,Rawlings,Jeffrey,Hamlett,Holton,Mcswain,Tobias,Maclin,Beale,Joshua,Ellerbe,Bracy,Grandberry,Bozeman,Windham,Lavender,Funches,Douglass,Brothers,Woodberry,Couch,Gomes,Bazemore,Gatson,Woodward,Hatch,Crooks,Dixson,Guerrier,Andrew,Holcomb,Rodney,Eggleston,Bobbitt,Lenoir,Whitt,Pearce,Ruth,Maxie,Hanks,Hagans,Richburg,Palmore,Monk,Bias,Tyree,Pounds,Murchison,Garth,Winbush,Puckett,Hogue,Truesdale,Walcott,Paschal,Manigault,Tribble,Nero,Vick,Marrow,Lathan,Gonzales,Kearse,Whitlow,Brodie,Boutte,Hazel,Springs,Foley,Nurse,Parish,Beckham,Falls,Crudup,Tuggle,Alexandre,Brand,Woodall,Bratton,Somerville,Bing,Jarmon,Hopper,Redmon,Mccrae,Burse,Augustus,Gurley,Garris,Boggs,Embry,Kane,Fitch,Tuck,March,Shropshire,Watford,Laurent,Gilyard,Bruno,Mott,Fagan,Dennard,Mckinnie,Mallett,Harvin,Piper,Colson,Rountree,Sturgis,Varnado,Autry,Dooley,Bruton,Mahoney,Pinder,Cantrell,Hagan,Davies,Stlouis,Troutman,Holston,Mcmillon,Turnage,Spaulding,Nolen,Blakeney,Downey,Ragsdale,Lashley,Colston,Hillard,Crittenden,Highsmith,Pridgen,Haughton,Medlock,Georges,Crocker,Pullen,Daughtry,Wiltz,Crook,Benn,Irons,Duckworth,Bickham,Crane,Mcgrew,Clyburn,Diamond,Mcinnis,Tillis,Pernell,Ridgeway,Wilcher,Mumford,Lea,Knighton,Saulsberry,Everette,Hutson,Wayne,Duffy,Gooding,Giddens,Guess,Padgett,Faust,Dansby,Speights,Hambrick,Mccrea,Trotman,Jules,Jimerson,Pompey,Grubbs,Goines,Cyrus,Brisco,Harold,Gathers,Crum,Partee,Cowart,Leake,Kellam,Sydnor,Still,Leverette,Chin,Tolson,Wideman,Crowley,Ashton,Stapleton,Pendergrass,Forney,Chamberlain,Adair,Cary,Hough,Satterfield,Woolridge,Houser,Mccorkle,Goree,Tharpe,Eskridge,Crowe,Bourne,Sweat,Bunn,Dees,Asberry,Knott,Blaylock,Huddleston,Cephas,Kincaid,Thurston,Nevels,Broadus,Scurry,Cromartie,Jewell,Pemberton,Ibrahim,Khan,Stanfield,Laguerre,Bazile,Lowry,Edge,Devine,Chiles,Jelks,Crisp,Anglin,Mccreary,Koonce,Browder,Pollock,Satchell,Spells,Prewitt,Jerome,Workman,Stancil,Holliman,Deshields,Haygood,Spates,Silva,Veney,Sherrill,Seabrook,Drakeford,Guest,Burkett,Parrott,Chatmon,Inman,Judge,Burrows,Nunley,Leverett,Linder,Beaty,Steen,Siler,Wheaton,Reliford,Robison,Peterkin,Winslow,Lyle,Loggins,Josey,Gaffney,Menefee,Oakley,Hoffman,Rock,Winchester,Kellum,Spinks,Flores,Stackhouse,Barnwell,Amerson,Salters,Spellman,Haines,Glasgow,Craddock,Futrell,Knighten,Lampley,Donnell,Brewton,Lark,Lubin,Mines,Marbury,Fitts,Broome,Rosemond,Batson,Olds,Meade,Shanklin,Lennon,Greenlee,Beach,Hare,Daugherty,Waiters,Gambrell,Ammons,Archibald,Broomfield,Treadwell,Owen,Bartlett,Sanderson,Mcclelland,Hubert,Farrington,Legette,Platt,Perrin,Ewell,Foxworth,Saxton,Dempsey,Southerland,Winstead,Sexton,Hughey,Roane,Glaze,Coffee,Priester,Frison,Sanon,Mcmiller,August,Slack,England,Waite,Melson,Nickens,Becton,Breland,Waldon,Kirkpatrick,Bundy,Willie,Peavy,Spurlock,Cuffee,Dow,Bonaparte,Gale,Selby,Boulware,Wicker,Humes,Mcdougald,Sumlin,Germain,Purifoy,Hatton,Duff,Ring,Bah,Straughter,Dade,Atwater,Edison,Dunning,Doughty,Gainer,Roseboro,Whetstone,Spiller,Postell,Morant,Babb,Israel,Twyman,Flint,Derrick,Teal,Hardiman,Zeno,Keene,Catchings,Mccowan,Jessie,Ortiz,Keels,Shockley,Malveaux,Wadley,Redman,Talton,Truss,Levine,Peele,Nowlin,Emery,Cardwell,Waddy,Boateng,Counts,Breedlove,Peck,Crain,Fogle,Hartsfield,Rhoden,Theodore,Nicolas,Otis,Tiller,Wesson,Twitty,Arnett,Boudreaux,Lunsford,Kelsey,Oliphant,Bufford,Prioleau,Woodland,Deans,Cranford,Taliaferro,Foxx,East,Leigh,Elliot,Raynor,Morehead,Chalmers,Harp,Mattison,Barclay,Shackleford,Beckwith,Northern,Adamson,Hasan,Turman,Fulmore,Greaves,Forman,Copper,Wheatley,Bagby,Forest,Richey,Clayborn,Owusu,Blalock,Barnette,Bettis,Tanksley,Lindo,Hand,Huston,Santiago,Huey,Pritchard,Puryear,Bateman,Wheat,Upchurch,Godbolt,Funderburk,Fant,Albright,Guillaume,Primus,Zachery,Bain,Camara,Moorehead,Dubois,Zachary,Zanders,Purcell,Binns,Sealy,Mooney,Albritton,Philpot,Shorts,Sumter,Morales,Worley,Dangerfield,Blow,Peay,Hollie,Obrien,Shaffer,Erwin,Mcmorris,Headen,Oconnor,Adam,Massenburg,Redden,Sorrell,Hassell,Hackney,Morman,Grandison,Mullings,Strozier,Engram,Lay,Earle,Mcdougal,Abdi,Furlow,Bellard,Weekes,Goodloe,Culbreath,Cates,Toler,Hundley,Squire,Peppers,Ousley,Hardnett,Badger,Bellinger,Weber,Crumpton,Cumberbatch,Charlton,Burrus,Binion,Bratcher,Rubin,Dismuke,Mcalister,Banner,Darnell,Bohannon,Lockridge,Meekins,Swint,Laney,Castillo,Stepney,Castle,Braden,Trapp,Sauls,Thomason,Friend,Sistrunk,Storey,Herman,Massie,Turpin,Pinson,Stowers,Bridgewater,Render,Conerly,Honore,Hibbler,Mccombs,Hearns,Shelley,Boney,Windom,Stegall,Demps,Cruse,Dicks,Helms,Shumpert,Nalls,Hamer,Birdsong,Broom,Fudge,Clayborne,Bills,Abner,Exum,Wynne,Omar,Doctor,Ryans,Greenidge,Guinn,Merrill,Colter,Bethune,Wylie,Toombs,Springfield,Guice,Troupe,Virgil,Sidney,Forrester,Mccurdy,Corbitt,Herrington,Fenner,Stribling,Gilmer,Looney,Mazyck,Bruner,Ceaser,Crouch,Hawes,Brim,Tyner,Baltimore,Enoch,Packer,Dew,Ravenell,Pinnock,Fryer,Briley,Brister,Garmon,Horace,Mayweather,Birch,Bertrand,Kindred,Neil,Comeaux,Berkley,Wilmore,Isaacs,Shears,Andrus,Richie,Hibbert,Kendricks,Hay,Nabors,Rorie,Osby,Ebron,Belk,Avent,Chew,Kenner,Griffen,Kay,Hargrave,Blackwood,Swinson,Roddy,Osman,Batchelor,Williford,Troy,Ledet,Goggins,Stukes,Boothe,Utley,Nelms,Trahan,Geiger,Seaton,Fordham,Mansfield,Dugger,Harbin,Bowe,Kitchens,Wingo,Groce,Hastings,Roebuck,Stanback,Grayer,Wimbley,Easton,Moorman,Mclain,Pirtle,Nesbit,Eatmon,Mundy,Bristol,Slaton,Thrasher,Watley,Tynes,Golson,Winder,Mikell,Hardrick,Self,Coburn,Chaplin,Roscoe,Weir,Carraway,Obryant,Dickinson,Shade,Denis,Runnels,Demery,Hoyle,Wainwright,Lovell,Coy,Nunnally,Revels,Mcadoo,Braddy,Denny,Dash,Southall,Hinkle,Wilhite,Philips,Fry,Eiland,Cowans,Gumbs,Rand,Childers,Fobbs,Roman,Riles,Hays,Player,Cosey,Lockwood,Fredrick,Conaway,Fielder,Baggett,Rone,Golston,Watters,Liles,Carruthers,Levi,Pitre,Chapple,Longmire,Lister,Rabb,Lytle,Lankford,Beason,Elie,Peete,Keitt,Dial,Edmonson,Mebane,Tabor,Miner,Cutler,Darling,Colon,Peart,Mcelveen,Lord,Metoyer,Pough,Whitten,Salaam,Mcwhorter,Cave,Walston,Carmon,Elamin,Wiseman,Bowling,Helm,Blakey,Ducksworth,Neville,Beauford,Boozer,Colquitt,Mcmurray,Nicks,Bouldin,Dominique,Clinkscales,Stockton,Mcreynolds,Heal,Munson,Lafleur,Bandy,Elston,Dacosta,Conrad,Spraggins,Purdie,Fells,Saffold,Thomson,Anders,Judkins,Quarterman,Ramirez,Iverson,Chinn,Lipsey,Niles,Grundy,Hadnot,Howe,Fullwood,Nickson,Epperson,Lacour,Senior,Paulk,Holifield,Julian,Russel,Pipkin,Drain,Joubert,Kinchen,Maiden,Mccalla,Rene,Olivier,Biggers,Bolds,Bartholomew,Edouard,Hyatt,Pilgrim,Grissett,Hardman,Headley,Scarlett,Culp,Caver,Sparkman,Junior,Seldon,Tabron,Joy,Caruthers,Showers,Tims,Tellis,Odum,Mcmanus,Oldham,Mcneely,Pass,Jameson,Lucky,Ulmer,Goddard,Camper,Mcelrath,Kennard,Mike,Yearwood,Poston,Steverson,Duvall,Hanley,Danner,Cozart,Peek,Hartwell,Days,Holsey,Dunkley,Westbrooks,Okafor,Haggins,Legrand,Akers,Chenault,Appling,Saxon,Dill,Bienaime,Glaspie,Simien,Mondesir,Alvarez,Newberry,Keel,Cromer,Mckie,Nathaniel,Leaks,Casimir,Stitt,Kittrell,Ealey,Fludd,Hutchison,Bunton,Poteat,Hawk,Peak,January,Standifer,Almond,Byars,Vanburen,Phoenix,Beckles,Pinkard,Gayden,Burr,Foust,Thrash,Hedgepeth,Millner,Senegal,Lauderdale,Beaver,Layton,Loftin,Tilley,Emmanuel,Tilghman,Fultz,Sonnier,Sealey,Sumler,Sumner,Belt,Mathieu,Hite,Dennison,Cooksey,Gayles,Hepburn,Brower,Triggs,Jeanty,Leday,Rochester,Bird,Everson,Chavers,Gaillard,Ferdinand,Hannon,Pegram,Swanigan,Berryman,Latson,Odoms,Woodfork,Berger,Bent,Range,Doby,Hovhannisyan,Harutyunyan,Sargsyan,Khachatryan,Grigoryan,Gruber,Huber,Bauer,Wagner,Müller,Pichler,Steiner,Moser,Mayer,Hofer,Leitner,Berger,Fuchs,Eder,Fischer,Schmid,Winkler,Weber,Schwarz,Maier,Schneider,Reiter,Mayr,Schmidt,Wimmer,Egger,Brunner,Lang,Baumgartner,Auer,Binder,Lechner,Wolf,Wallner,Aigner,Ebner,Koller,Lehner,Haas,Schuster,Heilig,Mammadov,Aliyev,Hasanov,Huseynov,Guliyev,Hajiyev,Rasulov,Suleymanov,Musayev,Abbasov,Babayev,Valiyev,Orujov,Ismayilov,Ibrahimov,Ivanoŭ,Kazloŭ,Kavalioŭ,Kazloŭski,Novik,Peeters,Janssens,Maes,Jacobs,Mertens,Willems,Claes,Goossens,Wouters,De Smet,Dubois,Lambert,Dupont,Martin,Simon,,Hodžić,Hadžić,Čengić,Delić,Demirović,Kovačević ,Tahirović,Ferhatović,Muratović,Ibrahimović,Hasanović,Mehmedović,Salihović,Terzić,Ademović,Adilović,Delemović,Zukić,Krličević,Suljić,Ahmetović,Kovačević,Subotić ,Savić,Popović,Jovanović,Petrović,Đurić,Babić ,Lukić,Knežević,Marković,Ilić,Đukić,Vuković,Vujić,Simić,Radić,Nikolić,Marić,Mitrović,Tomić,Božić,Golubović,Hoxha ,Hoxhaj,Prifti,Shehu ,Dervishi ,Bektashi,Leka,Lekaj,Gjoni,Murati,Mehmeti,Hysi,Gjika,Gjoka,Marku,Kola,Kolla,Nikolla,Hasani,Kristi,Luka,Brahimi,Sinani,Thanasi,Halili,Abazi,Dibra,Laci,Shkodra,Prishtina,Delvina,Koroveshi,Permeti,Frasheri,Gegaj,Gega,Tosku,Toskaj,Chami,Kelmendi,Shkreli,Berisha,Krasniqi,Gashi,Kuqi,Bardhi,Dimitrov,Dzhurov,Petrov,Ivanov,Stoyanov,Stefanov,Boyanov,Trifonov,Sofiyanski,Tasev,Metodiev,Katzarov,Iliev,Gospodinov,Apostolov,Hristov,Hasanov,Nikolov,Bojidarov,Stoichkov,Lechkov,Yanev,Yankov,Stoev,Konstantinov,Grigorov,Gruev,Georgiev,Kremenliev,Mihaylov,Blagoev,Horvat,Kovačević,Babić,Marić,Jurić,Novak,Kovačić,Knežević,Vuković,Marković,Petrović,Matić,Tomić,Pavlović,Kovač,Božić,Blažević,Grgić,Pavić,Radić,Perić,Filipović,Šarić,Lovrić,Vidović,Perković,Popović,Bošnjak,Jukić,Barišić,Nielsen,Jensen,Hansen,Pedersen,Andersen,Christensen,Larsen,Sørensen,Rasmussen,Jørgensen,Petersen,Madsen,Kristensen,Olsen,Thomsen,Christiansen,Poulsen,Johansen,Møller,Mortensen,Tamm,Saar,Sepp,Mägi,Kask,Kukk,Rebane,Ilves,Pärn,Koppel,Joensen,Hansen,Jacobsen,Olsen,Poulsen,Petersen,Johannesen,Thomsen,Nielsen,Johansen,Rasmussen,Simonsen,Djurhuus,Jensen,Danielsen,Mortensen,Mikkelsen,Dam,Højgaard,Andreasen,Korhonen,Virtanen,Mäkinen,Nieminen,Mäkelä,Hämäläinen,Laine,Heikkinen,Koskinen,Järvinen,Lehtonen,Lehtinen,Saarinen,Salminen,Heinonen,Niemi,Heikkilä,Kinnunen,Salonen,Turunen,Salo,Laitinen,Tuominen,Rantanen,Karjalainen,Jokinen,Mattila,Savolainen,Lahtinen,Ahonen,Martin,Bernard,Dubois,Thomas,Robert,Richard,Petit,Durand,Leroy,Moreau,Simon,Laurent,Lefebvre,Michel,Garcia,David,Bertrand,Roux,Vincent,Fournier,Morel,Girard,André,Lefèvre,Mercier,Dupont,Lambert,Bonnet,François,Martinez,Beridze,Mamedovi,Kapanadze,Alievi,Gelashvili,Maisuradze,Giorgadze,Lomidze,Tsiklauri,Bolkvadze,Müller,Schmidt,Schneider,Fischer,Meyer,Weber,Schulz,Wagner,Becker,Hoffmann,,Nagy,Horváth,Kovács,Szabó,Tóth,Varga,Kiss,Molnár,Németh,Farkas,Balogh,Papp,Takács,Juhász,Lakatos,Mészáros,Oláh,Simon,Rácz,Fekete,Murphy,(O')Kelly,(O')Sullivan,Walsh,Smith,O'Brien,(O')Byrne,(O')Ryan,O'Connor,O'Neill,(O')Reilly,Doyle,McCarthy,(O')Gallagher,(O')Doherty,Kennedy,Lynch,Murray,(O')Quinn,(O')Moore,Rossi,Russo,Ferrari,Esposito,Bianchi,Romano,Colombo,Bruno,Ricci,Greco,Marino,Gallo,De Luca,Conti,Costa,Mancini,Giordano,Rizzo,Lombardi,Barbieri,Moretti,Fontana,Caruso,Mariani,Ferrara,Santoro,Rinaldi,Leone,D'Angelo,Longo,Galli,Martini,Martinelli,Serra,Conte,Vitale,De Santis,Marchetti,Messina,Gentile,Villa,Marini,Lombardo,Coppola,Ferri,Parisi,De Angelis,Bianco,Amato,Fabbri,Gatti,Sala,Morelli,Grasso,Pellegrini,Ferraro,Monti,Palumbo,Grassi,Testa,Valentini,Carbone,Benedetti,Silvestri,Farina,D'Amico,Martino,Bernardi,Caputo,Mazza,Sanna,Fiore,De Rosa,Pellegrino,Giuliani,Rizzi,Di Stefano,Cattaneo,Rossetti,Orlando,Basile,Neri,Barone,Palmieri,Riva,Romeo,Franco,Sorrentino,Pagano,D'Agostino,Piras,Ruggiero,Montanari,Battaglia,Bellini,Castelli,Guerra,Poli,Valente,Ferretti");

    $maxcount = 1;
     do {
     $id =  $familynamelist[rand(0,count($familynamelist)-1)];
     $thisname = $pseudogname . " " . $id;
     } while (array_search($thisname, $usednames) !== false);
    $usednames[] = $thisname;
    return $id;
}

function assign_serial_pseudo_id($len) {
    // rather than just assigning a random string of junk,
    // this algorithm assembles a phrase string consisting of serialized words in base 26
    // accept a length parameter to determine how long the phrase needs to be for uniqueness
    // 1 word: fruit 26 possibilities
    // 2 words: animal with fruit 26*26 = 676
    // 3 words: color animal with fruit 26*676 = 17,576
    // 4 words: adjective, color, animal with fruit 26*17576 = 456,976
    // 5 words: verb, adjective, color, animal with fruit 26*456976 = 11,881,376
    // 6 words: adverb, verb, adjective, color, animal with fruit 26*11881376 = 308,915,776
    // 7 words: adverb, verb, adjective, color, animal with fruit and vegetable 26*308915776 = 8,031,810,176
    // if > 7, add a number at the end of the string containing modulus
    // Keep track of used IDs during the running of the script.

    static $usedserialpseudoids = array();
    static $countserialpseudoids = 0;

    $animallist = explode(",", "Analytics,Biology,Ceramics,Data,Ecology,Forensics,Genetics,Health,Investment,Journalism,Kinesiology,Lithography,Masonry,Nanoscience,Organization,Philosophy,Qualifications,Relativity,Syntax,Translation,Urbanization,Vertebrates,Wealth,Xenobiology,Youth,Zebras");
     $fruitlist = explode(",", "Astronomy,Behavior,Citizenship,Decisions,Engineering,Folklore,Gastronomy,Hospitality,Ideas,Jazz,Kant,Leisure,Mathematics,Neuroscience,Ontology,Parasitology,Queries,Reasoning,Semantics,Traditions,Utopia,Value,Worldviews,Xenophilia,Yersinia Pestis,Zygotes");
    $colorlist = explode(",", "Animal,Beverage,Career,Dialogue,Electron,Fossil,Greenhouse,Human,Identity,Justice,Knowledge,Landscape,Matrix,Nature,Optics,Place,Query,Rennaisance,Service,Trial,University,Video,Web,Xeriscape,Yeast,Zircon");
    $adjlist = explode(",", "Abstract,Behavioral,Cellular,Discrete,Economic,Finite,Global,Historical,Instrumental,Juvenile,Kinetic,Linear,Mechanical,Networked,Object-Oriented,Primary,Quantum,Renewable,Secondary,Thermal,Uniform,Virtual,Winter,Xerothermic,Yogic,Zoological");
    $verblist = explode(",", "Activating,Blending,Categorizing,Developing,Evaluating,Formulating,Generating,Hypothesizing,Imagining,Joining,Knowing,Learning,Moderating,Naming,Ordering,Predicting,Quantifying,Researching,Sampling,Teaching,Understanding,Valuing,Writing,eXamining,Yoking,Zeroing");
    $adverblist = explode(",", "Absolutely,Brilliantly,Creatively,Deeply,Empirically,Formally,Graphically,Holistically,Inferentially,Justly,Keenly,Locally,Medically,Non-Linearly,Organically,Physically,Qualitatively,Realistically,Statistically,Theoretically,Uniquely,Verbally,Wholly,Xerographically,Yearly,Zealously");
    $vegetablelist = explode(",", "Application,Basics,Challenges,Designs,Experiments,Factors,Generalizations,Hierarchy,Insights,Judgments,Keynotes,Literature,Materials,Norms,Opinions,Participation,Questions,Resources,Solutions,Topics,Uses,Vignettes,Ways,Xystus,Yields,");

	//debugging('count serial pseudoids ' . $countserialpseudoids, DEBUG_DEVELOPER);

     do {
    $maxcount = 1;

$fruitcount = count($fruitlist);
     // pick fruit//
     $id = $fruitlist[fmod($countserialpseudoids,count($fruitlist))];
	//debugging('fmod of counter  ' . $countserialpseudoids . ' , mod by fruitcount ' . count($fruitlist) . ' = ' . fmod($countserialpseudoids,count($fruitlist)), DEBUG_DEVELOPER);
     $maxcount = $maxcount * count($fruitlist);

     if ($len > $maxcount) {
    // 2 words: animal with fruit 26*26 = 676
     $id =  $animallist[fmod(floor($countserialpseudoids/$maxcount), count($animallist))] . " and " . $id;
	//debugging('floor of fmod of counter/maxcount ' . floor($countserialpseudoids/$maxcount) . ' ,  mod animalcount' . count($animallist) . ' = ' . fmod($countserialpseudoids,count($fruitlist)), DEBUG_DEVELOPER);
     		 $maxcount =  $maxcount * count($animallist);
     } else {
	     //debugging('stopped at fruit with ' . $id, DEBUG_DEVELOPER);
     }

     if ($len > $maxcount) {
    // 3 words: color animal with fruit 26*676 = 17,576
     $id = $colorlist[fmod(floor($countserialpseudoids/$maxcount), count($colorlist))] . " " . $id;
    		 $maxcount =  $maxcount * count($colorlist);
     } else {
	     //debugging('stopped at animals with ' . $id, DEBUG_DEVELOPER);
     }


     if ($len > $maxcount) {
    // 4 words: adjective, color, animal with fruit 26*17576 = 456,976
      $id = $adjlist[fmod(floor($countserialpseudoids/$maxcount), count($adjlist))] . " " . $id;
    		 $maxcount =  $maxcount * count($adjlist);
     } else {
	     //debugging('stopped at colors with ' . $id, DEBUG_DEVELOPER);
     }



     if ($len > $maxcount) {
    // 5 words: verb, adjective, color, animal with fruit 26*456976 = 11,881,376
     		 $id = $verblist[fmod(floor($countserialpseudoids/$maxcount), count($verblist))] . " " .  $id;
     		 $maxcount =  $maxcount * count($verblist);
     }

     if ($len > $maxcount) {
    // 6 words: adverb, verb, adjective, color, animal with fruit 26*11881376 = 308,915,776
        $id = $adverblist[fmod(floor($countserialpseudoids/$maxcount), count($adverblist))]  . " " . $id;
     		 $maxcount =  $maxcount * count($adverblist);
    }

     if ($len > $maxcount) {
    // 7 words: adverb, verb, adjective, color, animal with fruit and vegetable 26*308915776 = 8,031,810,176
       $id = $id  . ": " . $vegetablelist[fmod(floor($countserialpseudoids/$maxcount), count($vegetablelist))];
      		 $maxcount =  $maxcount * count($vegetablelist);
    }



    // add modulus (as string) to end of string if there is anything left
  if ($len > $maxcount) {
      $id = $id . " " . strval($len-$maxcount);
  } // if
	     //debugging('testing ' . $id . ' for uniqueness', DEBUG_DEVELOPER);
	     //debugging('count serial pseudoids ' . $countserialpseudoids, DEBUG_DEVELOPER);
	     //debugging('maxcount ' . $maxcount, DEBUG_DEVELOPER);
	     //debugging('len ' . $len, DEBUG_DEVELOPER);



     $countserialpseudoids++;
     } while (array_search($id,  $usedserialpseudoids) !== false);
	//debugging('passed ' . $id . ' for uniqueness', DEBUG_DEVELOPER);
     $usedserialpseudoids[] = $id;
     //print $countserialpseudoids;
    return $id;
}

function get_excluded_text_columns() {
    return array(
        'config' => array('value'),
        'config_plugins' => array('value'),
        'course_modules' => array('availability'),
        'course_sections' => array('sequence', 'availability'),
        'course_format_options' => array('value'),
        'filter_config' => array('value'),
        'message' => array('contexturl', 'contexturlname'),
        'message_read' => array('contexturl', 'contexturlname'),
        'scale' => array('scale'),
        'question_statistics' => array('subquestions', 'positions'),
        'events_handlers' => array('handlerfunction'),
        'grade_items' => array('calculation'),
        'grade_items_history' => array('calculation'),
        'tag_correlation' => array('correlatedtags'),
        'groupings' => array('configdata'),
        'files_reference' => array('reference'),
        'block_instances' => array('configdata'),
        'grading_definitions' => array('options'),
        'badge_issued' => array('uniquehash'),
        'competency' => array('ruleconfig', 'scaleconfiguration'),
        'competency_framework' => array('scaleconfiguration'),
        'logstore_standard_log' => array('other'),
        'question_multianswer' => array('sequence'),
        'qtype_ddmarker_drops' => array('coords'),
        'data' => array('singletemplate', 'listtemplate', 'listtemplateheader', 'listtemplatefooter', 'addtemplate', 'rsstemplate', 'csstemplate', 'jstemplate', 'asearchtemplate', 'config'),
        'lesson' => array('conditions'),
        'page' => array('displayoptions'),
        'feedback' => array('page_after_submit'),
        'resource' => array('displayoptions'),
        'quiz_attempts' => array('layout'),
        'workshopallocation_scheduled' => array('settings'),
        'lti_types' => array('enabledcapability', 'parameter'),
        'lti_tool_settings' => array('settings'),
        'scorm_scoes' => array('launch'),
        'scorm_scoes_data' => array('value'),
        'scorm_scoes_track' => array('value'),
        'assignfeedback_editpdf_annot' => array('path'),
        'assign_plugin_config' => array('value'),
        'survey_questions' => array('options'),
        'url' => array('displayoptions', 'parameters'),
        'imscp' => array('structure')
    );
}

function get_varchar_fields_to_update() {

    // I've left role names in db as they are although not 100% sure.
    // I've left tag as they are.
    $varchars = array(
        'assign' => array('name'),
        'assignment' => array('name'),
        'badge' => array('name', 'issuername', 'issuerurl', 'issuercontact'),
        'badge_backpack' => array('email', 'backpackurl', 'password'),
        'block_community' => array('coursename', 'courseurl', 'imageurl'),
        'block_rss_client' => array('preferredtitle', 'url'),
        'blog_external' => array('name'),
        'book' => array('name'),
        'book_chapters' => array('title', 'importsrc'),
        'chat' => array('name'),
        'chat_users' => array('sid'),
        'choice' => array('name'),
        'cohort' => array('name', 'idnumber'),
        'course_categories' => array('name', 'idnumber'),
        'course_published' => array('huburl'),
        'course_request' => array('fullname', 'shortname', 'password'),
        'course_sections' => array('name'),
        'course_modules' => array('idnumber'),
        'course' => array('fullname', 'shortname', 'idnumber'),
        'enrol' => array('name', 'password'),
        'enrol_paypal' => array('business', 'receiver_email', 'receiver_id', 'item_name', 'memo', 'tax', 'pending_reason', 'reason_code', 'txn_id', 'parent_txn_id'),
        'feedback' => array('name'),
        'feedback_item' => array('name', 'label', 'dependvalue'),
        'feedback_template' => array('name'),
        'files' => array('filename', 'author'),
        'forum_posts' => array('subject'),
        'glossary' => array('name'),
        'glossary_entries' => array('concept'),
        'grade_categories' => array('fullname'),
        'forum_discussions' => array('name'),
        'forum' => array('name'),
        'grade_categories_history' => array('fullname'),
        'grade_import_newitem' => array('itemname'),
        'grade_items' => array('itemname'),
        'grade_items_history' => array('itemname'),
        'grade_outcomes' => array('shortname'),
        'grade_outcomes_history' => array('shortname'),
        'grading_definitions' => array('name'),
        'gradingform_guide_criteria' => array('shortname'),
        'groupings' => array('name', 'idnumber'),
        'groups' => array('name', 'idnumber', 'enrolmentkey'),
        'imscp' => array('name'),
        'label' => array('name'),
        'lesson' => array('name', 'password'),
        'lesson_overrides' => array('password'),
        'lesson_pages' => array('title'),
        'lti' => array('name', 'instructorcustomparameters', 'resourcekey', 'password', 'servicesalt'),
        'lti_tool_proxies' => array('name', 'secret', 'vendorcode', 'name'),
        'lti_types' => array('name', 'tooldomain'),
        'messageinbound_datakeys' => array('datakey'),
        'mnet_application' => array('display_name', 'name', 'sso_jump_url', 'sso_land_url', 'xmlrpc_server_url'),
        'mnet_host' => array('ip_address', 'name', 'wwwroot'),
        'mnet_remote_rpc' => array('functionname', 'xmlrpcpath'),
        'mnet_rpc' => array('functionname', 'xmlrpcpath'),
        'mnet_service' => array('description', 'name'),
        'mnet_sso_access_control' => array('accessctrl', 'username'),
        'mnetservice_enrol_courses' => array('categoryname', 'fullname', 'idnumber', 'rolename', 'shortname'),
        'mnetservice_enrol_enrolments' => array('rolename'),
        'page' => array('name'),
        'portfolio_instance' => array('name'),
        'portfolio_mahara_queue' => array('token'),
        'post' => array('subject'),
        'profiling' => array('url'),
        'qtype_match_subquestions' => array('answertext'),
        'question' => array('name'),
        'question_categories' => array('name'),
        'question_dataset_definitions' => array('name'),
        'quiz' => array('name', 'password', 'subnet'),
        'quiz_overrides' => array('password'),
        'quiz_sections' => array('heading'),
        'registration_hubs' => array('hubname', 'huburl', 'secret', 'token'),
        'repository_instances' => array('name', 'password', 'username'),
        'resource' => array('name'),
        'resource_old' => array('name'),
        'scale' => array('name'),
        'scale_history' => array('name'),
        'scorm' => array('name', 'sha1hash', 'md5hash'),
        'scorm_scoes' => array('identifier', 'manifest', 'organization', 'title'),
        'survey' => array('name'),
        'tool_monitor_rules' => array('name'),
        'tool_recyclebin_category' => array('fullname', 'shortname'),
        'tool_recyclebin_course' => array('name'),
        'tool_usertours_tours' => array('name'),
        'url' => array('name'),
        'user' => array('address', 'aim', 'alternatename', 'city', 'department', 'email', 'firstname', 'firstnamephonetic', 'icq', 'idnumber', 'imagealt', 'institution', 'lastip', 'lastname', 'lastnamephonetic', 'middlename', 'msn', 'password', 'phone1', 'phone2', 'secret', 'skype', 'url', 'yahoo'),
        'user_devices' => array('appid', 'model', 'name', 'platform', 'pushid', 'uuid', 'version'),
        'user_info_category' => array('name'),
        'wiki' => array('firstpagetitle', 'name'),
        'wiki_links' => array('tomissingpage'),
        'wiki_locks' => array('sectionname'),
        'wiki_pages' => array('title'),
        'wiki_synonyms' => array('pagesynonym'),
        'workshop' => array('name'),
        'workshop_old' => array('name', 'password'),
        'workshop_submissions' => array('title'),
        'workshopallocation_scheduled' => array('resultmessage')
    );

    foreach ($varchars as $tablename => $columns) {
        $varchars[$tablename] = array_combine($columns, $columns);
    }

    return $varchars;
}

function get_activity_name_fields() {
    $activities = array(
        'assign' => array('name'),
        'assignment' => array('name'),
        'book' => array('name'),
        'chat' => array('name'),
        'choice' => array('name'),
        'data' => array('name'),
        'feedback' => array('name'),
        'folder' => array('name'),
        'forum' => array('name'),
        'glossary' => array('name'),
        'imscp' => array('name'),
        'label' => array('name'),
        'lesson' => array('name'),
        'lti' => array('name'),
        'page' => array('name'),
        'quiz' => array('name'),
        'resource' => array('name'),
        'scorm' => array('name'),
        'survey' => array('name'),
        'url' => array('name'),
        'wiki' => array('name'),
        'workshop' => array('name'),
    );

    foreach ($activities as $tablename => $columns) {
        $activities[$tablename] = array_combine($columns, $columns);
    }

    return $activities;

}
