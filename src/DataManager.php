<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require dirname(dirname(__FILE__)) . '/lib/Mysqldump/Mysqldump.php';

/**
 * Description of DataManager
 *
 * @author DJ
 */
final class DataManager {
    
    private $view;
    private $site;
    private $configDir;
    private $dumpDir;
    private $tokenDir;
    
    private $responseMessage;
    private $responseMessageEnum = array(
        1 => 'Data imported successfully.',
        2 => 'Expired token. Please try again.',
        3 => 'Invalid token. You probably reloaded a page you shouldn\'t have.',
    );
    private $redirect;
    
    private $configData;
    
    public function __construct() {
	$this->configDir = dirname(dirname(__FILE__)) . "/config";
	$this->dumpDir = dirname(dirname(__FILE__)) . "/dump";
        $this->tokenDir = dirname(dirname(__FILE__)) . "/tmp";
	
        if (isset($_GET['t'])) {
            $this->view='loading';
            $runData = $this->getTokenData($_GET['t']);
            if (!$runData) {
                header("Location: {$this->getBaseUrl()}?m=3");
                die;
            }
            $this->removeToken($_GET['t']);
            if (time() - $runData['time'] > 10) {
                header("Location: {$this->getBaseUrl()}?m=2");
                die;
            }
            $this->site = $runData['site'];
            $this->runDump();
            header("Location: {$this->getBaseUrl()}?m=1");
            die;
        } else {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'perform_dump':
                        $_POST['config']['misc']['last_action'] = 'perform_dump';
                        $this->view = 'loading';
                        $this->site = $_POST['site'];
                        $this->saveConfig($_POST['config']);
                        $token = $this->setTokenData(array('site'=>$this->site, 'time'=>time()));
                        $this->redirect = "{$this->getBaseUrl()}?t={$token}";
                        $this->responseMessage = "We're doing it now, sit tight&hellip;";
                        break;
                    case 'save_dump':
                        $_POST['config']['misc']['last_action'] = 'save_dump';
                        $this->view = 'result';
                        $this->site = $_POST['site'];
                        $this->saveConfig($_POST['config']);
                        $this->loadConfig();
                        $dumpfile = $this->getSqlDump();
                        header("Location: http://{$_SERVER['HTTP_HOST']}/dump/{$this->site}.sql");
                        die;
                    case 'save_config':
                        $_POST['config']['misc']['last_action'] = 'save_config';
                        $this->view = 'default';
                        $this->site = $_POST['site'];
                        $this->saveConfig($_POST['config']);
                        $this->responseMessage = 'Configuration saved successfully.';
                        break;
                    case 'status':
                        break;
                    default:
                        header("HTTP/1.1 405 Method Not Allowed");
                        echo 'Invalid action';
                        die;
                }
            } else {
                $this->view = 'default';
                $this->site = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], '.'));
                $this->loadConfig();
            }
        }
    }
    
    public static function init() {
	return new self();
    }
    
    public function getView() {
	return $this->view;
    }
    
    private function runDump() {
        $this->loadConfig();
        $dumpfile = $this->getSqlDump();
        $this->importSql($dumpfile);
        if ($this->getConfig('misc', 'delete_after_import')) {
            unlink($dumpfile);
        }
        $this->responseMessage = 'Data imported successfully.';
    }
    
    private function getSqlDump() {
        $remote_host = $this->getConfig('remote', 'db_host');
	$remote_dbname = $this->getConfig('remote', 'db_name');
	$remote_dbuser = $this->getConfig('remote', 'db_uname');
	$remote_pass = $this->getConfig('remote', 'db_pass');
        
        $dumpSettings = array(
            'add-drop-table' => true,
            'add-drop-database' => true,
            'exclude-tables' => array_filter(preg_split('/\s+/',$this->getConfig('misc', 'exclude_tables'))),
        );
	
	//https://github.com/ifsnop/mysqldump-php
	$dump = new Ifsnop\Mysqldump\Mysqldump("mysql:host=$remote_host;dbname=$remote_dbname", $remote_dbuser, $remote_pass, $dumpSettings);
	$dumpfile = "{$this->dumpDir}/{$this->site}.sql";
	$dump->start($dumpfile);
        return $dumpfile;
    }
    
    private function importSql($dumpfile) {
	
	//some half-assed security measures, please never ever use this out in the wild
	$dbname = $this->getConfig('local', 'db_name');
	$dbuser = $this->getConfig('local', 'db_uname');
	$pass = $this->getConfig('local', 'db_pass');

	exec("mysql -u $dbuser -p{$pass} $dbname < $dumpfile"); //yep it's super sketch
	
	$additional = $this->getConfig('misc', 'additional_sql');
	if ($additional) {
            $db = new PDO("mysql:host=localhost;dbname=$dbname", $dbuser, $pass);
            $db->prepare($additional)->execute();
	}
    }
 
    private function saveConfig($data) {
	if (!is_dir($this->configDir)) {
	    mkdir($this->configDir, 0775);
	}
	file_put_contents("{$this->configDir}/{$this->site}.json", json_encode($data));
    }
    
    private function loadConfig() {
	$configFile = "{$this->configDir}/{$this->site}.json";

	if (file_exists($configFile)) {
	    $this->configData = json_decode(file_get_contents($configFile), true);
	} else {
	    $this->configData['local']['db_name'] = $this->site;
	    $this->configData['local']['db_uname'] = 'root';
	    $this->configData['local']['db_pass'] = 'root'; 
	    $this->configData['local']['delete_after_import'] = 'y';
	}
    }
    
    public function getConfig($env, $key) {
	if (!is_array($this->configData)) {
	    $this->loadConfig();
	}
	if (array_key_exists($env, $this->configData) &&
	    array_key_exists($key, $this->configData[$env]
	)) {
	    return $this->configData[$env][$key];
	}
	return '';
    }
    
    public function getSite() {
	return $this->site;
    }
    
    public function getResponse() {
	return $this->response;
    }
    
    public function getResponseMessage() {
        $responseMessages = array();
        if (isset($_GET['m'])) {
            $responseMessages[] = $this->responseMessageEnum[$_GET['m']];
        }
        if ($this->responseMessage) {
            $responseMessages[] = $this->responseMessage;
        }
        return implode('<br />', $responseMessages);
    }
    
    private function setTokenData($data) {
        $token = uniqid();
        $jsonData = json_encode($data);
        file_put_contents("{$this->tokenDir}/{$token}", $jsonData);
        return $token;
    }
    
    private function getTokenData($token) {
        $fileContents = file_get_contents("{$this->tokenDir}/{$token}");
        return json_decode($fileContents, true);
    }
    
    private function removeToken($token) {
        unlink("{$this->tokenDir}/{$token}");
    }
    
    public function getRedirect() {
        return $this->redirect;
    }
    
    public function getBaseUrl() {
        $fullUrl = parse_url('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        return $fullUrl['scheme'] . '://' . $fullUrl['host'] . ':' . $fullUrl['port'] . $fullUrl['path'];
    }
    
    public function getStatusFavicon() {
        if (isset($_GET['m'])) {
            switch ($_GET['m']) {
                case '1':
                    return '/assets/img/favi-success.ico';
                    break;
                case '2':
                case '3':
                    return '/assets/img/favi-error.ico';
                    break;
                default:
                    break;
            }
        }
        return '/assets/img/favi-default.ico';
    }
}
