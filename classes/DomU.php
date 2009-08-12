<?php 

class DomU {
	
	public $name,$sid,$id,$xid,$state,$kernel,$weight,$cap,$record;
	public $s_max_ram,$s_min_ram,$d_max_ram,$d_min_ram,$template;
	public $auto_power_on,$suspend_vdi,$vcpu_max,$vcpus_at_startup,$actions_after_shutdown;
	public $actions_after_crash,$actions_after_reboot,$pvargs;
	public $vifs,$vbds,$consoles;
	public $handle;
	
	// AUTO GETTERS, call with e.g : obj->id 
	public function __get($attr) {
		if(isset($this->$attr)) return $this->$attr;
		else throw new Exception('Unknown attribute '.$attr);
	}
	
	// AUTO SETTERS
	public function __set($attr,$value) {
		if(isset($this->$attr)) $this->$attr = $value;
		else throw new Exception('Unknow attribute '.$attr);
	}
	
	public function __construct($id,$handle) {
		
		$this->id = $id;
		$this->handle = $handle;
		$this->record = $this->handle->send("VM.get_record",$this->id);
		
		// build record
		$this->sid 			= $this->record['uuid'];
		$this->name 		= $this->record['name_description'];
		$this->xid 			= $this->record['domid'];
		$this->state 		= $this->record['power_state'];
		$this->kernel 		= $this->record['PV_kernel'];
		$this->weight 		= $this->record['VCPUs_params']['weight'];
		$this->cap 			= $this->record['VCPUs_params']['cap'];
		$this->s_max_ram	= $this->record['memory_static_max'];
		$this->s_min_ram	= $this->record['memory_static_min'];
		$this->d_max_ram	= $this->record['memory_dynamic_max'];
		$this->d_min_ram	= $this->record['memory_dynamic_min'];
		$this->auto_power_on= $this->record['auto_power_on'];
		$this->vcpu_max 	= $this->record['VCPUs_max'];
		$this->template		= $this->record['is_a_template'];
		$this->pvargs		= $this->record['PV_args'];
		$this->vifs			= $this->record['VIFs'];
		$this->vbds			= $this->record['VBDs'];
		$this->consoles		= $this->record['consoles'];
		$this->vcpus_at_startup 		= $this->record['VCPUs_at_startup'];
		$this->actions_after_shutdown 	= $this->record['actions_after_shutdown'];
		$this->actions_after_reboot		= $this->record['actions_after_reboot'];
		$this->actions_after_crash 		= $this->record['actions_after_crash'];
		
	}
	
	public function get_preview() {
		
		return "$this->xid,$this->name,$this->state,$this->kernel";
	}
	
	public function get_all_infos() {

		return "$this->xid,$this->name,$this->state,$this->kernel,
		$this->weight,$this->cap,$this->s_max_ram,$this->s_min_ram,
		$this->d_max_ram,$this->d_min_ram,$this->auto_power_on,
		$this->suspend_vdi,$this->vcpu_max,$this->vcpus_at_startup,
		$this->actions_after_shutdown,$this->actions_after_reboot,
		$this->actions_after_crash,$this->template,$this->pvargs,
		$this->vifs,$this->vbds";
	}
	
	
	public function pause() {
		$this->handle->send("VM.pause",$this->id);
	}
	
	public function unpause() {
		$this->handle->send("VM.unpause",$this->id);
	}
	
	public function migrate($dest,$live) {
		$debug1 = array("port" => 8002);
		//$debug1 = "";
		$params = array($this->id,$dest,$live,$debug1);
		$this->handle->send("VM.migrate",$params);
	}

	/*
	public function __toString() {
		
		return "$this->xid,$this->name,$this->state,$this->kernel";
	}*/

}
