<?php

class OC_CLI {
    protected $registry;

    public function __construct($registry) {
        if (!$this->isActive()) {
            $this->echo_invalid_request();
        }

        $this->registry = $registry;
    }

    public function isActive() {
        return defined('OPENCART_CLI_MODE') && OPENCART_CLI_MODE === TRUE;
    }

    public function router() {
        global $argv;

        if (empty($argv[2]) || $argv[2] == 'oc_cli/router') {
            $route = $this->registry->get('config')->get('action_default');
        } else {
            $route = str_replace('../', '', (string)$argv[2]);
        }

        $output = null;

        // Trigger the pre events
        $result = $this->registry->get('event')->trigger('controller/' . $route . '/before', array(&$route, &$data, &$output));
        
        if (!is_null($result)) {
            return $result;
        }
        
        // We dont want to use the loader class as it would make any controller callable.
        $action = new Action($route);
        
        // Any output needs to be another Action object.
        $params = array_slice($argv, 3);
        $output = $action->execute($this->registry, $params); 
        
        // Trigger the post events
        $result = $this->registry->get('event')->trigger('controller/' . $route . '/after', array(&$route, &$data, &$output));
        
        if (!is_null($result)) {
            return $result;
        }
        
        return $output;
    }

    public function echo_invalid_request() {
        oc_cli_output("Invalid request!", 1);
    }

    public function echo_not_found() {
        oc_cli_output("Route not found!", 1);
    }

    public function echo_welcome_message() {
        oc_cli_output("This is OpenCart in CLI mode.");
    }
}
