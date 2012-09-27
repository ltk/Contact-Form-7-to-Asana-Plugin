<?php
/*
   Plugin Name: Contact Form 7 to Asana Extension
   Plugin URI: http://thejakegroup.com/wordpress/
   Version: 1.0
   Author: Lawson Kurtz
   Description: Automatically save form submissions from Contact Form 7 as new tasks in Asana.
   Text Domain: contact-form-7-to-asana-extension
   Attribution: This plugin is heavily modeled off of Michael Simpson's excellent "Contact Form 7 to Database Extension" plugin <http://wordpress.org/extend/plugins/contact-form-7-to-database-extension/>.
   License: GPL3
  */

class CF7toAsana {
    private $asana;
    private $displayName;
    private $viewTemplates;

    public function __construct(){
        $this->connectToAsana();
        $this->displayName = 'Contact Form to Asana Extension';
        $this->viewTemplates = array();
    }

    public function activate(){
        $this->initOptions();
    }

    public function addActions() {

        add_action('admin_menu', array(&$this, 'createAdminMenu'));

        // Hook into Contact Form 7 when a form post, but only if we have a good connection with Asana
        if($this->asana){
            add_action('wpcf7_before_send_mail', array(&$this, 'processSubmission'));
        }
    }

    public function processSubmission($cf7) {
        $this->sendToAsana($cf7->posted_data);
    }

    public function createAdminMenu() {
        $displayName = $this->getPluginDisplayName();
        $roleAllowed = $this->getRoleOption('Administrator');
        
        add_submenu_page('wpcf7',
                         'contact-form-7-to-asana-extension',
                         $displayName,
                         $this->roleToCapability($roleAllowed),
                         $this->getDBPageSlug(),
                         array(&$this, 'settingsPage'));
    }

    public function settingsPage() {
            if (!current_user_can('manage_options')) {
                wp_die('You do not have sufficient permissions to access this page.', 'contact-form-7-to-database-extension');
            }

            $optionMetaData = $this->getOptionMetaData();

            // Save Posted Options
            if ($optionMetaData != null) {
                foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
                    if (isset($_POST[$aOptionKey])) {
                        $this->updateOption($aOptionKey, $_POST[$aOptionKey]);

                        // If settings have been changed, refresh the connection with Asana
                        $this->connectToAsana();
                    }
                }
            }

            // HTML for the page
            $settingsGroup = get_class($this) . '-settings-group';

            $fields = '';

            if ($optionMetaData != null) {
                foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
                    $displayText = is_array($aOptionMeta) ? $aOptionMeta[0] : $aOptionMeta;
                    $displayText = $displayText;
                    if ($aOptionMeta[1] == 'AsanaAPIKey' || $this->asana) {
                        $fields .= sprintf($this->getViewTemplate('table_row'), $aOptionKey, $displayText, $this->getFormControl($aOptionKey, $aOptionMeta, $this->getOption($aOptionKey)));
                    }
                }
            }

            $form_settings = settings_fields($settingsGroup);
            $table = sprintf($this->getViewTemplate('table'), $fields);
            $form = sprintf($this->getViewTemplate('form'), $form_settings, $table);
            $page = sprintf($this->getViewTemplate('settings_page'), $this->getPluginDisplayName(), $form);

            echo $page;
        }


    private function getViewTemplate($view) {
        if(array_key_exists($view, $this->viewTemplates)) {
            return $this->viewTemplates[$view];
        } else {
            $template = file_get_contents(__DIR__ . "/views/$view.html");
            $this->viewTemplates[$view] = $template;
            return $template;
        }
    }

    private function getNoSaveFields() {
        return preg_split('/,|;/', $this->getOption('NoSaveFields'), -1, PREG_SPLIT_NO_EMPTY);
    }

    private function connectToAsana() {
        require_once('Asana.php');

        $asana_api_key = $this->getOption('AsanaAPIKey');
        $asana = new Asana($asana_api_key);
        $this->asana = ($asana->getUserInfo() ? $asana : false);

    }

    private function digestNotes($form_data) {
        $notes = '';
        $noSaveFields = $this->getNoSaveFields();

        foreach ($form_data as $name => $value) {
            $nameClean = stripslashes($name);
            
            if (in_array($nameClean, $noSaveFields)) {
                continue; // Don't save in DB
            }

            $value = is_array($value) ? implode($value, ', ') : $value;
            $valueClean = stripslashes($value);
            $notes .= $nameClean . ': ' . $valueClean . "\r\n";
        }
        return $notes;
    }

    private function sendToAsana($form_data, $task_title = false) {
        $new_task_array = array(
           "workspace" => $this->getOption('AsanaWorkspaceID'), // Workspace ID
           "name" => (!$task_title ? $this->getOption('AsanaTaskTitle') : $task_title), // Name of task
           "assignee" => $this->getAssigneeEmail(), // Right now we autoassign the task to the owner of the API key.
           "notes" => $this->digestNotes($form_data)
        );

        $errors = array();

        foreach($new_task_array as $field_key => $field_value) {
            if ( !$field_value || empty($field_value)) {
                error_log("Error: " . $field_key);
                array_push($errors, $field_key);
            }
        }

        if (empty($errors)){
            return ($this->asana->createTask($new_task_array) ? true : false);
        } else {
            return false;
        }
    }

    private function getOptionNamePrefix() {
        return get_class($this) . '_';
    }

    private function prefix($name) {
        $optionNamePrefix = $this->getOptionNamePrefix();
        if (strpos($name, $optionNamePrefix) === 0) { // 0 but not false
            return $name; // already prefixed
        }
        return $optionNamePrefix . $name;
    }
    
    private function addOption($optionName, $value) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return add_option($prefixedOptionName, $value);
    }

    private function getOption($optionName, $default = null) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        $retVal = get_option($prefixedOptionName);
        if (!$retVal && $default) {
            $retVal = $default;
        }
        return $retVal;
    }
    private function getAssigneeEmail() {
        $api_user_data = $this->parse_json($this->asana->getUserInfo());
        if(!empty($api_user_data['data'])){
            return $api_user_data['data']['email'];
        } else {
            return false;
        }
    }

    private function getAsanaWorkspaces() {
        $workspace_data = $this->parse_json($this->asana->getWorkspaces());
        if(!empty($workspace_data['data'])){
            return $workspace_data['data'];
        } else {
            return false;
        }

    }

    private function getAsanaUsers() {
        $users_data = $this->parse_json($this->asana->getUsers());
        if(!empty($users_data['data'])){
            return $users_data['data'];
        } else {
            return false;
        }

    }

    private function parse_json($json) {
        return(json_decode($json, TRUE));
    }

    private function getOptionMetaData() {
        return array(
            'AsanaAPIKey' => array('Enter your Asana API Key:', 'AsanaAPIKey'),
            'AsanaWorkspaceID' => array('Choose the Asana Workspace to which the tasks should be assigned:', 'AsanaWorkspaceID'),
            // Uncomment to choose an assignee for the automatically-created tasks within Asana... Warning: This is not 100% reliable at the moment.
            //'AsanaAssigneeEmail' => array('Select the Asana user to whom the tasks should be assigned:', 'AsanaAssigneeEmail'),
            'AsanaTaskTitle' => array('Enter your preferred task title for new form submissions:')
        );
    }

    private function getDBPageSlug() {
        return get_class($this) . 'Options';
    }

    private function getPluginFileUrl($pathRelativeToThisPluginRoot = '') {
        return plugins_url($pathRelativeToThisPluginRoot, __FILE__);
    }

    private function getPluginDisplayName() {
        return $this->displayName;
    }

    private function getRoleOption($optionName) {
        $roleAllowed = $this->getOption($optionName);
        if (!$roleAllowed || $roleAllowed == '') {
            $roleAllowed = 'Administrator';
        }
        return $roleAllowed;
    }

    private function updateOption($optionName, $value) {
        $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
        return update_option($prefixedOptionName, $value);
    }

    protected function getFormControl($aOptionKey, $aOptionMeta, $savedOptionValue) {
        $output = '';

        if ($this->asana && is_array($aOptionMeta) && $aOptionMeta[1] == 'AsanaAssigneeEmail') {            
            
            $choices = $this->getAsanaUsers();
            $options = array();

            foreach($choices as $choice){
                array_push($options, sprintf($this->getViewTemplate('option'), $choice['id'], ($savedOptionValue == $choice['id'] ? 'selected="selected"' : ''), $choice['name']));
            }

            $select = sprintf($this->getViewTemplate('select'), $aOptionKey, '', implode($options));

            $output .= $select;

        }
        elseif ($this->asana && is_array($aOptionMeta) && $aOptionMeta[1] == 'AsanaWorkspaceID') {
            
            $choices = $this->getAsanaWorkspaces();
            $options = array();

            foreach($choices as $choice){
                array_push($options, sprintf($this->getViewTemplate('option'), $choice['id'], ($savedOptionValue == $choice['id'] ? 'selected="selected"' : ''), $choice['name']));
            }

            $select = sprintf($this->getViewTemplate('select'), $aOptionKey, '', implode($options));

            $output .= $select;

        }
        elseif ($this->asana || $aOptionMeta[1] == 'AsanaAPIKey') { // Simple input field
            $output .= sprintf($this->getViewTemplate('text_input'), $aOptionKey, $aOptionKey, esc_attr($savedOptionValue));
        } else {
            $output .= sprintf($this->getViewTemplate('message'), 'Please enter a valid Asana API key to set this option.');
        }

        return $output;
    }

    protected function roleToCapability($roleName) {
        switch ($roleName) {
            case 'Super Admin':
                return 'manage_options';
            case 'Administrator':
                return 'manage_options';
            case 'Editor':
                return 'publish_pages';
            case 'Author':
                return 'publish_posts';
            case 'Contributor':
                return 'edit_posts';
            case 'Subscriber':
                return 'read';
            case 'Anyone':
                return 'read';
        }
        return '';
    }

    protected function initOptions() {
        // By default ignore CF7 metadata fields
        $this->addOption('NoSaveFields', '_wpcf7,_wpcf7_version,_wpcf7_unit_tag,_wpnonce,_wpcf7_is_ajax_call');
        $this->addOption('AsanaTaskTitle', 'New Form Submission');
    }
}

// Our initialization function
function CF7toAsana_init($file) {
    $cf7toAsana = new CF7toAsana();
    $cf7toAsana->addActions();

    if (!$file) {
        $file = __FILE__;
    }

    register_activation_hook($file, array(&$cf7toAsana, 'activate'));
}

// Let's get this party started.
CF7toAsana_init(__FILE__);

?>