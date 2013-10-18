<?php

if (!class_exists('WP_List_Table'))
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class GFFormsTable extends WP_List_Table {

    private $_forms;

    function __construct($forms) {
        $this->_forms = $forms;

        $this->_column_headers = array(
            array("title" => "Title", "id" => "Id", "entries"=>"Entries"),
            array(),
            array()
        );

        parent::__construct(array(
            'singular' => __('form', 'gravityforms'),
            'plural'   => __('forms', 'gravityforms'),
            'ajax'     => false
        ));
    }

    function prepare_items() {
        $this->items = isset($this->_forms) ? $this->_forms : array();
    }

    function no_items() {
        echo "You don't have any forms";
    }

    function column_default($item, $column) {

        return rgar($item, $column);
    }

    function column_title($item){

        $actions = array("edit" => "<a title='Edit Form' href=' " . add_query_arg(array("form_id" => $item["id"])) . "'>Edit</a>",
                         "entries" => "<a title='View Entries' href=' " . add_query_arg(array("form_id" => $item["id"], "view" => "entries")) . "'>Entries</a>",
                         "results" => "<a title='View Results' href=' " . add_query_arg(array("form_id" => $item["id"], "view" => "results")) . "'>Results</a>",
                         "delete" => "<a title='Delete' onclick='DeleteForm(" . $item["id"] . ")' href='javascript:void(0);'>Delete</a>"
                        );
        return rgar($item, "title") . " " . $this->row_actions($actions);
    }

    function column_cb($item) {
        return "";
    }


}
