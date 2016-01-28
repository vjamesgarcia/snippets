<?php
require_once('page.php');

abstract class FolderItem extends Page {
    public $id;
    public $table;
    public $parent_folder_id;
    public $parent_name;
    public $parent_icon;
    public $parent_link;
    public $editable;
    public $folder_client_id;
    public $folder_emp_id;
    public $folder_office_id;
    public $folder_court_id;
    public $folder_type_id;
    public $folder_state_id;
    abstract function processUpdate();
    function __construct() { parent::__construct(); }
    public function processArgs() {
        $this->processUpdate();
        $success = true;
        if (array_key_exists(0, $this->args)) {
            if (is_numeric($this->args[0])) {
                $this->id = $this->args[0];

                // select name & parent
                $sth = $this->db->prepare('SELECT `folder_id`,`name` '
                    .'FROM `'.$this->table.'` WHERE `id`=?');
                $sth->execute(array($this->id));
                if ($row = $sth->fetch()) {
                    $this->parent_folder_id = $row['folder_id'];
                    $this->title = $row['name'];
                } else $success = false;

                if ($this->table == 'folders') {
                    // balance
                    $sthx = $this->db->prepare('SELECT docket,fee_type,balance FROM folders WHERE id=?');
                    $sthx->execute(array($this->id));
                    $rowx = $sthx->fetch();
                    if ($rowx['docket']) {
                        $this->title = $rowx['docket'] . ' ' . $this->title;
                    }
                    if ($rowx['fee_type']) {
                        if ($rowx['fee_type'] == 'appt') {
                            $this->title .= ' (Appointed)';
                        } else {
                            if ($rowx['balance']) {
                                $this->title .= ' ($'.$rowx['balance'].')';
                            } else {
                                $this->title .= ' (No balance)';
                            }
                        }
                    }
                }

                if (array_key_exists(1, $this->args)) {
                    if ($this->args[1] == 'edit') {
                        // edit 
                        $this->title = 'Edit: '.$this->title;
                        $this->page_type = 'edit';
                        $this->page_id = $this->table.'-'.$this->id.'-edit';
                    } 
                } else {
                    // view 
                    $this->page_type = 'view';
                    $this->page_id = $this->table.'-'.$this->id;
                }
            } elseif ($this->args[0] == 'new' && array_key_exists(1, $this->args) && is_numeric($this->args[1])) {
                // new item
                $this->page_type = 'new';
                $this->page_id = $this->table.'-new';
                $this->parent_folder_id = $this->args[1];
                if (array_key_exists(2, $this->args) && strlen($this->args[2]) > 0 && !is_numeric($this->args[2])) {
                    $this->title = 'New '.$this->args[2];
                }
            } else $success = false; 

			$folder_id; // either parent folder id or current folder id if parent is null
			if ($this->parent_folder_id) {
				$folder_id = $this->parent_folder_id;
				$this->parent_link = $this->domain.'folder/'.$this->parent_folder_id;
			} else {
				$folder_id = $this->id;
			}

			$success = $this->userViewEdit($folder_id);
			if ($this->page_type != 'view' && !$this->editable) $success = false;
        } else $success = false; 
        return $success;
    }
    public function createFilePath($folder_id, $name) {
        $path = "files";
        if ( ! is_dir($path)) mkdir($path);
        if ($this->folder_client_id) {
            $path .= '/cli';
            if ( ! is_dir($path)) { mkdir($path); }
            $path .= '/'.$this->folder_client_id;
        } elseif ($this->folder_court_id) {
            $path .= '/crt';
            if ( ! is_dir($path)) { mkdir($path); }
            $path .= '/'.$this->folder_court_id;
        } elseif ($this->folder_office_id) {
            $path .= '/ofc';
            if ( ! is_dir($path)) { mkdir($path); }
            $path .= '/'.$this->folder_office_id;
        } elseif ($this->folder_emp_id) {
            $path .= '/emp';
            if ( ! is_dir($path)) { mkdir($path); }
            $path .= '/'.$this->folder_emp_id;
		} else {
			// library file
            $path .= '/lib';
            if ( ! is_dir($path)) { mkdir($path); }
			$path .= '/';
			if ($this->folder_state_id) $path .= $this->folder_state_id;
			else $path .= '0';
		}
        if ( ! is_dir($path)) mkdir($path);
        $path .= '/'.$folder_id;
        if ( ! is_dir($path)) mkdir($path);

        $name = str_replace("&",'\&',str_replace(" ",'_',addslashes($name))); 
        $target_path = $path .'/'. $name;
        $name_before_ext = $name;
        $ext = '';
        $last_dot = strrpos($name, '.');
        if ($last_dot !== false) {
            $name_before_ext = substr($name, 0, $last_dot);
            $ext = substr($name, $last_dot);
        }
        $safe_count = 0;
        while ($safe_count < 100000 && file_exists($target_path)) {
            $safe_count++;
            // increase digit after name
            if (preg_match("/_(\d+)$/", $name_before_ext)) {
                $name_before_ext = preg_replace("/_(\d+)$/e", '"_" . ("$1" + 1)', $name_before_ext);
            } else {
                $name_before_ext .= '_1';
            }

            $target_path = $path .'/'. $name_before_ext . $ext;
        }
        return $target_path;
    }
    public function deleteItem($table, $id, $folder_id) {
        $delete = true;
        if ($table == 'folders') {
            // dont delete if this folder contains subitems
            $sth = $this->db->prepare("SELECT `subitems` FROM `folders` WHERE `id`=?");
            $sth->execute(array($id));
            if ($row = $sth->fetch()) {
                if ($row['subitems'] > 0) {
                    $_SESSION['msg'] = 'Folder contains items and cannot be deleted.';
                    $delete = false;
                }
            } else $delete = false;

            // dont delete if this is a root folder 
            if (!$folder_id) {
                $delete = false;
                $_SESSION['msg'] = 'This folder cannot be deleted.';
            }
        }

        // delete item
        if ($delete) {
            $sth = $this->db->prepare('DELETE FROM `'.$table.'` '
                .'WHERE `id`=?');
            $sth->execute(array($id));
            if ($sth->rowCount()) {
                $_SESSION['msg'] = 'Item deleted.';
                // update parent folder's children row
                $this->updateSubitems($folder_id, false);
            } else {
                $delete = false;
                $_SESSION['msg'] = 'Error: item not deleted.';
            }
        } 
        return $delete;
    }
    public function moveFolderArr($parent_folder_id, $folder_id=null) {
        $folders = array(0=>'');

        // parent folder 
        $sth = $this->db->prepare('SELECT folders_b.`id`,folders_b.`name` '
            .'FROM `folders` AS folders_a '
            .'JOIN `folders` AS folders_b ON folders_a.`folder_id`=folders_b.`id` '
            .'WHERE folders_a.`id`=? ');
        $sth->execute(array($parent_folder_id));
        if ($row = $sth->fetch()) {
            $folders[$row['id']] = '&#9650;&nbsp;'.$row['name'];
        }

        // sibling folders
        $sth = $this->db->prepare('SELECT `id`,`name` '
            .'FROM `folders` WHERE `folder_id`=? ORDER BY `name`');
        $sth->execute(array($parent_folder_id));
        while ($row = $sth->fetch()) {
            if ($folder_id === null || $folder_id != $row['id']) {
                $folders[$row['id']] = '&nbsp;-&nbsp;'.$row['name'];
            }
        }

        return $folders;
    }
    public function userViewEdit($folder_id) {
        $success = true;
        $this->editable = false;

        $sth = $this->db->prepare('SELECT `folders`.`name`,`client_id`,`office_id`,`emp_id`,`court_id`,`type_id`,`locked`,`hidden`,'
			."`state_id`,`loc_states`.`abbr` "
            .'FROM `folders` LEFT OUTER JOIN `loc_states` ON `folders`.`state_id`=`loc_states`.`id` WHERE `folders`.`id`=?');
        $sth->execute(array($folder_id));
        if ($row = $sth->fetch()) {
            $this->parent_name = $row['name'];
            $this->parent_icon = 'folder';
            $this->folder_client_id = $row['client_id'];
            $this->folder_emp_id = $row['emp_id'];
            $this->folder_office_id = $row['office_id'];
            $this->folder_court_id = $row['court_id'];
            $this->folder_type_id = $row['type_id'];
            $this->folder_state_id = $row['state_id'];
            if ($row['client_id']) {
                // case folder
                // all users can view, only qualified can edit
                if ($row['locked']) {
                    if ($this->admin || 
                        ($row['emp_id'] == $this->emp_id) || 
                        ($this->office_admin && ($row['office_id'] == $this->office_id))) 
                        $this->editable = true;
                } else {
                    if ($this->admin || 
                        ($row['emp_id'] == $this->emp_id) || 
                        ($row['office_id'] == $this->office_id)) 
                        $this->editable = true;
                } 

                if (!$this->parent_folder_id) {
                    // case folder root
                    $sthx = $this->db->prepare("SELECT `contacts`.`name` AS 'client_name', "
                        ."CONCAT_WS(' ',`first_name`,`middle_name`,`suffix`) AS first_name "
                        .'FROM `clients` '
                        .'JOIN `contacts` ON `clients`.`contact_id`=`contacts`.`id` '
                        .'WHERE `clients`.`id`=?');
                    $sthx->execute(array($row['client_id']));
                    if ($rowx = $sthx->fetch()) {
                        $this->parent_name = $rowx['client_name'];
                        if ($rowx['first_name']) $this->parent_name .= ', '.$rowx['first_name'];
                        $this->parent_link = $this->domain.'client/'.$row['client_id'];
                        $this->parent_icon = 'client';
                    } else $success = false;
                } 
            } elseif ($row['court_id']) {
                // court folder
                // only admin & office admin can edit court folders
                if ($this->admin || $this->office_admin) $this->editable = true;

                if (!$this->parent_folder_id) {
                    $sthx = $this->db->prepare('SELECT `name` '
                        .'FROM `courts` '
                        .'JOIN `contacts` ON `courts`.`contact_id`=`contacts`.`id` '
                        .'WHERE `courts`.`id`=?');
                    $sthx->execute(array($row['court_id']));
                    if ($rowx = $sthx->fetch()) {
                        $this->parent_name = $rowx['name'];
                        $this->parent_link = $this->domain.'court/'.$row['court_id'];
                        $this->parent_icon = 'court';
                    } else $success = false;
                }
            } elseif ($row['emp_id']) {
                // employee folder
                // this employee folder can only be seen by this emp
                if ($this->admin || $row['emp_id'] == $this->emp_id) {
                    $this->editable = true;
                    if (!$this->parent_folder_id) {
                        $sthx = $this->db->prepare("SELECT CONCAT_WS(' ',`first_name`,`middle_name`,`contacts`.`name`,`suffix`) "
                            ."AS 'emp_name' "
                            .'FROM `employees` '
                            .'JOIN `contacts` ON `employees`.`contact_id`=`contacts`.`id` '
                            .'WHERE `employees`.`id`=?');
                        $sthx->execute(array($row['emp_id']));
                        if ($rowx = $sthx->fetch()) {
                            $this->parent_name = $rowx['emp_name'];
                            $this->parent_link = $this->domain.'employee/'.$row['emp_id'];
                            $this->parent_icon = 'employee';
                        }
                    }
                } else $success = false;
            } elseif ($row['office_id']) {
                // office folder
                if ($row['hidden']) {
                    // only admins can see/edit it
                    if ($this->admin || ($this->office_admin && ($row['office_id'] == $this->office_id)))
                        $this->editable = true;
                    else 
                        $success = false;
                } elseif ($row['locked']) {
                    // only admins can edit it, office emps can see it
                    if ($this->admin || $row['office_id'] == $this->office_id) {
                        if ($this->admin || ($this->office_admin && ($row['office_id'] == $this->office_id)))
                            $this->editable = true;
                    } else $success = false;
                } else {
                    // both admins & office emps can see/edit
                    if ($this->admin || $row['office_id'] == $this->office_id) {
                        $this->editable = true;

                    } else $success = false;
                }
                if ($success && !$this->parent_folder_id) {
                    $sthx = $this->db->prepare("SELECT `name` FROM `offices` "
                        .'WHERE `offices`.`id`=?');
                    $sthx->execute(array($row['office_id']));
                    if ($rowx = $sthx->fetch()) {
                        $this->parent_name = $rowx['name'];
                        $this->parent_link = $this->domain.'office/'.$row['office_id'];
                        $this->parent_icon = 'office';
                    }
                }
            } else {
                if ($this->admin || $this->office_admin || $this->atty) 
                    $this->editable = true;

                if (!$this->parent_folder_id) {
					$this->parent_icon = 'office';
					$this->parent_name = $this->office_name;
					$this->parent_link = $this->domain.'office/'.$this->office_id;
				} /*else {
					if ($row['state_id']) $this->parent_name .= ' ('.$row['abbr'].')';
					
                }*/
            } 
        } else $success = false;
        return $success;
    }
    public function echoToolbar() {
        echo '<div class="ui-grid-a">'
            .'<div class="ui-block-a">'
            .'<a class="ui-icon-custom back-btn" data-inset="true" data-mini="true" data-role="button" href="'.$this->parent_link.'" data-icon="'
            .$this->parent_icon.'">'
            .$this->parent_name.'</a>'
            .'</div><div class="ui-block-b">'
            .'</div></div>';
    }
}


?>
