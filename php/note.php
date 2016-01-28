<?php
require_once('folder_item.php');

class Note extends FolderItem {
    function __construct() {
        $this->table = 'notes';
        $this->title = 'New note';
        parent::__construct();
    }
    public function processUpdate() {
        $updated = false;
        if (isset($_POST['note']) && $this->userViewEdit($_POST['note']['folder_id']) && $this->editable) {
            $updated = true;
            $this->updateNote($_POST['note']);
        } 

        if ($updated) {
            session_write_close();
            header("Location: ".$this->callback);	
            die();
        } 
    }
    public function echoToolbar() {
        echo '<div class="ui-grid-a">'
            .'<div class="ui-block-a">'
            .'<a class="ui-icon-custom" data-inset="true" data-mini="true" data-role="button" href="'.$this->parent_link.'" data-icon="'
            .$this->parent_icon.'">'
            .$this->parent_name.'</a>'
            .'</div><div class="ui-block-b">';
        if ($this->page_type == 'view') {
            echo '<a href="'.$this->domain.'note/'.$this->id.'/edit" data-role="button" data-icon="pencil" data-inline="true" '
                .'data-iconpos="right" data-mini="true" class="ui-icon-custom" style="float:right">Edit note</a>';
        } elseif ($this->page_type == 'edit') {
            echo '<a href="'.$this->domain.'note/'.$this->id.'" data-role="button" data-icon="note" data-inline="true" '
                .'data-iconpos="right" data-mini="true" class="ui-icon-custom" style="float:right">View note</a>';
        }
        echo '</div></div>';
    }
    public function updateNote($form) {
        $this->callback = $this->domain.'folder/'.$form['folder_id'];

        // deleted?
        if (array_key_exists('delete', $form) && $form['delete'] == 1) {
            $this->deleteItem('notes', $form['id'], $form['folder_id']);
        } else {
            $validated = true;
            $name; $body;

            // move
            $move = $form['folder_id'];
            if (array_key_exists('move', $form) && $form['move'] > 0 && $form['move'] != $form['folder_id']) {
                $move = $form['move'];
                $this->updateSubitems($form['folder_id'], false);
                $this->updateSubitems($move);
            }

            // name & body
            if (array_key_exists('body', $form) && strlen($form['body']) > 0) {
                $body = $form['body'];
            } elseif (array_key_exists('bodysource', $form) && strlen($form['bodysource']) > 0) {
                $body = $form['bodysource'];
                //$newline = strpos($body, PHP_EOL);
                //error_log("body=\n".$body);
                $body = str_replace("\r", '', $body);
                $body = str_replace("\n", '<br>', $body);
                //$newline = strpos($body, PHP_EOL);
                //error_log('newline='.$newline);
            } else $validated = false; 

            // date
            $date;
            if (array_key_exists('date', $form) && strlen($form['date']) > 0) {
                if ($date = DateTime::createFromFormat('n#j#Y', $form['date'])) {
                    $date = $date->format('Y-m-d');
                } else $validated = false;
            } else $validated = false; 

            // update database with validated values
            if ($validated) {
                // create note name
                $name = str_replace('<br>', PHP_EOL, $body);
                $name = strip_tags($name);
                $eol = strpos($name, PHP_EOL);
                $name_end = ($eol !== false && $eol < 256) ? $eol : 256;
                $name = substr($name, 0, $name_end);

                if (array_key_exists('id', $form) && $form['id'] !== '') {

                    // update existing note
                    $sth = $this->db->prepare('UPDATE `notes` '
                        ."SET `date`=?,`folder_id`=?,`name`=?,`body`=? WHERE `id`=?");
                    $sth->execute(array($date,$move,$name,$body,$form['id']));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Note updated.';
                    } else {
                        $_SESSION['msg'] = 'Error: note not updated.';
                        $this->callback = $this->domain.'note/'.$form['id'].'/edit';
                    }
                } else {
                    // create new note
                    $sth = $this->db->prepare('INSERT INTO `notes` '
                        .'(`date`,`folder_id`,`name`,`body`,`created_by`) VALUES '
                        ."(?,?,?,?,?)");
                    $sth->execute(array($date,$form['folder_id'], $name, $body, $this->emp_id));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Note created.';
                        $this->updateSubitems($form['folder_id']);
                    } else {
                        $_SESSION['msg'] = 'Error: note not created.';
                    }
                }
            } else {
                $_SESSION['msg'] = 'Error: input validation failed.';
                if ($form['id']) $this->callback = $this->domain.'note/'.$form['id'].'/edit';
                else $this->callback = $this->domain.'folder/'.$form['folder_id'];
            }
        }
    }
    public function echoNote() {

        $sth = $this->db->prepare("SELECT `body` "
            .'FROM `notes` WHERE `id`=?');
        $sth->execute(array($this->id));
        if ($row = $sth->fetch()) {
            $id = $this->id;
            $body = $row['body'];
            echo $body;
        } 
    }
    public function echoNoteEdit() {
        $success = true;
        $today = new DateTime();
        $date = $today->format('n-j-Y');
        $body;
        if ($this->page_type != 'new') {
            $sth = $this->db->prepare("SELECT `body`, "
                ."DATE_FORMAT(`date`, '%c-%e-%Y') AS 'date_format' "
                .'FROM `notes` WHERE `id`=?');
            $sth->execute(array($this->id));
            if ($row = $sth->fetch()) {
                $id = $this->id;
                $body = $row['body'];
                $date = $row['date_format'];
            } else $success = false;
        } 

        if ($success) {
            $form = new Form('note');

            // cant directly comment out below regex
            if (false) {
                if ($this->platform == 'and') {
                    $nl = preg_replace('#<br\s*/?>#i', "\r\n", $body);
                    $form->addTextArea('','bodysource', true, $nl);
                } else {
                    $form->addTextArea('','body', true, $body);
                }
            }
            // end comment out
            $form->addTextArea('','body', true, $body);

            $form->addDate('Date:', 'date', true, true, $date);
            if ($this->page_type == 'edit' 
                && ($this->admin 
                || ($this->emp_id == $this->folder_emp_id) 
                || ($this->office_admin 
                && $this->folder_office_id == $this->office_id))) {
                $move_arr = $this->moveFolderArr($this->parent_folder_id);
                if (count($move_arr) > 1) {
                    //$form->addHeading('Move');
                    $form->addSelect('Move to:','move', $move_arr);
                }
                $form->addDelete();
            }
            $form->addHidden('id', $id);
            $form->addHidden('folder_id', $this->parent_folder_id);
            $form->echoForm();

            // add link popup

            echo '<div data-role="popup" id="link-popup" class="ui-content">'
                .'<form>'
                .'<h4>Create link</h4>'
                .'<label for="link-url">URL</label>'
                .'<input name="link-url" id="link-url" type="text">'
                .'<button id="link-ok">OK</button>'
                .'</form>'
                .'</div>';
        } 
    }
    public function echoContent() {
        $id; $body;

        echo '<div data-role="content">';

        if ($this->page_type == 'view') {
            $this->echoNote();
        } else {
            $this->echoNoteEdit();
        }

        echo '</div>';
    }
}

$page = new Note();
?>
