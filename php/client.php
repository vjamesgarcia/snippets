<?php
require_once('contact.php');

class Client extends Contact {
    function __construct() { parent::__construct(); }
    public function echoToolbar() { }
    public function processArgs() {
        $success = true;
        if (array_key_exists(0, $this->args)) {
            if (is_numeric($this->args[0])) {
                $this->id = $this->args[0];
                $this->callback = $this->domain.'client/'.$this->id;

                $this->processUpdate();

                // select client name
                $sth = $this->db->prepare("SELECT `contacts`.`name` AS 'client_name',"
                    ."CONCAT_WS(' ',`first_name`,`middle_name`,`suffix`) AS 'first_names' "
                    .'FROM `clients` '
                    .'JOIN `contacts` ON `clients`.`contact_id`=`contacts`.`id` '
                    .'WHERE `clients`.`id`=?');
                $sth->execute(array($this->id));
                if ($row = $sth->fetch()) {
                    $this->full_name = $row['client_name'];
                    $this->full_name .= ($row['first_names']) ? ', '.$row['first_names'] : '';
                } else $success = false;

                $success = $this->userViewEdit($this->id);
                if ($success && !$this->processContact()) {
                    // any employee can add/edit clients
                    if (array_key_exists(1, $this->args)) {
                        if ($this->args[1] == 'edit' && $this->editable) {
                            // edit client
                            $this->page_type = 'edit';
                            $this->page_id = 'client-'.$this->id.'-edit';
                            $this->title = 'Edit client: '.$this->full_name;
                        } else $success = false;
                    } else {
                        // view client
                        $this->page_type = 'view';
                        $this->page_id = 'client-'.$this->id;
                        $this->title = 'Client: '.$this->full_name;//.' (client)';
                    }
                }
            } elseif ($this->args[0] == 'new' && !array_key_exists(1, $this->args)) {
                $this->processUpdate();
                $this->page_type = 'new';
                $this->page_id = 'client-new';
                $this->title = 'New Client';
            } else $success = false;
        } else $success = false; 

        return $success;
    }
    public function processUpdate() {
        $updated = false;
        if (!$this->updateContact()) {
            if (isset($_POST['client'])) {
                $updated = true;
                $this->updateClient($_POST['client']);
            } 
        }

        if ($updated) {
            session_write_close();
            header("Location: ".$this->callback);	
            die();
        } 
    }
    public function userViewEdit($client_id) {
        $success = true;
        $this->editable = true;
        return $success;
    }
    public function updateClient($form) {
        // deleted?
        if (array_key_exists('delete', $form) && $form['delete'] == 1) {
            if ($this->admin && $this->deleteClient($form['id'])) {
                $this->callback = $this->domain.'clients';
            } else {
                $this->callback = $this->domain.'client/'.$form['id'];
            } 
        } else {
            if (array_key_exists('id', $form) && $form['id'] !== '') {
                // edit client
                $this->callback = $this->domain.'client/'.$form['id'];
                if ($this->updateContactInfo($form)) {
                    $_SESSION['msg'] = 'Client info updated.';
                } else $_SESSION['msg'] = 'Error: Client could not be updated.';
            } else {
                // new client
                $this->callback = $this->domain.'clients';

                if ($contact_id = $this->updateContactInfo($form)) {
                    // add client
                    $sth = $this->db->prepare('INSERT INTO `clients` '
                        .'(`contact_id`,`created_by`) VALUES '
                        .'(?,?)');
                    $sth->execute(array($contact_id, $this->emp_id));
                    if ($sth->rowCount()) {
                        $this->callback = $this->domain.'client/'.$this->db->lastInsertId();
                        $_SESSION['msg'] = 'Client added.';
                    } else {
                        $this->deleteContact($contact_id);
                        $_SESSION['msg'] = 'Error: Client could not be added.';
                    }
                } else $_SESSION['msg'] = 'Error: Contact could not be added.';
            }
        }
    }
    public function deleteClient($client_id) {
        $_SESSION['msg'] = 'Error: Unable to delete client.';
        return false;
    }
    public function echoContent() {
        echo '<div data-role="content">';

        if (!$this->echoContact()) {
            if ($this->page_type == 'view') {
                $this->echoClient();
            } else {
                $this->echoClientEdit();
            }
        }

        echo '</div>';
    }
    public function echoClient() {
        $sth = $this->db->prepare("SELECT `contact_id`,`ssn`,`aka`,`dba`, "
            ."DATE_FORMAT(`dob`, '%c-%e-%Y') AS 'dob_format' "
            .'FROM `clients` '
            .'JOIN `contacts` ON `clients`.`contact_id`=`contacts`.`id` '
            .'WHERE `clients`.`id`=?');
        $sth->execute(array($this->id));
        if ($row = $sth->fetch()) {
            $this->contact_id = $row['contact_id'];

            // client info
            $list = new ListObject(true);
            $item = new ListItem($this->full_name, $this->domain.'client/'.$this->id.'/edit', '/css/fugue/user.png',
                'class="mini-item"');
            if ($row['dob_format']) $item->addSubItem('DOB: '.$row['dob_format']);
            if ($row['ssn']) $item->addSubItem('SSN: '.$row['ssn']);
            if ($row['aka']) $item->addSubItem($paras[] = 'AKA: '.$row['aka']);
            if ($row['dba']) $item->addSubItem($paras[] = 'DBA: '.$row['dba']);
            //$list->addItem($this->full_name, $this->domain.'client/'.$this->id.'/edit', '/css/fugue/user.png', $paras);
            $list->addListItem($item);
            $list->echoList();

            // cases
            $list = new ListObject(true, 'info', 'data-split-theme="c" class="case-list"');
            $list->addHeading('Cases', '<u>N</u>ew', $this->domain.'case/new/'.$this->id);
            $sthx = $this->db->prepare("SELECT `closed`,`folders`.`id` AS 'folder_id',"
                ."`balance`,`docket`, "
                ."`employees`.`name` AS 'atty_name',"
                ."DATE_FORMAT(`opened`, '%c-%e-%y') AS 'open_format', "
                ."`folders`.`name` AS 'folder_name', "
                ."`case_types`.`name` AS 'type_name' "
                .'FROM `folders` '
                .'LEFT OUTER JOIN `case_types` ON `folders`.`type_id`=`case_types`.`id` '
                .'JOIN `employees` ON `folders`.`emp_id`=`employees`.`id` '
                .'WHERE `client_id`=? AND `folders`.`folder_id` IS NULL ORDER BY `folders`.`opened` DESC');
            $sthx->execute(array($this->id));
            if ($sthx->rowCount()) {
                while ($rowx = $sthx->fetch()) {
                    $item = new ListItem($rowx['folder_name'], $this->domain.'folder/'.$rowx['folder_id'], '/css/fugue/inbox-document-text.png'
                        , 'class="mini-item"');
                    $item->addSplit($this->domain.'case/'.$rowx['folder_id'].'/edit');
                    $item->setRightText($rowx['open_format']);

                    $subtext = '';
                    if ($rowx['type_name']) {
                        $subtext .= ($rowx['docket']) ? $rowx['docket'].' - ' : '';
                        $subtext .= $rowx['type_name'].' case. ';
                    }
                    $subtext .= 'Attorney: '.$rowx['atty_name'].'. ';
                    if ($rowx['closed']) $subtext .= 'Case closed. ';
                    if ($rowx['fee_type']) {
                        if ($rowx['fee_type'] == 'appt') {
                            $subtext .= 'Court appointed.';
                        } else {
                            if ($rowx['balance']) {
                                $subtext .= 'Balance: $'.$rowx['balance'];
                            }
                        }
                    }
                    $item->addSubItem($subtext);

                    $list->addListItem($item);
                }
            } else {
                $list->addText('No cases.');
            }
            $list->echoList();

            $this->listAddresses($this->contact_id, 'client', $this->id);
            $this->listPhones($this->contact_id, 'client', $this->id);
        }
    }
    public function echoClientEdit() {
        $id; $contact_id;
        $sex='m'; $first_name; $middle_name; $last_name; $suffix; 
        $ssn; $dob; $aka; $dba;
        $success = true;
        if ($this->page_type == 'edit') {
            $sth = $this->db->prepare("SELECT `contact_id`,`contacts`.`name` AS 'last_name',"
                ."`first_name`,`middle_name`,`suffix`,`sex`,`ssn`,`aka`,`dba`, "
                ."DATE_FORMAT(`dob`, '%c-%e-%Y') AS 'dob_format' "
                .'FROM `clients` '
                .'JOIN `contacts` ON `clients`.`contact_id`=`contacts`.`id` '
                .'WHERE `clients`.`id`=?');
            $sth->execute(array($this->id));
            if ($row = $sth->fetch()) {
                $id = $this->id;
                $contact_id = $row['contact_id'];
                $first_name = $row['first_name'];
                $middle_name = $row['middle_name'];
                $last_name = $row['last_name'];
                $suffix = $row['suffix'];
                $sex = $row['sex'] ? $row['sex'] : 'none';
                $ssn = $row['ssn'];
                $dob = $row['dob_format'];
                $aka = $row['aka'];
                $dba = $row['dba'];
            } else $success = false;
        }

        if ($success) {
            $form = new Form('client');
            $form->addHeading('Name');
            $form->addSelect('', 'sex', array('m'=>'Male','f'=>'Female','none'=>'None'), $sex);
            $form->addText('First name:','first_name', false, $first_name);
            $form->addText('Middle:','middle_name', false, $middle_name);
            $form->addText('Last (or biz name):','name', true, $last_name);
            $form->addText('Suffix (Jr/Sr/etc):','suffix', false, $suffix);
            $form->addHeading('Info');
            $form->addDate('Date of birth:', 'dob', false, true, $dob);
            $form->addText('SSN (no dashes):','ssn', false, $ssn, 'minlength="9" maxlength="9" digits="true"');
            $form->addText('AKA:','aka', false, $aka);
            $form->addText('DBA:','dba', false, $dba);
            $form->addHidden('id', $id);
            $form->addHidden('contact_id', $contact_id);
            if ($this->admin && $this->page_type == 'edit') $form->addDelete();
            $form->echoForm();
        } 
    }
}

$page = new Client();
?>
