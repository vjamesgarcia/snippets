<?php
require_once('page.php');

class Clients extends Page {
    public $status = '';
    public $sort = '';
    function __construct() {
        $this->icon = '/css/fugue/users.png';
        parent::__construct();
    }
    public function processArgs() {
        $success = true;
        $this->title = 'Client list';
        if (array_key_exists(0, $this->args)) {
            $this->page_type = 'filter';
            $this->page_id = 'clients-filter';
            if (in_array('open', $this->args)) {
                $this->status = '/open';
            } elseif (in_array('closed', $this->args)) {
                $this->status = '/closed';
            }
            if (in_array('balance', $this->args)) {
                $this->sort = '/balance';
            } elseif (in_array('paid', $this->args)) {
                $this->sort = '/paid';
            }
        } else {
            // default page for /online/clients
            $this->page_type = 'all';
            $this->page_id = 'clients-all';
        }
        return $success;
    }
    public function echoToolbar() {
        // new button in header
        echo '<a href="'.$this->domain.'client/new" class="new-btn" '
            .'data-role="button" data-icon="plus" data-inline="true" data-iconpos="right"><u>N</u>ew</a>';

        echo '<div class="ui-grid-a">'
            .'<div class="ui-block-a">';

        // client status
        $btn_title = 'All clients';
        if ($this->status != '') {
            if ($this->status == '/open') {
                $btn_title = 'Open clients';
            } elseif ($this->status == '/closed') {
                $btn_title = 'Closed clients';
            }
        }
        echo '<a href="#status-popup" data-role="button" data-rel="popup" data-icon="arrow-d">'.$btn_title.'</a>'
            .'<div data-role="popup" id="status-popup">'
            .'<ul data-role="listview" data-inset="true">'
            .'<li data-icon="false"><a href="'.$this->domain.'clients'.$this->sort.'">All clients</a></li>'
            .'<li data-icon="false"><a href="'.$this->domain.'clients/open'.$this->sort.'">Open clients</a></li>'
            .'<li data-icon="false"><a href="'.$this->domain.'clients/closed'.$this->sort.'">Closed clients</a></li>'
            .'</ul></div>'
            .'</div><div class="ui-block-b">';
            
        // client sort
        $btn_title = 'Sort by name';
        if ($this->sort != '') {
            if ($this->sort == '/balance') {
                $btn_title = 'Sort by balance';
            } elseif ($this->sort == '/paid') {
                $btn_title = 'Sort by paid';
            }
        }
        echo '<a href="#sort-popup" data-role="button" data-rel="popup" data-icon="arrow-d">'.$btn_title.'</a>'
            .'<div data-role="popup" id="sort-popup">'
            .'<ul data-role="listview" data-inset="true">'
            .'<li data-icon="false"><a href="'.$this->domain.'clients'.$this->status.'">Sort by name</a></li>'
            .'<li data-icon="false"><a href="'.$this->domain.'clients'.$this->status.'/balance">Sort by balance</a></li>'
            .'<li data-icon="false"><a href="'.$this->domain.'clients'.$this->status.'/paid">Sort by paid</a></li>'
            .'</ul></div>';

        echo '</div></div>';
    }
    public function echoContent() {
            echo '<div data-role="content">';

            $list = new ListObject(false, null, 'data-filter="true" class="clients-list"');

            // setup query statement
            $stmt = "SELECT `clients`.`id` AS 'client_id',"
                ."`contacts`.`name` AS 'client_name',"
                ."CONCAT_WS(' ',`first_name`,`middle_name`,`suffix`) AS 'first_names', "
                ."`balance`,`open_cases` "
                .'FROM `clients` '
                .'JOIN `contacts` ON `clients`.`contact_id`=`contacts`.`id` ';
            if ($this->status == '/open') {
                $stmt .= 'WHERE `open_cases`>0 ';
            } elseif ($this->status == '/closed') {
                $stmt .= 'WHERE `open_cases`=0 ';
            } 
            if ($this->sort == '/balance') {
                $stmt .= 'ORDER BY `balance` DESC ';
            } elseif ($this->sort == '/paid') {
                $stmt .= 'ORDER BY `last_payment` ';
            } else {
                $stmt .= 'ORDER BY `contacts`.`name` ';
            }

            $sth = $this->db->prepare($stmt);
            $sth->execute();
            if ($sth->rowCount()) {
                while ($row = $sth->fetch()) {
                    $title = $row['client_name'];
                    $title .= ($row['first_names']) ? ', '.$row['first_names'] : '';
                    $item = new ListItem($title, $this->domain.'client/'.$row['client_id'], '/css/fugue/user.png', 
                        'class="mini-item" data-mini="true" data-icon="false"', 'target="_blank"');
                    $open_cases = ($row['open_cases'] == 1) ? '1 open case.' : $row['open_cases'].' open cases.';
                    $balance = ($row['balance'] > 0) ? '$'.$row['balance'] : null;
                    if ($balance) $open_cases .= ' '.$balance;
                    $item->setRightText($open_cases);
                    $list->addListItem($item);
                }
            } else {
                $list->addText('No clients.');
            }

            $list->echoList();

            echo '</div>';
    }
}

$page = new Clients();
?>
