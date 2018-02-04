<?php
class ControllerExtensionModuleHelloWorld extends Controller {
	private $error = array();

	public function index() {

		$this->load->language('extension/module/hello_world');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_hello_world', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['hello_world_id'])) {
			$data['error_id'] = $this->error['hello_world_id'];
		} else {
			$data['error_id'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
			);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/hello_world', 'user_token=' . $this->session->data['user_token'], true)
			);

		$data['action'] = $this->url->link('extension/module/hello_world', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);		

		if(isset($this->request->post['module_hello_world_id'])) {
			$data['module_hello_world_id'] = $this->request->post['module_hello_world_id'];
		} elseif ($this->config->get('module_hello_world_id')){
			$data['module_hello_world_id'] = $this->config->get('module_hello_world_id');
		} else{
			$data['module_hello_world_id'] = '';
		}

		if (isset($this->request->post['module_hello_world_status'])) {
			$data['module_hello_world_status'] = $this->request->post['module_hello_world_status'];
		} elseif ($this->config->get('module_hello_world_status')) {			
			$data['module_hello_world_status'] = $this->config->get('module_hello_world_status');
		} else {
			$data['module_hello_world_status'] = 0;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/hello_world', $data));		
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/hello_world')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['module_hello_world_id']) {
			$this->error['hello_world_id'] = $this->language->get('error_id');
		}

		return !$this->error;
	}
}