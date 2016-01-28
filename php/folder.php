<?php
require_once('folder_item.php');

class Folder extends FolderItem {
    function __construct() {
        $this->table = 'folders';
        $this->title = 'New folder';
        $this->icon = '/css/fugue/folder-open.png';
        parent::__construct();
    }
    public function processUpdate() {
        $updated = false;
        if (isset($_POST['folder']) && $this->userViewEdit($_POST['folder']['folder_id']) && $this->editable) {
            $updated = true;
            $this->updateFolder($_POST['folder']);
        } 

        if ($updated) {
            session_write_close();
            header("Location: ".$this->callback);	
            die();
        } 
    }
    public function updateFolder($form) {
        $this->callback = $this->domain.'folder/'.$form['id'];

        // deleted?
        if (array_key_exists('delete', $form) && $form['delete'] == 1) {
            if ($this->deleteItem('folders', $form['id'], $form['folder_id'])) {
                // callback is parent folder
                $this->callback = $this->domain.'folder/'.$form['folder_id'];
            } 
        } else {
            $validated = true;
            $name; 

            // move
            $move = $form['folder_id'];
            if (array_key_exists('move', $form) && $form['move'] > 0 && $form['move'] != $form['folder_id']) {
                $move = $form['move'];
                $this->updateSubitems($form['folder_id'], false);
                $this->updateSubitems($move);
            }

            if (array_key_exists('name', $form) && strlen($form['name']) > 0) {
                $name = $form['name'];
            } else $validated = false; 

            // update database with validated values
            if ($validated) {
                if (array_key_exists('id', $form) && $form['id'] !== '') {
                    // update existing folder
                    $sth = $this->db->prepare('UPDATE `folders` '
                        .'SET `name`=?,`folder_id`=? WHERE `id`=?');
                    $sth->execute(array($name, $move, $form['id']));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'folder updated.';
                    } else $_SESSION['msg'] = 'Error: folder not updated.';
                } else {
                    $this->callback = $this->domain.'folder/'.$form['folder_id'];
                    // create new folder
                    $sth = $this->db->prepare('INSERT INTO `folders` '
                        .'(`client_id`,`office_id`,`court_id`,`emp_id`,`folder_id`,`name`,`state_id`) VALUES '
                        .'(?,?,?,?,?,?,?)');
                    $sth->execute(array($this->folder_client_id, $this->folder_office_id, $this->folder_court_id, 
                        $this->folder_emp_id, $form['folder_id'], $name, $this->folder_state_id));
                    if ($sth->rowCount()) {
                        $_SESSION['msg'] = 'Folder created.';
                        $this->updateSubitems($form['folder_id']);
                    } else {
                        $_SESSION['msg'] = 'Error: folder not created.';
                    }
                }
            } else $_SESSION['msg'] = 'Error: input validation failed.';
        }
    }
    public function echoToolbar() {
        if ($this->page_type == 'view') {
            echo '<a href="#new-popup" data-rel="popup" data-role="button" data-icon="new" data-inline="true" '
                .'data-iconpos="right" data-mini="true" class="ui-icon-custom" style="float:right"><u>N</u>ew</a>';
            //$from = $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
            echo '<div data-role="popup" id="new-popup">'
                .'<ul data-role="listview" data-inset="true">'
                .'<li data-role="list-divider">New</li>'
                .'<li class="ui-icon-custom" data-icon="folder"><a href="'.$this->domain.'folder/new/'.$this->id.'"><u>F</u>older</a></li>'
                //.'<li class="ui-icon-custom" data-icon="event"><a href="'.$this->domain.'event/new/'.$this->id.'?from='.$from.'">Event</a></li>'
                .'<li class="ui-icon-custom" data-icon="calendar"><a href="'.$this->domain.'event/new/'.$this->id.'"><u>E</u>vent</a></li>'
                .'<li class="ui-icon-custom" data-icon="task"><a href="'.$this->domain.'task/new/'.$this->id.'"><u>T</u>ask</a></li>'
                .'<li class="ui-icon-custom" data-icon="note"><a href="'.$this->domain.'note/new/'.$this->id.'"><u>N</u>ote</a></li>'
                .'<li class="ui-icon-custom" data-icon="file"><a href="'.$this->domain.'file/new/'.$this->id.'"><u>U</u>pload file</a></li>';
            if ($this->folder_client_id) {
                if ($this->admin) {
                    echo '<li class="ui-icon-custom" data-icon="doc"><a href="'.$this->domain.'doc/new/'.$this->id.'"><u>D</u>ocument</a></li>';
                }
                echo '<li class="ui-icon-custom" data-icon="payment"><a href="'.$this->domain.'bill/new/'.$this->id.'/payment"><u>P</u>ayment</a></li>'
                    .'<li class="ui-icon-custom" data-icon="fee"><a href="'.$this->domain.'bill/new/'.$this->id.'/fee"><u>C</u>ase Fee</a></li>';
            }
            echo '</ul></div>';

            echo '<div class="ui-grid-solo">'
                .'<div class="ui-block-a">';

            echo '<a class="back-btn" data-role="button" data-inline="true" data-mini="true" href="'.$this->parent_link
                //.'" class="ui-icon-custom" data-icon="'.$this->parent_icon.'">'
                .'" data-icon="back">'
                .$this->parent_name.'</a>';

            if ($this->parent_folder_id) {
                echo '<a href="'.$this->domain.'folder/'.$this->id.'/edit" data-role="button" data-icon="folder" data-inline="true" '
                    .'data-iconpos="right" data-mini="true" class="ui-icon-custom" style="float:right">Edit folder</a>';
            } elseif ($this->folder_client_id) {
                echo '<a href="'.$this->domain.'case/'.$this->id.'/edit" data-role="button" data-icon="folder" data-inline="true" '
                    .'data-iconpos="right" data-mini="true" class="ui-icon-custom" style="float:right">Case info</a>';
            }

            echo '</div></div>';
        }
    }
    public function echoContent() {

        if ($this->page_type == 'view') {
            echo '<div data-role="content" class="nopad">';
            //$this->echoFolderContents();
            $this->echoFolderColumns();
        } else {
            echo '<div data-role="content">';
            $this->echoFolderEdit();
        }

        echo ' </div>';
    }
    public function echoFolderEdit() {
        $success = true;
        $name; $id;

        if ($this->page_type == 'edit') {
            $sth = $this->db->prepare("SELECT `name` "
                .'FROM `folders` WHERE `id`=?');
            $sth->execute(array($this->id));
            if ($row = $sth->fetch()) {
                $id = $this->id;
                $name = $row['name'];
            } else $success = false;
        }

        if ($success) {
            $form = new Form('folder');
            $form->addText('Folder name:','name', true, $name);
            $form->addHidden('id', $id);
            $form->addHidden('folder_id', $this->parent_folder_id);
            if ($this->page_type == 'edit' && ($this->admin || ($this->emp_id == $this->folder_emp_id) 
                || ($this->office_admin && $this->folder_office_id == $this->office_id))) {
                $move_arr = $this->moveFolderArr($this->parent_folder_id, $this->id);
                if (count($move_arr) > 1) {
                    //$form->addHeading('Move');
                    $form->addSelect('Move to:','move', $move_arr);
                }
                $form->addDelete();
            }
            $form->echoForm();
        } 
    }
    public function echoFolderColumns() {
        echo '<div class="ui-grid-a"><div class="ui-block-a folder-left">';

        $subitems = false;
        $this->db->exec("SET time_zone='-5:00'");

        // folders
        $sth = $this->db->prepare('SELECT id,subitems,name FROM folders WHERE folder_id=? ORDER BY name');
        $sth->execute(array($this->id));
        $count = $sth->rowCount();
        if ($count > 0) {
            $subitems = true;
            //$collapsed = $count > 9 ? 'true' : 'false';
            $collapsed = 'false';
            echo '<div data-role="collapsible" data-theme="a" data-collapsed="'.$collapsed.'" data-inset="false" data-mini="true" data-icon="false">'
                .'<h4 class="nomargin">Folders ('.$count.')</h4>';
            $list = new ListObject(false, null, ' class="item-list folder-list nomargin" data-split-theme="d" data-theme="d"');
            while ($row = $sth->fetch()) {
                $item = new ListItem($row['name'], $this->domain.'folder/'.$row['id'], 
                    '/css/fugue/folder-open.png', 'data-icon="false" class="mini-item" data-mini="true"');
                if ($row['subitems'] > 0) $item->setRightText($row['subitems']);
                //$item->unsetBold();
                $list->addListItem($item);
            }
            $list->echoList();
            echo '</div>';
        }

        // files 
        $sth = $this->db->prepare("SELECT `id`,`name`, "
            ."DATE_FORMAT(`date`, '%b %e') AS 'date_format' "
            .'FROM `files` WHERE `folder_id`=? '
            //.'order by `date` DESC,`name`');
            .'order by `name`');
        $sth->execute(array($this->id));
        $count = $sth->rowCount();
        if ($count > 0) {
            $subitems = true;
            //$collapsed = $count > 9 ? 'true' : 'false';
            $collapsed = 'false';
            echo '<div data-role="collapsible" data-theme="a" data-collapsed="'
                .$collapsed
                .'" data-inset="false" data-mini="true" data-icon="false">'
                .'<h4 class="nomargin">Files ('.$count.')</h4>';
            $list = new ListObject(false, null, 
                ' class="item-list folder-list nomargin" data-split-theme="d" '
                .'data-theme="d"');
            while ($row = $sth->fetch()) {
                $item = new ListItem($row['name'], 
                    $this->domain.'file/'.$row['id'], 
                    '/css/fugue/disk-black.png', 
                    'data-icon="false" class="mini-item" data-mini="true"');
                $item->setRightText($row['date_format']);
                $list->addListItem($item);
            }
            $list->echoList();
            echo '</div>';
        }

        // notes 
        $sth = $this->db->prepare("SELECT `id`,`name`, "
            ."DATE_FORMAT(`date`, '%b %e') AS 'date_format' "
            .'FROM `notes` WHERE `folder_id`=? '
            //.'order by `date` DESC,`name`');
            .'order by `name`');
        $sth->execute(array($this->id));
        $count = $sth->rowCount();
        if ($count > 0) {
            $subitems = true;
            //$collapsed = $count > 9 ? 'true' : 'false';
            $collapsed = 'false';
            echo '<div data-role="collapsible" data-theme="a" data-collapsed="'
                .$collapsed
                .'" data-inset="false" data-mini="true" data-icon="false">'
                .'<h4 class="nomargin">Notes ('.$count.')</h4>';
            $list = new ListObject(false, null, 
                ' class="item-list folder-list nomargin" data-split-theme="d" '
                .'data-theme="d"');
            while ($row = $sth->fetch()) {
                $item = new ListItem($row['name'], 
                    $this->domain.'note/'.$row['id'], 
                    '/css/fugue/sticky-note-pin.png', 
                    'data-icon="false" class="mini-item" data-mini="true"');
                $item->setRightText($row['date_format']);
                $list->addListItem($item);
            }
            $list->echoList();
            echo '</div>';
        }

        echo '</div><div class="ui-block-b folder-right">';

        //$list->addHeading('Timeline');
        $today = new DateTime();
        $today_ts = $today->format('U');
        $today_time = strtotime($today->format('Y-m-d'));
        $now_time = time();

        // events 
        $sth = $this->db->prepare("SELECT `id`,`name`,`repeat_type`,`date`, "
            ."DATE_FORMAT(`date`, '%b %e') AS 'date_format', "
            ."LOWER(TIME_FORMAT(`time`, '%l:%i%p')) AS 'time_format', "
            ."`ts` "
            .'FROM `events` WHERE `folder_id`=? '
            .'order by `ts` DESC');
        $sth->execute(array($this->id));
        $count = $sth->rowCount();
        if ($count > 0) {
            $subitems = true;
            //$collapsed = $count > 9 ? 'true' : 'false';
            $collapsed = 'false';
            echo '<div data-role="collapsible" data-theme="a" data-collapsed="'
                .$collapsed
                .'" data-inset="false" data-mini="true" data-icon="false">'
                .'<h4 class="nomargin">Events ('.$count.')</h4>';
            $list = new ListObject(false, null, 
                ' class="item-list folder-list nomargin" data-split-theme="d" '
                .'data-theme="d"');
            while ($row = $sth->fetch()) {
                $right = $row['date_format'];
                $icon = '/css/fugue/calendar.png';
                if ($row['repeat_type']) {
                    $icon = '/css/fugue/calendar-relation.png';
                }
                $class = 'mini-item';
                $class .= ($now_time > $row['ts']) ? ' folder-complete' : ''; 
                $right .= $row['time_format'] ? ' '.$row['time_format'] : '';
                $item = new ListItem($row['name'], 
                    $this->domain.'event/'.$row['id'].'/edit', $icon, 
                    'data-icon="false" class="'.$class.'" data-mini="true"');
                $item->setRightText($right);
                $list->addListItem($item);
            }
            $list->echoList();
            echo '</div>';
        }

        // tasks 
        $sth = $this->db->prepare("SELECT `id` "
            .'FROM `tasks` WHERE `folder_id`=? ');
        $sth->execute(array($this->id));
        $count = $sth->rowCount();
        if ($count > 0) {
            $subitems = true;
            //$collapsed = $count > 9 ? 'true' : 'false';
            $collapsed = 'false';
            echo '<div data-role="collapsible" data-theme="a" data-collapsed="'
                .$collapsed
                .'" data-inset="false" data-mini="true" "
                ."data-icon="false"><h4 class="nomargin">Tasks ('.$count.')</h4>';
            $list = new ListObject(false, null, 
                ' class="item-list folder-list nomargin" '
                .'data-split-theme="d" data-theme="d"');

            // tasks with a date, not complete
            $sth = $this->db->prepare("SELECT `task_tags`.`name` AS 'tag_name',"
                ."tasks.`id`,tasks.`name`, "
                ."DATE_FORMAT(`date`, '%b %e') AS 'date_format', "
                ."LOWER(TIME_FORMAT(`time`, '%l:%i%p')) AS 'time_format', "
                ."tasks.ts "
                .'FROM `tasks` '
                .'LEFT OUTER JOIN task_tags ON tasks.tag_id=task_tags.id '
                .'WHERE `folder_id`=? AND `date` IS NOT NULL '
                .'AND `complete` IS NULL ORDER BY `date` DESC,'
                .'CASE WHEN task_tags.`name` IS NULL THEN 1 ELSE 0 END, '
                .'task_tags.`name`');
            $sth->execute(array($this->id));
            while ($row = $sth->fetch()) {
                $name = $row['name'];
                if ($row['date_format'] && $row['ts'] < $today_ts) 
                    $name = '<u>'.$name.'</u>';
                $name .= ($row['tag_name'] !== null) ? ' #'.$row['tag_name'] : '';
                $item = new ListItem($name, 
                    $this->domain.'task/'.$row['id'].'/edit', 
                    '/css/fugue/tick.png', 
                    'data-icon="false" class="mini-item" data-mini="true"');
                $right = $row['date_format'];
                $right .= $row['time_format'] ? ' '.$row['time_format'] : '';
                $item->setRightText($right);
                $list->addListItem($item);
            }

            // tasks without a date, with a tag, not complete
            $sth = $this->db->prepare("SELECT `task_tags`.`name` AS 'tag_name',"
                ."tasks.`id`,tasks.`name` "
                .'FROM `tasks` '
                .'LEFT OUTER JOIN task_tags ON tasks.tag_id=task_tags.id '
                .'WHERE `folder_id`=? AND `date` IS NULL '
                .'AND tasks.tag_id IS NOT NULL AND `complete` IS NULL '
                .'ORDER BY task_tags.`name`,tasks.`name`');
            $sth->execute(array($this->id));
            while ($row = $sth->fetch()) {
                $name = $row['name'];
                $name .= ($row['tag_name']) ? ' #'.$row['tag_name'] : '';
                $item = new ListItem($name, 
                    $this->domain.'task/'.$row['id'].'/edit', 
                    '/css/fugue/tick.png', 
                    'data-icon="false" class="mini-item" data-mini="true"');
                $list->addListItem($item);
            }

            // tasks without a date, without a tag, not complete
            $sth = $this->db->prepare("SELECT tasks.`id`,tasks.`name` "
                .'FROM `tasks` '
                .'WHERE `folder_id`=? AND `date` IS NULL AND tasks.tag_id IS NULL '
                .'AND `complete` IS NULL ORDER BY tasks.`name`');
            $sth->execute(array($this->id));
            while ($row = $sth->fetch()) {
                $name = $row['name'];
                $item = new ListItem($name, 
                    $this->domain.'task/'.$row['id'].'/edit', 
                    '/css/fugue/tick.png', 
                    'data-icon="false" class="mini-item" data-mini="true"');
                $list->addListItem($item);
            }

            // tasks completed
            $sth = $this->db->prepare("SELECT `task_tags`.`name` AS 'tag_name',"
                ."tasks.`id`,tasks.`name`, "
                ."DATE_FORMAT(DATE(`complete`), '%b %e') AS 'comp_format' "
                .'FROM `tasks` '
                .'LEFT OUTER JOIN task_tags ON tasks.tag_id=task_tags.id '
                .'WHERE `folder_id`=? AND `complete` IS NOT NULL '
                .'ORDER BY `complete` DESC');
            $sth->execute(array($this->id));
            while ($row = $sth->fetch()) {
                $name = $row['name'];
                $name .= ($row['tag_name']) ? ' #'.$row['tag_name'] : '';
                $item = new ListItem('<s>'.$name.'</s>', 
                    $this->domain.'task/'.$row['id'].'/edit', 
                    '/css/fugue/cross.png', 
                    'data-icon="false" class="mini-item folder-complete" '
                    .'data-mini="true"');
                $item->setRightText($row['comp_format']);
                $list->addListItem($item);
            }

            $list->echoList();
            echo '</div>';
        }

        // bill 
        $sth = $this->db->prepare("SELECT `id`,`amount`,`type`, "
            ."DATE_FORMAT(`date`, '%b %e') AS 'date_format' "
            .'FROM `client_trans` WHERE `folder_id`=? '
            .'order by `date` DESC');
        $sth->execute(array($this->id));
        $count = $sth->rowCount();
        if ($count > 0) {
            $subitems = true;
            //$collapsed = $count > 9 ? 'true' : 'false';
            $collapsed = 'false';
            echo '<div data-role="collapsible" data-theme="a" data-collapsed="'.$collapsed
                .'" data-inset="false" data-mini="true" data-icon="false"><h4 class="nomargin">Bill ('.$count.')</h4>';
            $list = new ListObject(false, null, ' class="item-list folder-list nomargin" data-split-theme="d" data-theme="d"');
            while ($row = $sth->fetch()) {
                $name; $icon;
                switch ($row['type']) {
                case 'fee':
                    $name = 'Fee charged: $'.$row['amount'];
                    $icon = '/css/fugue/money--minus.png';
                    break;
                case 'pay':
                    $icon = '/css/fugue/money--plus.png';
                    $name = 'Payment: $'.$row['amount'];
                    break;
                case 'trust':
                    $icon = '/css/fugue/money--exclamation.png';
                    $name = 'Trust payment: $'.$row['amount'];
                    break;
                }
                $item = new ListItem($name, $this->domain.'bill/'.$row['id'].'/edit', $icon, 
                    'data-icon="false" class="mini-item" data-mini="true"');
                $item->setRightText($row['date_format']);
                $list->addListItem($item);
            }
            $list->echoList();
            echo '</div>';
        }

        if (!$subitems) {
            $list = new ListObject(false, null, ' data-mini="true" class="item-list folder-list nomargin" data-split-theme="d" data-theme="d"');
            $list->addText('No items.', 'left');
            $list->echoList();
        }

        echo '</div></div>';
    }
    public function echoFolderContents() {
        $list = new ListObject(false, null, ' data-mini="true" class="item-list folder-list nomargin" data-split-theme="d" data-theme="d"');

        $subitems = false;

        // folders
        $sth = $this->db->prepare('SELECT id,subitems,name FROM folders WHERE folder_id=? ORDER BY name');
        $sth->execute(array($this->id));
        //if ($sth->rowCount()) $list->addHeading('Folders');
        while ($row = $sth->fetch()) {
            $subitems = true;
            $item = new ListItem($row['name'], $this->domain.'folder/'.$row['id'], 
                //'/css/fugue/inbox-document-text.png', 'data-icon="false"');
                '/css/fugue/folder-open.png', 'data-icon="false" data-mini="true"');
            if ($row['subitems'] > 0) $item->setRightText($row['subitems']);
            //$item->unsetBold();
            $list->addListItem($item);
        }

        // upcoming events & tasks
        /*
         * show all overdue tasks
         * show all events/tasks for today & future ordered by date, time
         * ...
         * show passed events
         * show completed tasks
         */
        $today = new DateTime();
        $today_time = strtotime($today->format('Y-m-d'));

        $event_ids = array();
        $sth = $this->db->prepare('SELECT table_name,id,name, '
            .'`date`, '
            ."DATE_FORMAT(`date`, '%b %e') AS 'date_format', "
            ."TIME_FORMAT(`time`, '%l:%i %p') AS 'time_format' "
            ."FROM `calendar` WHERE folder_id=?");
        //$sth->execute(array($this->id,$today->format('Y-m-d')));
        $sth->execute(array($this->id));
        if ($sth->rowCount()) {
            $subitems = true;
            $list->addHeading('Calendar & Tasks');
        }
        while ($row = $sth->fetch()) {
            /*
            $this_date = new DateTime($row['date']);
            $diff = $today->diff($this_date);
            $day_diff = (int) $diff->format('%r%a');
            $time = $row['time_format'] ? strtolower($row['time_format']) : null;
             */
            $this_time = strtotime($row['date']);
            $day_diff = floor(($this_time-$today_time)/3600/24);

            if ($row['table_name'] == 'tasks') {
                // task
                $class='folder-task'; 
                $countdown = $row['date_format'].' - ';
                if ($day_diff < 0) {
                    // task is overdue
                    $class .= ' folder-task-overdue';
                    $countdown .= abs($day_diff) . ' days ago';
                    //$countdown = $diff->format('%d') . ' days ago';
                } elseif ($day_diff == 0) {
                    // task is today
                    $class .= ' folder-task-1';
                    $countdown .= 'Today';
                    $countdown .= ($row['time_format']) ? ' '.$row['time_format'] : '';
                } elseif ($day_diff == 1) {
                    $class .= ' folder-task-2';
                    $countdown .= 'Tomorrow';
                    $countdown .= ($row['time_format']) ? ' '.$row['time_format'] : '';
                }
                else {
                    // event is in the future
                    $countdown .= $day_diff . ' days';
                }


                $item = new ListItem($row['name'], $this->domain.'task/'.$row['id'].'/edit', '/css/fugue/tick.png', 
                    ' class="'.$class.'" data-icon="false" data-mini="true"');
                $item->setRightText($countdown);

                $list->addListItem($item);
                $subitems = true;
            } else {
                $success = false;
                if ($day_diff > -1) {
                    if ($row['table_name'] == 'events') {
                        $success = true;
                        $event_ids[$row['id']] = 1;
                    } else {
                        if (!isset($event_ids[$row['id']])) {
                            $event_ids[$row['id']] = 1;
                            $success = true;
                        }
                    }
                }

                if ($success) {
                    // event
                    $class='folder-event'; 
                    $countdown = $row['date_format'].' - ';

                    if ($day_diff == 0) {
                        // event is today
                        $class .= ' folder-event-1';
                        $countdown .= 'Today';
                        $countdown .= ($row['time_format']) ? ' '.$row['time_format'] : '';
                    } elseif ($day_diff == 1) {
                        $class .= ' folder-event-2';
                        $countdown .= 'Tomorrow';
                        $countdown .= ($row['time_format']) ? ' '.$row['time_format'] : '';
                    }
                    else {
                        // event is in the future
                        $countdown .= $day_diff . ' days';
                    }

                    $item = new ListItem($row['name'], $this->domain.'event/'.$row['id'].'/edit', '/css/fugue/calendar-month.png',
                        ' class="'.$class.'" data-icon="false" data-mini="true"');
                    $item->setRightText($countdown);

                    $list->addListItem($item);
                }
            } 
        }

        // tasks with no date and a priority
        $sth = $this->db->prepare('SELECT id,name,priority '
            .'FROM `tasks` WHERE `folder_id`=? AND `date` IS NULL '
            .'AND `complete` IS NULL AND `priority` IS NOT NULL '
            .'ORDER BY `priority`,`name` ');
        $sth->execute(array($this->id));
        while ($row = $sth->fetch()) {
            $subitems = true;
            $class = 'folder-task folder-task-'.$row['priority'];
            $item = new ListItem($row['name'], $this->domain.'task/'.$row['id'].'/edit', '/css/fugue/tick.png', 
                ' class="'.$class.'" data-icon="false" data-mini="true"');
            if ($row['priority']) $item->setRightText($row['priority']);

            $list->addListItem($item);
        }

        // tasks with no date and no priority
        $sth = $this->db->prepare('SELECT id,name '
            .'FROM `tasks` WHERE `folder_id`=? AND `date` IS NULL '
            .'AND `complete` IS NULL AND `priority` IS NULL '
            .'ORDER BY `name` ');
        $sth->execute(array($this->id));
        while ($row = $sth->fetch()) {
            $subitems = true;
            $item = new ListItem($row['name'], $this->domain.'task/'.$row['id'].'/edit', '/css/fugue/tick.png', 
                'class="folder-task" data-icon="false" data-mini="true"');

            $list->addListItem($item);
        }

        // notes
        $sth = $this->db->prepare('SELECT id,name '
            .'FROM `notes` WHERE `folder_id`=? '
            .'ORDER BY `name` ');
        $sth->execute(array($this->id));
        if ($sth->rowCount()) $list->addHeading('Notes');
        while ($row = $sth->fetch()) {
            $subitems = true;
            $link = $this->domain.'note/'.$row['id'];

            $item = new ListItem($row['name'], $link, '/css/fugue/notebook-sticky-note.png', 
                'data-icon="false" data-mini="true"');

            $list->addListItem($item);
        }

        // files
        $sth = $this->db->prepare('SELECT id,name,path '
            .'FROM `files` WHERE `folder_id`=? '
            .'ORDER BY `name` ');
        $sth->execute(array($this->id));
        if ($sth->rowCount()) $list->addHeading('Files');
        while ($row = $sth->fetch()) {
            $subitems = true;
            $item = new ListItem($row['name'], $this->domain.'file/'.$row['id'], '/css/fugue/disk-black.png', 
                'data-icon="false" data-mini="true"');

            $list->addListItem($item);
        }

        // bill
        $sth = $this->db->prepare('SELECT id,amount,`type`, '
            ."DATE_FORMAT(`date`, '%b %e, %Y') AS 'date_format' "
            .'FROM `client_trans` WHERE `folder_id`=? '
            .'ORDER BY `date` ');
        $sth->execute(array($this->id));
        if ($sth->rowCount()) {
            $sthx = $this->db->prepare('SELECT balance FROM folders WHERE id=?');
            $sthx->execute(array($this->id));
            $rowx = $sthx->fetch();
            $title = 'Bill';
            if ($rowx['balance']) {
                $title .= ' balance: $'.$rowx['balance'];
            } 
            $list->addHeading($title);
        }
        while ($row = $sth->fetch()) {
            $subitems = true;
            $icon;
            $title;
            switch ($row['type']) {
            case 'fee':
                $title = 'Fee charged: $'.$row['amount'];
                $icon = '/css/fugue/money--minus.png';
                break;
            case 'pay':
                $icon = '/css/fugue/money--plus.png';
                $title = 'Payment: $'.$row['amount'];
                break;
            case 'trust':
                $icon = '/css/fugue/money--exclamation.png';
                $title = 'Trust payment: $'.$row['amount'];
                break;
            }
            $item = new ListItem($title, $this->domain.'bill/'.$row['id'].'/edit', $icon,
                'data-icon="false" data-mini="true"');
            $item->setRightText($row['cr_format']);

            $list->addListItem($item);
        }

        // passed events
        $events = false;
        $sth = $this->db->prepare('SELECT id,name, '
            .'`date`, '
            ."DATE_FORMAT(`date`, '%b %e, %Y') AS 'date_format', "
            ."TIME_FORMAT(`time`, '%l:%i %p') AS 'time_format' "
            ."FROM `events` WHERE folder_id=? ORDER BY `date` DESC");
        //$sth->execute(array($this->id,$today->format('Y-m-d')));
        $sth->execute(array($this->id));
        while ($row = $sth->fetch()) {
            $subitems = true;
            $this_time = strtotime($row['date']);
            $day_diff = floor(($this_time-$today_time)/3600/24);

            if ($day_diff < 0 && !isset($event_ids[$row['id']])) {
                if (!$events) {
                    $list->addHeading('Completed');
                    $events = true;
                }
                $item = new ListItem($row['name'], $this->domain.'event/'.$row['id'].'/edit', '/css/fugue/calendar-month.png',
                    ' class="'.$class.'" data-icon="false" data-mini="true"');
                $item->setRightText($row['date_format']);

                $list->addListItem($item);
            }
        }

        // completed tasks
        $sth = $this->db->prepare('SELECT id,name,complete '
            .'FROM `tasks` WHERE `folder_id`=? AND `complete` IS NOT NULL ORDER BY `complete` DESC');
        $sth->execute(array($this->id));
        while ($row = $sth->fetch()) {
            if (!$events) {
                $list->addHeading('Completed');
                $events = true;
            }
            $subitems = true;
            $comp_timestamp = strtotime($row['complete'].' UTC');
            $local_date = date('M j, Y', $comp_timestamp);

            $item = new ListItem($row['name'], $this->domain.'task/'.$row['id'].'/edit', '/css/fugue/tick.png',
                ' class="folderitem-complete" data-icon="false" data-mini="true"');
            $item->setRightText($local_date);

            $list->addListItem($item);
        }

        if (!$subitems) {
            $list->addText('Nothing in this folder.');
        }

        $list->echoList();
    }
}

$page = new Folder();
?>
