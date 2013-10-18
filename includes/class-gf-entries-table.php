<?php

if (!class_exists('WP_List_Table'))
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class GFEntriesTable extends WP_List_Table {

    private $_entries;

    function __construct($entries) {
        $this->_entries = $entries;

        $this->_column_headers = array(
            array("id" => "Id", "date_created" => "Date", "is_read" => "Read", "is_starred" => "Starred", "status"=> "Status"),
            array(),
            array("id" => array("id", true), "date_created" => array("date_created", true), "is_read" => array("is_read", false), "is_starred" => array("is_starred", false), "status"=> array("status", false))
        );

        parent::__construct(array(
            'singular' => __('entry', 'gravityforms'),
            'plural'   => __('entries', 'gravityforms'),
            'ajax'     => false
        ));
    }

    function prepare_items() {
        $this->items = isset($this->_entries) ? $this->_entries : array();
    }

    function no_items() {
        echo "This form doesn't have any entries";
    }

    function column_default($item, $column) {

        return rgar($item, $column);
    }

    function column_id($item){

        $actions = array("edit" => "<a title='Edit Entry' href=' " . add_query_arg(array("entry_id" => $item["id"])) . "'>Edit</a>",
                         "delete" => "<a title='Delete' onclick='DeleteEntry(" . $item["id"] . ")' href='javascript:void(0);'>Delete</a>"
                        );
        return rgar($item, "id") . " " . $this->row_actions($actions);
    }

    function column_cb($item) {
        return "";
    }


}
