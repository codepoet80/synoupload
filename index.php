<?php
	// --- SETTINGS --- //
	
	// for local NAS webserver you can use URL: http://localhost
	$NAS_url   = 'http://localhost';
	$NAS_port  = '5000';
	$descrypt_pass = '8f8e41cfc054f45eda';  // this has to be same as in app preferences
	
	// --- SETTINGS --- //

	//$NAS_url   = 'https://home.panel.sk';
	//$NAS_port  = '5001';

function ret($return) {
	echo json_encode($return);	
	exit();
}
	
	
	if ($_GET['test'] == 1) {
		// testing if is script available
		ret(array('success' => true));
	}
	
	if ($_POST && $_FILES['file'] && $_FILES['file']['size'] > 0) {
		require 'aes.class.php';     // AES PHP implementation
		require 'aesctr.class.php';  // AES Counter Mode implementation
	
		$descrString = AesCtr::decrypt($_POST['private'], $descrypt_pass, 256);
		if (!$descrString || strpos($descrString, '_SYNO_') === false) {
			ret(array('success' => false, 'err' => 'bad_key'));
		}
		
		list($user, $pass) = explode('_SYNO_', $descrString);
		$loginUrl   = $NAS_url.':'.$NAS_port.'/webman/modules/login.cgi?action=login&username='.$user.'&passwd='.$pass;
		$uploadUrl  = $NAS_url.':'.$NAS_port.'/webman/modules/FileBrowser/webfm/webUI/file_upload.cgi';
		
		define(DATA, dirname(__FILE__).'/');
		define(TEMP, dirname(__FILE__).'/temp/');
		 
		$cookieFile = DATA.'cookies.txt';
		$certFile   = DATA.'cacert.pem';
		if (!file_exists(TEMP)) {
			mkdir(TEMP);
		}
		chmod(TEMP, 0777);
		chown(TEMP, 'admin');

        $filename = $_FILES["file"]["name"];
        $up = move_uploaded_file($_FILES["file"]["tmp_name"], TEMP.$filename);
        if (!$up) { 
			ret(array('success' => false, 'err' => 'upload_failed'));
		}
        else {
        	chmod(TEMP.$filename, 0777);
        	chown(TEMP.$filename, 'admin');
        	 
        	$log = curl_init();
			curl_setopt($log, CURLOPT_URL, $loginUrl);
			curl_setopt($log, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($log, CURLOPT_SSL_VERIFYPEER,true); 
			curl_setopt($log, CURLOPT_CAINFO, $certFile);
			curl_setopt($log, CURLOPT_COOKIEJAR, $cookieFile);   
			curl_setopt($log, CURLOPT_COOKIEFILE, $cookieFile);		 
			$response = curl_exec($log);
			curl_close($log);	
	
			$taskid = 'formupload'.time().'315_0';
			$chkUrl = $uploadUrl.'?action=checkfile&path='.urlencode($_POST['path']).'&filename='.urlencode($filename).'&taskid='.urlencode($taskid);
			$chk = curl_init();
			curl_setopt($chk, CURLOPT_URL, $chkUrl);
			curl_setopt($chk, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($chk, CURLOPT_SSL_VERIFYPEER,true); 
			curl_setopt($chk, CURLOPT_CAINFO, $certFile);
			curl_setopt($chk, CURLOPT_COOKIEJAR, $cookieFile);   
			curl_setopt($chk, CURLOPT_COOKIEFILE, $cookieFile);		 
			$response = curl_exec($chk);
			curl_close($chk);	
			$resp = json_decode($response);
			
			if ($resp->result == 'success') {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,true);
				curl_setopt($ch, CURLOPT_CAINFO, $certFile);
				curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
				curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
				$local = false;
				if (strpos($NAS_url, 'localhost') !== false) {
					$local = true;
					$exp = explode('/', substr(TEMP, 1));
					unset($exp[0]);
					$dest = '/'.implode('/', $exp).$filename;
					$req = "?action=move&overwrite=false&files=" . urlencode($dest) . "&destpath=" . urlencode($_POST['path']);
					$copyUrl = $NAS_url.':'.$NAS_port.'/webman/modules/FileBrowser/webfm/webUI/file_MVCP.cgi'.$req;
					curl_setopt($ch, CURLOPT_URL, $copyUrl);
					sleep(3);
				}
				else {
					curl_setopt($ch, CURLOPT_URL, $uploadUrl);
					curl_setopt($ch, CURLOPT_POST, true);
					$post = array(
						"overwrite" => $_POST['overwrite'],
						"path" => $_POST['path'],
						"taskid" => $taskid,
						"file" => "@".TEMP.$filename
					);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				}			
				$response = curl_exec($ch);
				if ($response) {
					if (!$local) { unlink(TEMP.$filename);	}
					$resp = json_decode($response);
					if ($resp->success == true) {
						ret(array('success' => true));
					}
					else {
					    ret(array('success' => false, 'reason' => $response));
					}
				}
				else {
					ret(array('success' => false, 'err' => 'cannot_upload', 'err_txt' => curl_error($ch)));
				}
			}
			else {
				ret(array('success' => false, 'err' => 'cannot_upload'));
			
			}
		}	
	}
	
?>