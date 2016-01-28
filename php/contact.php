<?php
require_once('page.php');

abstract class Contact extends Page {
    public $id;
    public $contact_id;
    public $contact_item_id=0;
    public $contact_page=false;
    public $full_name;
    //public $form_callback;
    public function processArgs() {}
    public function echoToolbar() {}
    function __construct() { parent::__construct(); }
    public function processContact() {
        $processed = false;
        if (array_key_exists(1, $this->args) && is_numeric($this->args[1]) && array_key_exists(2, $this->args) ) {
            $this->contact_id = $this->args[1];
            if ($this->args[2] == 'address') {
                if (is_numeric($this->args[3])) {
                    // existing address 
                    $processed = true;
                    $this->contact_item_id = $this->args[3];
                    $this->page_type = 'address';
                    $this->page_id = 'address-'.$this->contact_item_id;
                    $this->title = 'Edit address for '.$this->full_name;
                } elseif ($this->args[3] == 'new') {
                    // new address
                    $processed = true;
                    $this->page_type = 'address';
                    $this->page_id = 'address-new';
                    $this->title = 'New address for '.$this->full_name;
                } 
            } elseif ($this->args[2] == 'phone') {
                if (array_key_exists(3, $this->args)) { 
                    if (is_numeric($this->args[3])) {
                        // existing phone 
                        $processed = true;
                        $this->contact_item_id = $this->args[3];
                        $this->page_type = 'phone';
                        $this->page_id = 'phone-'.$this->contact_item_id;
                        $this->title = 'Edit phone for '.$this->full_name;
                    } elseif ($this->args[3] == 'new') {
                        // new phone
                        $processed = true;
                        $this->page_type = 'phone';
                        $this->page_id = 'phone-new';
                        $this->title = 'New phone for '.$this->full_name;
                    } 
                }
            } elseif ($this->args[2] == 'email') {
                if (array_key_exists(3, $this->args)) { 
                    if (is_numeric($this->args[3])) {
                        // existing email 
                        $processed = true;
                        $this->contact_item_id = $this->args[3];
                        $this->page_type = 'email';
                        $this->page_id = 'email-'.$this->contact_item_id;
                        $this->title = 'Edit email for '.$this->full_name;
                    } elseif ($this->args[3] == 'new') {
                        // new email
                        $processed = true;
                        $this->page_type = 'email';
                        $this->page_id = 'email-new';
                        $this->title = 'New email for '.$this->full_name;
                    } 
                }
            }
        }
        $this->contact_page = $processed;
        return $processed;
    }
    public function echoContact() {
        if ($this->contact_page) {
            switch ($this->page_type) {
            case 'address':
                $this->echoAddress();
                break;
            case 'phone':
                $this->echoPhone();
                break;
            case 'email':
                $this->echoEmail();
                break;
            }
        }
        return $this->contact_page;
    }
    public function countyArray($state_id) {
        $counties = array(0=>'No county selected.','new'=>'Create a new county.');
        if ($state_id) {
            $sth = $this->db->prepare("SELECT `id`,`name` "
                .'FROM `loc_counties` '
                .'WHERE `loc_counties`.`state_id`=? '
                .'ORDER BY `name`');
            $sth->execute(array($state_id));
            while ($row = $sth->fetch()) {
                $counties[$row['id']] = $row['name'];
            }
        }
        return $counties;
    }
    public function cityArray($state_id) {
        $cities = array(0=>'No city selected.','new'=>'Create a new city.');
        $sth = $this->db->prepare("SELECT `id`,`name` "
            .'FROM `loc_cities` '
            .'WHERE `loc_cities`.`state_id`=? '
            .'ORDER BY `name`');
        $sth->execute(array($state_id));
        while ($row = $sth->fetch()) {
            $cities[$row['id']] = $row['name'];
        }
        return $cities;
    }
    public function echoAddress() {
        $street; $type; $zip; $city=0; $state; $foreign; $abbr;
        $success = true; $foreign_loc=0; $primary=1;
        if ($this->contact_item_id) {
            $sth = $this->db->prepare("SELECT con_addresses.`abbr`,`primary`,`street`,`type`,`zip`,`foreign`, "
                ."`city_id`,`loc_states`.`id` AS 'state' "
                .'FROM `con_addresses` '
                .'LEFT OUTER JOIN `loc_cities` ON `con_addresses`.`city_id`=`loc_cities`.`id` '
                .'LEFT OUTER JOIN `loc_states` ON `loc_cities`.`state_id`=`loc_states`.`id` '
                .'WHERE `con_addresses`.`id`=?');
            $sth->execute(array($this->contact_item_id));
            if ($row = $sth->fetch()) {
                $abbr = $row['abbr'];
                $street = $row['street'];
                $type = $row['type'];
                $zip = $row['zip'];
                $city = $row['city_id'] ? $row['city_id'] : 0;
                $state = $row['state'];
                $primary = $row['primary'];
                if ($row['foreign']) {
                    $foreign_loc = 1;
                    $foreign = $row['foreign'];
                }
            } else $success = false;
        } 
        if ($city == 0) {
            //pre-set state
            $state = $this->office_state_id;
            $city = $this->office_city_id;
        }

        if ($success) {
            $form = new Form('address');
            $form->addSelect('', 'type', array('Home'=>'Home','Work'=>'Work','Other'=>'Other'), $type);
            $form->addSwitch('Location:', 'foreign_loc', array('0'=>'United States','1'=>'Foreign'), $foreign_loc, 'class="wideswitch"');
            $form->addText('Foreign city, state:','foreign', false, $foreign);
            $form->addSelect('State:', 'state', $this->stateArray(), $state);
            $form->addSelect('City:', 'city', $this->cityArray($state), $city);
            $form->addText('New city:','new_city', false);
            $form->addTextArea('Street:','street', true, $street);
            $form->addText('Zip:','zip', true, $zip, 'maxlength="10"');
            $form->addText('Abbreviation:','abbr', false, $abbr, 'maxlength="2"');
            $form->addSwitch('Primary:', 'primary', array('0'=>'No','1'=>'Yes'), $primary);//, 'class="wideswitch"');
            $form->addHidden('id', $this->contact_item_id);
            $form->addHidden('contact_id', $this->contact_id);
            //$form->addHidden('callback', $this->form_callback);
            if ($this->contact_item_id) $form->addDelete();
            $form->echoForm();
        } 
    }
    public function echoPhone() {
        $phone; $dscr; $primary=1; $type;
        $success = true; 
        if ($this->contact_item_id) {
            $sth = $this->db->prepare("SELECT `type`,`primary`,`phone`,`dscr` "
                .'FROM `con_phones` '
                .'WHERE `con_phones`.`id`=?');
            $sth->execute(array($this->contact_item_id));
            if ($row = $sth->fetch()) {
                $type = $row['type'];
                $phone = $row['phone'];
                $type = $row['type'];
                $dscr = $row['dscr'];
                $primary = $row['primary'];
            } else $success = false;
        } 

        if ($success) {
            $form = new Form('phone');
            $form->addSelect('', 'type', array('Cell'=>'Cell','Home'=>'Home','Work'=>'Work','Fax'=>'Fax','Other'=>'Other'), $type);
            $form->addText('Phone:','phone', true, $phone, 'maxlength="20"');
            $form->addText('Description:','dscr', false, $dscr, 'maxlength="255"');
            $form->addSwitch('Primary:', 'primary', array('0'=>'No','1'=>'Yes'), $primary);//, 'class="wideswitch"');
            $form->addHidden('id', $this->contact_item_id);
            $form->addHidden('contact_id', $this->contact_id);
            //$form->addHidden('callback', $this->form_callback);
            if ($this->contact_item_id) $form->addDelete();
            $form->echoForm();
        } 
    }
    public function echoEmail() {
        $email; $alert=0; $primary=0; 
        $success = true; 
        if ($this->contact_item_id) {
            $sth = $this->db->prepare("SELECT `primary`,`alert`,`email` "
                .'FROM `con_emails` '
                .'WHERE `con_emails`.`id`=?');
            $sth->execute(array($this->contact_item_id));
            if ($row = $sth->fetch()) {
                $email = $row['email'];
                $alert = $row['alert'];
                $primary = $row['primary'];
            } else $success = false;
        } 

        if ($success) {
            $form = new Form('email');
            $form->addText('Email:','email', true, $email, 'email="true"');
            $form->addSwitch('Primary:', 'primary', array('0'=>'No','1'=>'Yes'), $primary);
            $form->addSwitch('Receive alerts:', 'alert', array('0'=>'No','1'=>'Yes'), $alert);
            $form->addHidden('id', $this->contact_item_id);
            $form->addHidden('contact_id', $this->contact_id);
            //$form->addHidden('callback', $this->form_callback);
            if ($this->contact_item_id) $form->addDelete();
            $form->echoForm();
        } 
    }
    public function updateContact() {
        $updated = false;
        // callback set in child class
        if (isset($_POST['address'])) {
            $this->updateAddress($_POST['address']);
            $updated = true;
        } elseif (isset($_POST['phone'])) {
            $this->updatePhone($_POST['phone']);
            $updated = true;
        } elseif (isset($_POST['email'])) {
            $this->updateEmail($_POST['email']);
            $updated = true;
        } 

        if ($updated) {
            session_write_close();
            header("Location: ".$this->callback);	
            die();
        } 
        return $updated;
    }
    public function deleteContactItem($table, $id) {
        $delete = true;
        $sth = $this->db->prepare('DELETE FROM `'.$table.'` '
            .'WHERE `id`=?');
        $sth->execute(array($id));
        if ($sth->rowCount()) {
            $_SESSION['msg'] = 'Item deleted.';
        } else {
            $delete = false;
            $_SESSION['msg'] = 'Error: item not deleted.';
        } 
        return $delete;
    }
    public function listEmails($contact_id, $child_name, $child_id) {
        $list = new ListObject(true, null, 'class="item-list"');
        $list->addHeading('Email addresses', 'New', $this->domain.$child_name.'/'.$child_id.'/'.$contact_id.'/email/new');
        $sthx = $this->db->prepare("SELECT `primary`,`email`, "
            ."`con_emails`.`id` AS 'email_id' "
            .'FROM `contacts` '
            .'JOIN `con_emails` ON `contacts`.`id`=`con_emails`.`contact_id` '
            .'WHERE `contact_id`=? '
            .'ORDER BY `primary` DESC,`con_emails`.`created` DESC ');
        $sthx->execute(array($contact_id));
        if ($sthx->rowCount()) {
            while ($rowx = $sthx->fetch()) {
                $item = new ListItem($rowx['email'], $this->domain.$child_name.'/'.$child_id.'/'.$contact_id.'/email/'.$rowx['email_id'].'/edit',
                    '/css/fugue/mail.png', 'class="mini-item"');
                if ($rowx['primary']) $item->setRightText('primary');
                $list->addListItem($item);
            }
        } else {
            $list->addText('No email addresses.');
        }
        $list->echoList();
    }
    public function listPhones($contact_id, $child_name, $child_id) {
        $list = new ListObject(true, null, 'class="item-list"');
        $list->addHeading('Phone numbers', 'New', $this->domain.$child_name.'/'.$child_id.'/'.$contact_id.'/phone/new');
        $sthx = $this->db->prepare("SELECT `type`,`primary`,`phone`,`dscr`, "
            ."`con_phones`.`id` AS 'phone_id' "
            .'FROM `contacts` '
            .'JOIN `con_phones` ON `contacts`.`id`=`con_phones`.`contact_id` '
            .'WHERE `contact_id`=? '
            .'ORDER BY `primary` DESC,`con_phones`.`dscr`');
        $sthx->execute(array($contact_id));
        if ($sthx->rowCount()) {
            while ($rowx = $sthx->fetch()) {
                $item = new ListItem($rowx['phone'], $this->domain.$child_name.'/'.$child_id.'/'.$contact_id.'/phone/'.$rowx['phone_id'].'/edit',
                    '/css/fugue/telephone.png', 'class="mini-item"');
                $right = $rowx['type'];
                if ($rowx['primary']) $right .= ' (primary)';
                $item->setRightText($right);
                if ($rowx['dscr']) $item->addSubItem($rowx['dscr']);
                $list->addListItem($item);
            }
        } else {
            $list->addText('No phone numbers.');
        }
        $list->echoList();
    }
    public function listAddresses($contact_id, $child_name, $child_id) {
        $list = new ListObject(true, null, 'class="item-list"');
        $list->addHeading('Addresses', 'New', $this->domain.$child_name.'/'.$child_id.'/'.$contact_id.'/address/new');
        $sthx = $this->db->prepare("SELECT con_addresses.abbr,`primary`,`street`,`type`,`foreign`, "
            ."`con_addresses`.`id` AS 'addr_id', "
            ."CONCAT(`loc_cities`.`name`,', ',`loc_states`.`abbr`,' ',`zip`) AS 'city_state' "
            .'FROM `contacts` '
            .'JOIN `con_addresses` ON `contacts`.`id`=`con_addresses`.`contact_id` '
            .'LEFT OUTER JOIN `loc_cities` ON `con_addresses`.`city_id`=`loc_cities`.`id` '
            .'LEFT OUTER JOIN `loc_states` ON `loc_cities`.`state_id`=`loc_states`.`id` '
            .'WHERE `contact_id`=? '
            .'ORDER BY `con_addresses`.`primary` DESC,`con_addresses`.`created` DESC ');
        $sthx->execute(array($contact_id));
        if ($sthx->rowCount()) {
            while ($rowx = $sthx->fetch()) {

                $str_arr = explode(PHP_EOL, $rowx['street']);
                $item = new ListItem($str_arr[0], $this->domain.$child_name.'/'.$child_id.'/'.$contact_id.'/address/'.$rowx['addr_id'].'/edit',
                    '/css/fugue/card-address.png', 'class="mini-item"');
                for ($idx = 1; $idx < count($str_arr); $idx++) {
                    $item->addSubItem($str_arr[$idx]);
                }
                if ($rowx['city_state']) $item->addSubitem($rowx['city_state']);
                else $item->addSubitem($rowx['foreign']);
                $right = $rowx['abbr'] ? $rowx['abbr'] : $rowx['type'];
                //if ($rowx['primary']) $right = 'Primary: '.$right;
                if ($rowx['primary']) $right .= '  (primary)';
                $item->setRightText($right);
                if ($rowx['ext']) $item->addSubItem($rowx['ext']);
                $list->addListItem($item);
            }
        } else {
            $list->addText('No addresses.');
        }
        $list->echoList();
    }
    public function insertNewCity($state_id, $city_name) {
        $city_id;
        // check if a duplicate city already exists
        $sth = $this->db->prepare("SELECT `id` FROM `loc_cities` "
            .'WHERE `name`=? AND `state_id`=?');
        $sth->execute(array($city_name, $state_id));
        if ($sth->rowCount() > 0) $city_id = false;
        else {
            // if not, insert new city
            $sth = $this->db->prepare('INSERT INTO `loc_cities` '
                .'(`state_id`,`name`) '
                .'VALUES (?,?)');
            $sth->execute(array($state_id, $city_name));
            if ($sth->rowCount()) {
                // get city id
                $city_id = $this->db->lastInsertId();
            } else $city_id = false;
        }
        return $city_id;
    }
    public function insertNewCounty($state_id, $name, $district) {
        $county_id;
        // check if a duplicate county already exists
        $sth = $this->db->prepare("SELECT `id` FROM `loc_counties` "
            ."WHERE `state_id`=? AND (`name`=? OR `district`=?) ");
        $sth->execute(array($state_id, $name, $district));
        if ($sth->rowCount() > 0) $county_id = false;
        else {
            // if not, insert new county
            $sth = $this->db->prepare('INSERT INTO `loc_counties` '
                .'(`state_id`,`name`,`district`) '
                .'VALUES (?,?,?)');
            $sth->execute(array($state_id, $name, $district));
            if ($sth->rowCount()) {
                // get city id
                $county_id = $this->db->lastInsertId();
            } else $county_id = false;
        }
        return $county_id;
    }
    public function updateAddress($form) {
        //$this->callback = $form['callback'];
        if (array_key_exists('delete', $form) && $form['delete'] == 1 && array_key_exists('id', $form)) {
            $this->deleteContactItem('con_addresses', $form['id']);
        } else {
            $success = true;
            $type = $form['type'];
            $street = $form['street'];
            $zip = $form['zip'];
            $abbr = (strlen($form['abbr']) > 0) ? $form['abbr'] : null;
            $primary = $form['primary'] == 1 ? 1 : 0;
            if (strlen($street) > 255 || strlen($street) < 1) $success = false;
            if (strlen($zip) > 10) $success = false;
            $foreign=null; $state; $city=null; 
            if ($form['foreign_loc']) {
                $foreign = $form['foreign'];
                if (strlen($foreign) < 1 || strlen($foreign) > 255) $success = false;
            } else {
                $state = $form['state'];
                $city = $form['city'];
                if ($city == 'new') {
                    $new_city = $form['new_city'];
                    if (strlen($new_city) < 1 || strlen($new_city) > 255) $success = false;
                    else {
                        $city = $this->insertNewCity($state, $new_city);
                        if (!$city) $success = false;
                    }
                } elseif ($city == 0) $success = false;
            }

            if ($success) {
                if ($primary) {
                    // set all other addresses to non-primary
                    $sth = $this->db->prepare('UPDATE `con_addresses` '
                        .'SET `primary`=0 '
                        ."WHERE `contact_id`=?");
                    $sth->execute(array($form['contact_id']));
                }
                if (array_key_exists('id', $form) && $form['id'] != 0) {
                    // edit address
                    $sth = $this->db->prepare('UPDATE `con_addresses` '
                        .'SET `abbr`=?,`primary`=?,`city_id`=?,`type`=?,`street`=?, '
                        .'`zip`=?,`foreign`=? '
                        ."WHERE `id`=?");
                    $sth->execute(array($abbr,$primary,$city,$type,$street,$zip,$foreign,$form['id']));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Address updated.';
                    } else {
                        $_SESSION['msg'] = 'Error: Address not updated.';
                    }
                } else {
                    // new address
                    $sth = $this->db->prepare('INSERT INTO `con_addresses` '
                        .'(`abbr`,`primary`,`contact_id`,`city_id`,`type`,`street`,`zip`,`foreign`) '
                        .'VALUES (?,?,?,?,?,?,?,?)');
                    $sth->execute(array($abbr,$primary,$form['contact_id'],$city,$type,$street,$zip,$foreign));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Address added.';
                    } else {
                        $_SESSION['msg'] = 'Error: Address not added.';
                    }
                } 
            } else $_SESSION['msg'] = 'Error: Form not completed correctly.';
        }
    }
    public function updatePhone($form) {
        //$this->callback = $form['callback'];
        if (array_key_exists('delete', $form) && $form['delete'] == 1 && array_key_exists('id', $form)) {
            $this->deleteContactItem('con_phones', $form['id']);
        } else {
            $success = true;
            $type = $form['type'];
            $phone = $form['phone'];
            $primary = $form['primary'] == 1 ? 1 : 0;
            $dscr = null;
            if (array_key_exists('dscr', $form) && strlen($form['dscr']) > 0 && strlen($form['dscr']) < 256)
                $dscr = $form['dscr'];
            if (strlen($phone) > 20 || strlen($phone) < 1) $success = false;
            if (strlen($ext) > 20) $success = false;

            if ($success) {
                if ($primary) {
                    // set all other addresses to non-primary
                    $sth = $this->db->prepare('UPDATE `con_phones` '
                        .'SET `primary`=0 '
                        ."WHERE `contact_id`=?");
                    $sth->execute(array($form['contact_id']));
                }
                if (array_key_exists('id', $form) && $form['id'] != 0) {
                    // edit address
                    $sth = $this->db->prepare('UPDATE `con_phones` '
                        .'SET `type`=?,`primary`=?,`phone`=?,`dscr`=? '
                        ."WHERE `id`=?");
                    $sth->execute(array($type,$primary,$phone,$dscr,$form['id']));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Phone updated.';
                    } else {
                        $_SESSION['msg'] = 'Error: Phone not updated.';
                    }
                } else {
                    // new address
                    $sth = $this->db->prepare('INSERT INTO `con_phones` '
                        .'(`type`,`primary`,`contact_id`,`phone`,`dscr`) '
                        .'VALUES (?,?,?,?,?)');
                    $sth->execute(array($type,$primary,$form['contact_id'],$phone,$dscr));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Phone number added.';
                    } else {
                        $_SESSION['msg'] = 'Error: Phone not added.';
                    }
                } 
            } else $_SESSION['msg'] = 'Error: Form not completed correctly.';
        }
    }
    public function updateEmail($form) {
        if (array_key_exists('delete', $form) && $form['delete'] == 1 && array_key_exists('id', $form)) {
            $this->deleteContactItem('con_emails', $form['id']);
        } else {
            $success = true;
            $email = $form['email'];
            $alert = $form['alert'] == 1 ? 1 : 0;
            $primary = $form['primary'] == 1 ? 1 : 0;
            if (strlen($email) > 255 || strlen($email) < 1) $success = false;

            if ($success) {
                if ($primary) {
                    // set all other addresses to non-primary
                    $sth = $this->db->prepare('UPDATE `con_emails` '
                        .'SET `primary`=0 '
                        ."WHERE `contact_id`=?");
                    $sth->execute(array($form['contact_id']));
                }
                if (array_key_exists('id', $form) && $form['id'] != 0) {
                    // edit address
                    $sth = $this->db->prepare('UPDATE `con_emails` '
                        .'SET `primary`=?,`alert`=?,`email`=? '
                        ."WHERE `id`=?");
                    $sth->execute(array($primary,$alert,$email,$form['id']));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Email updated.';
                    } else {
                        $_SESSION['msg'] = 'Error: Email not updated.';
                    }
                } else {
                    // new address
                    $sth = $this->db->prepare('INSERT INTO `con_emails` '
                        .'(`primary`,`alert`,`contact_id`,`email`) '
                        .'VALUES (?,?,?,?)');
                    $sth->execute(array($primary,$alert,$form['contact_id'],$email));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Email address added.';
                    } else {
                        $_SESSION['msg'] = 'Error: Email not added.';
                    }
                } 
            } else $_SESSION['msg'] = 'Error: Form not completed correctly.';
        }
    }
    public function updateContactInfo($form) {
        // doesn't set callback, called from a child method
        $success = true;
        $id = (array_key_exists('contact_id', $form) && strlen($form['contact_id']) > 0) ? $form['contact_id'] : null;
        $sex = (array_key_exists('sex', $form) && ($form['sex'] == 'm' || $form['sex'] == 'f')) ? $form['sex'] : null;
        $name = $form['name'];
        if (strlen($name) < 1) $success = false;
        $first_name = (array_key_exists('first_name', $form) && strlen($form['first_name']) > 0) ? $form['first_name'] : null;
        $middle_name = (array_key_exists('middle_name', $form) && strlen($form['middle_name']) > 0) ? $form['middle_name'] : null;
        $suffix = (array_key_exists('suffix', $form) && strlen($form['suffix']) > 0) ? $form['suffix'] : null;
        $dob=null; 
        if (array_key_exists('dob', $form) && strlen($form['dob']) > 0) {
            $dob_date = DateTime::createFromFormat('m-d-Y', $form['dob']);
            $dob = $dob_date->format('Y-m-d');
        }
        $ssn=null;
        if (array_key_exists('ssn', $form) && strlen($form['ssn']) > 0) {
            if (strlen($form['ssn']) < 10) $ssn = $form['ssn'];
            else $success = false;
        }
        $aka = (array_key_exists('aka', $form) && strlen($form['aka']) > 0) ? $form['aka'] : null;
        $dba = (array_key_exists('dba', $form) && strlen($form['dba']) > 0) ? $form['dba'] : null;
        if ($success) {
            if ($id) {
                // edit contact, return true on success
                $sth = $this->db->prepare('UPDATE `contacts` '
                    ."SET `sex`=?,`name`=?,`first_name`=?,`middle_name`=?,"
                    ."`suffix`=?,`dob`=?,`ssn`=?,`aka`=?,`dba`=? WHERE `id`=?");
                $sth->execute(array($sex, $name, $first_name, $middle_name, 
                    $suffix, $dob, $ssn, $aka, $dba, $id));
            } else {
                // new contact, return new contact id on success
                $sth = $this->db->prepare('INSERT INTO `contacts` '
                    .'(`sex`,`name`,`first_name`,`middle_name`,`suffix`,`dob`,`ssn`,`aka`,`dba`) VALUES '
                    .'(?,?,?,?,?,?,?,?,?)');
                $sth->execute(array($sex, $name, $first_name, $middle_name, $suffix, $dob, $ssn, $aka, $dba));
                if ($sth->rowCount()) {
                    // get id
                    $success = $this->db->lastInsertId();
                } else $success = false;
            }
        }
        return $success;
    }
    public function deleteContact($contact_id) {
        $sth = $this->db->prepare('DELETE FROM `contacts` '
            .'WHERE `id`=?');
        $sth->execute(array($contact_id));
        if ($sth->rowCount())
            return true;
        else 
            return false;
    }
}


?>
