<?php
	class file_Confuse{
		private $selfPath;
		private $tree=array();
		private $rootPath;
		private $varZval=array();
		private $glovarZval=array();
		private $funcZval=array();
		private $fileZval=array();
		private $globalZval=array();
		private $locvarZval=array();
		private $classZval=array();
		private $replacePath='new';
		private $superArray=array(
			'$GLOBALS',
			'$_SERVER',
			'$_REQUEST',
			'$_POST',
			'$_GET',
			'$_FILES',
			'$_ENV',
			'$_COOKIE',
			'$_SESSION'
		);
		private $magicFunc=array(
			'__construct',
			'__destruct',
			'__call',
			'__callStatic',
			'_initialize',
			'__get',
			'__set',
			'__isset',
			'__unset',
			'__sleep',
			'__wakeup',
			'__toString',
			'__invoke',
			'__set_state',
			'__clone',
			'__debugInfo'
		);
		private static $_instance;
		private $num=0;
		private function __construct(){
			header("Content-type:text/html;charset=utf-8");
			$this->selfPath = basename(__FILE__);
			$this->rootPath = __DIR__;
			$this->tree = $this->setTree($this->rootPath);
			$this->run();
		}
		
		public static function getInstance(){
			if(!(self::$_instance instanceof self)){
				self::$_instance = new self;
			}
			return self::$_instance;
		}
		
		public function getTree(){
			if(empty($this->tree)){
				$this->tree = $this->setTree($this->rootPath);
			}
			return $this->tree;
		}
		
		public function getPath(){
			return $this->rootPath;
		}
		
		public function setPath($dir){
			if(!is_dir($dir)){
				exit('error:this is not a dir');
			}
			$this->rootPath = $dir;
			$this->tree = $this->setTree($this->rootPath);
		}
		
		private function setTree($rootPath){
			$dir = opendir($rootPath);
			$tree = array();
			while(($file=readdir($dir)) !== false){
				if($file == '..' || $file == '.' || $file == $this->selfPath || $file == $this->replacePath){
					continue;
				}
				if(is_dir($sondir = $rootPath.DIRECTORY_SEPARATOR.$file)){
					$tree[$file] = $this->setTree($sondir);
				}else{
					if(substr($file, strrpos($file, '.')+1)=='php'){
						$tree[] = $rootPath.DIRECTORY_SEPARATOR.$file;
					}
				}
			}
			closedir($dir);
			return $tree;
		}
		
		private function run(){
			$this->handle($this->tree,$this->rootPath);
			$this->confuse();
		}
		
		private function handle($arr,$rootPath){
			foreach($arr as $k=>$v){
				if(is_array($v)){
					$sonPath = $rootPath.DIRECTORY_SEPARATOR.$k;
					$this->rule($sonPath,1);
					$this->handle($v,$sonPath);
				}else{
					$this->rule($v,0);
				}
			}
		}
		
		private function rule($url,$type,$model=1){
			if($model){
				$newpath = $this->rootPath.DIRECTORY_SEPARATOR.$this->replacePath;
				
				if(!file_exists($newpath)){
					mkdir($newpath);
				}
				
				if($type){
					$url = str_replace($this->rootPath,$newpath,$url);
					if(!file_exists($url)){
						mkdir($url);
					}
				}else{
					$this->fileZval[] = $url;
					$end = end($this->fileZval);
					$key = array_search($end,$this->fileZval);
					$content = file_get_contents($url);
					if(empty($content)){
						unset($this->fileZval[$key]);
						unlink($url);
					}else{
						$content = $this->txtReplace($content,$key);
						$url = str_replace($this->rootPath,$newpath,$url);
						$file = fopen($url,'w') or die('Unable to open file!');
							
						$flag=fwrite($file,$content);
						if(!$flag){
							echo $url,'<br>';
							echo '文件无法被写入';
						}
						fclose($file);
					}
				}
			}
		}
		
		private function txtReplace($content,$key){
			$str = "";
			$data = token_get_all($content);
			
			$glo = false;
			
			$funcFlag = false;
			$funcBorder = 0;
			$funcName = '';
			
			$classFlag = false;
			$classBorder = 0;
			$className = '';
			
			$num=0;
			
			for ($i=0,$count=count($data);$i<$count;$i++){
				if(is_string($data[$i])){
					if($glo == true && $data[$i] == ';'){
						$glo = false;
					}
					if($data[$i] == '{'){
						if($funcFlag){
							$funcBorder++;
						}
						
						if($classFlag){
							$classBorder++;
						}
					}
					if($data[$i] == '}'){
						if($funcFlag){
							$funcBorder--;
							if($funcBorder == 0){
								$funcFlag = false;
								$num = 0;
							}
						}
						
						if($classFlag){
							$classBorder--;
							if($classBorder == 0){
								$classFlag = false;
							}
						}
					}
					$str .= $data[$i];
				}else{
					switch($data[$i][0]){
						case T_COMMENT:
							break;
						case T_DOC_COMMENT:
							break;
						case T_WHITESPACE:
							$str .= " ";
							break;
						case T_START_HEREDOC:
							$str .= "<<<EOT".PHP_EOL;
							break;
						case T_END_HEREDOC:
							$str .= "EOT;".PHP_EOL;
							for ($m = $i + 1; $m < $count; $m++) {
								if (is_string($data[$m]) && $data[$m] == ';') {
									$i = $m;
									break;
								}
								if ($data[$m] == T_CLOSE_TAG) {
									break;
								}
							}
							break;
						
						case T_VARIABLE:
							if($data[$i][1]=='$this'){
								$str .= $data[$i][1];
								break;
							}
							if(in_array($data[$i][1],$this->superArray)){
								$str .= $data[$i][1];
								break;
							}
							
							if($funcFlag){
								if($glo){
									if(!isset($this->globalZval[$key][$funcName][$data[$i][1]])){
										if(!isset($this->varZval[$data[$i][1]])){
											$this->varZval[$data[$i][1]] = '$_glo'.$this->num;
											$this->num++;
										}
										$this->globalZval[$key][$funcName][$data[$i][1]] = $this->varZval[$data[$i][1]];
									}
									$data[$i][1] = $this->varZval[$data[$i][1]];
								}else{
									if(isset($this->globalZval[$key][$funcName][$data[$i][1]])){
										$data[$i][1] = $this->globalZval[$key][$funcName][$data[$i][1]];
										$str .= $data[$i][1];
										break;
									}elseif(!isset($this->locvarZval[$key][$funcName][$data[$i][1]])){
										$this->locvarZval[$key][$funcName][$data[$i][1]] = '$_loc'.$num;
										$num++;
									}
									$data[$i][1] = $this->locvarZval[$key][$funcName][$data[$i][1]];
								}
								$str .= $data[$i][1];
								break;
							}
							
							
							
							if(!isset($this->varZval[$data[$i][1]])){
								$this->varZval[$data[$i][1]] = '$_glo'.$this->num;
								$this->num++;
							}
							$data[$i][1] = $this->varZval[$data[$i][1]];
							$str .= $data[$i][1];
							
							break;
						
						case T_GLOBAL:
							$glo = true;
							$str .= $data[$i][1];
							break;
						case T_FUNCTION:
							$str .= $data[$i][1];
							$str .= $data[$i+1][1];
							
							if(!is_string($data[$i+2]) && is_string('(')){
								$str .= $data[$i+2][1];
								$funcName = $data[$i+2][1];
								$i+=2;
							}else{
								$i++;
							}
							
							if($classFlag){
								$this->funcZval[$className][$funcName] = 'loc'.$num;
							}else{
								$this->funcZval[0][$funcName] = 'glo'.$this->num;
								$this->num++;
							}
							
							$funcFlag = true;
							
							break;
						case T_CLASS:
							$str .= $data[$i][1];
							$str .= $data[$i+1][1];
							$str .= $data[$i+2][1];
							$classFlag = true;
							$className = $data[$i+2][1];
							$this->classZval[$key][] = $className;
							$i+=2;
							break;
						default:
							$str .= $data[$i][1];
					}
				}
			}
			return $str;
		}
		
		private function confuse(){
			$newpath = $this->rootPath.DIRECTORY_SEPARATOR.$this->replacePath;
			foreach($this->fileZval as $k=>$v){
				$url = str_replace($this->rootPath,$newpath,$v);
				$content = file_get_contents($url);
				$content = $this->funcReplace($content,$k);
				$url = str_replace($newpath,$this->rootPath,$url);
				$file = fopen($url,'w') or die('Unable to open file!');
				$flag=fwrite($file,$content);
				if(!$flag){
					echo '文件无法被写入';
				}
				fclose($file);
			}
			
			$content = serialize($this->varZval);
			$content .= '|||';
			$content .= serialize($this->funcZval);
			$content .= '|||';
			$content .= serialize($this->fileZval);
			$content .= '|||';
			$content .= serialize($this->globalZval);
			$content .= '|||';
			$content .= serialize($this->classZval);
			
			$file = fopen($this->rootPath.DIRECTORY_SEPARATOR.'bak.txt','w') or die('Unable to open file!');
			$flag=fwrite($file,$content);
			if(!$flag){
				echo '文件无法被写入';
			}
			fclose($file);
			
			$this->deldir($this->rootPath.DIRECTORY_SEPARATOR.$this->replacePath);
			
			echo "<pre>";
			echo "混淆变量名总数共".count($this->varZval).'<br>';
			echo "混淆函数名总数共".count($this->funcZval[0]).'<br>';
			echo "混淆文件总数共".count($this->fileZval).'<br>';
		}
		
		private function funcReplace($content,$key){
			$str = '';
			$data = token_get_all($content);
			for ($i=0,$count=count($data);$i<$count;$i++){
				if(is_string($data[$i])){
					$str .= $data[$i];
				}else{
					switch($data[$i][0]){
						case T_WHITESPACE:
							$str .= " ";
							break;
						case T_START_HEREDOC:
							$str .= "<<<EOT".PHP_EOL;
							break;
						case T_END_HEREDOC:
							$str .= "EOT;".PHP_EOL;
							for ($m = $i + 1; $m < $count; $m++) {
								if (is_string($data[$m]) && $data[$m] == ';') {
									$i = $m;
									break;
								}
								if ($data[$m] == T_CLOSE_TAG) {
									break;
								}
							}
							break;
						
						case T_CONSTANT_ENCAPSED_STRING:
							if(strpos($this->fileZval[$key],'citylive.php')){
								$index = trim($data[$i][1],'\'');
								if(isset($this->funcZval[0][$index])){
									$data[$i][1] = $this->funcZval[0][$index];
								}
							}
							$str .= $data[$i][1];
							break;
						
						case T_STRING:
							$func = $data[$i][1];
							if(isset($this->funcZval[0][$func])){
								$data[$i][1] = $this->funcZval[0][$func];
							}
							$str .= $data[$i][1];
							break;
							
						default:
							$str .= $data[$i][1];
					}
				}
			}
			return $str;
		}
		
		private function deldir($dir){
			$dh=opendir($dir);
			while ($file=readdir($dh)){
				if($file!="." && $file!="..") {
					$fullpath=$dir."/".$file;
					if(!is_dir($fullpath)) {
						unlink($fullpath);
					} else {
						$this->deldir($fullpath);
					}
				}
			}
			closedir($dh);
			if(rmdir($dir)) {
				return true;
			} else {
				return false;
			}
		}
	}
	
	$obj = file_Confuse::getInstance();
?>