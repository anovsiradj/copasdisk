<?php
namespace anovsiradj;

class CopasDisk
{
	const VERSION = '1.0.0';
	const AUTHOR = 'Mayendra Costanov (anovsiradj)';

	public $opts_a = array('-a', '--from', '--source');
	public $opts_b = array('-b', '--to', '--destination');
	public $opts_special = array('--help', '--version');
	public $opts;

	public $copas_ignore = array('.', '..', '.git');
	public $copas_full_ignore = array();

	public $a = array(); // source(s)
	public $b; // target

	public $is_cli;
	public $cli_requests = array();

	function __construct() {
		$sapi = php_sapi_name();
		$this->is_cli = substr($sapi, 0, 3) === 'cli';
	}

	public function cli($requests = array(), $do_array_shift = true)
	{
		if (!$this->is_cli) {
			$this->error(sprintf('Forbidden SAPI interface (%s). Only CLI allowed.', $sapi));
		}

		// 1st param (0-index) is this file itself
		array_shift($requests);

		$this->cli_requests = $requests;
		$this->opts = array_merge($this->opts_a, $this->opts_b);

		foreach (array_uintersect($this->cli_requests, $this->opts_special, 'strcmp') as $special) {
			$this->execute_special($special);
		}

		$this->cli_validate();
	}

	public function cli_validate()
	{
		$type_opt = null;
		foreach ($this->cli_requests as $request) {
			if ($this->is_optly($request)) {
				$type_opt = $request;
			} elseif (!$this->set_opt_by_type($type_opt, $request)) {
				$this->error(sprintf('Not found option with key "%s" (trying set value to "%s")', $type_opt, $request));
			}
		}

		$this->execute();
	}

	public function execute_special($opt)
	{
		// todo: --help
		switch ($opt) {
			case '--version':
				$this->log(sprintf('%s version %s', __CLASS__, $this::VERSION));
			break;
			default: $this->log($opt); break;
		}
		die();
	}

	// http://stackoverflow.com/a/8459443/3036312
	protected function copas($a)
	{
		if (is_file($a)) return $this->disk($a);

		if (is_dir($a)) {
			$dir = dir($a);
			while (false !== ($entry = $dir->read())) {
				if (in_array($entry, $this->copas_ignore)) continue;

				$current_a = $a . DIRECTORY_SEPARATOR . $entry;

				// todo
				if (in_array($a, $this->copas_full_ignore)) continue;

				$this->copas($current_a);
			}
			$dir->close();
		}
	}

	// https://php.net/manual/en/function.file-put-contents.php#84180
	protected function disk($source)
	{
		$destination = $this->get_a2b_path($source);

		$parts = explode(DIRECTORY_SEPARATOR, $destination);
		$file = array_pop($parts);
		$dest = array();
		foreach ($parts as $part) {
			array_push($dest, $part);
			$curr_dest = implode(DIRECTORY_SEPARATOR, $dest);
			if (!is_dir($curr_dest)) mkdir($curr_dest);
		}

		if (!@copy($source, $destination)) {
			$this->log(sprintf('Failed to copy from "%s" to "%s"', $source, $destination));
		}
	}

	public function execute()
	{
		if (!isset($this->b)) $this->error('TO is not set');
		if (count($this->a) < 1) $this->error('FROM is empty');

		foreach ($this->a as $a) {
			if ($this->is_cli) $this->log('Copy: ', $a, ' => ', $this->get_a2b_path($a));
			$this->copas($a);
		}
	}

	protected function set_opt_by_type($key, $value)
	{
		if (in_array($key, $this->opts_a)) {
			array_push($this->a, $this->tidy_path($value));
			return true;
		}
		if (in_array($key, $this->opts_b)) {
			$this->b = $this->tidy_path($value);
			return true;
		}
		return false;
	}

	protected function is_optly($request)
	{
		return preg_match('/^(-[a-zA-Z0-9]|\-\-[a-zA-Z0-9]+)$/', $request) ? true : false;
	}

	protected function get_a2b_path($source)
	{
		$parts = explode(DIRECTORY_SEPARATOR, $source);
		foreach ($parts as $k => $v) {
			if ($v === '..') $parts[$k] = null;
		}
		$clean_source = implode(DIRECTORY_SEPARATOR, array_filter($parts));
		$destination = $this->b . DIRECTORY_SEPARATOR . $clean_source;
		return $destination;
	}

	public function set_a($a)
	{
		if (is_array($a)) {
			foreach ($a as $var_a) {
				array_push($this->a, $var_a);
			}
		} else {
			array_push($this->a, $a);
		}
	}

	public function set_b($b)
	{
		$this->b = $b;
	}

	protected function error($message = null)
	{
		if ($message !== null) {
			$message = trim($message);
			$this->log($message, ($message[strlen($message)-1] === '.' ? '' : '.'));
		}
		die();
	}

	protected function log()
	{
		foreach (func_get_args() as $arg) echo $arg;
		echo PHP_EOL;
	}

	protected function confirm_boolean($message = null)
	{
		$message = trim($message);
		echo $message, ($message[strlen($message)-1] === '?' ? '' : '?') , ' '; // add space between stdin

		$cmd_handle = fopen("php://stdin", "r");
		$cmd_uservoice = trim(strtolower(fgets($cmd_handle)));

		return preg_match('/^(1|y)/', $cmd_uservoice) ? true : false;
	}

	public function tidy_path($path)
	{
		return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, trim($path, '/\\'));
	}

	public function __destruct()
	{
		if ($this->is_cli) $this->log('Done.');
		clearstatcache(); // to uncache path
	}
}
