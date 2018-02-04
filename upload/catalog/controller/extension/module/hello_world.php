<?php
class ControllerExtensionModuleHelloWorld extends Controller {
	public function index() {
		$this->load->language('extension/module/hello_world');

		$data['id'] = $this->config->get('module_hello_world_id');

		return $this->load->view('extension/module/hello_world', $data);
	}
}