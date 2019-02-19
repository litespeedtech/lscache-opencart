<?php

class ControllerOcCliWelcome extends Controller {
    public function index() { 
        $this->oc_cli->echo_welcome_message();
    }
}