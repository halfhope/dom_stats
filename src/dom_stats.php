
<?php 
/**
 * @author Shashakhmetov Talgat <talgatks@gmail.com>
 */

$password = 'ZeMel42DB';

// Authorization 
if (!isset($_COOKIE['l5rsr']) || $_COOKIE['l5rsr'] !== $password){
	die("<html><head><script>var pwd=prompt('Enter password','');document.cookie='l5rsr='+encodeURIComponent(pwd);location.reload();</script></head></html>");
}

$_version = '1.0';

class Helper
{
	public static function humanBytes($size)
	{
		$t    = array(
			" Bytes",
			" KB",
			" MB",
			" GB",
			" TB",
			" PB",
			" EB",
			" ZB",
			" YB"
		);
		$size = abs($size);
		return $size ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $t[$i] : '0 Bytes';
	}
	public static function cmp_size($a, $b)
	{
		if ((int) $a['size'] == (int) $b['size']) {
			return 0;
		}
		return ((int) $a['size'] > (int) $b['size']) ? -1 : 1;
	}
	public static function cmp_level($a, $b)
	{
		if ((int) $a['max_level'] == (int) $b['max_level']) {
			return 0;
		}
		return ((int) $a['max_level'] > (int) $b['max_level']) ? -1 : 1;
	}
	public static function cmp_childs($a, $b)
	{
		if ((int) $a['ccount'] == (int) $b['ccount']) {
			return 0;
		}
		return ((int) $a['ccount'] > (int) $b['ccount']) ? -1 : 1;
	}
	public static function percent2Color($value,$brightness = 230, $max = 100,$min = 0, $thirdColorHex = '00')
	{
		$first = (1-($value/$max))*$brightness;
		$second = ($value/$max)*$brightness;

		$diff = abs($first-$second);
		$influence = ($brightness-$diff)/2;
		$first = intval($first + $influence);
		$second = intval($second + $influence);

		$firstHex = str_pad(dechex($first),2,0,STR_PAD_LEFT);     
		$secondHex = str_pad(dechex($second),2,0,STR_PAD_LEFT); 

		return $firstHex . $secondHex . $thirdColorHex ; 

		// return $thirdColorHex . $firstHex . $secondHex;
		// return $firstHex . $thirdColorHex . $secondHex;
	}	
}
/**
 * DOMStats
 */
class DOMStats
{
	private $doc;
	private $file;
	private $sort;
	private $sorts;

	private $filesize   	= 0;
	private $counter    	= 1;
	private $doc_size   	= 0;
	private $max_level  	= 1;
	private $max_childs 	= 0;
	private $total_childs 	= 0;

	function __construct()
	{
		set_time_limit(0);
		ini_set('max_execution_time', 0);
		ini_set('memory_limit', '700M');

		return $this;
	}
	public function getSort(){
		return $this->sort;
	}
	public function setSort($sort){
		$this->sort = $sort;
	}
	public function getSorts(){
		return $this->sorts;
	}
	public function setSorts($sorts){
		$this->sorts = $sorts;
	}
	public function getFile(){
		return $this->file;
	}
	public function setFile($file){
		$this->file = $file;
	}
	public function execute(){
		$result = array();
		$this->filesize = strlen($this->file);
		$this->doc = new DOMDocument();
		$this->doc->recover             = true;
		$this->doc->strictErrorChecking = false;
		@$this->doc->loadHTML($this->file);
		$result = $this->makeTree($this->doc, 0);

		$result = $this->updateTree($result);
		$result = $this->levelit($result);
		$result = $this->childit($result);
		$result = $this->sortTree($result);
		$result = $this->cleanit($result);
		return $result;
	}
	private function makeTree(DOMNode $domNode, $pid = 1, $level = 1)
	{
		$items  = array();
		$stored = array();

		foreach ($domNode->childNodes as $node) {
			if (in_array($node->nodeName, array(
				'#text',
				'#comment',
				'#cdata-section'
			))) {
				continue;
			}
			if ($level == 1) {
				$this->doc_size = strlen($node->nodeValue);
			}
			$this->counter++;
			
			$id = ($node->hasAttributes()) ? $node->getAttribute('id') : false;
			if ($id) {
				$id = '#' . $id;
			}
			
			$class = ($node->hasAttributes()) ? $node->getAttribute('class') : false;
			if ($class) {
				$class = '.' . str_replace(' ', '.', $class);
			}
			
			$size = strlen($node->nodeValue);
			
			$name   = $node->nodeName . $id . $class;
			if ($node->nodeName == 'li') {
				$hash_name = $node->nodeName;
			}else{
				$hash_name = $node->nodeName . $class;
			}
			$hash      = hash('crc32b', $hash_name);
			
			$item = array(
				'id' => $this->counter,
				'name' => $name,
				'text' => $name,
				'hash' => $hash,
				'level' => $level,
				'size' => $size
			);
			
			$item['parent_id'] = $pid;
			
			if ($node->hasChildNodes()) {
				$item['children'] = $this->makeTree($node, $this->counter, $level + 1);
				foreach ($item['children'] as $key => $value) {
					foreach ($value as $key2 => $value2) {
						$hash = hash('crc32b', $hash_name . $value2['name']);
					}
				}
				$item['state'] = array(
					'opened' => true
				);
			} else {
				$hash = hash('crc32b', $hash_name);
			}
			
			$item['hash'] = $hash;
			
			$items[$hash][] = $item;
		}
		
		
		return (!empty($items)) ? $items : array();
	}

	private function getChildsDomPath($elem)
	{
		$result = $elem['name'];
		if (isset($elem['children'])) {
			$last = end($elem['children']);
			if (isset($last[0])) {
				$result .= ' > ' . $this->getChildsDomPath($last[0]);
			}
		}
		return $result;
	}
	private function updateTree($tree, $pid = 1)
	{
		
		foreach ($tree as $hash => $nodes) {
			$unite      = count($tree[$hash]) >= 2;
			$hash_count = count($tree[$hash]);
			if (!$unite) {
				foreach ($nodes as $node) {
					$item = $node;
					if (isset($node['children'])) {
						$item['children'] = $this->updateTree($node['children'], $node['id']);
					}
					$item['icon'] = false;
					$items[]      = $item;
				}
			} else {
				$this->counter++;
				$item = array(
					'id' => $this->counter,
					'text' => '[' . $hash_count . '] ' . $this->getChildsDomPath(end($nodes)),
					'data' => array(
						'cycled' => true
					),
					'parent_id' => $pid,
					'size' => 0,
					'icon' => false,
					'state' => array(
						'expanded' => false,
						'opened' => false
					),
					'li_attr' => array(
						'class' => 'cycled'
					)
				);
				
				$dyn_size      = 0;
				$dyn_level     = 0;
				$dyn_max_level = 0;
				
				foreach ($nodes as $node) {
					$sub_item = $node;
					$dyn_size += $sub_item['size'];
					$dyn_level = $sub_item['level'];
					if ($dyn_max_level < $sub_item['level']) {
						$dyn_max_level = $sub_item['level'];
					}
					$sub_item['icon'] = false;
					if (isset($node['children'])) {
						// $sub_item['children'] = $this->updateTree($node['children'], $level + 1, $this->counter);
						$sub_item['children'] = $this->updateTree($node['children'], $this->counter);
					}
					$item['children'][] = $sub_item;
				}
				
				$item['level'] = $dyn_level;
				// $item['max_level'] = $dyn_max_level;
				$item['size']  = $dyn_size;
				
				$items[] = $item;
			}
		}
		
		return (!empty($items)) ? $items : null;
	}
	private function levelit($array)
	{
		$result2 = array();

		foreach ($array as $key => $value) {
			$item = $value;


			if ($this->max_level < $value['level']) {
				$this->max_level = $value['level'];
			}
			
			$stored = $this->max_level;
			
			$item['max_level'] = $this->max_level;
			if (isset($value['children']) && is_array($value['children'])) {
				$item['children'] = $this->levelit($value['children']);
				$max_level        = 0;
				foreach ($item['children'] as $key2 => $value2) {
					if ($this->max_level < $value2['max_level']) {
						$this->max_level = $value2['max_level'];
					}
				}
				$item['max_level'] = $this->max_level;
			}
			
			if ($this->max_level > $value['level']) {
				$this->max_level = $value['level'];
			}
			
			$result2[$key] = $item;
		}
		return $result2;
	}
	private function childit($array)
	{
		$result2 = array();
		
		foreach ($array as $key => $value) {
			
			$this->max_childs = 1;
			
			$stored = $this->max_childs;

			$item = $value;
			$item['ccount'] = $this->max_childs;
			$this->total_childs++;

			if (isset($item['children'])) {
				$item['children'] = $this->childit($item['children']);
				foreach ($item['children'] as $key2 => $value2) {
					$this->max_childs += $value2['ccount'];
				}
				$item['ccount'] = $this->max_childs;
				$this->max_childs = count($array);
			}
			
			$this->max_childs = $stored;

			$result2[$key] = $item;
		}
		return $result2;
	}
	private function sortTree($array)
	{
		foreach ($this->sorts as $value) {
			if ('cmp_' . $this->sort == $value) {
				uasort($array, array('helper', $value));
			}
		}
		
		foreach ($array as $key => $value) {
			$array[$key]['text'] = $array[$key]['text'] . ' <span class="sub level">[' . $value['level'] . '/' . $value['max_level'] . ']</span>';
			$array[$key]['text'] = $array[$key]['text'] . ' <span class="sub size" style="color:#'.Helper::percent2Color(100-round($value['size'] / ($this->doc_size / 100),  0)).'">[' . Helper::humanBytes($value['size']) . '/' . round($value['size'] / ($this->doc_size / 100), 2) . '%]</span>';
			if ($value['ccount'] > 1) {
				$array[$key]['text'] = $array[$key]['text'] . ' <span class="sub childs" style="color:#'.Helper::percent2Color(100-round($value['ccount'] / ($this->total_childs / 100), 0)).'">[' . $value['ccount'] . '/' . round($value['ccount'] / ($this->total_childs / 100), 2) . '%]</span>';
			}

			if (isset($value['children']) && is_array($value['children'])) {
				$array[$key]['children'] = $this->sortTree($value['children']);
			}
		}
		
		$result = array();
		foreach ($array as $key => $value) {
			$result[] = $value;
		}
		
		return $result;
	}
	private function cleanit($array)
	{
		$result2 = array();
		
		foreach ($array as $key => $value) {
			$item = $value;

			if (isset($item['children'])) {
				$item['children'] = $this->cleanit($item['children']);
			}
			unset($item['name'], $item['hash'], $item['level'], $item['max_level'], $item['size'], $item['ccount']);
							
			$result2[$key] = $item;
		}
		return $result2;
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>DOM Stats v<?php echo $_version ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha256-YLGeXaapI0/5IgZopewRJcFXomhRMlYYjugPLSyNjTY=" crossorigin="anonymous" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha256-CjSoeELFOcH0/uxWu6mC/Vlrc1AARqbm/jiiImDGV3s=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.8/jstree.min.js" integrity="sha256-NPMXVob2cv6rH/kKUuzV2yXKAQIFUzRw+vJBq4CLi2E=" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.8/themes/default/style.min.css" integrity="sha256-gX9Z4Eev/EDg9VZ5YIkmKQSqcAHL8tST90dHvtutjTg=" crossorigin="anonymous" />
<style>
html{overflow:scroll}
#ams{
	margin-top:10px;
}
.sub{
	color: #444;
}
.sub.level{
	
}
li.cycled>a.jstree-anchor{
	background: #fafafa !important;
	border-radius: 3px;
}
</style>
</head>
<body>
<?php if (!isset($_GET['link']) && !isset($_GET['text'])): ?>
<nav class="navbar  sticky-top navbar-expand-lg navbar-dark bg-dark">
  <a class="navbar-brand" href="#">DOMStats v<?php echo $_version ?></a>
</nav>
<div id="ams" class="container">
	<form action="<?php	echo basename(__FILE__); ?>" method="GET">
	<div class="form-group">
		<label for="link">Link (with protocol)</label>
		<input type="text" id="link" name="link" class="form-control">
	</div>
	<div class="form-group">
		<label for="sort">Sort</label>
		<select name="sort" id="sort" class="form-control">
			<option value="default">By default</option>
			<option value="size">Code size (bytes)</option>
			<option value="level">Nesting level</option>
			<option value="childs">Children count</option>
		</select>
	</div>
	<input type="submit" class="btn btn-primary">
	</form>
<?php else: ?>
<?php

$DOMStats = new DOMStats();

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$DOMStats->setSort($sort);

$DOMStats->setSorts(array('cmp_level', 'cmp_size', 'cmp_childs'));

if (isset($_GET['link']) && !empty($_GET['link'])) {
	// $file = file_get_contents($_GET['link']);
	$curl_handle = curl_init();
	curl_setopt($curl_handle, CURLOPT_URL, $_GET['link']);
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	$file = curl_exec($curl_handle);
	$response = curl_getinfo($curl_handle);
	curl_close($curl_handle);
} elseif (isset($_GET['text']) && !empty($_GET['text'])) {
	$file = $_GET['text'];
}
$DOMStats->setFile($file);
$result = $DOMStats->execute();

?>
<nav class="navbar  sticky-top navbar-expand-lg navbar-dark bg-dark">
  <a class="navbar-brand" href="#">DOMStats v<?php echo $_version ?>: <?php echo htmlspecialchars_decode($_GET['link']); ?></a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarText">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item <?php echo (!isset($_GET['sort'])) ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo basename(__FILE__) . '?link=' . $_GET['link'] ?>">By default</a>
      </li>
      <li class="nav-item <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'size') ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo basename(__FILE__) . '?link=' . $_GET['link'] . '&sort=size' ?>">Code size (bytes)</a>
      </li>
      <li class="nav-item <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'level') ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo basename(__FILE__) . '?link=' . $_GET['link'] . '&sort=level' ?>">Nesting level</a>
      </li>
      <li class="nav-item <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'childs') ? 'active' : ''; ?>">
        <a class="nav-link" href="<?php echo basename(__FILE__) . '?link=' . $_GET['link'] . '&sort=childs' ?>">Children count</a>
      </li>
    </ul>
        <a class="btn btn-outline-danger" href="<?php echo basename(__FILE__) ?>">Exit</a>
  </div>
</nav>
<div id="ams" class="container">

<div id="us">
	
</div>
<script>
$(document).ready(function() {
	var data = <?php
		echo json_encode($result);
?>;
	$('#us').jstree({
		'core' : {
    		'data' : data,
    		'dblclick_toggle' : false
		}
	});
});
</script>
<?php endif; ?>
</div>
</body>
</html>