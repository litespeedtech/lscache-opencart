<?php

class ControllerOcCliNotFound extends Controller {
    public function index() {
        $this->oc_cli->echo_not_found();
    }
}